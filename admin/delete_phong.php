<?php
require_once("../connect_db.php");
require_once("../config.php");
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: " . ADMIN_URL . "login_admin.php");
    exit();
}

$conn = connect_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $maPhong = intval($_POST['MaPhong'] ?? 0);

    if ($maPhong <= 0) {
        echo "<script>alert('Mã phòng không hợp lệ!'); window.location='diadiem.php';</script>";
        exit();
    }

    // Xóa phòng
    $stmt = $conn->prepare("DELETE FROM PhongChieu WHERE MaPhong = ?");
    $stmt->bind_param("i", $maPhong);

    if ($stmt->execute()) {
        echo "<script>alert('Đã xóa phòng chiếu!'); window.location='diadiem.php';</script>";
    } else {
        echo "<script>alert('Không thể xóa! Phòng này đã có lịch chiếu.'); window.location='diadiem.php';</script>";
    }
}
?>
