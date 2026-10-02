<?php
require_once("../connect_db.php");
require_once("../config.php");
session_start();
if(!isset($_SESSION['admin'])){
    header("Location: ".ADMIN_URL."login_admin.php");
    exit();
}

$conn = connect_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tenRap = trim($_POST['TenRap'] ?? '');
    $diaChi = trim($_POST['DiaChi'] ?? '');
    $sdt = trim($_POST['Sdt'] ?? '');

    $stmt = $conn->prepare("INSERT INTO RapChieu (TenRap, DiaChi, Sdt) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $tenRap, $diaChi, $sdt);

    if ($stmt->execute()) {
        header("Location: diadiem.php");
        exit();
    } else {
        echo "Lỗi: " . $stmt->error;
    }
}
