<?php
session_start();
require_once("../config.php");
require_once("../connect_db.php");

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

$conn = connect_db();
$years = [2025, 2024, 2023]; // Năm hiển thị

// Lấy danh sách rạp
function getCinemas($conn) {
    $cinemas = [];
    $res = $conn->query("SELECT MaRap, TenRap FROM RapChieu ORDER BY TenRap ASC");
    if($res){
        while($row = $res->fetch_assoc()){
            $cinemas[] = $row;
        }
    }
    return $cinemas;
}
$cinemas = getCinemas($conn);

// Lấy giá trị đã chọn hoặc mặc định
$selectedYear = $_GET['year'] ?? date('Y');
$selectedCinema = $_GET['cinema'] ?? 'all';

// --- Logic Truy vấn Doanh Thu Theo Ngày ---
$revenueData = [];
$title = "Doanh thu theo ngày năm " . $selectedYear;

if($selectedCinema === 'all'){
    $res = $conn->query("SELECT DATE(NgayLap) AS Ngay, SUM(TongTien) AS total
                         FROM HoaDon
                         WHERE YEAR(NgayLap) = $selectedYear
                         GROUP BY DATE(NgayLap)
                         ORDER BY Ngay ASC");
    while($row = $res->fetch_assoc()){
        $revenueData[$row['Ngay']] = $row['total'];
    }
    $title = "Tổng doanh thu năm $selectedYear";
}else{
    // Doanh thu của rạp cụ thể
    $stmt = $conn->prepare("SELECT DATE(h.NgayLap) AS Ngay, SUM(h.TongTien) AS total
                            FROM HoaDon h
                            JOIN Ve v ON h.MaKH = v.MaKH
                            JOIN LichChieu lc ON v.MaLich = lc.MaLich
                            JOIN PhongChieu pc ON lc.MaPhong = pc.MaPhong
                            WHERE pc.MaRap = ? AND YEAR(h.NgayLap) = ?
                            GROUP BY DATE(h.NgayLap)
                            ORDER BY Ngay ASC");
    $stmt->bind_param("si", $selectedCinema, $selectedYear);
    $stmt->execute();
    $res = $stmt->get_result();
    while($row = $res->fetch_assoc()){
        $revenueData[$row['Ngay']] = $row['total'];
    }
    $cinemaName = "Rạp đã chọn";
    foreach($cinemas as $c){
        if($c['MaRap'] == $selectedCinema) $cinemaName = $c['TenRap'];
    }
    $title = "Doanh thu năm $selectedYear - $cinemaName";
}

// Chuẩn bị dữ liệu cho Chart.js
$labels = array_keys($revenueData);
$values = array_values($revenueData);

$labels_json = json_encode($labels);
$values_json = json_encode($values);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doanh Thu Ngày</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .revenue-container {
            padding: 25px;
        }
        .revenue-container h2 {
            text-align: center;
            margin-bottom: 25px;
            color: #333;
        }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
        }
        .filter-form label {
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        .filter-form select, .filter-form button {
            padding: 8px 15px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 14px;
        }
        .filter-form button {
            background-color: #1abc9c;
            color: #fff;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .filter-form button:hover {
            background-color: #16a085;
        }
        #revenueChart {
            margin-top: 20px;
            max-width: 100%;
            height: 400px;
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
            <div class="revenue-container">
                <h2><?php echo htmlspecialchars($title); ?></h2>
                <form method="GET" class="filter-form">
                    <label for="year">Chọn Năm:</label>
                    <select name="year" id="year">
                        <?php foreach($years as $y): ?>
                            <option value="<?php echo $y; ?>" <?php echo ($selectedYear==$y)?'selected':''; ?>><?php echo $y; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="cinema">Chọn Rạp/Tổng:</label>
                    <select name="cinema" id="cinema">
                        <option value="all" <?php echo ($selectedCinema==='all')?'selected':''; ?>>Tổng (Tất cả rạp)</option>
                        <?php foreach($cinemas as $c): ?>
                            <option value="<?php echo $c['MaRap']; ?>" <?php echo ($selectedCinema==$c['MaRap'])?'selected':''; ?>>
                                <?php echo htmlspecialchars($c['TenRap']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Xem</button>
                </form>
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        const ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo $labels_json; ?>,
                datasets: [{
                    label: 'Doanh thu (VNĐ)',
                    data: <?php echo $values_json; ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true },
                    title: { display: true, text: 'Doanh thu theo ngày' }
                },
                scales: { 
                    y: { beginAtZero: true, title: { display: true, text: 'VNĐ' } },
                    x: { title: { display: true, text: 'Ngày' } }
                }
            }
        });
    </script>
</body>
</html>
