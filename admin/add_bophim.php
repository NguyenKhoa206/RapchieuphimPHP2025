<?php
require_once("../config.php");
require_once("../connect_db.php");
session_start();
if(!isset($_SESSION['admin'])){
    header("Location: ".ADMIN_URL."login_admin.php");
    exit();
}

$conn = connect_db();
$uploadDirPhysical = __DIR__ . "/../uploads/";
$uploadUrlPrefix  = "../uploads/";
//uploads = hình ảnh + video
if (!is_dir($uploadDirPhysical)) mkdir($uploadDirPhysical, 0777, true);

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $tenPhim       = trim($_POST['tenPhim'] ?? '');
    $theLoai       = trim($_POST['theLoai'] ?? '');
    $gioiHanTuoi   = intval($_POST['gioiHanTuoi'] ?? 0);
    $thoiLuong     = intval($_POST['thoiLuong'] ?? 0);
    $ngayKhoiChieu = $_POST['ngayKhoiChieu'] ?? '';
    $ngayKetThuc   = $_POST['ngayKetThuc'] ?? '';
    $moTa          = trim($_POST['moTa'] ?? '');

    // --- Xử lý trạng thái bằng DateTime ---
    $today = new DateTime(); 
    $ngayKhoi = new DateTime($ngayKhoiChieu);
    $ngayKet = new DateTime($ngayKetThuc);

    if ($ngayKhoi > $today) {
        $trangThai = 0; // sắp chiếu
    } elseif ($ngayKet < $today) {
        $trangThai = 2; // đã kết thúc
    } else {
        $trangThai = 1; // đang chiếu
    }

    // --- Upload ảnh ---
    $anhPathDB = "";
    if (!empty($_FILES['anhBia']) && $_FILES['anhBia']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['anhBia']['name'], PATHINFO_EXTENSION);
        $anhName = time() . "_img_" . bin2hex(random_bytes(4)) . ($ext ? ('.' . $ext) : '');
        $anhPathFile = $uploadDirPhysical . $anhName;
        if (move_uploaded_file($_FILES['anhBia']['tmp_name'], $anhPathFile)) {
            $anhPathDB = $uploadUrlPrefix . $anhName;
        }
    }

    // --- Upload trailer ---
    $videoPathDB = "";
    if (!empty($_FILES['trailer']) && $_FILES['trailer']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['trailer']['name'], PATHINFO_EXTENSION);
        $videoName = time() . "_vid_" . bin2hex(random_bytes(4)) . ($ext ? ('.' . $ext) : '');
        $videoPathFile = $uploadDirPhysical . $videoName;
        if (move_uploaded_file($_FILES['trailer']['tmp_name'], $videoPathFile)) {
            $videoPathDB = $uploadUrlPrefix . $videoName;
        }
    }

    // --- Insert DB ---
    $sql = "INSERT INTO BoPhim (TenPhim, TheLoai, GioiHanTuoi, ThoiLuong, NgayKhoiChieu, NgayKetThuc, MoTa, Trailer, AnhBia, TrangThai)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiissssss",$tenPhim, $theLoai, $gioiHanTuoi, $thoiLuong,$ngayKhoiChieu, $ngayKetThuc,$moTa, $videoPathDB, $anhPathDB, $trangThai);
    if ($stmt->execute()) { // Nếu thực thi thành công
        $stmt->close();
        header("Location:".ADMIN_URL. "bophim.php");
        exit();
    } else { // Nếu thực thi thất bại
        echo "Lỗi khi thêm phim: " . $stmt->error; // Hiển thị lỗi SQL
        $stmt->close();
        // **Thêm logic xóa file đã upload nếu DB insert thất bại (nên làm)**
        if (file_exists($uploadDirPhysical . pathinfo($anhPathDB, PATHINFO_BASENAME))) unlink($uploadDirPhysical . pathinfo($anhPathDB, PATHINFO_BASENAME));
        if (file_exists($uploadDirPhysical . pathinfo($videoPathDB, PATHINFO_BASENAME))) unlink($uploadDirPhysical . pathinfo($videoPathDB, PATHINFO_BASENAME));
        exit();
    }
}