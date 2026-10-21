# Tenant đi theo path, khách không phải user

Đặt lịch công khai dùng `/t/{slug}`. Slug nằm trên URL, ví dụ `/t/demo`. Subdomain để sau, khi có DNS và cookie theo host.

`customers` không phải user. Khách nhập tên, số điện thoại và email tùy chọn. Hệ thống tìm customer theo `(tenant_id, phone)` sau khi trim. Cùng số thì dùng lại dòng đó và cập nhật tên. `POST` public không tạo `users` và để `created_by` null.

Khách không tự đổi giờ hoặc hủy. Owner, manager và staff làm việc đó trong app.
