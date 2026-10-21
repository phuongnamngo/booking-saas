# TODO

Việc được hoãn có chủ đích, ngoài change hiện tại. Các mục còn lại của `docs/PLAN.md` giữ nguyên trên plan, không ghi lại ở đây.

## Tài khoản Customer và tự đổi hoặc hủy lịch

- Kết quả: khách có user trong tenant, đăng nhập trên `/t/{slug}`, đổi và hủy lịch của mình. Hủy trước 24 giờ thì được, sau mốc đó thì bị từ chối.
- Lý do hoãn: change `platform-booking-core` chốt khách vãng lai, không tạo role Customer.
- Change gốc: `platform-booking-core`.
- Cách làm tiếp: thêm đăng ký customer tách khỏi đăng ký owner. Customer chỉ thấy appointment gắn với user của mình. Policy hủy đọc mốc 24 giờ theo timezone chi nhánh.

## Tenant theo subdomain

- Kết quả: host `{slug}.domain` resolve cùng tenant với path `/t/{slug}`.
- Lý do hoãn: Docker Compose hiện không có wildcard DNS. Change `platform-booking-core` dùng path vì Vite proxy và cookie Sanctum đang chạy trên một origin.
- Change gốc: `platform-booking-core`.
- Cách làm tiếp: giữ `ResolvePublicTenant` nhưng cho phép lấy slug từ host trước, rồi mới tới path. Có test host `demo.localhost` ra cùng dữ liệu với `/t/demo`.

## Visual pass cho layout admin và trang đặt lịch

- Kết quả: một hướng visual được chốt qua skill sketch, rồi áp lên các route đã có: login, register, `/app/*`, wizard `/t/:slug`.
- Lý do hoãn: skill sketch không có trong workspace. Change `platform-booking-core` không chọn design system thay thế, chỉ giao HTML và CSS đủ để các luồng chạy.
- Change gốc: `platform-booking-core`.
- Cách làm tiếp: mở sketch, user chọn một phương án, đóng session, rồi đổi presentation của các route đó mà không đổi hợp đồng API.
