<?php
require_once("../config.php");
require_once("../connect_db.php");
session_start();
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
    // Xóa thông báo sau khi đã hiển thị
    unset($_SESSION['error_message']);
}

// 1. Thiết lập múi giờ Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');

// 2. Kiểm tra quyền Admin
if(!isset($_SESSION['admin'])){
    header("Location: ".ADMIN_URL."login_admin.php");
    exit();
}

$conn = connect_db();

// Hàm xử lý lỗi: Chuyển hướng quay lại trang thêm lịch chiếu kèm thông báo lỗi
function baoLoiVaQuayLai($thongBao, $maPhim) {
    // Sử dụng $_SESSION để lưu thông báo lỗi tạm thời (Tốt hơn dùng $_GET)
    $_SESSION['error_message'] = $thongBao; 
    header("Location: bophim_detail.php?MaPhim=$maPhim&error=1");
    exit();
}

// 3. Chỉ xử lý khi có POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu đầu vào
    $maPhim    = intval($_POST['MaPhim']);
    $maPhong   = intval($_POST['MaPhong']);
    $ngayChieu = $_POST['NgayChieu']; // YYYY-MM-DD
    $gioChieu  = $_POST['GioChieu'];  // HH:MM
    $giaVe     = 90000; 
    $thoiGianDonDep = 15; // phút

    // --- BƯỚC 1: LẤY THÔNG TIN PHIM (Kiểm tra phim có tồn tại không)---
    $stmtPhim = $conn->prepare("SELECT ThoiLuong FROM BoPhim WHERE MaPhim = ?");
    $stmtPhim->bind_param("i", $maPhim);
    $stmtPhim->execute();
    $resPhim = $stmtPhim->get_result();

    if ($resPhim->num_rows == 0) {
        $stmtPhim->close();
        // Giả sử chuyển về trang quản lý danh sách phim nếu MaPhim không hợp lệ
        header("Location: bophim.php?error=phim_notfound");
        exit();
    }
    $phim = $resPhim->fetch_assoc();
    $thoiLuongPhim = intval($phim['ThoiLuong']);
    $stmtPhim->close();

    // --- BƯỚC 2: KIỂM TRA PHÒNG CHIẾU CÓ TỒN TẠI KHÔNG ---
    $stmtPhong = $conn->prepare("SELECT MaPhong FROM PhongChieu WHERE MaPhong = ?");
    $stmtPhong->bind_param("i", $maPhong);
    $stmtPhong->execute();
    $resPhong = $stmtPhong->get_result();
    $stmtPhong->close();

    // KIỂM TRA LỖI Ở ĐÂY
    if ($resPhong->num_rows === 0) {
        // Báo lỗi và quay lại trang chi tiết phim
        baoLoiVaQuayLai("Lỗi: Mã phòng chiếu không tồn tại.", $maPhim);
    }
    
    // Tính toán thời gian bắt đầu và kết thúc suất chiếu mới
    $newStart = strtotime("$ngayChieu " . $gioChieu);
    $newEnd = $newStart + ($thoiLuongPhim * 60);

    // --- BƯỚC 3: KIỂM TRA TRÙNG LỊCH TRONG PHÒNG CHIẾU ---
    // Truy vấn tất cả lịch chiếu đã có trong phòng vào ngày này, và lấy thêm ThoiLuong của phim đó
    $sqlCheck = "SELECT LC.GioChieu, BP.ThoiLuong 
                 FROM LichChieu LC
                 JOIN BoPhim BP ON LC.MaPhim = BP.MaPhim
                 WHERE LC.MaPhong = ? AND LC.NgayChieu = ?";
    
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bind_param("is", $maPhong, $ngayChieu);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();

    while ($row = $resultCheck->fetch_assoc()) {
        $oldStart = strtotime("$ngayChieu " . $row['GioChieu']);
        $oldDuration = intval($row['ThoiLuong']);
        $oldEnd = $oldStart + ($oldDuration * 60) + ($thoiGianDonDep * 60);

        // Kiểm tra chồng lấn thời gian
        if ($newStart < $oldEnd && $newEnd + ($thoiGianDonDep * 60) > $oldStart) {
            $stmtCheck->close();
            $gioBiTrung = date("H:i", $oldStart);
            baoLoiVaQuayLai("Trùng lịch! Phòng này đã có phim chiếu bắt đầu lúc $gioBiTrung. (Thời gian trống cần > " . $thoiGianDonDep . " phút)", $maPhim);
        }
    }
    $stmtCheck->close();

    // --- BƯỚC 4: THÊM LỊCH CHIẾU ---
    $stmtInsert = $conn->prepare("INSERT INTO LichChieu(MaPhim, MaPhong, NgayChieu, GioChieu, GiaVe) VALUES (?, ?, ?, ?, ?)");
    $stmtInsert->bind_param("iissd", $maPhim, $maPhong, $ngayChieu, $gioChieu, $giaVe);
    if ($stmtInsert->execute()) {
        $stmtInsert->close();
        // Chuyển hướng thành công
        header("Location: bophim_detail.php?MaPhim=$maPhim&msg=success");
        exit();
    } else {
        $stmtInsert->close();
        // Chuyển hướng lỗi SQL
        baoLoiVaQuayLai("Lỗi SQL khi thêm lịch chiếu: " . $conn->error, $maPhim);
    }
} else {
    // Nếu truy cập trực tiếp bằng GET, chuyển về trang danh sách phim
    header("Location: bophim.php");
    exit();
}
?>