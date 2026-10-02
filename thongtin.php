<?php
// BẮT ĐẦU PHẦN PHP LOGIC
session_start();
// CHÚ Ý: Cần đảm bảo file connect_db.php tồn tại và hằng số INDEX_URL đã được định nghĩa.
require_once("connect_db.php"); 
// Giả định INDEX_URL đã được định nghĩa (ví dụ: define('INDEX_URL', 'http://localhost/'));

// 1. Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['Khachhang'])) {
    // CHÚ Ý: Đảm bảo INDEX_URL được định nghĩa trong config.php
    header("Location: " . INDEX_URL . "Login_Register.php"); 
    exit();
}

$MaKH = $_SESSION['Khachhang'];
$khachhang_info = [];

// --- LOGIC XỬ LÝ FORM ĐỔI MẬT KHẨU ---
if (isset($_POST["btnSubmit"])) {
    $passwordcu = trim($_POST["passwordcu"] ?? '');
    $passwordmoi = trim($_POST["passwordmoi"] ?? '');
    $passwordmoixn = trim($_POST["passwordmoixn"] ?? '');
    $error_msg = '';

    if (empty($passwordcu) || empty($passwordmoi) || empty($passwordmoixn)) {
        $error_msg = "Vui lòng điền đầy đủ tất cả các trường mật khẩu.";
    } elseif ($passwordmoi !== $passwordmoixn) {
        $error_msg = "Mật khẩu mới và Xác nhận mật khẩu mới không khớp.";
    } elseif (strlen($passwordmoi) < 6) { 
        $error_msg = "Mật khẩu mới phải có ít nhất 6 ký tự.";
    } else {
        $conn = connect_db();
        if ($conn) {
            // Kiểm tra mật khẩu hiện tại
            $stmt = $conn->prepare("SELECT MatKhau FROM Khachhang WHERE MaKH = ?");
            if ($stmt) {
                $stmt->bind_param("i", $MaKH); // MaKH là INT
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $sv = $result->fetch_assoc();
                    $matkhau_hientai_hashed = $sv["MatKhau"];
                    
                    // Kiểm tra MD5 của mật khẩu cũ với mật khẩu đã lưu
                    if ($matkhau_hientai_hashed != md5($passwordcu)) {
                        $error_msg = "Mật khẩu hiện tại không chính xác.";
                    } else {
                        // Cập nhật mật khẩu mới (MD5)
                        $matkhau_moi_hashed = md5($passwordmoi);
                        
                        $update_stmt = $conn->prepare("UPDATE Khachhang SET MatKhau = ? WHERE MaKH = ?");
                        if ($update_stmt) {
                            $update_stmt->bind_param("si", $matkhau_moi_hashed, $MaKH);
                            
                            if ($update_stmt->execute()) {
                                $error_msg = "Đổi mật khẩu thành công!";
                            } else {
                                $error_msg = "Lỗi hệ thống khi cập nhật mật khẩu: " . $update_stmt->error;
                            }
                            $update_stmt->close();
                        } else {
                            $error_msg = "Lỗi Prepared Statement khi cập nhật.";
                        }
                    }
                } else {
                    $error_msg = "Lỗi: Không tìm thấy tài khoản Khách hàng.";
                }
                $stmt->close();
            } else {
                 $error_msg = "Lỗi Prepared Statement khi truy vấn.";
            }
            $conn->close();
        } else {
            $error_msg = "Không thể kết nối đến cơ sở dữ liệu.";
        }
    }
    
    $_SESSION['message_changepass'] = $error_msg;
    // Sử dụng header redirect để tránh gửi lại form
    header("Location: " . $_SERVER['PHP_SELF']); 
    exit();
}
// --- HẾT LOGIC ĐỔI MẬT KHẨU ---


// --- LẤY THÔNG TIN KHÁCH HÀNG ĐỂ HIỂN THỊ VÀ ĐIỀN VÀO FORM ---
$conn = connect_db();
$displayName = 'Khách hàng'; // Giá trị mặc định
$displayEmail = 'Chưa cập nhật'; // Giá trị mặc định
$displayPhone = ''; // Giá trị mặc định

