# Rạp chiếu phim PHP

Ứng dụng PHP/MySQL quản lý phim, rạp, phòng, lịch chiếu, đặt ghế, combo và doanh thu. Bản này gom phần xử lý dùng chung, bỏ các trang HTML/file `copy` trùng lặp, và bổ sung luồng đặt vé lưu vào CSDL.

## Yêu cầu

- PHP 8.1 trở lên; đã kiểm thử với PHP 8.3.
- MySQL 8 hoặc MariaDB 10.6 trở lên; các bảng dùng InnoDB, `utf8mb4`.
- Extension PHP: `pdo_mysql`, `mbstring`, `fileinfo`. Bật upload nếu muốn thêm poster/trailer.
- Không cần Node.js hoặc Composer để chạy ứng dụng.

## Cài mới

1. Clone repository và vào thư mục dự án.
2. Sao chép `config/local.example.php` thành `config/local.php`, sửa thông tin CSDL. Hoặc đặt biến môi trường `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.
3. Tạo bảng và dữ liệu minh họa:

```bash
php scripts/install.php
php scripts/seed.php
php scripts/create-admin.php admin
php -S 127.0.0.1:8000 -t public
```

Lệnh tạo admin yêu cầu nhập mật khẩu trong terminal; không có tài khoản hay mật khẩu mặc định. Nếu không cần dữ liệu mẫu, bỏ qua `seed.php` và thêm phim/rạp/lịch chiếu trong quản trị.

Mở `http://127.0.0.1:8000/`; quản trị tại `/admin/login.php`. Đăng ký tài khoản khách hàng trên giao diện. Dữ liệu seed dùng lịch tương đối trong 3 ngày tới; không phải lịch chiếu, thời lượng hay giá thực tế.

**XAMPP trên Windows:** đặt dự án trong `htdocs`, bật MySQL và Apache. Nếu `php` chưa có trong PATH, dùng `C:\xampp\php\php.exe` thay cho `php` trong các lệnh trên. Truy cập `http://localhost/RapchieuphimPHP2025/public/`. Đường dẫn tự nhận diện thư mục con; có thể đặt `base_path` trong `config/local.php` nếu dùng reverse proxy. Apache cần `AllowOverride All` để đọc `.htaccess`. PHP development server chỉ chạy với `-t public`.

## Chuyển từ bản cũ

**Sao lưu CSDL và thư mục uploads trước**, chạy thử trên bản sao trước khi dùng dữ liệu thật. `install.php` chỉ tạo các bảng có tiền tố `cinema_`, không xóa bảng cũ.

```bash
php scripts/install.php
php scripts/migrate-legacy.php /duong-dan/toi/uploads-cu
php scripts/create-admin.php admin
```

Không chạy `seed.php` hoặc tạo admin trước bước import. Script nhập khách hàng, admin, phim, rạp, phòng và lịch chiếu từ các tên bảng/cột mà mã nguồn cũ sử dụng. Khóa khách hàng dạng `KH001` được giữ ở `legacy_key`; phim/phòng/lịch giữ ID cũ. MD5 cũ được nâng cấp thành `password_hash` sau lần đăng nhập thành công đầu tiên. Lệnh tạo admin sẽ từ chối ghi đè admin có sẵn; chỉ dùng `--reset` khi chủ động cần đặt lại mật khẩu.

Repository cũ không có schema SQL đầy đủ. Vì quan hệ `HoaDon`–`Ve` chưa xác định được và báo cáo cũ nối qua khách hàng, **script chưa nhập lịch sử vé/hóa đơn cũ vào báo cáo mới**. Các bảng cũ vẫn giữ nguyên để đối chiếu. Sơ đồ ghế được tạo từ tổng `SoGhe`, chưa thể tái tạo vị trí thực nếu không có bảng ghế gốc. Xem [hướng dẫn chuyển dữ liệu](docs/MIGRATION.md) trước khi chuyển hệ thống đang dùng.

## Cấu trúc

| Đường dẫn | Mục đích |
| --- | --- |
| `public/` | Điểm vào HTTP; trang khách hàng, quản trị, API ghế, CSS/JS/ảnh |
| `app/Services/` | Nghiệp vụ phim/lịch, giữ ghế, đơn vé, doanh thu |
| `app/Support/` | CSDL, đăng nhập, CSRF, upload, helper |
| `app/Views/` | Layout và thành phần giao diện dùng chung |
| `config/` | Cấu hình mặc định và mẫu cấu hình riêng |
| `database/` | Schema mới và ánh xạ ảnh cũ đã loại trùng |
| `scripts/` | Lệnh cài đặt, dữ liệu mẫu, admin, chuyển dữ liệu |
| `storage/uploads/` | Media tải lên; phục vụ qua `public/media.php` |
| `tests/` | Kiểm thử nghiệp vụ trên CSDL riêng |
| `docs/` | Chuyển dữ liệu, triển khai, kiểm thử |

Không commit `config/local.php`, mật khẩu CSDL, dữ liệu upload hoặc bản sao lưu. Các URL cũ như `trangchu.php`, `admin/bophim.php` đã đổi sang cấu trúc trong `public/`; hãy cập nhật bookmark và liên kết ngoài.

## Luồng đặt vé

Khách đăng nhập → chọn suất/ghế/combo → tạo đơn chờ → thanh toán tại quầy → nhân viên xác nhận trong quản trị → xem/in vé. Đơn giữ ghế tối đa 15 phút, hoặc đến giờ chiếu. Hủy đơn chờ trả lại ghế; đơn hết hạn không khóa ghế nữa. Ghế hết hạn được dọn khi xử lý đặt vé tiếp theo. Đơn đã trả tiền không được hủy theo luồng này.

Giá lấy từ CSDL, không tin tổng tiền gửi từ trình duyệt. Transaction, khóa suất chiếu và khóa duy nhất `(showtime_id, seat_id)` ngăn đặt trùng ghế. Gửi lại cùng biểu mẫu không tạo thêm đơn. Phim giới hạn tuổi yêu cầu khách cập nhật ngày sinh.

**Chưa tích hợp cổng thanh toán trực tuyến.** `allow_demo_payment` mặc định tắt; bật bằng `ALLOW_DEMO_PAYMENT=1` hoặc cấu hình riêng để thử luồng. Mọi đơn demo được gắn nhãn và tách khỏi doanh thu tại quầy. Đơn tại quầy chỉ được admin xác nhận. Không dùng demo để xác nhận tiền thật.

## Kiểm thử

Chỉ dùng CSDL riêng có tên kết thúc bằng `_test`; lệnh kiểm thử sẽ xóa dữ liệu `cinema_` trong CSDL đó.

```bash
DB_NAME=cinema_test php scripts/install.php
DB_NAME=cinema_test php tests/run.php
DB_NAME=cinema_test php tests/concurrency.php
DB_NAME=cinema_migration_test php scripts/install.php
DB_NAME=cinema_migration_test php tests/migration.php
```

PowerShell: đặt `$env:DB_NAME='cinema_test'` rồi chạy các lệnh PHP. Kiểm thử đồng thời dùng hai process PHP độc lập; cần cho phép `proc_open` trong PHP CLI. Xem [kiểm thử](docs/TESTING.md) và [triển khai](docs/DEPLOYMENT.md).

Workflow GitHub Actions chạy PHP lint, kiểm thử nghiệp vụ, đặt ghế đồng thời, migration giả lập và các luồng HTTP trên MariaDB riêng. Không sử dụng CSDL thật hoặc mật khẩu triển khai để chạy CI.
