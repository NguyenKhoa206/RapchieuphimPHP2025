<?php
require_once("config.php");
require_once("connect_db.php");

$conn = connect_db();
$today = date("Y-m-d");

// Cập nhật trạng thái
$conn->query("UPDATE bophim SET TrangThai='SapChieu' WHERE '$today' < NgayKhoiChieu");
$conn->query("UPDATE bophim SET TrangThai='DangChieu' WHERE '$today' >= NgayKhoiChieu AND '$today' <= NgayKetThuc");
$conn->query("UPDATE bophim SET TrangThai='NgungChieu' WHERE '$today' > NgayKetThuc");
?>
