# Hướng dẫn setup ban đầu (Giai đoạn 0)

Monorepo: Laravel API (`api/`) + React SPA (`web/`) + Docker Compose.

## 0. Cần cài sẵn

- Git
- Docker Desktop (hoặc Docker Engine + Compose v2)
- Node.js 20+ (chỉ để chạy lệnh tạo project, sau đó có thể chạy trong container)
- Không cần cài PHP/Composer trên máy, ta dùng container

## 1. Cấu trúc thư mục đích

```
booking-saas/
├── api/                    # Laravel
├── web/                    # React + Vite + TS
├── docker/
│   ├── php/Dockerfile
│   └── nginx/default.conf
├── docs/
│   ├── PLAN.md
│   └── decisions/
├── .github/workflows/ci.yml
├── docker-compose.yml
├── .gitignore
└── README.md
```

## 2. Khởi tạo repo

```bash
mkdir booking-saas && cd booking-saas
git init
mkdir -p docker/php docker/nginx docs/decisions .github/workflows
# copy PLAN.md vào docs/ (hoặc để ở root)
```

`.gitignore` ở root:

```gitignore
.DS_Store
.idea/
.vscode/
node_modules/
vendor/
.env
```

## 3. Tạo Laravel (không cần PHP trên máy)

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/app -w /app composer:2 \
  composer create-project laravel/laravel api
```

> Windows (PowerShell): thay `$(pwd)` bằng `${PWD}` và bỏ `-u "$(id -u):$(id -g)"`. Nếu dùng WSL2 thì chạy lệnh Linux ở trên trong WSL, để code nằm trong filesystem của WSL cho nhanh.

## 4. Tạo React (Vite + TypeScript)

```bash
npm create vite@latest web -- --template react-ts
cd web && npm install && cd ..
```

## 5. Docker: PHP

`docker/php/Dockerfile`:

```dockerfile
FROM php:8.3-fpm

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libicu-dev libpng-dev libonig-dev \
    && docker-php-ext-install pdo_mysql zip intl bcmath pcntl gd \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd -g ${GID} app && useradd -u ${UID} -g app -m app
USER app
WORKDIR /var/www/html
```

## 6. Docker: Nginx

`docker/nginx/default.conf`:

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## 7. Docker Compose

`docker-compose.yml`:

```yaml
services:
  nginx:
    image: nginx:1.27-alpine
    ports:
      - "8080:80"
    volumes:
      - ./api:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - app

  app:
    build:
      context: ./docker/php
      args:
        UID: ${UID:-1000}
        GID: ${GID:-1000}
    volumes:
      - ./api:/var/www/html
    depends_on:
      - mysql
      - redis

  queue:
    build:
      context: ./docker/php
      args:
        UID: ${UID:-1000}
        GID: ${GID:-1000}
    command: php artisan queue:work --tries=3 --timeout=90   # đổi thành "php artisan horizon" sau khi cài Horizon
    volumes:
      - ./api:/var/www/html
    depends_on:
      - app
    restart: unless-stopped

  scheduler:
    build:
      context: ./docker/php
      args:
        UID: ${UID:-1000}
        GID: ${GID:-1000}
    command: php artisan schedule:work
    volumes:
      - ./api:/var/www/html
    depends_on:
      - app
    restart: unless-stopped

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: booking
      MYSQL_USER: booking
      MYSQL_PASSWORD: secret
      MYSQL_ROOT_PASSWORD: root
    ports:
      - "3307:3306"
    volumes:
      - mysql_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-proot"]
      interval: 10s
      retries: 10

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data

  mailpit:
    image: axllent/mailpit
    ports:
      - "8025:8025"   # giao diện xem mail
      - "1025:1025"   # SMTP

  web:
    image: node:22-alpine
    working_dir: /app
    command: sh -c "npm install && npm run dev -- --host 0.0.0.0"
    ports:
      - "5173:5173"
    volumes:
      - ./web:/app
    depends_on:
      - nginx

volumes:
  mysql_data:
  redis_data:
```

> Port MySQL map ra `3307` để tránh đụng MySQL đang cài trên máy anh.

## 8. Cấu hình Laravel (`api/.env`)

Sửa các dòng sau:

```env
APP_NAME="Booking SaaS"
APP_URL=http://localhost:5173
FRONTEND_URL=http://localhost:5173

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=booking
DB_USERNAME=booking
DB_PASSWORD=secret

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_FROM_ADDRESS=no-reply@booking.test

# Sanctum SPA (cookie)
SANCTUM_STATEFUL_DOMAINS=localhost:5173
SESSION_DOMAIN=localhost
```

## 9. Cấu hình Vite proxy

Để trình duyệt chỉ thấy một origin (`localhost:5173`), cookie Sanctum hoạt động đơn giản, không dính CORS.

`web/vite.config.ts`:

```ts
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: {
    host: true,
    port: 5173,
    proxy: {
      '/api': { target: 'http://nginx', changeOrigin: false },
      '/sanctum': { target: 'http://nginx', changeOrigin: false },
    },
  },
})
```

> `target: 'http://nginx'` đúng khi Vite chạy **trong** container. Nếu chạy `npm run dev` trực tiếp trên máy thì đổi thành `http://localhost:8080`.

