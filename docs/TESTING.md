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

## Thanh toán và lịch sử

```bash
DB_NAME=cinema_test php tests/payments.php
DB_NAME=cinema_test php tests/payment-concurrency.php
DB_NAME=cinema_test php tests/history.php
```

`payments.php`: 30 kiểm tra với merchant giả, gồm vector HMAC độc lập, URL ký đúng giá, quyền sở hữu đơn ở lớp dịch vụ, IPN giả/sai số tiền/sai merchant, ReturnURL chỉ đọc, callback lặp, thử lại, khoản thanh toán thứ hai, tiền về muộn/hủy và doanh thu Sandbox/production tách riêng. CSRF được kiểm tra qua HTTP. Không gọi ngân hàng thật. `payment-concurrency.php` chạy IPN lặp và IPN muộn cùng lúc người khác đặt lại ghế bằng process độc lập.

`history.php`: 19 kiểm tra với bảng nguồn giả, gồm dry-run không ghi, khóa chuỗi, hóa đơn nhiều vé không nhân tổng, không suy đoán qua khách hàng, phân quyền lịch sử, giữ dữ liệu gốc, nhập lại không trùng, nguồn/mapping thay đổi, quan hệ sai/thiếu, rollback, SQL identifier và tiền lẻ.

Bản `http.php` hiện kiểm tra thêm VNPay và lịch sử; server thử nghiệm cần môi trường merchant giả giống CLI:

```bash
export DB_NAME=cinema_http_test
export VNPAY_ENABLED=1 VNPAY_ENVIRONMENT=sandbox VNPAY_TMN_CODE=TEST1234
export VNPAY_HASH_SECRET=test-only-vnpay-signing-secret
export VNPAY_RETURN_URL=https://cinema.example/payment/return.php
php -S 127.0.0.1:8000 -t public
```

Trong terminal khác đặt cùng các biến thử rồi chạy `php tests/http.php`. Đây là khóa fixture công khai, không dùng trong triển khai hoặc kết nối nhà cung cấp.

## Trình duyệt

Node.js 20+ chỉ dùng để chạy kiểm thử. `package-lock.json` cố định các dependency của công cụ; website PHP không cần `npm install` để hoạt động.

```bash
npm ci --ignore-scripts --no-audit --no-fund
npx playwright install --with-deps chromium
```

Chuẩn bị **CSDL UI mới** tên `cinema_ui_test`, đặt biến VNPay thử như trên, chạy installer/seed và `php tests/ui-fixture.php`, rồi khởi động PHP server. Trong terminal có cùng cấu hình: `npm run test:ui`.

Kiểm tra 1440×960 và 390×844: trang phim, chi tiết phim, tìm kiếm không có kết quả, đăng nhập, lịch, chọn ghế/tính giá, chuyển sang trang ngân hàng mô phỏng, IPN ký từ Node, vé đã thanh toán, danh sách vé, lịch sử, quản trị và doanh thu. Kiểm tra nút/bàn phím đổi banner, tìm phim từ header, menu tài khoản/hóa đơn cũ, tràn ngang, ảnh thiếu, JavaScript/CSP, mở/đóng menu và các luật WCAG A/AA bằng axe. `test-results/` chứa ảnh và báo cáo; CI tải lên artifact `cinema-ui-checks`. Kiểm tra tự động không chứng minh toàn bộ WCAG hay thay thế rà soát thị giác/thử merchant thật.

Trang chủ được kiểm tra thêm ở 320/768/900/1024px để bắt lỗi tại ngưỡng đổi menu. Trang chọn ghế và tổng quan quản trị có thêm ảnh/axe ở 320px; sơ đồ ghế và bảng dữ liệu được phép cuộn trong vùng riêng, toàn trang không tràn ngang.

Bộ kiểm thử chặn POST `/payment/start.php`, thực hiện request đến PHP với `maxRedirects: 0`, xác minh phản hồi 303/URL/số tiền/chữ ký rồi thay phản hồi trong trình duyệt bằng trang ngân hàng mô phỏng tại cùng URL. Cách này dừng trước khi gọi nhà cung cấp; không xác minh giao diện ngân hàng hoặc toàn bộ bước chuyển trang khác nguồn của một giao dịch merchant thật. Ứng dụng vẫn trả 303 bình thường ngoài kiểm thử.
