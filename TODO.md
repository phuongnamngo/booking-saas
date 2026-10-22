# TODO

Việc được hoãn có chủ đích, ngoài change hiện tại. Các mục còn lại của `docs/PLAN.md` giữ nguyên trên plan, không ghi lại ở đây.

## Tài khoản Customer

- Kết quả: khách có user trong tenant, đăng nhập trên `/t/{slug}`, và xem lịch sử của mình.
- Lý do hoãn: `complete-core-ae` làm khách đổi và hủy bằng manage token trên một lịch, không tạo role Customer. Hồ sơ, lịch sử và ghi chú nội bộ vẫn thuộc mục H của `docs/PLAN.md`.
- Change gốc: `platform-booking-core`. Phần đổi/hủy và mốc 24 giờ chuyển vào `complete-core-ae`.
- Cách làm tiếp: đăng ký customer tách khỏi owner. Customer chỉ thấy appointment gắn với user của mình. Không làm lại token, mốc 24 giờ, hay route công khai của `complete-core-ae`.

## Tenant theo subdomain

- Kết quả: host `{slug}.domain` resolve cùng tenant với path `/t/{slug}`.
- Lý do hoãn: Docker Compose hiện không có wildcard DNS. Change `platform-booking-core` dùng path vì Vite proxy và cookie Sanctum đang chạy trên một origin.
- Change gốc: `platform-booking-core`.
- Cách làm tiếp: giữ `ResolvePublicTenant` nhưng cho phép lấy slug từ host trước, rồi mới tới path. Có test host `demo.localhost` ra cùng dữ liệu với `/t/demo`.

## Visual pass cho layout admin và trang đặt lịch

- Kết quả: một hướng visual được chốt qua skill sketch, rồi áp lên các route đã có: login, register, `/app/*`, wizard `/t/:slug`, cùng các route mới của `complete-core-ae` (`/forgot-password`, `/reset-password`, `/verify-email`, `/app/profile`, `/admin`, `/t/:slug/appointments/:id`). Biểu đồ và toast của `receipts-dashboard-access` nằm trên các route `/app` đó, cùng hàng đợi.
- Lý do hoãn: skill sketch không có trong workspace. `platform-booking-core` và `complete-core-ae` không chọn design system thay thế. Màn mới dùng lại form đăng nhập, khung `/app` và trang đặt lịch công khai.
- Change gốc: `platform-booking-core`. `complete-core-ae` thêm route vào cùng hàng đợi.
- Cách làm tiếp: mở sketch, user chọn một phương án, đóng session, rồi đổi presentation của các route đó mà không đổi hợp đồng API.
