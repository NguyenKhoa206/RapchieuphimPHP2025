<?php
require_once("../config.php");
require_once("../connect_db.php");
session_start();
if(!isset($_SESSION['admin'])){
    header("Location: ".ADMIN_URL."index.php");
    exit();
}
$conn = connect_db();

if(isset($_POST['update_user'])){
    $id = intval($_POST['id']);
    $hoten = $_POST['hoten'];
    $email = $_POST['email'];
    $sdt = $_POST['sdt'];
    $gioitinh = intval($_POST['gioitinh']);

    $stmt = $conn->prepare("UPDATE KhachHang SET HoTen=?, Email=?, SoDienThoai=?, GioiTinh=? WHERE MaKH=?");
    $stmt->bind_param("sssii", $hoten, $email, $sdt, $gioitinh, $id);
    $stmt->execute();
}

// Chuyển về lại user.php
header("Location: user.php");
exit();
?>
