# Khóa dòng staff khi đặt lịch

`AppointmentBooker` khóa đúng dòng `staff_profiles` bằng `lockForUpdate()` trong transaction, rồi tính lại slot. Tạo lịch khóa một dòng. Đổi nhân viên khóa các id theo thứ tự tăng dần.

Không dùng `Cache::lock`. TTL của khóa cache có thể hết trước khi transaction commit. Hai request khi đó cùng thấy slot trống và cùng ghi lịch.
