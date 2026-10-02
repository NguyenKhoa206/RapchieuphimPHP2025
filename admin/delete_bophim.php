<?php
require_once("../config.php");
require_once("../connect_db.php");
session_start();
if(!isset($_SESSION['admin'])){
    header("Location: ".ADMIN_URL."login_admin.php");
    exit();
}

$conn = connect_db();

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $maPhim = intval($_POST['MaPhim']);

    // Lấy thông tin ảnh + trailer
    $sql = "SELECT AnhBia, Trailer FROM BoPhim WHERE MaPhim = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $maPhim);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Xóa file vật lý
    if (file_exists($result['AnhBia'])) unlink($result['AnhBia']);
    if (file_exists($result['Trailer'])) unlink($result['Trailer']);

    // Xóa DB
    $stmt2 = $conn->prepare("DELETE FROM BoPhim WHERE MaPhim = ?");
    $stmt2->bind_param("i", $maPhim);
    $stmt2->execute();
    $stmt2->close();

    header("Location:".ADMIN_URL."bophim.php");
    exit();
}
?>