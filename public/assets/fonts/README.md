# Font giao diện

| Font | Dùng cho | Tệp WOFF2 |
| --- | --- | --- |
| Be Vietnam Pro 1.002 | Nội dung, menu, biểu mẫu, tin tức, quản trị; đậm 400–800 | `be-vietnam-pro/be-vietnam-pro-{400,500,600,700,800}.woff2` |
| Oswald 4.103 | Tiêu đề phía khách, tên phim trên thẻ poster, banner; trục đậm 200–700, giao diện dùng 600/700 | `oswald/oswald-variable.woff2` |

Tổng sáu tệp: 273.440 byte. Tệp gốc được chuyển từ TTF sang WOFF2 bằng FontTools 4.61.1, giữ toàn bộ glyph, dấu tiếng Việt, bảng OpenType và trục biến thiên. Không sửa hình dáng chữ hoặc tạo subset. Website không cần Python, Node.js hay kết nối Google Fonts để dùng font.

`../css/fonts.css` khai báo font với `font-display: swap`. Các token `--font-body` và `--font-display` nằm trong `../css/app.css`; cỡ chữ/giãn dòng của giao diện cũ nằm trong `../css/classic.css`. Layout preload Be Vietnam Pro 400 và Oswald ở trang khách; các độ đậm còn lại chỉ tải khi trình duyệt cần. URL preload trùng URL trong CSS, kể cả khi chạy ở thư mục con của XAMPP. Các font có fallback sans-serif.

## Nguồn và giấy phép

Nguồn từ [Google Fonts](https://github.com/google/fonts/tree/main/ofl), dự án gốc [Be Vietnam Pro](https://github.com/bettergui/BeVietnamPro) và [Oswald](https://github.com/googlefonts/OswaldFont). Cả hai dùng SIL Open Font License 1.1; giữ `be-vietnam-pro/OFL.txt` và `oswald/OFL.txt` khi phân phối mã nguồn.

Blob SHA của các tệp TTF nguồn đã đối chiếu trước khi chuyển định dạng:

| Tệp trong `google/fonts/ofl/` | Blob SHA |
| --- | --- |
| `bevietnampro/BeVietnamPro-Regular.ttf` | `dfa34c09fe2bae0626e00aa3265af2119345c27b` |
| `bevietnampro/BeVietnamPro-Medium.ttf` | `d6deb87afdd7ddf003f3d0ab1070ab9df1df0724` |
| `bevietnampro/BeVietnamPro-SemiBold.ttf` | `b25bad31fe8984d5db6cbce61a23323bbb68bc97` |
| `bevietnampro/BeVietnamPro-Bold.ttf` | `52aadc6da089ed8ae647b5e0c8cac4f8b3e24750` |
| `bevietnampro/BeVietnamPro-ExtraBold.ttf` | `85484699c638dda2de01658ef2a1dae41dc54ca7` |
| `oswald/Oswald[wght].ttf` | `d1a3b9cb1325bf20b3a06ef9849d21c411212a3b` |
