<?php
session_start();
require_once("connect_db.php");
require_once("config.php");

$conn = connect_db();

$isLoggedIn = false;
$username = '';

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// --- 1. XỬ LÝ ĐĂNG NHẬP ---
$isLoggedIn = false;
$username = '';

// Nếu submit form login từ trang này (nếu có popup login)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
    $emailOrsdt = $_POST['email_sdt'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM KhachHang WHERE (Email=? OR SoDienThoai=?) LIMIT 1");
    $stmt->bind_param("ss", $emailOrsdt, $emailOrsdt);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        // Verify mật khẩu hash
        if (password_verify($password, $user['MatKhau'])) {
            $_SESSION['Khachhang'] = true;
            $_SESSION['HoTen'] = $user['HoTen'];
            $_SESSION['Email'] = $user['Email'];
            $_SESSION['SoDienThoai'] = $user['SoDienThoai'];
            
            $isLoggedIn = true;
            $username = $user['HoTen'];
        }
    } 
}

// Kiểm tra session đã đăng nhập chưa
if (isset($_SESSION['Khachhang']) && $_SESSION['Khachhang'] === true) {
    $isLoggedIn = true;
    $username = $_SESSION['HoTen'] ?? 'Khách hàng';
}

// --- 2. LẤY DỮ LIỆU PHIM ---

// Banner: Lấy ngẫu nhiên 3 phim đang chiếu
$bannerQuery = $conn->query("SELECT * FROM BoPhim WHERE TrangThai = 1 ORDER BY RAND() LIMIT 3");
$bannerMovies = $bannerQuery->fetch_all(MYSQLI_ASSOC);

// Phim đang chiếu (TrangThai = 1)
$phimDangChieuQuery = $conn->query("SELECT * FROM BoPhim WHERE TrangThai = 1 ORDER BY NgayKhoiChieu DESC");
$phimDangChieu = $phimDangChieuQuery->fetch_all(MYSQLI_ASSOC);