if ($conn) {
    // SỬA CỘT: HoTen, SoDienThoai (Theo CSDL của bạn)
    $stmt = $conn->prepare("SELECT HoTen, Email, SoDienThoai, GioiTinh, MatKhau FROM Khachhang WHERE MaKH = ?");
    if ($stmt) {
        $stmt->bind_param("i", $MaKH);
        $stmt->execute();
        $result = $stmt->get_result();
        $khachhang_info = $result->fetch_assoc();
        
        if ($khachhang_info) {
             // Định nghĩa các biến hiển thị
            $displayName = htmlspecialchars($khachhang_info['HoTen'] ?? 'Khách hàng');
            $displayEmail = htmlspecialchars($khachhang_info['Email'] ?? 'Chưa cập nhật');
            $displayPhone = htmlspecialchars($khachhang_info['SoDienThoai'] ?? '');
        }
        $stmt->close();
    }
    $conn->close();
}

// Lấy thông báo từ session (cho chức năng Đổi mật khẩu)
$error = '';
if (isset($_SESSION['message_changepass'])) {
    $error = $_SESSION['message_changepass'];
    unset($_SESSION['message_changepass']);
}

// Lấy thông báo từ session (cho chức năng Cập nhật Profile - Giả định gửi từ update_profile.php)
$profile_error = '';
if (isset($_SESSION['message_profile'])) {
    $profile_error = $_SESSION['message_profile'];
    unset($_SESSION['message_profile']);
}
// KẾT THÚC PHẦN PHP LOGIC
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Hồ Sơ Thành Viên</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"
        integrity="sha512-HK5fgLBL+xu6dm/Ii3z4xhlSUyZgTT9tuc/hSrtw6uzJOvgRr2a9jyxxT1ely+B+xFAmJKVSTbpM/CuL7qxO8w=="
        crossorigin="anonymous" />
