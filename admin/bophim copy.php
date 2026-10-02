<?php 
require_once("../config.php");
require_once("../connect_db.php");
session_start();
if(!isset($_SESSION['admin'])){
    header("Location: ".ADMIN_URL."login_admin");
    exit();
}
$adminUsername = $_SESSION['admin']; // tên đăng nhập
// Lấy họ tên admin từ DB
$conn=connect_db();
$stmt = $conn->prepare("SELECT TenDangNhap FROM Administrator WHERE TenDangNhap = ?");
$stmt->bind_param("s", $adminUsername);
$stmt->execute();
$result = $stmt->get_result();
$adminName = $result->fetch_assoc()['HoTen'] ?? $adminUsername; // nếu không có HoTen thì dùng username
$conn=connect_db();

//Lấy ngày hiện tại
$today=date("Y-m-d");

//Phim đang chiếu: NgayKhoiChieu <= today AND NgayKetThuc >= today
$sqlDangChieu="SELECT * FROM BoPhim WHERE NgayKhoiChieu <=? AND NgayKetThuc >=?";
$stmt=$conn->prepare($sqlDangChieu);
$stmt->bind_param("ss",$today,$today);
$stmt->execute();
$resultDangChieu=$stmt->get_result();
$phimDangChieu=$resultDangChieu->fetch_all(MYSQLI_ASSOC);