// Phim sắp chiếu (TrangThai = 0)
$phimSapChieuQuery = $conn->query("SELECT * FROM BoPhim WHERE TrangThai = 0 ORDER BY NgayKhoiChieu ASC");
$phimSapChieu = $phimSapChieuQuery->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Galaxy Cinema - Trang Chủ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css" />
    <link rel="stylesheet" href="index.css">
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
                        <li class="nav-item line"><a class="nav-link" href="#phim">Phim</a></li>
                        <li class="nav-item line"><a class="nav-link" href="#tinmoi">Tin Mới & Ưu Đãi</a></li>
                </ul>
                    
                <div class="d-flex align-items-center">
                        <div class="searchbox mx-3">
                            <input type="text" placeholder="Tìm phim rạp">
                            <button><i class="fa fa-search"></i></button>
                        </div>

                        <div class="user">
                            <i class="fa fa-user-circle"></i>
                            <?php if ($isLoggedIn): ?>
                                <div class="dropdown">
                                    <button class="btn btn-link dropdown-toggle text-dark p-0" data-bs-toggle="dropdown"
                                            style="font-weight: bold; text-decoration: none;">
                                        Xin chào, <strong><?= htmlspecialchars($username) ?></strong>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="thongtincanhan.php">Thông tin cá nhân</a></li>
                                        <li><a class="dropdown-item" href="veudadat.php">Vé đã đặt</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="logout.php">Đăng Xuất</a></li>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <a href="Login_Register.php" class="text-dark fw-bold">Đăng Nhập</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <section class="banner">
        <div id="banner_movie" class="carousel slide carousel-fade" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <?php foreach ($bannerMovies as $i => $movie): ?>
                    <button type="button" data-bs-target="#banner_movie" data-bs-slide-to="<?= $i ?>" class="<?= $i===0?'active':'' ?>"></button>
                <?php endforeach; ?>
            </div>
            <div class="carousel-inner">
                <?php foreach ($bannerMovies as $i => $movie): 
                    // SỬA LỖI ĐƯỜNG DẪN ẢNH BANNER
                    $anhBia = ltrim($movie['AnhBia'], './'); // Loại bỏ ./ ở đầu
                    $anhBia = str_replace('../', '', $anhBia); // Loại bỏ tất cả ../ còn sót
                    $trailer = str_replace('../', '', $movie['Trailer']); // Giữ nguyên cho trailer
                ?>
                    <div class="carousel-item <?= $i===0?'active':'' ?>">
                        <img src="<?= htmlspecialchars($anhBia) ?>" class="d-block w-100" alt="<?= htmlspecialchars($movie['TenPhim']) ?>" style="height: 650px; object-fit: cover;">
                        <div class="carousel-caption d-none d-md-block">
                            <h2 class="animate__animated animate__fadeInDown"><?= htmlspecialchars($movie['TenPhim']) ?></h2>
                            <p class="animate__animated animate__fadeInUp"><?= htmlspecialchars($movie['TheLoai']) ?></p>
                            <p class="d-none d-lg-block"><?= htmlspecialchars(mb_substr($movie['MoTa'], 0, 150)) ?>...</p>
                            <?php if (!empty($movie['Trailer'])): ?>
                                <div class="movietrailer animate__animated animate__fadeInUp">
                                    <a href="<?= htmlspecialchars($trailer) ?>" target="_blank" class="btn text-white" style="background: linear-gradient(to right, #ffc773, rgb(255, 125, 3)); border-radius: 30px;">
                                        <i class="fa fa-play"></i> PLAY TRAILER
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#banner_movie" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#banner_movie" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
            </button>
        </div>
    </section>

    <section class="phim section" id="phim">
        <div class="container">
            <div class="title_phim d-flex align-items-center">
                <h3 class="me-4" style="border-left: 5px solid #fe5e01; padding-left: 10px; margin-bottom: 0; line-height: 1;">PHIM</h3>
                <div class="phim-tabs">
                    <span class="tab-item active" onclick="openTab(event, 'dangchieu')">Đang chiếu</span>
                    <span class="tab-item" onclick="openTab(event, 'sapchieu')">Sắp Chiếu</span>
                </div>
            </div>
            
            <!-- Phim đang chiếu -->
            <div id="dangchieu" class="tab-content-movie" style="display: block;">
                <div class="row mt-4">
                    <?php foreach ($phimDangChieu as $movie):
                        // SỬA LỖI ĐƯỜNG DẪN ẢNH
                        $anhBia = ltrim($movie['AnhBia'], './'); 
                        $anhBia = str_replace('../', '', $anhBia);
                        $trailer = str_replace('../', '', $movie['Trailer']);
                    ?>
                    <div class="col-6 col-md-3 mb-4">
                        <div class="phim__img">
                            <div class="phim__margin position-relative">
                                <a href="movie.php?MaPhim=<?= $movie['MaPhim'] ?>">
                                    <img class="img-fluid rounded" src="<?= htmlspecialchars($anhBia) ?>" alt="<?= htmlspecialchars($movie['TenPhim']) ?>" style="width:100%; height: 350px; object-fit: cover;">
                                </a>
                                <div class="phim__play">
                                    <div class="d-flex flex-column align-items-center">
                                        <?php if(!empty($trailer)): ?>
                                            <a href="<?= htmlspecialchars($trailer) ?>" target="_blank" class="btn-trailer mb-2">Trailer <i class="fa fa-play"></i></a>
                                        <?php endif; ?>
                                        <a href="datve.php?MaPhim=<?= $movie['MaPhim'] ?>" class="btn-ticket">Mua Vé <i class="fa fa-ticket-alt"></i></a>
                                    </div>
                                </div>
                            </div>
                            <div class="phim__ten mt-2">
                                <p class="fw-bold text-center text-truncate mb-1">
                                    <a href="movie.php?MaPhim=<?= $movie['MaPhim'] ?>" class="text-dark text-decoration-none">
                                        <?= htmlspecialchars($movie['TenPhim']) ?>
                                    </a>
                                </p>
                                <p class="text-center text-muted small mb-1"><?= htmlspecialchars($movie['TheLoai']) ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- phim sắp chiếu -->
            <div id="sapchieu" class="tab-content-movie" style="display: none;">
                <div class="row mt-4">
                    <?php foreach ($phimSapChieu as $movie): 
                        // SỬA LỖI ĐƯỜNG DẪN ẢNH
                        $anhBia = ltrim($movie['AnhBia'], './'); 
                        $anhBia = str_replace('../', '', $anhBia);
                        $trailer = str_replace('../', '', $movie['Trailer']);
                    ?>
                    <div class="col-6 col-md-3 mb-4">
                        <div class="phim__img">
                            <div class="phim__margin position-relative">
                                <a href="movie.php?MaPhim=<?= $movie['MaPhim'] ?>">
                                    <img class="img-fluid rounded" src="<?= htmlspecialchars($anhBia) ?>" alt="<?= htmlspecialchars($movie['TenPhim']) ?>" style="width:100%; height: 350px; object-fit: cover;">
                                </a>
                                <div class="phim__play">
                                    <div class="d-flex flex-column align-items-center">
                                        <?php if(!empty($trailer)): ?>
                                            <a href="<?= htmlspecialchars($trailer) ?>" target="_blank" class="btn-trailer mb-2">Trailer <i class="fa fa-play"></i></a>
                                        <?php endif; ?>
                                        <span class="btn-ticket disabled" style="background: #ccc; cursor: default;">Sắp chiếu</span>
                                    </div>
                                </div>
                            </div>
                            <div class="phim__ten mt-2">
                                <p class="fw-bold text-center text-truncate mb-1">
                                    <a href="movie.php?MaPhim=<?= $movie['MaPhim'] ?>" class="text-dark text-decoration-none">
                                        <?= htmlspecialchars($movie['TenPhim']) ?>
                                    </a>
                                </p>
                                <p class="text-center text-muted small mb-1"><?= htmlspecialchars($movie['TheLoai']) ?></p>
                                <p class="text-center text-danger small">Khởi chiếu: <?= date('d/m/Y', strtotime($movie['NgayKhoiChieu'])) ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
    </section>

    <section class="tinmoi" id="tinmoi">
        <div class="promotion">
            <h2><span class="icon"></span> TIN KHUYẾN MÃI</h2>
            <div class="promo-list">
                <div class="slider">
                    <div class="promo-item">
                        <a href="#"> <img src="./hinh/tinmoikhuyenmai/com.jpg" alt="Bắp ngọt vị cốm"></a>
                        <p class="promo-title">Bắp Ngọt Vị Cốm-Hương Vị Mùa Thu Hà Nội Giữa Lòng Rạp Chiếu</p>
                    </div>
                    <div class="promo-item">
                        <a href="#"> <img src="./hinh/tinmoikhuyenmai/momo-galaxy.jpg" alt="MoMo khuyến mãi"></a>
                        <p class="promo-title">Galaxy Cinema Và MoMo Tặng Bắp Nước Miễn Phí</p>
                    </div>
                    </div>
            </div>
        </div>
    </section>

    <section class="contact section">
        <div class="contact__content">
            <p>Cần trợ giúp? Liên hệ đội ngũ hỗ trợ của chúng tôi tại</p>
            <p><a href="tel:0338914299" style="color: #fbbd61; font-size: 40px; text-decoration: none;">0338914299</a></p>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="row section" style="padding: 40px 0;">
                <div class="col-12 col-md-3">
                    <h3>GIỚI THIỆU</h3>
                    <ul>
                        <li><a href="#">VỀ CHÚNG TÔI</a></li>
                        <li><a href="#">THỎA THUẬN SỬ DỤNG</a></li>
                        <li><a href="#">CHÍNH SÁCH BẢO MẬT</a></li>
                    </ul>
                </div>
                <div class="col-12 col-md-3">
                    <h3>GÓC ĐIỆN ẢNH</h3>
                    <ul>
                        <li><a href="#">THỂ LOẠI PHIM</a></li>
                        <li><a href="#">BÌNH LUẬN</a></li>
                        <li><a href="#">BLOG ĐIỆN ẢNH</a></li>
                    </ul>
                </div>
                <div class="col-12 col-md-3">
                    <h3>HỖ TRỢ</h3>
                    <ul>
                        <li><a href="#">GÓP Ý</a></li>
                        <li><a href="#">TUYỂN DỤNG</a></li>
                        <li><a href="#">FAQ</a></li>
                    </ul>
                </div>
                <div class="col-12 col-md-3">
                    <h3>KẾT NỐI</h3>
                    <ul>
                        <li><a href="#"><i class="fab fa-facebook-f"></i> Facebook</a></li>
                        <li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer__copyright text-center py-3 border-top border-secondary">
                <p class="m-0">2025 © Galaxy Cinema Clone</p>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openTab(evt, tabName) {
            // 1. Ẩn tất cả nội dung tab
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content-movie");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
            }

            // 2. Xóa class active khỏi tất cả các tab
            tablinks = document.getElementsByClassName("tab-item");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }

            // 3. Hiển thị tab được chọn và thêm class active
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.className += " active";
        }
    </script>
</body>
</html>