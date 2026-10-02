# Kiểm thử

`tests/run.php` chạy trực tiếp lớp nghiệp vụ, dùng CSDL riêng và tạo fixture tương đối theo ngày hiện tại. Tên CSDL phải kết thúc bằng `_test`. Không dùng CSDL chứa dữ liệu cần giữ: script xóa các bảng dữ liệu `cinema_` trong đó.

```bash
DB_NAME=cinema_test php scripts/install.php
DB_NAME=cinema_test php tests/run.php
DB_NAME=cinema_test php tests/concurrency.php
DB_NAME=cinema_migration_test php scripts/install.php
DB_NAME=cinema_migration_test php tests/migration.php
```

Nhóm kiểm tra: truy vấn đăng nhập chống SQL injection, nâng cấp MD5, mật khẩu/phiên, ghế sai phòng/đặt trùng, gửi lại đơn, giá trên máy chủ, giới hạn tuổi, trả ghế khi hủy/hết hạn, phân quyền đơn, thanh toán demo/tại quầy, doanh thu không nhân lên theo số vé, bảo toàn lịch sử, trùng lịch qua nửa đêm.

`concurrency.php` dùng hai process và hai kết nối độc lập, bắt đầu cùng thời điểm để đặt cùng ghế. Chỉ một đơn được tạo; người còn lại nhận lỗi nghiệp vụ. Cần cho phép `proc_open` trong PHP CLI; nếu hàm bị tắt script sẽ in SKIP và không tính là đã kiểm thử đồng thời.

`migration.php` tạo các bảng cũ giả lập trên CSDL riêng `cinema_migration_test`, kiểm tra khóa chuỗi khách hàng, ID danh mục, MD5, ảnh cũ, 780 ghế, giờ kết thúc, tính idempotent và giữ nguyên hóa đơn cũ. Không chạy script này trên CSDL migration cần giữ dữ liệu.

`http.php` kiểm tra request/response thật: cookie, CSRF, đăng ký, mật khẩu có khoảng trắng, điều hướng sau đăng nhập, hồ sơ, giữ ghế, gửi lại form, IDOR, quản trị, MIME upload và byte range. Chuẩn bị CSDL riêng rồi chạy server trong một terminal:

```bash
DB_NAME=cinema_http_test php scripts/install.php
DB_NAME=cinema_http_test php scripts/seed.php
DB_NAME=cinema_http_test php -S 127.0.0.1:8000 -t public
```

Trong terminal khác: `DB_NAME=cinema_http_test php tests/http.php`. Script tự tạo tài khoản thử trong CSDL thử nghiệm; không có tài khoản mặc định cho ứng dụng. Có thể đổi địa chỉ server bằng `TEST_BASE_URL`.

## Kiểm tra giao diện trước khi bàn giao

- Đăng ký → đăng nhập → hồ sơ → đổi mật khẩu → đăng xuất. Thử email/điện thoại trùng, mật khẩu sai, CSRF thiếu.
- Đăng nhập admin → thêm/sửa phim với poster hợp lệ → thử upload file sai MIME → rạp/phòng/ghế → lịch hợp lệ và lịch trùng → combo.
- Đặt vé tại quầy → xem đơn/hủy → đặt lại → admin xác nhận → xem/in vé → báo cáo ngày/tháng/năm và theo rạp.
- Mở cùng suất bằng hai tài khoản; thử giữ cùng ghế. Hết hạn giữ ghế phải chọn được lại, đơn đã thanh toán vẫn khóa ghế.
- Truy cập đơn của tài khoản khác phải trả 404; khách không vào được quản trị. POST sai CSRF phải trả 419, đăng xuất GET trả 405.
- Kiểm tra màn hình 390px/1440px, điều hướng bàn phím, lỗi console, ảnh thiếu, thanh toán demo mặc định không xuất hiện.

PHP lint và JS syntax:

```bash
find app config public scripts tests -name '*.php' -exec php -l {} \;
node --check public/assets/js/app.js
```
