# Booking SaaS: Kế hoạch dự án

Hệ thống đặt lịch hẹn multi-tenant cho spa, salon, phòng khám.
**Stack:** Laravel 13 (REST API) · React + Vite + TypeScript · MySQL 8 · Redis · Docker · GitHub Actions
**Cấu trúc:** monorepo (`api/` + `web/`)

> Cách dùng: đánh dấu `[x]` khi xong một mục. Mỗi mục nên là 1 PR/commit nhỏ.

---

## 1. Vai trò người dùng

| Vai trò | Quyền chính |
|---|---|
| Super Admin | Quản lý tenant, gói dịch vụ, theo dõi hệ thống |
| Owner | Toàn quyền trong tenant, thanh toán, báo cáo |
| Manager | Quản lý nhân viên, lịch, dịch vụ của chi nhánh |
| Staff | Xem và xử lý lịch của mình, cập nhật trạng thái |
| Customer | Đặt, đổi, hủy lịch, xem lịch sử |

---

## 2. Danh sách tính năng

### A. Nền tảng và multi-tenancy
- [ ] Đăng ký tenant (tạo owner + tenant), slug riêng (`/t/{slug}` hoặc subdomain)
- [ ] Middleware xác định tenant, global scope theo `tenant_id`
- [ ] Test cô lập dữ liệu giữa các tenant
- [ ] Gói dịch vụ Free/Pro có giới hạn (số chi nhánh, số nhân viên)
- [ ] Super Admin: danh sách tenant, khóa/mở tenant

### B. Xác thực và phân quyền
- [ ] Đăng ký, đăng nhập, đăng xuất (Sanctum SPA cookie)
- [ ] Quên mật khẩu, đặt lại mật khẩu, xác thực email
- [ ] RBAC bằng Policy/Gate (spatie/laravel-permission)
- [ ] Mời nhân viên qua email (invitation link có hạn)
- [ ] Hồ sơ cá nhân, đổi mật khẩu
- [ ] Rate limit cho login và đặt lịch

### C. Quản lý danh mục
- [ ] Chi nhánh: thông tin, giờ mở cửa, ngày nghỉ lễ
- [ ] Dịch vụ: tên, thời lượng, giá, danh mục, bật/tắt
- [ ] Nhân viên: gán chi nhánh, gán dịch vụ có thể thực hiện
- [ ] Ca làm việc: lịch tuần, ngoại lệ (nghỉ phép, đổi ca)
- [ ] Tài nguyên phòng/ghế (tùy chọn)

### D. Đặt lịch (phần lõi)
- [ ] API khung giờ trống theo dịch vụ + nhân viên + chi nhánh (cache Redis)
- [ ] Đặt lịch chống trùng: transaction + `lockForUpdate()` / `Cache::lock`
- [ ] Trạng thái: pending → confirmed → in_progress → completed / cancelled / no_show
- [ ] Đổi lịch, hủy lịch với chính sách hủy (ví dụ trước 24h)
- [ ] Staff/manager đặt hộ khách, khách vãng lai
- [ ] Nhiều dịch vụ trong một lịch hẹn
- [ ] Waitlist khi hết slot, tự thông báo khi có slot trống
- [ ] Idempotency key khi đặt lịch

### E. Thông báo và job nền
- [ ] Email xác nhận, đổi lịch, hủy lịch (queue)
- [ ] Nhắc lịch trước 24h và 2h (scheduler + queue)
- [ ] Thông báo in-app (notification bell)
- [ ] Retry, failed job, Horizon dashboard
- [ ] Tự động đánh dấu no-show quá giờ

### F. Thanh toán
- [ ] Đặt cọc/thanh toán qua Stripe (test mode)
- [ ] Webhook idempotent, xác thực chữ ký
- [ ] Hóa đơn/biên nhận, hoàn tiền khi hủy
- [ ] Lịch sử giao dịch

### G. Báo cáo và dashboard
- [ ] Doanh thu theo ngày/tuần/tháng, theo dịch vụ, theo nhân viên
- [ ] Tỷ lệ hủy/no-show, giờ cao điểm
- [ ] Xuất CSV bằng queue
- [ ] Cache báo cáo

### H. Đánh giá và khách hàng
- [ ] Khách đánh giá sau khi hoàn thành
- [ ] Hồ sơ khách hàng, lịch sử, ghi chú nội bộ
- [ ] Audit log (ai sửa gì, lúc nào)

### I. Giao diện React
- [ ] Trang đặt lịch công khai theo tenant (dịch vụ → nhân viên → giờ → xác nhận)
- [ ] Lịch tuần/ngày cho staff/manager
- [ ] Trang quản trị: CRUD chi nhánh, dịch vụ, nhân viên, ca làm
- [ ] Dashboard (Recharts), protected routes theo vai trò
- [ ] Responsive, loading/error state, toast
- [ ] Dark mode (tùy chọn)

---

## 3. Yêu cầu phi chức năng