</head>
<style>
    /* Thêm CSS cho thông báo lỗi/thành công */
    .alert-success {
        padding: 10px;
        border: 1px solid #c3e6cb;
        background-color: #d4edda;
        color: #155724;
        border-radius: 4px;
        margin-bottom: 15px;
        font-size: 14px;
    }

    .alert-error {
        padding: 10px;
        border: 1px solid #f5c6cb;
        background-color: #f8d7da;
        color: #721c24;
        border-radius: 4px;
        margin-bottom: 15px;
        font-size: 14px;
    }

    /* Các CSS khác của bạn */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: Arial, Helvetica, sans-serif;
        color: black;
        background-color: rgb(255, 251, 251);
        letter-spacing: 1px;
        padding-top: 100px;

    }



    .line.active::after {
        width: 100%;
    }

    .line::after {
        content: '';
        width: 0;
        height: 3px;
        background: linear-gradient(to right, #fbbd61, #fe5e01);
        display: block;
        box-shadow: 0 0 20px 0 #fbbd61;
        transition: all 0.5s;
    }

    .line:hover::after {
        width: 100%;
    }

    .section {
        padding: 75px 0;
    }

    /* HEADER */
    header {
        position: absolute;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        border-bottom: 2px solid rgb(0, 0, 0, 0.2);


    }

    #navbarSupportedContent .navbar-nav .nav-item .nav-link {
        color: black;

        font-weight: 500;
        font-size: 17px;

    }

    #navbarSupportedContent .navbar-nav {
        border-bottom: 2px solid rgb(0, 0, 0, 0.2);
        margin-left: 100px;
    }

    #navbarSupportedContent .navbar-nav .nav-link:hover {
        color: #fe5e01;

    }

    #navbarSupportedContent .navbar-nav .nav-item.active .nav-link {
        color: #fe5e01;

    }

    #navbarSupportedContent .navbar-nav .nav-link {
        font-size: 16px;
        letter-spacing: 2px;
        font-weight: 500;
        padding: 5px 12px;
        color: black;
        /* CHỈNH SỬA: Đảm bảo màu chữ là đen */
    }

    .searchbox {
        display: flex;
        border: 1px solid #ccc;
        border-radius: 20px;
        overflow: hidden;
    }

    .searchbox input {
        border: none;
        padding: 5px 10px;
        outline: none;
    }

    .searchbox button {
        background-color: white;
        border: none;
        cursor: pointer;
        padding: 0 10px;
    }

    .user {
        display: flex;
        align-items: center;
        gap: 5px;
        padding: 7px 15px;
        border: 1px solid black;
        border-radius: 25px;
        background-color: white;
    }

    .user a {
        text-decoration: none;
        color: black;
    }

    .user button {
        border: none;
        background-color: white;
    }

    /* ===== PROFILE LAYOUT ===== */
    .profile {
        width: 90%;
        margin: auto;
        display: flex;
        gap: 35px;
        margin-bottom: 80px;
        margin-top: 30px;
    }

    /* LEFT CARD */
    .profile-card {
        width: 290px;
        background: white;
        border-radius: 14px;
        padding: 25px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.07);
        text-align: center;
    }

    .avatar-upload img {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 3px solid #ddd;
        object-fit: cover;
        cursor: pointer;
    }

    .avatar-upload input {
        display: none;
    }

    .username {
        margin-top: 15px;
        font-size: 19px;
        font-weight: 600;
    }

    .divider {
        width: 100%;
        height: 1px;
        background: #eee;
        margin: 15px 0;
    }

    .info-line {
        margin: 6px 0;
        color: #444;
    }

    /* RIGHT BOX */
    .content-box {
        flex: 1;
        background: white;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.07);
        padding: 25px;
        border-radius: 14px;
    }

    /* TABS */
    .tabs {
        display: flex;
        border-bottom: 2px solid #eee;
        margin-bottom: 20px;
    }

    .tab {
        padding: 12px 25px;
        cursor: pointer;
        color: #666;
        font-weight: 500;
    }

    .tab.active {
        color: #ff7a00;
        border-bottom: 3px solid #ff7a00;
    }

    /* CONTENT */
    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .tab-content .form-row {
        display: flex; 
        align-items: center; 
        margin-bottom: 18px;
    }

    .tab-content .form-row > label {
        min-width: 120px; 
        margin-bottom: 0; 
        font-weight: 600;
    }

    .empty-box {
        text-align: center;
        padding: 60px 0;
        color: #666;
        font-size: 15px;
    }

    .empty-box i {
        font-size: 40px;
        color: #ff7a00;
        margin-bottom: 10px;
    }

    /* FORM */
    .form-row {
        margin-bottom: 18px;
    }

    label {
        font-size: 14px;
        margin-bottom: 6px;
    }

    input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
    }

    input:focus {
        border-color: #ff8800;
        outline: none;
    }

    input[type="radio"] {
        width: auto; 
        margin: 0 5px 0 0; 
        padding: 0;
    }

    .gender-group {
        display: flex;
        gap: 30px;
        flex-grow: 1; 
    }

    .save-btn {
        margin-top: 10px;
        padding: 12px 28px;
        border: none;
        border-radius: 8px;
        background: #ff8800;
        color: white;
        cursor: pointer;
        font-size: 15px;
    }

    .save-btn:hover {
        background: #ff7700;
    }


    /* FOOTER*/
    footer {
        background-color: #101010;
    }

    footer h3 {
        font-size: 16px;
        color: #ec7532;
        letter-spacing: 2px;
        margin-bottom: 15px;
    }

    footer li {
        list-style: none;
    }

    footer a {
        font-size: 13px;
        color: white;
        text-decoration: none;
        letter-spacing: 1.5px;
        padding: 3px 0;
        transition: 0.5s;
    }

    footer a:hover {
        color: #fbbd61;
        text-decoration: none;
    }

    .footer__copyright {
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        color: white;
        padding: 15px 0;
    }

    .footer__copyright p {
        margin-bottom: 0;
    }
</style>

