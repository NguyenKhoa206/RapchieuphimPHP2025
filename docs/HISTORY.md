# Nhập lịch sử hóa đơn/vé cũ

Công cụ `scripts/import-history.php` lưu bản gốc của `HoaDon` và `Ve` vào bảng lịch sử riêng. Nó không tạo đơn giữ ghế, không tự đánh dấu đã trả tiền và không cộng lịch sử vào doanh thu vé mới. Khách chỉ xem các bản ghi đã liên kết với tài khoản của mình; quản trị có trang đối chiếu riêng.

Repo gốc không kèm SQL/schema. `config/history.example.json` là mẫu ánh xạ theo các cột đã thấy trong mã cũ, chưa phải xác nhận cấu trúc CSDL của bạn. Cần kiểm tra bằng `SHOW CREATE TABLE HoaDon` và `SHOW CREATE TABLE Ve` hoặc bản xuất SQL thực.

## Trình tự

1. Sao lưu CSDL; làm trước trên bản sao. Restore các bảng cũ vào cùng CSDL đã cấu hình cho ứng dụng.
2. Chạy `php scripts/install.php`. Chuyển danh mục/khách hàng bằng `scripts/migrate-legacy.php` trước khi nhập lịch sử, để tài khoản có `legacy_key` chính xác. Không dùng dữ liệu seed làm danh mục cũ.
3. Sao chép `config/history.example.json` thành `config/history.local.json`. Đối chiếu tên bảng và cột:

| Trường mapping | Ý nghĩa |
| --- | --- |
| `source_name` | Tên nguồn ổn định, ví dụ `legacy-2025`; dùng lại khi nhập tiếp cùng nguồn |
| `id` | Khóa duy nhất của bản ghi; `null` chỉ tự nhận nếu bảng có khóa chính một cột |
| `customer_id` | Mã khách hàng cũ, đối chiếu chính xác với `cinema_users.legacy_key` |
| `tickets.columns.invoice_id` | Cột khóa hóa đơn thật trên vé; để `null` khi chưa chứng minh quan hệ |
| `issued_at`, `starts_at` | Ngày nguồn dạng `Y-m-d` hoặc `Y-m-d H:i:s` |
| `amount` | Số tiền nguyên VND, không có dấu phân cách; số DECIMAL có phần lẻ bằng 0 được chấp nhận |
| `status` | Trạng thái gốc, lưu nguyên văn, không suy diễn thành paid |
| `movie_title`, `cinema_name`, `room_name`, `seat_label` | Cột snapshot nếu nguồn có; `null` nếu không có |
| `showtime_id` | Có thể tra danh mục đã migrate và giữ ID cũ; không tra danh mục seed |

Cột không tồn tại phải được sửa hoặc để `null`. Với ngày/số tiền định dạng khác, chuẩn hóa trên **bản sao nguồn** sau khi đối chiếu. Không tự làm tròn số tiền hay sửa bảng gốc để ép import.

4. Chạy thử, mặc định không ghi dữ liệu:

```bash
php scripts/import-history.php --mapping=config/history.local.json
```

Báo cáo JSON gồm số hóa đơn/vé, số mới/đã có, tổng tiền hóa đơn nguồn, vé có/chưa có quan hệ, khách chưa liên kết, lịch chưa xác định và hóa đơn thiếu số tiền. Tổng hóa đơn cộng đúng một lần cho mỗi hóa đơn, không nhân theo số vé.

5. Đối chiếu báo cáo với nguồn rồi nhập:

```bash
php scripts/import-history.php --mapping=config/history.local.json --apply
```

6. Vào quản trị → **Lịch sử cũ**. Kiểm tra số lượng, tổng tiền nguồn, khách chưa liên kết và dữ liệu nguồn trên từng bản ghi. Đăng nhập khách cũ → **Vé & hóa đơn của tôi** → **Hóa đơn cũ / Vé cũ** để kiểm tra quyền xem.

## Quy tắc bảo toàn

- Khóa dạng `HD001`, `KH001`, có số 0 đầu được giữ nguyên. Không ghép hóa đơn–vé qua `MaKH`, tên, email hoặc cùng thời gian. Một khách có thể có nhiều hóa đơn.
- Khi có mapping khóa hóa đơn, hóa đơn phải tồn tại và mã khách trên vé phải khớp. Sai quan hệ dừng toàn bộ lượt nhập và rollback.
- Không có khóa hóa đơn thì vé lưu độc lập. Không có tài khoản/lịch thì dữ liệu gốc vẫn lưu và báo chưa liên kết; khách chưa xác định chỉ xuất hiện trong quản trị.
- Cùng nguồn + khóa + nội dung: nhập lại bỏ qua, không trùng. Nếu nguồn hoặc mapping của bản đã nhập thay đổi, công cụ dừng để đối chiếu, không ghi đè lịch sử. Không đổi `source_name` để lách kiểm tra vì sẽ tạo một nguồn lưu trữ khác.
- Bản gốc lưu trong `raw_data`, chỉ quản trị được xem. Nội dung hiển thị đã escape. Các bảng nguồn không bị sửa/xóa; một lỗi trong lúc áp dụng rollback toàn bộ lượt nhập.

Chạy khi nguồn đã ngừng nhận ghi mới, hoặc trên bản xuất nhất quán. Không nhập liên tục từ hệ thống cũ đang chỉnh sửa hóa đơn. Nên nhập sau khi đã liên kết khách hàng: bản ghi được bỏ qua khi nhập lại sẽ giữ liên kết đã tạo ở lần đầu.

Chưa có bản xuất thật của dự án trong workspace; dữ liệu hiện đã được kiểm thử bằng fixture, chưa nhập lịch sử thực của bạn.