- [ ] **Hiệu năng:** index hợp lý, xử lý N+1, cache khung giờ trống, ghi số liệu trước/sau
- [ ] **Bảo mật:** validation, policy, rate limit, CORS, dùng ULID/UUID cho ID public
- [ ] **Test:** tenant isolation, chống trùng lịch (concurrency), webhook thanh toán; Vitest cho phần chính của FE
- [ ] **Quan sát:** log có cấu trúc, Sentry, health check `/api/health`
- [ ] **Tài liệu API:** Scribe (OpenAPI), versioning `/api/v1`
- [ ] **DevOps:** Docker Compose, GitHub Actions (lint, test, build, deploy)

---

## 4. Thiết kế dữ liệu sơ bộ

| Nhóm | Bảng |
|---|---|
| Tenant & người dùng | `tenants`, `users`, `roles`, `permissions`, `invitations` |
| Danh mục | `branches`, `service_categories`, `services`, `staff_profiles`, `staff_services` |
| Lịch làm việc | `working_hours`, `time_off`, `holidays` |
| Đặt lịch | `customers`, `appointments`, `appointment_services`, `waitlist_entries` |
| Thanh toán | `payments`, `refunds` |
| Khác | `reviews`, `notifications`, `audit_logs` |

**Quy ước:**
- Mọi bảng nghiệp vụ có `tenant_id` (index, nằm đầu composite index)
- ID public dùng ULID, ID nội bộ dùng bigint
- Thời gian lưu UTC, hiển thị theo timezone của chi nhánh
- Tiền lưu số nguyên (đơn vị nhỏ nhất), không dùng float
- Index quan trọng: `appointments (tenant_id, staff_id, starts_at, ends_at)`

---

## 5. Lộ trình

### Giai đoạn 0: Khởi tạo (1 đến 2 ngày)
- [x] Tạo monorepo, Docker Compose chạy được (xem `SETUP.md`)
- [x] Laravel + React chạy, gọi được `/api/v1/health` từ React
- [ ] Lint: Pint và ESLint đã có trong CI; còn thiếu Prettier config và pre-commit hook
- [x] GitHub Actions bản đầu (lint + test)
- [x] Vẽ ERD (dbdiagram.io) và sơ đồ kiến trúc
- [x] README khung ở root repo

### Tuần 1: Nền tảng
- [x] Migration, model, factory, seeder cho tenants/users/roles
- [x] Multi-tenancy (middleware + global scope + trait `BelongsToTenant`)
- [x] Auth Sanctum, RBAC, invitation
- [x] React: layout, login/register, protected routes, API client, TanStack Query
- [x] Test tenant isolation

### Tuần 2: Danh mục và đặt lịch lõi
- [x] CRUD chi nhánh, dịch vụ, nhân viên, ca làm, ngày nghỉ
- [x] API khung giờ trống
- [x] Đặt lịch chống trùng, đổi/hủy lịch
- [x] React: trang quản trị, trang đặt lịch công khai

### Tuần 3: Queue, cache, thông báo
- [ ] Email, nhắc lịch, notification in-app, Horizon
- [ ] Cache Redis, tối ưu query, rate limiting, idempotency
- [ ] React: lịch tuần/ngày, notification bell
- [ ] **Mốc:** bản chạy được A đến E, đưa vào CV ngay

### Tuần 4: Thanh toán, báo cáo, chất lượng
- [ ] Stripe test + webhook, hoàn tiền
- [ ] Báo cáo, xuất CSV
- [ ] Đánh giá, audit log, waitlist
- [ ] Bổ sung test, coverage phần lõi

### Tuần 5: Deploy và hoàn thiện
- [ ] Deploy thật (VPS/AWS), domain, HTTPS
- [ ] CI/CD tự deploy
- [ ] Load test k6, ghi số liệu vào README
- [ ] Sentry, seed dữ liệu demo
- [ ] README kiến trúc + quyết định thiết kế
- [ ] Video demo, cập nhật CV

---

## 6. Thứ tự ưu tiên nếu thiếu thời gian

1. **Bắt buộc:** A, B, C, D, E
2. **Nên có:** F, G, deploy, test, CI/CD
3. **Có thì tốt:** H, waitlist, dark mode, tài nguyên phòng/ghế

---

## 7. Quy ước làm việc

- **Branch:** `main` (luôn chạy được), `feat/<tên>`, `fix/<tên>`
- **Commit:** Conventional Commits (`feat:`, `fix:`, `test:`, `chore:`, `docs:`)
- **Mỗi tính năng xong khi:** có test, qua lint, cập nhật README/API docs nếu cần
- **Ghi lại quyết định kỹ thuật** vào `docs/decisions/` (vì sao chọn lock này, vì sao cache kiểu này)
- **Ghi số liệu hiệu năng** ngay khi đo, đừng để cuối mới nhớ

---

## 8. Checklist cho CV (cuối dự án)

- [ ] Link demo chạy thật + tài khoản demo
- [ ] Link GitHub, README có sơ đồ kiến trúc
- [ ] Ít nhất 2 số liệu cụ thể (ví dụ query 700ms → 80ms, chịu 300 req/s p95 < 250ms)
- [ ] CI xanh, badge trong README
- [ ] Một bài blog/ghi chú về vấn đề khó nhất (chống trùng lịch)
