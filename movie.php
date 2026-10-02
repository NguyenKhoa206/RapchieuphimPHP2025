<?php
session_start();
require_once("connect_db.php");
require_once("config.php");

$conn = connect_db();

// Lấy MaPhim từ URL
$maPhim = intval($_GET['MaPhim'] ?? 0);
if ($maPhim === 0) {
    // Chuyển hướng an toàn
    header("Location: " . (defined('INDEX_URL') ? INDEX_URL : "") . "index_user.php");
    exit();
}

// Lấy thông tin phim
$stmt = $conn->prepare("SELECT * FROM BoPhim WHERE MaPhim=?");
$stmt->bind_param("i",$maPhim);
$stmt->execute();
$phim = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$phim) {
    // Chuyển hướng an toàn
    header("Location: " . (defined('INDEX_URL') ? INDEX_URL : "") . "index_user.php?error=notfound");
    exit();
}

// === XỬ LÝ ĐƯỜNG DẪN ẢNH VÀ TRAILER ===
// Thêm tiền tố 'uploads/' vào đường dẫn để đảm bảo file được tìm thấy
$anhBiaPath = 'uploads/' . ltrim($phim['AnhBia'] ?? '', './');
$trailerPath = 'uploads/' . ltrim($phim['Trailer'] ?? '', './');


// Lấy lịch chiếu 7 ngày
$today = date('Y-m-d');
$endDay = date('Y-m-d', strtotime('+6 days'));
$sql = "SELECT LC.MaLich, LC.NgayChieu, LC.GioChieu, LC.GiaVe, P.TenPhong, R.TenRap
        FROM LichChieu LC
        JOIN PhongChieu P ON LC.MaPhong=P.MaPhong
        JOIN RapChieu R ON P.MaRap=R.MaRap
        WHERE LC.MaPhim=? AND LC.NgayChieu BETWEEN ? AND ?
        ORDER BY LC.NgayChieu,R.TenRap,LC.GioChieu";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss",$maPhim,$today,$endDay);
$stmt->execute();
$lich = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Nhóm theo ngày -> rạp -> suất
$lichTheoNgay=[];
foreach($lich as $item){
    $ngay = $item['NgayChieu'];
    $rap = $item['TenRap'];
    if(!isset($lichTheoNgay[$ngay])) $lichTheoNgay[$ngay]=[];
    if(!isset($lichTheoNgay[$ngay][$rap])) $lichTheoNgay[$ngay][$rap]=[];
    $lichTheoNgay[$ngay][$rap][]=$item;
}

// Kiểm tra đăng nhập
$isLoggedIn = isset($_SESSION['Khachhang']);
$username = $_SESSION['HoTen'] ?? 'Khách hàng';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($phim['TenPhim']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"/>
<link rel="stylesheet" href="index.css"> 

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    color: black;
    background-color: rgb(255, 255, 255);
    letter-spacing: 1px;
    /* Đảm bảo nội dung không bị header cố định che mất */
    padding-top: 100px;
}

/* ================= HEADER (Dựa trên index.css & Tối giản) ================= */
/* Lưu ý: Các style chi tiết cho Header (như vị trí, màu chữ menu) nên được đặt trong index.css
   vì hiện tại movie.php đang sử dụng header tối giản. */

/* ================= VIDEO TRAILER ================= */
.vdtrailer {
    background-color: #000000;
    margin: 30px 0;
}

.video-banner {
    width: 100%;
    text-align: center;
    padding: 20px 0;
}

/* Container căn giữa, không quá to cho video */
.video-container {
    width: 70%; /* Chỉnh nhỏ video */
    max-width: 1000px; 
    margin: 0 auto; 
    border-radius: 10px;
    overflow: hidden;
    /* Sử dụng ratio-16x9 của Bootstrap để giữ tỉ lệ */
}

.video-container video {
    width: 100%;
    height: 100%;
    border: none;
}

/* ================= INFO PHIM ================= */
/* Phần này chủ yếu dùng các class Bootstrap (py-4, row, col-md-4, img-fluid, shadow) */

.container .col-md-4 img {
    /* Đảm bảo ảnh bìa không quá cao */
    height: auto;
    max-height: 450px; 
    object-fit: cover;
}

.container .col-md-8 h2 {
    color: #ec7532; /* Màu cam cho tiêu đề phim */
    font-size: 28px;
    margin-bottom: 20px;
}

.container .col-md-8 p {
    font-size: 16px;
    line-height: 1.6;
    margin-bottom: 10px;
}

