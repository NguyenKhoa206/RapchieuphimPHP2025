# Thanh toán VNPay

Ứng dụng có luồng chuyển đến VNPay, ReturnURL, IPN có chữ ký và trang đối soát. Mặc định cổng bị tắt; chỉ hiển thị lựa chọn VNPay khi cấu hình đầy đủ. Sandbox và đơn demo có nhãn riêng, không cộng vào doanh thu thực.

## Cấu hình thử nghiệm

1. Đăng ký merchant sandbox tại [VNPay](https://sandbox.vnpayment.vn/devreg/), nhận TmnCode và HashSecret riêng. Đối chiếu [tài liệu PAY 2.1.0](https://sandbox.vnpayment.vn/apis/docs/thanh-toan-pay/pay.html).
2. Chạy `php scripts/install.php` trên CSDL đã sao lưu để tạo thêm bảng giao dịch/lịch sử. Lệnh dùng được cho bản đã cài; không xóa dữ liệu cũ.
3. Đặt các giá trị dưới đây trong môi trường máy chủ, hoặc phần `payments.vnpay` của `config/local.php`:

| Biến | Giá trị |
| --- | --- |
| `VNPAY_ENABLED` | `1` |
| `VNPAY_ENVIRONMENT` | `sandbox` |
| `VNPAY_TMN_CODE` | Mã merchant 8 ký tự do VNPay cấp |
| `VNPAY_HASH_SECRET` | Khóa riêng do VNPay cấp |
| `VNPAY_RETURN_URL` | URL HTTPS đầy đủ đến `/payment/return.php` |

4. Khai báo IPN với VNPay: `https://ten-mien-cua-ban/payment/ipn.php`. Nếu chạy trong thư mục con, cả IPN và ReturnURL phải chứa đúng đường dẫn, ví dụ `/RapchieuphimPHP2025/public/payment/ipn.php`. VNPay phải truy cập được URL này từ Internet; localhost không nhận được IPN của nhà cung cấp.
5. Chọn ghế → phương thức VNPay → đơn vé → **Thanh toán qua VNPay**. Kiểm tra giao dịch trên trang quản trị **Thanh toán online** và vé trong tài khoản.

Không commit khóa hoặc cấu hình riêng. Không dùng khóa fixture trong `tests/` để kết nối merchant thật. Không cần gửi HashSecret qua chat.

## Cách xác nhận

- Giá và hạn thanh toán lấy từ đơn đã lưu. Mỗi lần thử có mã giao dịch duy nhất; nhấn lặp khi giao dịch đang chờ dùng lại yêu cầu đó. Sau thông báo thất bại hợp lệ, khách có thể thử lại trong thời gian giữ ghế còn lại.
- Chữ ký HMAC-SHA512, mã merchant, số tiền, mã giao dịch và dữ liệu callback được kiểm tra ở máy chủ. IPN lặp không cộng lại tiền. Hai mã kết quả phải cùng thành công để xác nhận đơn.
- ReturnURL chỉ xác minh và điều hướng về đơn; không cập nhật trả tiền. Trang đơn tự theo dõi trạng thái từ CSDL. Đóng trình duyệt không làm mất IPN.
- Tiền về sau khi đơn hết hạn/hủy, ghế không còn đủ, hoặc có thêm khoản thanh toán sau một lần thành công được đánh dấu **Cần đối soát**. Khoản này không cấp vé và không cộng vào doanh thu vé đã bán. Dùng mã VNPay để đối chiếu/hoàn tiền trên hệ thống merchant theo quy trình của rạp. Ứng dụng chưa tự gọi API hoàn tiền.
- Nhân viên chỉ xác nhận tiền tại quầy hoặc demo; không có nút xác nhận thủ công cho VNPay. Đơn đã trả tiền không bị hủy bằng luồng hủy đơn chờ.

Trang doanh thu mặc định **thu thực tế**: tại quầy + VNPay production đã xác nhận. Có bộ lọc riêng cho quầy, VNPay thực, Sandbox và demo. Lịch sử nhập từ hệ thống cũ được thống kê riêng, chưa được coi là tiền đã đối soát.

## Đưa vào vận hành

Dùng tài khoản và khóa production do VNPay cấp, đặt `VNPAY_ENVIRONMENT=production`, cấu hình HTTPS/callback production và hoàn tất kiểm tra theo merchant của bạn. URL đích do mã nguồn cố định theo môi trường; không lấy từ trình duyệt hoặc Host header.

Không thay merchant/môi trường/HashSecret khi còn giao dịch chờ hoặc IPN chưa xử lý; callback của cấu hình cũ sẽ không được xác minh bằng cấu hình mới. Đối chiếu các đơn chờ với hệ thống merchant trước khi chuyển cấu hình. Trường hợp thiếu IPN cần đối soát ở VNPay; ứng dụng chưa có tác vụ tự truy vấn giao dịch.

Các kiểm thử trong repo dùng merchant giả, callback tự ký và trang ngân hàng mô phỏng. Chúng xác minh logic và giao diện, không thay thế việc chạy một giao dịch end-to-end với merchant sandbox của bạn. Chưa kích hoạt thu tiền thật trong repository.
