# 🎬 Galaxy Cinema — Hệ thống đặt vé PHP 2025

> Hệ thống đặt vé xem phim trực tuyến xây dựng bằng PHP thuần, hỗ trợ đặt vé, chọn ghế, thanh toán VNPay và quản trị rạp chiếu phim toàn diện.

![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=flat&logo=mysql&logoColor=white)
![Apache](https://img.shields.io/badge/Apache-XAMPP-CA2136?style=flat&logo=apache&logoColor=white)

---

## 📋 Mục lục

- [Tính năng](#-tính-năng)
- [Yêu cầu hệ thống](#-yêu-cầu-hệ-thống)
- [Cài đặt](#-cài-đặt)
- [Cấu hình](#-cấu-hình)
- [Cấu trúc thư mục](#-cấu-trúc-thư-mục)
- [Sử dụng](#-sử-dụng)
- [Thanh toán VNPay](#-thanh-toán-vnpay)
- [Import lịch sử cũ](#-import-lịch-sử-cũ)
- [Chạy Tests](#-chạy-tests)
- [Deploy](#-deploy)
- [Bảo mật](#-bảo-mật)

---

## ✨ Tính năng

### 👤 Người dùng
- 🎥 Xem danh sách phim đang chiếu & sắp chiếu
- 🕐 Xem lịch chiếu theo rạp, phim, ngày
- 💺 Đặt vé & chọn ghế ngồi trực quan (giữ ghế 15 phút)
- 🛒 Thêm combo bắp nước vào đơn hàng
- 💳 Thanh toán online qua **VNPay**
- 🎫 Xem & in vé điện tử
- 📜 Xem lịch sử đặt vé & hóa đơn cũ
- 👤 Quản lý hồ sơ cá nhân & đổi mật khẩu
- 📰 Đọc tin tức & khuyến mãi

### 🛠️ Quản trị (Admin)
- 🎬 Quản lý phim (thêm, sửa, xoá, upload poster)
- 🏟️ Quản lý rạp chiếu & phòng chiếu
- 🕐 Quản lý suất chiếu
- 📦 Quản lý combo bắp nước
- 📋 Quản lý đơn hàng & xác nhận thanh toán tại quầy
- 💳 Quản lý thanh toán VNPay & đối soát
- 👥 Quản lý người dùng
- 📰 Quản lý tin tức
- 📊 Báo cáo doanh thu (tại quầy / VNPay / Sandbox)
- 📥 Import & đối soát lịch sử hóa đơn cũ

---

## 💻 Yêu cầu hệ thống

| Phần mềm | Phiên bản tối thiểu |
|---|---|
| PHP | >= 7.4 |
| MySQL | >= 5.7 |
| Apache | >= 2.4 (có `mod_rewrite`, `AllowOverride All`) |
| Node.js | >= 14.x *(để build assets)* |

> **Khuyến nghị:** Cài [XAMPP](https://www.apachefriends.org/) để có PHP + MySQL + Apache đầy đủ.

---

## 🚀 Cài đặt

### Bước 1: Lấy source code

```bash
git clone https://github.com/NguyenKhoa206/RapchieuphimPHP2025.git
```

Hoặc copy thư mục vào XAMPP:
```
C:\xampp\htdocs\RapchieuphimPHP2025\
```

### Bước 2: Tạo database

Mở **phpMyAdmin** (`http://localhost/phpmyadmin`) và tạo database:
```sql
CREATE DATABASE RapChieuPhim CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Sau đó import schema:
```bash
mysql -u root RapChieuPhim < database/schema.sql
```

### Bước 3: Cấu hình

Copy file cấu hình mẫu:
```bash
# Windows
copy config\local.example.php config\local.php

# Linux/Mac
cp config/local.example.php config/local.php
```

Chỉnh sửa `config/local.php` theo thông tin của bạn.

### Bước 4: Chạy script cài đặt

```
http://localhost/RapchieuphimPHP2025/scripts/install.php
```

> Script này tạo các bảng cần thiết, an toàn khi chạy lại — không xóa dữ liệu cũ.

### Bước 5: Tạo tài khoản Admin

```
http://localhost/RapchieuphimPHP2025/scripts/create-admin.php
```

### Bước 6: (Tuỳ chọn) Thêm dữ liệu mẫu

```
http://localhost/RapchieuphimPHP2025/scripts/seed.php
http://localhost/RapchieuphimPHP2025/scripts/seed-content.php
```

### Bước 7: Truy cập hệ thống

| Trang | URL |
|---|---|
| Trang chủ | `http://localhost/RapchieuphimPHP2025/` |
| Trang Admin | `http://localhost/RapchieuphimPHP2025/public/admin/` |

---

## ⚙️ Cấu hình

Chỉnh sửa `config/local.php` (file này **không được commit** lên Git):

```php
<?php
return [
    'database' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'RapChieuPhim',
        'user'     => 'root',
        'password' => '',
    ],

    // Để trống để tự phát hiện. Ví dụ XAMPP thư mục con: '/RapchieuphimPHP2025/public'
    'base_path' => '',

    // Bật thanh toán demo (chỉ dùng khi phát triển)
    'allow_demo_payment' => false,

    'payments' => [
        'vnpay' => [
            'enabled'     => false,
            'environment' => 'sandbox',   // sandbox | production
            'tmn_code'    => '',           // Mã merchant 8 ký tự từ VNPay
            'hash_secret' => '',           // Khóa bí mật từ VNPay
            'return_url'  => 'https://your-domain.example/payment/return.php',
        ],
    ],
];
```

### Cấu hình qua biến môi trường

Thay vì sửa file, bạn có thể dùng biến môi trường:

| Biến | Mô tả |
|---|---|
| `DB_HOST` | Host database (mặc định: `127.0.0.1`) |
| `DB_PORT` | Port database (mặc định: `3306`) |
| `DB_NAME` | Tên database (mặc định: `RapChieuPhim`) |
| `DB_USER` | Username database (mặc định: `root`) |
| `DB_PASS` | Password database |
| `VNPAY_ENABLED` | `1` để bật VNPay |
| `VNPAY_ENVIRONMENT` | `sandbox` hoặc `production` |
| `VNPAY_TMN_CODE` | Mã merchant VNPay |
| `VNPAY_HASH_SECRET` | Hash secret VNPay |
| `VNPAY_RETURN_URL` | URL callback sau thanh toán |
| `ALLOW_DEMO_PAYMENT` | `1` để bật thanh toán demo |

---

## 📁 Cấu trúc thư mục

```
RapchieuphimPHP2025/
│
├── app/                            # Core application
│   ├── Services/                   # Business logic
│   │   ├── BookingService.php      # Xử lý đặt vé, giữ ghế
│   │   ├── CinemaService.php       # Rạp & phòng chiếu
│   │   ├── PaymentService.php      # Thanh toán VNPay
│   │   ├── ReportService.php       # Báo cáo doanh thu
│   │   ├── NewsService.php         # Tin tức & khuyến mãi
│   │   ├── HistoryService.php      # Lịch sử đặt vé
│   │   └── HistoryImportService.php# Import hóa đơn cũ
│   │
│   ├── Support/                    # Helpers & utilities
│   │   ├── Auth.php                # Xác thực & phân quyền
│   │   ├── Database.php            # Kết nối PDO
│   │   ├── Upload.php              # Upload ảnh poster
│   │   ├── functions.php           # Helper functions
│   │   └── icons.php               # SVG icons
│   │
│   └── Views/                      # Partial views / components
│       ├── layouts/                # Header & Footer chung
│       ├── auth/                   # Form đăng nhập/đăng ký
│       ├── booking/                # Giao diện đặt vé & chọn ghế
│       ├── movies/                 # Card phim, suất chiếu
│       ├── news/                   # Card tin tức
│       ├── account/                # Lịch sử & vé cá nhân
│       └── admin/                  # Pagination admin
│
├── config/
│   ├── app.php                     # Cấu hình mặc định (an toàn commit)
│   ├── local.php                   # Cấu hình local — KHÔNG commit
│   ├── local.example.php           # Mẫu cấu hình
│   └── history.example.json        # Mẫu mapping import lịch sử
│
├── database/
│   ├── schema.sql                  # Cấu trúc toàn bộ bảng
│   └── demo-content.php            # Dữ liệu demo
│
├── docs/
│   ├── DEPLOYMENT.md               # Hướng dẫn deploy Apache/Nginx
│   ├── PAYMENTS.md                 # Cấu hình & vận hành VNPay
│   ├── HISTORY.md                  # Import lịch sử hóa đơn cũ
│   ├── MIGRATION.md                # Nâng cấp từ phiên bản cũ
│   └── TESTING.md                  # Hướng dẫn chạy tests
│
├── public/                         # Web root (DocumentRoot trỏ vào đây)
│   ├── index.php                   # Trang chủ — danh sách phim
│   ├── movie.php                   # Chi tiết phim
│   ├── showtimes.php               # Lịch chiếu
│   ├── booking.php                 # Chọn ghế & đặt vé
│   ├── order.php                   # Xác nhận đơn hàng
│   ├── media.php                   # Phục vụ media (poster, video)
│   ├── news.php                    # Danh sách tin tức
│   ├── news-detail.php             # Chi tiết tin tức
│   │
│   ├── admin/                      # Khu vực quản trị
│   │   ├── index.php               # Dashboard
│   │   ├── movies.php / movie-edit.php
│   │   ├── cinemas.php / cinema-edit.php
│   │   ├── showtimes.php / showtime-edit.php
│   │   ├── orders.php / order.php
│   │   ├── users.php / user-edit.php
│   │   ├── payments.php            # Thanh toán VNPay & đối soát
│   │   ├── revenue.php             # Báo cáo doanh thu
│   │   ├── news.php / news-edit.php
│   │   ├── combos.php              # Combo bắp nước
│   │   ├── history.php             # Lịch sử hóa đơn cũ
│   │   └── password.php            # Đổi mật khẩu admin
│   │
│   ├── auth/
│   │   ├── login.php / register.php / logout.php
│   │
│   ├── account/
│   │   ├── profile.php             # Hồ sơ cá nhân
│   │   ├── history.php             # Lịch sử đặt vé
│   │   └── tickets.php             # Vé điện tử
│   │
│   ├── payment/
│   │   ├── start.php               # Khởi tạo thanh toán VNPay
│   │   ├── return.php              # ReturnURL sau thanh toán
│   │   └── ipn.php                 # IPN callback từ VNPay
│   │
│   └── api/
│       ├── seats.php               # Trạng thái ghế real-time
│       └── order-status.php        # Trạng thái đơn hàng
│
├── scripts/                        # Script tiện ích (không public)
│   ├── install.php                 # Khởi tạo bảng database
│   ├── create-admin.php            # Tạo tài khoản admin đầu tiên
│   ├── seed.php                    # Seed phim, rạp, suất chiếu mẫu
│   ├── seed-content.php            # Seed tin tức & ảnh mẫu
│   ├── import-history.php          # Import lịch sử hóa đơn cũ
│   └── migrate-legacy.php          # Chuyển đổi dữ liệu từ hệ thống cũ
│
├── storage/
│   ├── logs/                       # Log lỗi ứng dụng
│   └── uploads/                    # Ảnh upload (poster phim...)
│
└── tests/                          # Test suite
    ├── run.php                      # Chạy toàn bộ tests
    ├── payments.php                 # Test luồng thanh toán VNPay
    ├── concurrency.php              # Test đặt vé đồng thời
    ├── payment-concurrency.php      # Test thanh toán đồng thời
    └── ui.cjs                       # UI end-to-end tests
```

---

## 🖥️ Sử dụng

### Luồng đặt vé (User)

```
Trang chủ → Chọn phim → Chọn suất chiếu
  → Chọn ghế (giữ 15 phút) → Chọn combo
  → Xác nhận đơn → Thanh toán VNPay
  → Nhận vé điện tử
```

### Luồng quản trị (Admin)

```
Đăng nhập Admin → Dashboard
  → Thêm phim / rạp / suất chiếu
  → Theo dõi đơn hàng
  → Xác nhận thanh toán tại quầy
  → Đối soát VNPay → Báo cáo doanh thu
```

---

## 💳 Thanh toán VNPay

Xem hướng dẫn đầy đủ tại [`docs/PAYMENTS.md`](docs/PAYMENTS.md)

### Cấu hình nhanh (Sandbox)

1. Đăng ký merchant tại [sandbox.vnpayment.vn/devreg](https://sandbox.vnpayment.vn/devreg/)
2. Cập nhật `config/local.php`:

```php
'payments' => [
    'vnpay' => [
        'enabled'     => true,
        'environment' => 'sandbox',
        'tmn_code'    => 'ABC12345',       // Mã merchant từ VNPay
        'hash_secret' => 'YOUR_SECRET',    // Khóa bí mật từ VNPay
        'return_url'  => 'https://your-domain/payment/return.php',
    ],
],
```

3. Khai báo IPN URL với VNPay: `https://your-domain/payment/ipn.php`

> **Lưu ý:** VNPay cần URL HTTPS truy cập được từ Internet. Localhost không nhận IPN từ VNPay.

### Báo cáo doanh thu

Trang doanh thu hiển thị **thu thực tế** (tại quầy + VNPay production). Có bộ lọc riêng cho:
- 🏪 Tại quầy
- 💳 VNPay thực (production)
- 🧪 Sandbox & demo
- 📜 Lịch sử cũ đã import

---

## 📥 Import lịch sử cũ

Xem hướng dẫn đầy đủ tại [`docs/HISTORY.md`](docs/HISTORY.md)

### Trình tự nhanh

```bash
# 1. Sao lưu database trước
# 2. Chạy install để tạo bảng lịch sử
php scripts/install.php

# 3. Tạo file mapping
copy config\history.example.json config\history.local.json
# Sửa các tên bảng/cột cho khớp với DB cũ

# 4. Chạy thử (không ghi dữ liệu)
php scripts/import-history.php --mapping=config/history.local.json

# 5. Xem báo cáo, đối chiếu rồi nhập thật
php scripts/import-history.php --mapping=config/history.local.json --apply
```

---

## 🧪 Chạy Tests

```bash
# Chạy toàn bộ tests
php tests/run.php

# Test riêng từng phần
php tests/payments.php
php tests/concurrency.php
php tests/payment-concurrency.php
```

> Xem hướng dẫn chi tiết tại [`docs/TESTING.md`](docs/TESTING.md)

---

## 🌐 Deploy

Xem hướng dẫn đầy đủ tại [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md)

### Apache (Ví dụ)

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

### Checklist trước khi deploy

- [ ] Đặt `allow_demo_payment => false` trong `config/local.php`
- [ ] Không commit `config/local.php`
- [ ] Thư mục `storage/uploads/` có quyền ghi (không dùng `777`)
- [ ] Bật HTTPS — cookie phiên cần thuộc tính `Secure`
- [ ] Cấu hình PHP: `display_errors=Off`, `log_errors=On`, `upload_max_filesize=25M`
- [ ] Múi giờ MySQL đặt `+07:00` để khớp với PHP `Asia/Ho_Chi_Minh`
- [ ] Tài khoản DB chỉ cần `SELECT/INSERT/UPDATE/DELETE` sau khi cài đặt
- [ ] Chạy lại `php scripts/install.php` sau mỗi lần nâng cấp

---

## 🔐 Bảo mật

- `config/local.php` chứa thông tin nhạy cảm → **KHÔNG commit lên Git** (đã có trong `.gitignore`)
- Các thư mục `app/`, `config/`, `database/`, `scripts/`, `storage/` được bảo vệ bằng `.htaccess` — **không public trực tiếp**
- Sử dụng **PDO + prepared statements** để chống SQL Injection
- Mật khẩu mã hoá bằng `password_hash()` / `password_verify()`
- Chữ ký VNPay dùng **HMAC-SHA512**, xác minh ở server
- Không tin tưởng `Host` header từ client khi tạo callback URL

---

## 📝 Changelog

Xem lịch sử thay đổi tại [`docs/HISTORY.md`](docs/HISTORY.md)

---

## 👨‍💻 Tác giả

**NguyenKhoa206** — [GitHub](https://github.com/NguyenKhoa206)

---

## 📄 License

MIT License — Free to use & modify.
