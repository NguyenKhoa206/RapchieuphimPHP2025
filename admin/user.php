<?php
require_once("../config.php");
require_once("../connect_db.php");
session_start();
if(!isset($_SESSION['admin'])){
    header("Location: ".ADMIN_URL."index.php");
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

// Xử lý xóa user
if(isset($_GET['delete_id'])){
    $del_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM KhachHang WHERE MaKH = $del_id");
    header("Location: user.php");
    exit();
}

// Lấy danh sách user
$sql = "SELECT MaKH, HoTen, Email, SoDienThoai, GioiTinh, NgayTao FROM KhachHang ORDER BY NgayTao DESC";
$result = $conn->query($sql);
$users = [];
if($result){
    while($row = $result->fetch_assoc()){
        $users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
    /* Container chính */
    .content h2{
        margin-bottom: 20px;
        color: #1abc9c;
        text-align: center;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        border-radius: 6px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    thead {
        background-color: #1abc9c;
        color: #fff;
    }

    thead th {
        padding: 12px 15px;
        text-align: left;
    }

    tbody tr {
        background-color: #f9f9f9;
    }

    tbody tr:nth-child(even){
        background-color: #f2f2f2;
    }

    tbody tr:hover{
        background-color: #d1f0eb;
    }

    td {
        padding: 12px 15px;
        border-bottom: 1px solid #ddd;
    }

    /* ===== Nút sửa xóa ===== */
    .btn {
        padding: 6px 12px;
        border-radius: 5px;
        border: none;
        cursor: pointer;
        font-size: 14px;
        transition: 0.3s;
        text-decoration: none;
        display: inline-block;
    }

    .btn-edit {
        background-color: #3498db;
        color: #fff;
    }

    .btn-edit:hover {
        background-color: #2980b9;
    }

    .btn-delete {
        background-color: #e74c3c;
        color: #fff;
    }

    .btn-delete:hover {
        background-color: #c0392b;
    }

    /* ===== Responsive ===== */
    @media screen and (max-width:768px){
        table, thead, tbody, th, td, tr {
            display: block;
        }
        thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }
        tr {
            margin-bottom: 15px;
            border-bottom: 2px solid #ddd;
        }
        td {
            border: none;
            position: relative;
            padding-left: 50%;
        }
        td::before {
            position: absolute;
            left: 15px;
            top: 12px;
            width: 45%;
            white-space: nowrap;
            font-weight: bold;
        }
        td:nth-of-type(1)::before { content: "ID"; }
        td:nth-of-type(2)::before { content: "Họ tên"; }
        td:nth-of-type(3)::before { content: "Email"; }
        td:nth-of-type(4)::before { content: "SĐT"; }
        td:nth-of-type(5)::before { content: "Giới tính"; }
        td:nth-of-type(6)::before { content: "Ngày tạo"; }
        td:nth-of-type(7)::before { content: "Hành động"; }
    }
    .modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.55);
        backdrop-filter: blur(2px);
    }

    /* Khung modal */
    .modal-content {
        background: #fff;
        width: 420px;
        margin: 8% auto;
        padding: 25px;
        border-radius: 12px;
        animation: popup 0.25s ease-out;
        box-shadow: 0 8px 25px rgba(0,0,0,0.25);
    }

    /* Animation mở modal */
    @keyframes popup {
        from {
            opacity: 0;
            transform: translateY(-20px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    /* Nút đóng */
    .close {
        float: right;
        font-size: 26px;
        cursor: pointer;
        color: #444;
        transition: 0.2s;
    }
    .close:hover {
        color: red;
        transform: scale(1.15);
    }

    /* Tiêu đề modal */
    .modal-content h3 {
        margin-bottom: 15px;
        font-size: 22px;
        font-weight: bold;
        border-bottom: 2px solid #1abc9c;
        padding-bottom: 8px;
        color: #1abc9c;
    }

    /* Form */
    .modal-content label {
        font-weight: bold;
        margin-top: 10px;
        display: block;
        font-size: 14px;
        color: #333;
    }

    .modal-content input,
    .modal-content select {
        width: 100%;
        padding: 10px;
        margin-top: 4px;
        border-radius: 8px;
        border: 1px solid #ccc;
        font-size: 15px;
        transition: 0.2s;
    }

    .modal-content input:focus,
    .modal-content select:focus {
        border-color: #1abc9c;
        box-shadow: 0 0 4px rgba(26, 188, 156, 0.5);
        outline: none;
    }

    /* Nút lưu thay đổi */
    .modal-content button {
        width: 100%;
        background: #1abc9c;
        border: none;
        padding: 12px;
        margin-top: 18px;
        border-radius: 8px;
        color: white;
        font-weight: bold;
        cursor: pointer;
        font-size: 16px;
        transition: 0.25s;
    }

    .modal-content button:hover {
        background: #17a589;
        transform: translateY(-2px);
    }

    /* Responsive cho mobile */
    @media (max-width: 480px) {
        .modal-content {
            width: 90%;
            margin-top: 25%;
            padding: 20px;
        }
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
            <h2>Danh sách User</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>Họ tên</th><th>Email</th><th>SĐT</th><th>Giới tính</th><th>Ngày tạo</th><th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td><?php echo $user['MaKH']; ?></td>
                        <td><?php echo htmlspecialchars($user['HoTen']); ?></td>
                        <td><?php echo htmlspecialchars($user['Email']); ?></td>
                        <td><?php echo htmlspecialchars($user['SoDienThoai']); ?></td>
                        <td><?php echo $user['GioiTinh']==1?'Nam':'Nữ'; ?></td>
                        <td><?php echo $user['NgayTao']; ?></td>
                        <td>
                            <button class="btn btn-edit" onclick="openModal(<?php echo $user['MaKH']; ?>, '<?php echo htmlspecialchars(addslashes($user['HoTen'])); ?>', '<?php echo htmlspecialchars($user['Email']); ?>', '<?php echo $user['SoDienThoai']; ?>', <?php echo $user['GioiTinh']; ?>)">Sửa</button>
                            <a class="btn btn-delete" href="user.php?delete_id=<?php echo $user['MaKH']; ?>" onclick="return confirm('Bạn có chắc muốn xóa user này?')">Xóa</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Sửa User -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>Sửa User</h3>
            <!-- Form gửi sang update_user.php -->
            <form method="POST" action="update_user.php">
                <input type="hidden" name="id" id="modalId">
                <label>Họ tên</label>
                <input type="text" name="hoten" id="modalHoTen" required>
                <label>Email</label>
                <input type="email" name="email" id="modalEmail" required>
                <label>SĐT</label>
                <input type="text" name="sdt" id="modalSDT" required>
                <label>Giới tính</label>
                <select name="gioitinh" id="modalGioiTinh" required>
                    <option value="1">Nam</option>
                    <option value="0">Nữ</option>
                </select>
                <button type="submit" name="update_user">Lưu thay đổi</button>
            </form>
        </div>
    </div>
    <script src="script.js"></script>
    <script>
    function openModal(id, hoten, email, sdt, gioitinh){
        document.getElementById('modalId').value = id;
        document.getElementById('modalHoTen').value = hoten;
        document.getElementById('modalEmail').value = email;
        document.getElementById('modalSDT').value = sdt;
        document.getElementById('modalGioiTinh').value = gioitinh;
        document.getElementById('editModal').style.display = 'block';
    }
    function closeModal(){
        document.getElementById('editModal').style.display = 'none';
    }
    window.onclick = function(event){
        if(event.target == document.getElementById('editModal')){
            closeModal();
        }
    }
</script>
</body>
</html>