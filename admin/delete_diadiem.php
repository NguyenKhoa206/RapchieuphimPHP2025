<?php
require_once("../connect_db.php");
require_once("../config.php");
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: " . ADMIN_URL . "login_admin.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $maRap = intval($_POST['MaRap'] ?? 0);
    if ($maRap <= 0) {
        echo "<script>alert('Mã rạp không hợp lệ!'); window.location='diadiem.php';</script>";
        exit();
    }

    $conn = connect_db();

    // 1. Lấy tất cả phòng của rạp
    $stmtPhong = $conn->prepare("SELECT MaPhong FROM PhongChieu WHERE MaRap = ?");
    $stmtPhong->bind_param("i", $maRap);
    $stmtPhong->execute();
    $dsPhong = $stmtPhong->get_result();

    // 2. Kiểm tra lịch chiếu từng phòng
    while ($phong = $dsPhong->fetch_assoc()) {
        $checkLC = $conn->prepare("SELECT * FROM LichChieu WHERE MaPhong = ?");
        $checkLC->bind_param("i", $phong["MaPhong"]);
        $checkLC->execute();
        $kq = $checkLC->get_result();

        if ($kq->num_rows > 0) {
            echo "<script>
                    alert('Không thể xóa rạp! Một hoặc nhiều phòng trong rạp đang có lịch chiếu.');
                    window.location='diadiem.php';
                  </script>";
            exit();
        }
    }

    // 3. Xóa phòng trước
    $del1 = $conn->prepare("DELETE FROM PhongChieu WHERE MaRap = ?");
    $del1->bind_param("i", $maRap);
    $del1->execute();

    // 4. Xóa rạp
    $del2 = $conn->prepare("DELETE FROM RapChieu WHERE MaRap = ?");
    $del2->bind_param("i", $maRap);

    if ($del2->execute()) {
        echo "<script>alert('Đã xóa rạp thành công!'); window.location='diadiem.php';</script>";
    } else {
        echo "<script>alert('Lỗi SQL: {$del2->error}');</script>";
    }
}
?>
