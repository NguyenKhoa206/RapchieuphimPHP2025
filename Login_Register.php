<?php
    require_once("config.php");
    require_once("connect_db.php");
    session_start();

    $conn = connect_db();
    //nếu đã đăng nhập thì chuyển hướng
    if (isset($_SESSION['Khachhang'])) {
        header("Location: ".INDEX_URL."index_user.php");
        exit();
    }

    // Hàm tạo mã khách hàng dạng KH001, KH002…
    function taoMaKhachHangMoi($conn) {
        $sql = "SELECT MaKH FROM KhachHang ORDER BY MaKH DESC LIMIT 1";
        $result = $conn->query($sql);
        if ($result->num_rows == 0) return "KH001";

        $row = $result->fetch_assoc();
        $so = intval(substr($row['MaKH'], 2));
        $soMoi = $so + 1;
        return "KH" . str_pad($soMoi, 3, "0", STR_PAD_LEFT);
    }

    // đăng ký
    $errors = [];
    $success = "";
    $activeTab = "login"; // Tab mặc định

    // Xử lý đăng ký
    if (isset($_POST['register'])) {
        $hoten = $conn->real_escape_string($_POST['hoten'] ?? '');
        $email = $conn->real_escape_string($_POST['email'] ?? '');
        $sdt = $conn->real_escape_string($_POST['sdt'] ?? '');
        $pass = $_POST['password'] ?? '';
        $repass = $_POST['repassword'] ?? '';
        $gioitinh = $conn->real_escape_string($_POST['gioitinh'] ?? null);

        $activeTab = "register";

        if ($pass !== $repass) {
            $errors[] = "Mật khẩu nhập lại không đúng!";
        }

        // Kiểm tra email/SĐT đã tồn tại chưa
        $check = $conn->query("SELECT * FROM KhachHang WHERE Email='$email' OR SoDienThoai='$sdt'");
        if ($check->num_rows > 0) {
            $errors[] = "Email hoặc số điện thoại đã được sử dụng!";
        }

        if (empty($errors)) {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $makh = taoMaKhachHangMoi($conn);

            $sql = "INSERT INTO KhachHang (MaKH, HoTen, Email, SoDienThoai, MatKhau, GioiTinh)
                    VALUES ('$makh', '$hoten', '$email', '$sdt', '$hash', '$gioitinh')";

            if ($conn->query($sql)) {
                $success = "Đăng ký thành công! Bạn có thể đăng nhập.";
                $activeTab = "login"; // Chuyển sang tab login sau khi đăng ký thành công
            } else {
                $errors[] = "Lỗi SQL: " . $conn->error;
            }
        }
    }

    //  đăng nhập
    if (isset($_POST['login'])) {
        $input = $conn->real_escape_string($_POST['username'] ?? '');
        $pass  = $_POST['password'] ?? '';

        $activeTab = "login";

        $sql = "SELECT * FROM KhachHang WHERE Email='$input' OR SoDienThoai='$input'";
        $result = $conn->query($sql);

        if ($result->num_rows == 0) {
            $errors[] = "Sai tài khoản hoặc mật khẩu!";
        } else {
            $row = $result->fetch_assoc();
            if (password_verify($pass, $row['MatKhau'])) {
                $_SESSION['Khachhang'] = $row['MaKH'];
                header("Location: ".INDEX_URL."index_user.php");
                exit();
            } else {
                $errors[] = "Sai tài khoản hoặc mật khẩu!";
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login_Register</title>
  <!-- Tailwind CSS is preferred, but using Bootstrap to maintain original style structure -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
    xintegrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
  <!-- EFFECT -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <!-- ICON -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.2/css/all.min.css"
    xintegrity="sha512-HK5fgLBL+xu6dm/Ii3z4xhlSUyZgTT9tuc/hSrtw6uzJOvgRr2a9jyxxT1ely+B+xFAmJKVSTbpM/CuL7qxO8w=="
    crossorigin="anonymous" />
  <!-- CSS (Assuming login.css exists for header/footer styling) -->
  <link rel="stylesheet" href="index.css">
</head>

<body class="bg-light">
    <!-- HEADER -->
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

  <!-- PHẦN ĐĂNG NHẬP / ĐĂNG KÝ -->
  <div class="container d-flex justify-content-center align-items-center mt-5 mb-5">
    <div class="card shadow-lg rounded-4 p-4" style="max-width: 420px; width: 100%;">

      <!-- Tabs -->
      <ul class="nav nav-tabs nav-fill mb-4" id="authTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active fw-bold" id="login-tab" data-bs-toggle="tab" data-bs-target="#login"
            type="button" role="tab" aria-controls="login" aria-selected="true">Đăng nhập</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link fw-bold" id="register-tab" data-bs-toggle="tab" data-bs-target="#register"
            type="button" role="tab" aria-controls="register" aria-selected="false">Đăng ký</button>
        </li>
      </ul>
        <?php
        if (!empty($errors)) {
            echo "<div class='alert alert-danger'>";
            foreach($errors as $e) echo "<div>$e</div>";
            echo "</div>";
        }
        if ($success) echo "<div class='alert alert-success'>$success</div>";
        ?>
      <div class="tab-content">
        <div class="tab-pane fade show active" id="login" role="tabpanel" aria-labelledby="login-tab">
          <form method="POST" action="Login_Register.php">
            <div class="mb-3">
              <label for="usernameInput" class="form-label">Email hoặc Số điện thoại</label>
              <input type="text" name="username" id="usernameInput" class="form-control form-control-lg" placeholder="Email hoặc Số điện thoại" required>
            </div>
            <div class="mb-3">
              <label for="passwordInput" class="form-label">Mật khẩu</label>
              <input type="password" name="password" id="passwordInput" class="form-control form-control-lg" placeholder="Nhập mật khẩu" required>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="remember">
              <label class="form-check-label" for="remember">Ghi nhớ đăng nhập</label>
            </div>
            <input type="hidden" name="login_attempt" value="1">
            <button type="submit" class="btn btn-primary w-100 btn-lg" name="login">Đăng nhập</button>  
          </form>
        </div>
        <!-- Đăng ký (Register) -->
        <div class="tab-pane fade" id="register" role="tabpanel" aria-labelledby="register-tab">
          <!-- Form đăng ký này hiện chỉ là HTML, cần thêm logic PHP/database nếu muốn chức năng hoạt động -->
          <form method="POST" action="Login_Register.php">
            <div class="mb-3">
              <label class="form-label">Họ và tên</label>
              <input type="text" name="hoten" class="form-control form-control-lg" placeholder="Nhập họ và tên" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control form-control-lg" placeholder="Nhập email" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Số điện thoại</label>
              <input type="text" name="sdt" class="form-control form-control-lg" placeholder="Nhập số điện thoại" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Mật khẩu</label>
              <input type="password" name="password" class="form-control form-control-lg" placeholder="Tạo mật khẩu" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Xác nhận mật khẩu</label>
              <input type="password" name="repassword" class="form-control form-control-lg" placeholder="Nhập lại mật khẩu" required>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="agree" required>
              <label class="form-check-label" for="agree">
                Tôi đồng ý với <a href="#">điều khoản sử dụng</a>
              </label>
            </div>
            <button type="submit" class="btn btn-success w-100 btn-lg" name="register">Đăng ký</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- FOOTER  -->
    <footer>
        <div class="container">
            <div class="row section">
                <div class="col-12 col-sm-12 col-md-3 col-lg-3 col-xl-3">
                    <h3>GIỚI THIỆU </h3>
                    <ul>
                        <li><a href="#">VỀ CHÚNG TÔI</a></li>
                        <li><a href="#">THỎA THUẬN SỬ DỤNG</a></li>
                        <li><a href="#">CHÍNH SÁCH BẢO MẬT</a></li>
                    </ul>
                </div>
                <div class="col-12 col-sm-12 col-md-3 col-lg-3 col-xl-3">
                    <h3>GÓC ĐIỆN ẢNH </h3>
                    <ul>
                        <li><a href="#">THỂ LOẠI PHIM</a></li>
                        <li><a href="#">BÌNH LUẬN</a></li>
                        <li><a href="#">BLOG ĐIỆN ẢNH</a></li>
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
            <!--FOOTER-->
            <div class="footer__copyright">
                <p>2025 © kkk</p>
            </div>
        </div>
    </footer>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>