.container .col-md-8 strong {
    color: #333;
}

/* Nút Mua vé ngay */
.btn-warning {
    background-color: #ec7532 !important;
    border-color: #ec7532 !important;
    color: white !important;
    font-weight: bold;
    letter-spacing: 1px;
}
.btn-warning:hover {
    background-color: #d8682a !important;
    border-color: #d8682a !important;
}

/* ================= LỊCH CHIẾU (Tabs) ================= */

/* Tiêu đề Lịch Chiếu */
.container h5 {
    color: #ec7532;
    font-size: 22px;
    border-bottom: 2px solid #ccc;
    padding-bottom: 10px;
    margin-bottom: 15px;
}

/* Thanh chọn ngày (nav-day) */
.nav-day { 
    cursor: pointer; 
    margin-right: 12px; 
    padding: 8px 15px; 
    border-radius: 20px; 
    border: 1px solid #ccc;
    transition: all 0.3s;
    font-weight: 500;
    color: #4a4a4a;
    text-align: center;
}
.nav-day:hover {
    background-color: #f0f0f0;
}

.nav-day.active { 
    background-color: #ec7532; /* Màu cam nổi bật khi active */
    color: #ffffff;
    border-color: #ec7532;
    box-shadow: 0 4px 8px rgba(236, 117, 50, 0.3);
}

/* Thẻ Rạp chiếu (card) */
.card {
    border: 1px solid #eee;
    background-color: #fff;
}

.card h6 {
    color: #ec7532; /* Tên rạp màu cam */
    font-size: 18px;
    margin-bottom: 10px;
}

/* Nút suất chiếu */
.btn-outline-secondary {
    color: #717171;
    border-color: #ccc;
    background-color: #d8d8d8; /* Màu nền xám nhạt */
    font-weight: bold;
    transition: all 0.2s;
}

.btn-outline-secondary:hover {
    color: #ffffff;
    background-color: #ec7532; /* Hover chuyển sang màu cam */
    border-color: #ec7532;
}
</style>
</head>
<body>
    <header class="container" id="Home">
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid">
                <a class="navbar-brand" href="index_user.php">
                    <img src="./hinh/galaxy-logo-mobile.074abeac.png" alt="logo" style="width: 150px;" 
                         onerror="this.src='https://placehold.co/150x50?text=LOGO';"/>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"><i class="fa fa-bars"></i></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item line active"><a class="nav-link" href="index_user.php">Home</a></li>
                        <li class="nav-item line"><a class="nav-link" href="datve.php">Đặt vé</a></li>
                        <li class="nav-item line"><a class="nav-link" href="index_user.php#phim">Phim</a></li>
                        <li class="nav-item line"><a class="nav-link" href="index_user.php#tinmoi">Tin Mới & Ưu Đãi</a></li>
                    </ul>
                    
                    <div class="d-flex align-items-center">
                        <div class="searchbox mx-3">
                            <input type="text" placeholder="Tìm phim rạp">
                            <button><i class="fa fa-search"></i></button>
                        </div>

                        <div class="user">
                            <i class="fa fa-user-circle"></i>
                                <a href="Login_Register.php" class="text-decoration-none text-dark fw-bold"> Đăng Nhập</a>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>

<div class="vdtrailer">
<section class="video-banner">
<div class="video-container">
<div class="ratio ratio-16x9">
<video controls poster="<?= htmlspecialchars($anhBiaPath)?>"><source src="<?= htmlspecialchars($trailerPath)?>" type="video/mp4">Trình duyệt không hỗ trợ video.</video>
</div>
</div>
</section>
</div>

<div class="container py-4">
<div class="row">
<div class="col-md-4"><img src="<?= htmlspecialchars($anhBiaPath)?>" class="img-fluid rounded shadow" alt="Ảnh Bìa">
<a href="#lichchieu" class="btn btn-warning w-100 mt-3 p-3">Mua vé ngay</a></div>
<div class="col-md-8">
<h2><?= htmlspecialchars($phim['TenPhim']) ?></h2>
<p><strong>Thể loại:</strong> <?= htmlspecialchars($phim['TheLoai'])?></p>
<p><strong>Thời lượng:</strong> <?= htmlspecialchars($phim['ThoiLuong'])?> phút</p>
<p><strong>Mô tả:</strong> <?= nl2br(htmlspecialchars($phim['MoTa']))?></p>
</div>
</div>
</div>

