# Triển khai

DocumentRoot phải trỏ đến `public/`; `app`, `config`, `database`, `scripts`, `storage`, `tests` nằm ngoài vùng HTTP. Không phục vụ root bằng PHP development server. Với XAMPP trong thư mục con, bật `mod_rewrite`, `AllowOverride All`; các thư mục riêng còn có `.htaccess` chặn trực tiếp.

Ví dụ Apache:

```apache
<VirtualHost *:80>
    ServerName cinema.local
    DocumentRoot /var/www/cinema/public
    <Directory /var/www/cinema/public>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>
</VirtualHost>
```

Với Nginx/PHP-FPM, đặt `root /var/www/cinema/public`, chỉ chuyển file PHP tồn tại bằng `try_files $uri =404` trong location PHP, truyền đúng `SCRIPT_FILENAME`, chặn đường dẫn bắt đầu bằng dấu chấm. Không bật location alias đến `storage`.

- Bật HTTPS để cookie phiên có thuộc tính Secure. Nếu TLS kết thúc ở proxy, cấu hình web server truyền HTTPS theo proxy tin cậy; ứng dụng không tự tin mọi header từ client.
- Cấu hình PHP `display_errors=Off`, `log_errors=On`, `upload_max_filesize=25M`, `post_max_size=52M`, `max_file_uploads` ít nhất 2.
- Cho tài khoản PHP quyền ghi `storage/uploads/`, không dùng quyền `777`. Media được phục vụ qua `media.php`; tên do máy chủ tạo, hỗ trợ byte range cho video.
- Dùng tài khoản CSDL giới hạn vào CSDL dự án. `install.php` cần quyền tạo database/table; sau cài đặt tài khoản chạy web chỉ cần SELECT/INSERT/UPDATE/DELETE.
- Đặt `DB_PASS` bằng biến môi trường hoặc `config/local.php`; file này không được commit. Sao lưu CSDL và uploads cùng nhau.
- Dùng múi giờ `Asia/Ho_Chi_Minh`; kết nối MySQL đặt timezone `+07:00` để hết hạn/giờ chiếu khớp PHP.
- Mặc định `allow_demo_payment=false`. Xác nhận tại quầy trong `/admin/orders.php`; khi triển khai thật cần đối chiếu tiền đã thu trước khi bấm xác nhận.

VNPay có redirect/ReturnURL/IPN xác minh tại máy chủ; xem [cấu hình thanh toán](PAYMENTS.md). Cần merchant và URL HTTPS truy cập được trước khi bật cổng. Ứng dụng chưa tự hoàn tiền, truy vấn giao dịch thất lạc, gửi email/SMS hay kiểm vé QR. Các khoản cần đối soát được ghi nhận riêng để xử lý theo quy trình của rạp. Không dùng demo/Sandbox như chứng từ thu tiền.

Khi nâng cấp từ nhánh đã cấu trúc trước, chạy lại `php scripts/install.php` để tạo bảng thanh toán và lịch sử mới. Không chạy seed hoặc migrate danh mục lại trên CSDL có dữ liệu. Các URL giao diện giữ nguyên; tài nguyên CSS/JS có version để cập nhật cache sau triển khai.