## 10. Chạy lần đầu

```bash
# 1. Build và chạy
export UID GID=$(id -g)        # Linux/macOS; Windows bỏ qua
docker compose up -d --build

# 2. Cài dependency và tạo key
docker compose exec app composer install
docker compose exec app php artisan key:generate

# 3. Cài API (Sanctum) và migrate
docker compose exec app php artisan install:api
docker compose exec app php artisan migrate
```

Kiểm tra:

| URL | Kỳ vọng |
|---|---|
| http://localhost:8080 | Trang Laravel welcome |
| http://localhost:5173 | Trang Vite + React |
| http://localhost:8025 | Mailpit |

## 11. Cài các package nền tảng

```bash
# Backend
docker compose exec app composer require laravel/horizon spatie/laravel-permission
docker compose exec app composer require --dev pestphp/pest pestphp/pest-plugin-laravel laravel/pint knuckleswtf/scribe
docker compose exec app php artisan horizon:install
docker compose exec app php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# Frontend
docker compose exec web npm install react-router-dom @tanstack/react-query axios react-hook-form zod @hookform/resolvers
docker compose exec web npm install -D vitest @testing-library/react @testing-library/jest-dom jsdom prettier eslint-config-prettier
```

> Sau khi cài Horizon, đổi lệnh service `queue` thành `php artisan horizon`, rồi `docker compose up -d queue`.

## 12. Tạo health check để kiểm tra FE ↔ BE

`api/routes/api.php`:

```php
<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json([
        'status' => 'ok',
        'time' => now()->toIso8601String(),
    ]));
});
```

`web/src/App.tsx`:

```tsx
import { useEffect, useState } from 'react'
import axios from 'axios'

export default function App() {
  const [status, setStatus] = useState('loading...')

  useEffect(() => {
    axios.get('/api/v1/health')
      .then((r) => setStatus(r.data.status))
      .catch(() => setStatus('error'))
  }, [])

  return <h1>API status: {status}</h1>
}
```

Mở http://localhost:5173, thấy **API status: ok** là xong Giai đoạn 0 phần kết nối.

## 13. Lint và test cơ bản

```bash
docker compose exec app ./vendor/bin/pint
docker compose exec app php artisan test
docker compose exec web npm run lint
```

Thêm Pest: `docker compose exec app ./vendor/bin/pest --init`

## 14. GitHub Actions bản đầu

`.github/workflows/ci.yml`:

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:

jobs:
  api:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: booking_test
          MYSQL_ROOT_PASSWORD: root
        ports: ["3306:3306"]
        options: >-
          --health-cmd="mysqladmin ping -proot"
          --health-interval=10s --health-retries=10
      redis:
        image: redis:7-alpine
        ports: ["6379:6379"]
    defaults:
      run:
        working-directory: api
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: "8.3"
          extensions: pdo_mysql, redis, intl, bcmath
          coverage: none
      - run: composer install --no-interaction --prefer-dist
      - run: cp .env.example .env && php artisan key:generate
      - run: ./vendor/bin/pint --test
      - run: php artisan test
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: booking_test
          DB_USERNAME: root
          DB_PASSWORD: root
          REDIS_HOST: 127.0.0.1

  web:
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: web
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: 22
          cache: npm
          cache-dependency-path: web/package-lock.json
      - run: npm ci
      - run: npm run lint
      - run: npm run build
```

## 15. Commit đầu tiên

```bash
git add .
git commit -m "chore: init monorepo with Laravel, React and Docker Compose"
git branch -M main
git remote add origin git@github.com:<username>/booking-saas.git
git push -u origin main
```

## 16. Lỗi thường gặp

| Triệu chứng | Cách xử lý |
|---|---|
| `Permission denied` ở `storage/` hoặc `bootstrap/cache` | `docker compose exec -u root app chmod -R 775 storage bootstrap/cache` (hoặc kiểm tra UID/GID trùng user máy) |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL chưa sẵn sàng, đợi vài giây rồi chạy lại migrate; kiểm tra `DB_HOST=mysql` |
| React gọi API bị 419 (CSRF) | Kiểm tra `SANCTUM_STATEFUL_DOMAINS`, gọi `/sanctum/csrf-cookie` trước khi login, axios cần `withCredentials: true` |
| Vite proxy trả 502 | Sai `target` (trong container dùng `http://nginx`, ngoài container dùng `http://localhost:8080`) |
| Chậm trên Windows/macOS | Để code trong WSL2 (Windows); macOS bật VirtioFS trong Docker Desktop |

## 17. Việc tiếp theo (Tuần 1)

1. Vẽ ERD trên dbdiagram.io cho nhóm Tenant & người dùng
2. Migration `tenants`, thêm `tenant_id` vào `users`
3. Trait `BelongsToTenant` + global scope + middleware xác định tenant
4. Auth Sanctum SPA (login/logout/me) + test
5. Test tenant isolation đầu tiên

Xem chi tiết trong `PLAN.md`.
