# Chuyển dữ liệu cũ

## Trình tự

1. Xuất bản sao lưu CSDL đầy đủ và sao chép `uploads` cũ. Giữ bản gốc riêng để có thể quay lại.
2. Restore vào CSDL thử nghiệm; cấu hình `config/local.php` trỏ đến bản sao.
3. Chạy `php scripts/install.php`. Script tạo thêm các bảng `cinema_`, không DROP bảng cũ.
4. Khi các bảng mới còn trống, chạy `php scripts/migrate-legacy.php /duong-dan/uploads-cu`. Không chạy seed trước.
5. Kiểm tra số lượng khách hàng, admin, phim, rạp, phòng, lịch. Script dừng và rollback nếu dữ liệu vi phạm khóa ngoại/khóa duy nhất; kiểm tra email/điện thoại trùng, ngày trống hoặc ID không khớp khi import thất bại.
6. Đăng nhập thử khách/admin cũ. Nếu cần, `php scripts/create-admin.php tenadmin --reset` đặt lại mật khẩu admin do bạn chọn.
7. Đối chiếu vị trí ghế thực, giới hạn tuổi, poster và trailer. Khách cũ cần bổ sung ngày sinh để đặt phim giới hạn tuổi.
8. Chuyển DocumentRoot sang `public/` sau khi đối chiếu. Nếu quay về bản cũ, khôi phục mã và bản sao lưu đã lưu ở bước 1.

Import được đánh dấu trong `cinema_meta` để tránh nhập trùng. Tên bảng được tra không phân biệt hoa/thường; tên cột được chuẩn hóa từ kết quả truy vấn. Script chỉ nhận mật khẩu MD5 hoặc hash PHP, không nhập mật khẩu dạng văn bản.

## Ánh xạ

| Bảng cũ | Bảng mới | Ghi chú |
| --- | --- | --- |
| `KhachHang` | `cinema_users` | `MaKH` giữ trong `legacy_key`; email/điện thoại duy nhất |
| `Administrator` | `cinema_admins` | Nâng cấp MD5 khi đăng nhập |
| `BoPhim` | `cinema_movies` | Giữ ID, tính trạng thái theo ngày |
| `RapChieu` | `cinema_cinemas` | Giữ ID |
| `PhongChieu` | `cinema_rooms`, `cinema_seats` | Sinh ghế A1… theo `SoGhe`, tối đa 26 hàng × 30 cột |
| `LichChieu` | `cinema_showtimes` | Giữ ID, tính giờ kết thúc theo thời lượng phim |
| `HoaDon`, `Ve` | Giữ nguyên bảng cũ | Chưa đưa vào báo cáo/đơn mới |

Repo cũ không cung cấp SQL schema hay liên kết đáng tin cậy giữa hóa đơn và từng vé. Không suy đoán quan hệ qua `MaKH`: một khách có thể có nhiều đơn. Để nhập lịch sử tài chính, cần schema thật và đối chiếu khóa hóa đơn trên vé, giá, ghế, trạng thái, phương thức, thời điểm thanh toán. Sau đó mới viết migration riêng có tổng kiểm tra trước/sau.

Các lịch cũ được giữ nguyên, gồm cả lịch quá khứ. Các lịch trùng sẵn có không được tự dịch chuyển; hãy rà soát trước khi mở bán. Khi tạo/sửa lịch mới, hệ thống kiểm tra giao nhau và 15 phút dọn phòng qua cả nửa đêm.

Ảnh `hinh/` có trong repo được ánh xạ sang ảnh đã loại trùng. File `uploads/` được kiểm tra MIME, giới hạn dung lượng, đổi tên ngẫu nhiên rồi sao chép sang kho mới. File thiếu hoặc sai định dạng được báo và dùng ảnh mặc định; không chép trực tiếp file PHP vào thư mục public.