<body>
    <header class="container" id="Home">
        <nav class="navbar navbar-expand-lg  ">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">
                    <img src="./hinh/galaxy-logo-mobile.074abeac.png" alt="logo" style="width: 150px;">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon">
                        <i class="fa fa-bars"></i>
                    </span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">

                    <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                        <li class="nav-item line">
                            <a class="nav-link  " href="/Galaxy.html">Home</a>
                        </li>
                        <li class="nav-item line">
                            <a class="nav-link" href="/datve.html">Đặt Vé</a>
                        </li>
                        <li class="nav-item line ">
                            <a class="nav-link " href="/movie.html">Phim</a>
                        </li>
                        <li class="nav-item line">
                            <a class="nav-link" href="#tinmoi">Tin Mới&Ưu Đãi</a>
                        </li>
                    </ul>
                    <form class="d-flex">
                        <div class="searchbox mx-3">
                            <input type="text" placeholder="Tìm phim rạp">
                            <button><i class="fa fa-search"></i></button>
                        </div>
                        <div class="user">
                            <i class="fa fa-user-circle"></i>
                            <button type="submit"> <a href="/Login_Register.html" class="ionicons ion-ios-contact"> Đăng
                                        Nhập</a></button>
                        </div>
                    </form>
                </div>
            </div>
        </nav>
    </header>


    <div class="profile">
        <div class="profile-card">
            <label for="avatar-upload" class="avatar-upload">
                <img id="avatar-img" src="/./hinh/dien-thoai-den-2-ca-chua-tuoi-2.jpg" alt="Avatar">
                <input type="file" id="avatar-upload" accept="image/*">
            </label>
            <h2 id="display-name" class="username"><?= $displayName ?></h2>
            <p class="info-line">Mã KH: <?= htmlspecialchars($MaKH) ?></p>

            <div class="divider"></div>

            <p class="info-line"><b>HOTLINE:</b> 19002224 (9:00-22:00)</p>
            <p class="info-line"><b>Email:</b> <?= $displayEmail ?></p>
        </div>
        
        <div class="content-box">
            <div class="tabs">
                <div class="tab" data-tab="history">Lịch Sử Giao Dịch</div>
                <div class="tab active" data-tab="profile">Thông Tin Cá Nhân</div>
                <div class="tab" data-tab="changepass">Đổi Mật Khẩu</div>
            </div>

            <div id="history" class="tab-content">
                <div class="empty-box">
                    <i class="fa fa-receipt"></i>
                    <p>Chưa có giao dịch nào.</p>
                </div>
            </div>
            
            <div id="profile" class="tab-content active">
                <?php if (!empty($profile_error)): ?>
                    <p class="<?= (strpos($profile_error, 'thành công') !== false) ? 'alert-success' : 'alert-error' ?>">
                        <?= htmlspecialchars($profile_error) ?>
                    </p>
                <?php endif; ?>
                
                <form action="update_profile.php" method="post"> 
                    <input type="hidden" name="MaKH" value="<?= htmlspecialchars($MaKH) ?>">

                    <div class="form-row">
                        <label>Họ và tên</label>
                        <input name="HoTen" type="text" value="<?= htmlspecialchars($khachhang_info['HoTen'] ?? '') ?>" required>
                    </div>
                    <div class="form-row">
                        <label>Email</label>
                        <input name="Email" type="email" value="<?= htmlspecialchars($khachhang_info['Email'] ?? '') ?>" required>
                    </div>
                    <div class="form-row">
                        <label>Số điện thoại</label>
                        <input name="SoDienThoai" type="text" value="<?= htmlspecialchars($khachhang_info['SoDienThoai'] ?? '') ?>">
                    </div>
                    <div class="form-row">
                        <label>Giới tính</label>
                        <div class="gender-group">
                            <label>
                                <input name="GioiTinh" type="radio" value="1" 
                                    <?= ($khachhang_info['GioiTinh'] ?? '') == 1 ? 'checked' : '' ?>> Nam
                            </label>
                            <label>
                                <input name="GioiTinh" type="radio" value="0" 
                                    <?= ($khachhang_info['GioiTinh'] ?? '') == 0 ? 'checked' : '' ?>> Nữ
                            </label>
                            <label>
                                <input name="GioiTinh" type="radio" value="2" 
                                    <?= ($khachhang_info['GioiTinh'] ?? '') == 2 ? 'checked' : '' ?>> Khác
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <label>Mật khẩu hiện tại (Xác nhận Cập nhật)</label>
                        <input name="current_password_confirm" type="password" required> 
                    </div>
                    
                    <button type="submit" name="btnUpdateProfile" class="save-btn">Cập nhật</button>
                </form>
            </div>
            
            <div id="changepass" class="tab-content">
                <div class="changepass-section">
                    <h3>Đổi Mật Khẩu Khách hàng</h3>
                    <?php if (!empty($error)): ?>
                        <p class="<?= (strpos($error, 'thành công') !== false) ? 'alert-success' : 'alert-error' ?>">
                            <?= htmlspecialchars($error) ?>
                        </p>
                    <?php endif; ?>

                    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="post">
                        <div class="form-row">
                            <label>Mã Khách hàng</label>
                            <input type="text" name="MaKH_display" value="<?= htmlspecialchars($MaKH) ?>" readonly> 
                        </div>
                        <div class="form-row">
                            <label>Mật khẩu hiện tại</label>
                            <input type="password" name="passwordcu" required>
                        </div>
                        <div class="form-row">
                            <label>Mật khẩu mới</label>
                            <input type="password" name="passwordmoi" required>
                        </div>
                        <div class="form-row">
                            <label>Xác nhận mật khẩu mới</label>
                            <input type="password" name="passwordmoixn" required>
                        </div>
                        
                        <input type="submit" value="Đổi mật khẩu" name="btnSubmit" class="save-btn">
                    </form>
                </div>
            </div>

        </div>

    </div>

    <footer>
        <div class="container">
            <div class="row section">
                <div class="col-12 col-sm-12 col-md-3 col-lg-3 col-xl-3">
                    <h3>GIỚI THIỆU </h3>
                    <ul>
                        <li><a href="#">VỀ CHÚNG TÔI</a></li>
                        <li><a href="#">THỎA THUẬN SỬ </a></li>
                        <li><a href="#">CHÍNH SÁCH BẢO </a></li>
                    </ul>
                </div>
                <div class="col-12 col-sm-12 col-md-3 col-lg-3 col-xl-3">
                    <h3>GÓC ĐIỆN ẢNH </h3>
                    <ul>
                        <li><a href="#">THỂ LOẠI PHIM</a></li>
                        <li><a href="#">BÌNH LUẬN </a></li>
                        <li><a href="#">BLOG ĐIỆN ẢNH </a></li>
                        <li><a href="#">PHIM HAY THÁNG</a></li>
                    </ul>
                </div>
                <div class="col-12 col-sm-12 col-md-3 col-lg-3 col-xl-3">
                    <h3>HỖ TRỢ </h3>
                    <ul>
                        <li><a href="#">GÓP Ý</a></li>
                        <li><a href="#">TUYỂN DỤNG</a></li>
                        <li><a href="#">FAQ</a></li>

                    </ul>
                </div>
                <div class="col-12 col-sm-12 col-md-3 col-lg-3 col-xl-3">
                    <h3>KẾT NỐI VỚI CHÚNG TÔI</h3>
                    <ul>
                        <li><a href="https://www.facebook.com/minhtai.nguyen.75248795?locale=vi_VN"><i
                                        class="fab fa-facebook-f"></i> Facebook</a></li>
                        <li><a href="https://www.facebook.com/luu.quoc.an.423103?locale=vi_VN"><i
                                        class="fab fa-twitter"></i> Twitter</a></li>
                        <li><a href="https://www.facebook.com/le.kiet.364526?locale=vi_VN"><i
                                        class="fab fa-google-plus-g"></i> Google +</a></li>

                    </ul>
                </div>
            </div>


            <div class="footer__copyright">
                <p>2025 © kkk</p>
            </div>
        </div>
    </footer>
    

    <script src="./scriptprofile.js"></script>
</body>
</html>