//Phim sắp chiếu: NgayKhoiChieu > today
$sqlSapChieu="SELECT *FROM BoPhim WHERE NgayKhoiChieu>?";
$stmt2=$conn->prepare($sqlSapChieu);
$stmt2->bind_param("s",$today);
$stmt2->execute();
$resultSapChieu=$stmt2->get_result();
$phimSapChieu=$resultSapChieu->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bộ phim</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .movie-container{
            max-width: 1000px;
            margin:auto;
            background: white;
            padding: 30px;
        }

        h2{
            text-align: center;
            margin-bottom: 25px;
            color:#222;
        }

        .tab-buttons{
            display:flex;
            justify-content: center;
            gap:20px;
            margin-bottom: 25px;
        }

        .tab-btn{
            padding:10px 20px;
            border: none;
            background-color: white;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.3s;
            font-weight: bold;
        }

        .tab-btn.active{
            background-color: #007bff;
            color: white;
            transform: scale(1.05);
        }

        .tab-btn:hover{
            background-color: #007bff;
            color:white;
        }

        .tab-pane{
            display:none;
        }

        .tab-pane.active{
            display: block;
            animation: fadeIn 0.4s ease;
        }

        .movie-grid{
            display:flex;
            flex-wrap: wrap;
            justify-content: center;
            gap:25px;
        }

        .movie-card{
            width:200px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align:center;
            padding-bottom: 10px;
            transition: 0.3s;
        }

        .movie-card:hover{
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        .movie-card img {
            width: 100%;
            height: 280px;
            object-fit: cover;
            border-radius: 8px 8px 0 0;
        }
        
        .movie-card h4{
            margin: 10px 0 5px;
            color: #333;
        }

        .movie-card p{
            font-size: 14px;
            color:#555;
        }
        .movie-card a{
            text-decoration: none;
        }

        .open-modal-btn {
        padding:10px 20px;
        border:none;
        background:#007bff;
        color:white;
        border-radius:5px;
        cursor:pointer;
        font-weight: bold;
        transition:0.3s;
        }
        .open-modal-btn:hover {
        background:#0056b3;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6); /* Đậm hơn chút cho nổi bật modal */
            backdrop-filter: blur(4px); /* Làm mờ nền phía sau (hiệu ứng kính) */
            justify-content: center;
            align-items: center;
            animation: fadeInBg 0.3s ease;
            overflow: hidden;
        }

        /* Hộp nội dung chính */
        .modal-content {
            background-color: #fff;
            width: 500px; /* Rộng hơn chút */
            max-width: 95%;
            border-radius: 12px; /* Bo góc mềm mại */
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: slideIn 0.3s cubic-bezier(0.18, 0.89, 0.32, 1.28); /* Hiệu ứng nảy nhẹ */
        }

        .modal-content input, .modal-content button {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        .close-btn {
            background: #6c757d;
            color: white;
            border: none;
            cursor: pointer;
        }

        .close-btn:hover {
            background: red;
        }
        
        select {
            padding: 8px;
            border-radius: 5px;
            border:1px solid #ccc;
        }

        .add-btn {
            background: #28a745;
            color: white;
            border: none;
            cursor: pointer;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: bold;
            transition:0.3s;
        }
        .add-btn:hover {
            background:#218838;
        }

        .delete-movie-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 5px;
            font-weight: bold;
            transition: 0.3s;
        }

        .delete-movie-btn:hover {
            background: #c82333;
        }

        @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
        }

        .modal-body {
            padding: 20px;
            max-height: 70vh; /* Giới hạn chiều cao nếu màn hình nhỏ */
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 13px;
            color: #555;
        }
        .modal-header {
            background: #007bff;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }

        /* Input style đẹp */
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
            box-sizing: border-box; /* Quan trọng để không bị vỡ layout */
        }

        /* Hiệu ứng khi click vào input */
        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
            outline: none;
            padding:8px; 
            border-radius:5px; 
            border:1px solid;
        }

        /* Layout chia đôi cột */
        .form-row {
            display: flex;
            gap: 15px; /* Khoảng cách giữa 2 cột */
        }

        .half {
            flex: 1; /* Chia đều không gian */
        }

        /* Style cho input file */
        .custom-file {
            padding: 8px;
            background: #f8f9fa;
            border: 1px dashed #ccc;
            cursor: pointer;
        }

        .modal-footer {
            padding: 5px;
            display: flex;
            justify-content: flex-end; /* Đẩy nút sang phải */
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <h3>Danh sách phim</h3>
        <ul>
            <li><a href="bophim.php">Bộ phim</a></li>
            <li><a href="diadiem.php">Địa điểm rạp</a></li>
        </ul>
        <h3>Doanh thu</h3>
        <ul>
            <li><a href="doanhthunam.php">Năm</a></li>
            <li><a href="doanhthuthang.php">Tháng</a></li>
            <li><a href="doanhthungay.php">Ngày</a></li>
        </ul>
        <h3>User</h3>
        <ul>
            <li><a href="user.php">Danh sách user</a></li>
        </ul>
        <ul>
            <li class="logout"><a href="logout.php">Đăng xuất</a></li>
        </ul>
    </div>

    <div class="main" id="main">
        <div class="topbar">
            <button class="toggle-btn" id="toggleBtn">☰</button>
            <div class="welcome-admin">
                Xin chào, <?php echo htmlspecialchars($adminName); ?>!
            </div>
        </div>
        <div class="content">
            <!-- Nội dung chính hiển thị ở đây -->

        <div class="movie-container">
        <h2>Bộ phim</h2>
        <div class="tab-buttons">
            <button class="tab-btn active" data-target="dang-chieu">🎬 Phim đang chiếu</button>
            <button class="tab-btn" data-target="sap-chieu">⏳ Phim sắp chiếu</button>
        </div>

        <div class="tab-content">
            <div class="tab-pane active" id="dang-chieu">
                <div class="movie-grid">
                    <?php foreach($phimDangChieu as $phim): ?>
                        <div class="movie-card">
                            <a href="bophim_detail.php?MaPhim=<?= $phim['MaPhim'] ?>">
                                <img src="<?= $phim['AnhBia'] ?>" alt="<?= $phim['TenPhim'] ?>">
                                <h4><?= $phim['TenPhim'] ?></h4>
                                <p><?= $phim['TheLoai'] ?></p>
                            <form method="post" action="delete_bophim.php">
                                <input type="hidden" name="MaPhim" value="<?= $phim['MaPhim'] ?>">
                                <button type="submit" class="delete-movie-btn">Xóa</button>
                            </form>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="tab-pane" id="sap-chieu">
                <div class="movie-grid">
                    <?php foreach($phimSapChieu as $phim): ?>
                        <div class="movie-card">
                            <a href="bophim_detail.php?MaPhim=<?= $phim['MaPhim'] ?>" >
                                <img src="<?= $phim['AnhBia'] ?>" alt="<?= $phim['TenPhim'] ?>">
                                <h4><?= $phim['TenPhim'] ?></h4>
                                <p><?= $phim['TheLoai'] ?></p>
                            <form method="post" action="delete_bophim.php">
                                <input type="hidden" name="MaPhim" value="<?= $phim['MaPhim'] ?>">
                                <button type="submit" class="delete-movie-btn">Xóa</button>
                            </form>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            </div>
        </div>
        <div style="display:flex; justify-content:flex-end; margin-top:15px;">
            <button id="openModalBtn" class="open-modal-btn fixed-btn">Thêm phim</button>
        </div>
        <div class="modal" id="movieModal" style="display:none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>🎬 Thêm Phim Mới</h3>
                </div>

                <form action="add_bophim.php" method="post" enctype="multipart/form-data" class="modal-body">
                    <div class="form-group">
                        <label>Tên phim</label>
                        <input type="text" name="tenPhim" required>
                    </div>

                    <div class="form-group">
                        <label>Thể loại</label>
                        <input type="text" name="theLoai" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group half">
                            <label>Giới hạn độ tuổi</label>
                            <input type="number" name="gioiHanTuoi" required>
                        </div>
                        <div class="form-group half">
                            <label>Thời lượng (phút)</label>
                            <input type="number" name="thoiLuong" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group half">
                            <label>Ngày khởi chiếu</label>
                            <input type="date" name="ngayKhoiChieu" required>
                        </div>
                        <div class="form-group half">
                            <label>Ngày kết thúc</label>
                            <input type="date" name="ngayKetThuc" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Mô tả</label>
                        <textarea name="moTa"></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group half">
                            <label>Trailer (video)</label>
                            <input type="file" name="trailer" accept="video/*" required class="custom-file">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group half">
                            <label>Ảnh bìa</label>
                            <input type="file" name="anhBia" accept="image/*" required class="custom-file">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="add-btn">Thêm</button>
                        <button type="button" class="close-btn" id="closeModalBtn">Đóng</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="./script.js"></script>
    <script src="./bophim.js"></script>
</body>
</html>