<div class="container py-4" id="lichchieu">
    <h5>Lịch Chiếu 7 Ngày</h5>
    
    <?php 
    // Kiểm tra nếu có dữ liệu lịch chiếu
    if (empty($lichTheoNgay)): 
    ?>
        <div class="alert alert-warning text-center" role="alert">
            Hiện tại chưa có suất chiếu nào được mở bán cho bộ phim này trong 7 ngày tới. Vui lòng quay lại sau!
        </div>
    <?php 
    else: 
        // Mảng chuyển đổi thứ sang Tiếng Việt
        $thuVN=['Sun'=>'CN','Mon'=>'T2','Tue'=>'T3','Wed'=>'T4','Thu'=>'T5','Fri'=>'T6','Sat'=>'T7'];
        $firstDay = true;
    ?>
        
        <div class="d-flex flex-wrap mb-3">
        <?php foreach($lichTheoNgay as $ngay=>$raps):
            $thu = $thuVN[date('D',strtotime($ngay))];
        ?>
            <div class="nav-day <?= $firstDay ? 'active' : '' ?>" data-tab="<?= htmlspecialchars($ngay) ?>">
                <?= $thu ?> <?= date('d/m',strtotime($ngay)) ?>
            </div>
        <?php $firstDay = false; endforeach; ?>
        </div>

        <div class="tab-content">
        <?php 
        $firstPane = true;
        foreach($lichTheoNgay as $ngay=>$raps): 
        ?>
            <div class="tab-pane <?= $firstPane ? 'active' : '' ?>" id="tab-<?= htmlspecialchars($ngay) ?>">
            <?php foreach($raps as $tenRap=>$suats): ?>
                <div class="card mb-3 p-3 shadow-sm">
                    <h6><?= htmlspecialchars($tenRap)?></h6>
                    <div class="d-flex flex-wrap gap-2">
                    <?php foreach($suats as $suat): ?>
                        <a href="datve.php?MaLich=<?= htmlspecialchars($suat['MaLich']) ?>" class="btn btn-outline-secondary btn-sm" 
                            title="Phòng: <?= htmlspecialchars($suat['TenPhong'])?> | Giá: <?= number_format($suat['GiaVe'])?> VNĐ">
                            <?= date('H:i',strtotime($suat['GioChieu']))?>
                        </a>
                    <?php endforeach;?>
                    </div>
                </div>
            <?php endforeach;?>
            </div>
        <?php $firstPane = false; endforeach;?>
        </div>
    <?php endif; ?>
</div>
<footer>
<div class="container">
<div class="row section">
<div class="col-md-3"><h3>GIỚI THIỆU</h3><ul><li><a href="#">VỀ CHÚNG TÔI</a></li><li><a href="#">THỎA THUẬN SỬ</a></li><li><a href="#">CHÍNH SÁCH BẢO</a></li></ul></div>
<div class="col-md-3"><h3>GÓC ĐIỆN ẢNH</h3><ul><li><a href="#">THỂ LOẠI PHIM</a></li><li><a href="#">BÌNH LUẬN</a></li><li><a href="#">BLOG ĐIỆN ẢNH</a></li><li><a href="#">PHIM HAY THÁNG</a></li></ul></div>
<div class="col-md-3"><h3>HỖ TRỢ</h3><ul><li><a href="#">GÓP Ý</a></li><li><a href="#">TUYỂN DỤNG</a></li><li><a href="#">FAQ</a></li></ul></div>
<div class="col-md-3"><h3>KẾT NỐI</h3><ul><li><a href="#"><i class="fab fa-facebook-f"></i> Facebook</a></li><li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li><li><a href="#"><i class="fab fa-google-plus-g"></i> Google+</a></li></ul></div>
</div>
<div class="footer__copyright"><p>2025 © NgVuAnhKhoa / kkk</p></div>
</div>
</footer>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// JavaScript để chuyển đổi giữa các tab ngày chiếu
const tabs=document.querySelectorAll('.nav-day');
const panes=document.querySelectorAll('.tab-pane');
tabs.forEach(tab=>tab.addEventListener('click',()=>{
    // Xóa active khỏi tất cả tabs và panes
    tabs.forEach(t=>t.classList.remove('active'));
    panes.forEach(p=>p.classList.remove('active'));
    
    // Thêm active cho tab và pane được chọn
    tab.classList.add('active');
    document.getElementById('tab-'+tab.dataset.tab).classList.add('active');
}));
</script>
</body>
</html>