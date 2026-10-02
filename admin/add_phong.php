<?php
require_once("../connect_db.php");
require_once("../config.php");
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: " . ADMIN_URL . "login_admin.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $conn = connect_db();

    $maRap = intval($_POST['MaRap'] ?? 0);
    $tenPhong = trim($_POST['TenPhong'] ?? '');
    $soGhe = intval($_POST['SoGhe'] ?? 0);

    // Kiểm tra dữ liệu hợp lệ
    if ($maRap <= 0 || $tenPhong === '' || $soGhe <= 0) {
        echo "<script>alert('Vui lòng nhập đầy đủ thông tin phòng!'); window.history.back();</script>";
        exit();
    }

    // Thêm phòng mới
    $stmt = $conn->prepare("INSERT INTO PhongChieu (MaRap, TenPhong, SoGhe) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $maRap, $tenPhong, $soGhe);

    if ($stmt->execute()) {
        echo "<script>alert('Đã thêm phòng chiếu!'); window.location='diadiem.php';</script>";
    } else {
        echo "<script>alert('Lỗi SQL: " . $stmt->error . "');</script>";
    }
}
?>
