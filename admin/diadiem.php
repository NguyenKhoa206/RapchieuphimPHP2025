<?php
require_once("../connect_db.php");
require_once("../config.php");
session_start();
$conn=connect_db();

if(!isset($_SESSION['admin'])){
    header("Location: ".ADMIN_URL."login_admin.php");
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

// lấy danh sách rạp
$result=$conn->query("SELECT * FROM RapChieu ORDER BY MaRap DESC");
$raps=$result->fetch_all(MYSQLI_ASSOC);

// lấy danh sách phòng
$sqlPhong = $conn->query("SELECT * FROM PhongChieu ORDER BY MaRap");
$phongs = [];

while ($row = $sqlPhong->fetch_assoc()) {
    $phongs[$row['MaRap']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Địa điểm rạp</title>
    <link rel="stylesheet" href="style.css">
    <style>
        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #222;
        }

        /* Modal chung */
        .modal {
            display: none;
            position: fixed;
            top:0;
            left:0;
            width:100%;
            height:100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 100;
            padding: 20px;
        }

        .modal-content {
            background-color: #fff;
            width: 500px;
            max-width: 95%;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            display: flex;
            flex-direction: column;
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
        }

        .modal-body {
            padding: 20px;
        }

        .modal-body input {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }

        /* Container danh sách rạp */
        .rap-container {
            max-width: 1000px;
            margin: auto;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
        }

        .rap-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        /* Card hiển thị từng rạp */
        .rap-card {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border: 1px solid #eee;
        }

        /* Header rạp */
        .rap-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .rap-info h4 {
            font-size: 18px;
            color: #007bff;
            margin-bottom: 5px;
        }

        .rap-info p {
            font-size: 14px;
            color: #666;
            margin: 2px 0;
        }

        /* Danh sách phòng */
        .room-list {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed #ccc;
        }

        .room-list h5 {
            margin-bottom: 10px;
            font-size: 14px;
            text-transform: uppercase;
            color: #555;
        }

        .room-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .room-tag {
            background: #e9ecef;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: bold;
            color: #495057;
            border: 1px solid #ced4da;
        }

        /* Buttons */
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }

        /* Nút đóng modal */
        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
        }

        /* Vùng nút hành động */
        .actions {
            display: flex;
            gap: 5px;
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
            <div class="rap-container">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h2>Danh sách Rạp chiếu</h2>
                    <button id="openRapModalBtn" class="btn btn-primary">+ Thêm rạp mới</button>
                </div>

                <div class="rap-list">
                    <?php foreach($raps as $rap): ?>
                    <div class="rap-card">
                        <div class="rap-header">
                            <div class="rap-info">
                                <h4><?= htmlspecialchars($rap['TenRap']) ?></h4>
                                <p><?= htmlspecialchars($rap['DiaChi']) ?></p>
                                <p><?= htmlspecialchars($rap['Sdt']) ?></p>
                            </div>
                            <div class="actions">
                                <button class="btn btn-success" onclick="openAddRoomModal(<?= $rap['MaRap'] ?>, '<?= htmlspecialchars($rap['TenRap']) ?>')">
                                    + Thêm phòng
                                </button>
                                
                                <form method="post" action="delete_diadiem.php">
                                    <input type="hidden" name="MaRap" value="<?= $rap['MaRap'] ?>">
                                    <button type="submit" class="btn btn-danger">Xóa</button>
                                </form>
                            </div>
                        </div>

                        <div class="room-list">
                            <h5>Danh sách phòng:</h5>
                            <div class="room-tags">
                                <?php if(isset($phongs[$rap['MaRap']])): ?>
                                    <?php foreach($phongs[$rap['MaRap']] as $phong): ?>
                                        <span class="room-tag">
                                            <?= $phong['TenPhong'] ?> (<?= $phong['SoGhe'] ?> ghế)
                                                <form action="delete_phong.php" method="post" style="display:inline;">
                                                <input type="hidden" name="MaPhong" value="<?= $phong['MaPhong'] ?>">
                                                <button style="background:red; color:white; border:none; padding:2px 6px; border-radius:3px; cursor:pointer;">X</button>
                                            </form>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color:#999; font-size:13px;">Chưa có phòng chiếu</span>
                                <?php endif; ?>
                            </div>
                        </div>  
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" id="rapModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🎬 Thêm Rạp Mới</h3>
                <button class="close-btn" id="closeRapModalBtn">&times;</button>
            </div>
            <form action="add_diadiem.php" method="post" class="modal-body">
                <input type="text" name="TenRap" placeholder="Tên rạp" required>
                <input type="text" name="DiaChi" placeholder="Địa chỉ rạp" required>
                <input type="text" name="Sdt" placeholder="Số điện thoại" required>
                <button type="submit" class="btn btn-primary" style="width:100%">Thêm Rạp</button>
            </form>
        </div>
    </div>

    <div class="modal" id="roomModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalRoomTitle">📺 Thêm Phòng Chiếu</h3>
                <button class="close-btn" onclick="document.getElementById('roomModal').style.display='none'">&times;</button>
            </div>
            <form action="add_phong.php" method="post" class="modal-body">
                <input type="hidden" name="MaRap" id="inputMaRap">
                
                <label>Tên phòng</label>
                <input type="text" name="TenPhong" required>
                
                <label>Số ghế</label>
                <input type="number" name="SoGhe" required>
                
                <button type="submit" class="btn btn-success" style="width:100%">Lưu Phòng</button>
            </form>
        </div>
    </div>
    <script src="./script.js"></script>
    <script>
    //modal rạp
    const rapModal = document.getElementById("rapModal");
    const openRapBtn = document.getElementById("openRapModalBtn");
    const closeRapBtn = document.getElementById("closeRapModalBtn");

    // Mở modal Thêm Rạp
    openRapBtn.addEventListener("click", () => {
        rapModal.style.display = "flex";
    });

    // Đóng modal Thêm Rạp
    closeRapBtn.addEventListener("click", () => {
        rapModal.style.display = "none";
    });

    // Click ra ngoài để đóng modal
    window.addEventListener("click", (e) => {
        if (e.target === rapModal) {
            rapModal.style.display = "none";
        }
    });

    //modal phòng
    const roomModal = document.getElementById("roomModal");

    // Hàm mở modal Thêm Phòng
    function openAddRoomModal(maRap, tenRap) {
        document.getElementById("inputMaRap").value = maRap;
        document.getElementById("modalRoomTitle").innerText = `📺 Thêm Phòng - ${tenRap}`;
        roomModal.style.display = "flex";
    }

    // Đóng modal phòng chiếu khi bấm X
    const closeRoomBtn = roomModal.querySelector(".close-btn");

    closeRoomBtn.addEventListener("click", () => {
        roomModal.style.display = "none";
    });

    // Click ra ngoài để đóng modal phòng
    window.addEventListener("click", (e) => {
        if (e.target === roomModal) {
            roomModal.style.display = "none";
        }
    });
    </script>
</body>
</html>
