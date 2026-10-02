<?php
require_once("../config.php");
require_once("../connect_db.php");
session_start();

// 1. THIẾT LẬP MÚI GIỜ VIỆT NAM (QUAN TRỌNG)
date_default_timezone_set('Asia/Ho_Chi_Minh');

$conn = connect_db();
if(!isset($_SESSION['admin'])){
    header("Location: ".ADMIN_URL."login_admin");
    exit();
}

// Lấy phim từ GET (mặc định 1)
$maPhim = $_GET['MaPhim'] ?? 1;
$stmt = $conn->prepare("SELECT * FROM BoPhim WHERE MaPhim = ?");
$stmt->bind_param("i", $maPhim);
$stmt->execute();
$phim = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Lấy 7 ngày từ hôm nay (Đã có timezone đúng)
$dates = [];
for($i=0; $i<7; $i++){
    $dates[] = date('Y-m-d', strtotime("+$i days"));
}

// Lấy lịch chiếu 7 ngày
$stmt = $conn->prepare("SELECT * FROM LichChieu WHERE MaPhim=? AND NgayChieu BETWEEN ? AND ? ORDER BY NgayChieu, GioChieu");
$start = $dates[0];
$end = end($dates);
$stmt->bind_param("iss", $maPhim, $start, $end);
$stmt->execute();
$lich = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Nhóm lịch theo ngày
$lichTheoNgay = [];
foreach($lich as $lc){
    $lichTheoNgay[$lc['NgayChieu']][] = $lc;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($phim['TenPhim']) ?></title>
<style>
body {
    font-family: Arial, sans-serif;
    background:#f5f5f5;
    margin:0;
    padding:20px;
}
.container {
    max-width:900px;
    margin:auto;
    background:white;
    padding:20px;
    border-radius:10px;
}
.trailer video {
    width:100%;
    max-height:500px;
    border-radius:10px;
    margin-bottom:20px;
}
.movie-info {
    display:flex;
    flex-wrap:wrap;
    gap:20px;
    margin-bottom:20px;
}
.movie-info img {
    width:300px;
    border-radius:10px;
    object-fit:cover;
}
.movie-details {
    flex:1;
    min-width:200px;
}
.movie-details p{
    margin:8px 0;
}
.tabs {
    display:flex;
    flex-wrap:wrap;
    gap:5px;
    margin-bottom:15px;
}
.tablink {
    padding:10px 15px;
    cursor:pointer;
    background:#eee;
    border:none;
    border-radius:5px;
}
.tablink.active {
    background:#007bff;
    color:white;
}
.tabcontent{
    display:none;
}
.tabcontent.show{
    display:block;
}
.showtime{
    padding:8px 12px;
    background:#007bff;
    color:white;
    border-radius:5px;
    margin-bottom:5px;
}
.add-btn{
    margin-top:10px;
    padding:10px 15px;
    border:none;
    background:#28a745;
    color:white;
    border-radius:5px;
    cursor:pointer;
}
.add-btn:hover{
    background:#218838;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 100;
    left: 0; top: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    justify-content: center;
    align-items: center;
    padding: 20px;
}
.modal-content{
    background: #fff;
    padding: 25px 20px;
    border-radius: 12px;
    max-width: 400px;
    width: 100%;
    box-shadow: 0 8px 25px rgba(0,0,0,0.4);
    position: relative;
}
.modal-content h3 {
    margin-top: 0;
}
.modal-content form label {
    display: block;
    margin-bottom: 10px;
    font-weight: bold;
}
.modal-content form input,
.modal-content form select {
    width: 100%;
    padding: 10px 12px;
    margin-top: 5px;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 14px;
    box-sizing: border-box;
}
.modal-content form select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background: #fff url('data:image/svg+xml;utf8,<svg fill="gray" height="16" viewBox="0 0 24 24" width="16" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>') no-repeat right 10px center;
    background-size: 12px;
    cursor: pointer;
}

.modal-content form input[type=number]::-webkit-inner-spin-button,
.modal-content form input[type=number]::-webkit-outer-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.modal-content form button{
    padding: 10px 15px;
    border:none;
    background:#007bff;
    color:white;
    border-radius:5px;
    cursor:pointer;
    margin-top:10px;
    width:100%;
}
.modal-content form button:hover{
    background:#0056b3;
}
.close{
    position: absolute;
    top: 15px;
    right: 20px;
    font-size:20px;
    cursor:pointer;
    color:#ff4c4c;
}
</style>
</head>
<body>

<div class="container">

    <!-- Trailer -->
    <?php if(!empty($phim['Trailer'])): ?>
    <div class="trailer">
        <video controls>
            <source src="<?= $phim['Trailer'] ?>" type="video/mp4">
        </video>
    </div>
    <?php endif; ?>

    <!-- Ảnh & Thông tin -->
    <div class="movie-info">
        <img src="<?= $phim['AnhBia'] ?>" alt="<?= htmlspecialchars($phim['TenPhim']) ?>">
        <div class="movie-details">
            <p><strong>Thể loại:</strong> <?= $phim['TheLoai'] ?></p>
            <p><strong>Thời lượng:</strong> <?= $phim['ThoiLuong'] ?> phút</p>
            <p><strong>Giới hạn tuổi:</strong> <?= $phim['GioiHanTuoi'] ?>+</p>
            <p><strong>Ngày khởi chiếu:</strong> <?= $phim['NgayKhoiChieu'] ?></p>
            <p><strong>Ngày kết thúc:</strong> <?= $phim['NgayKetThuc'] ?></p>
            <p><strong>Mô tả:</strong> <?= nl2br($phim['MoTa']) ?></p>
        </div>
    </div>

    <!-- Lịch chiếu -->
    <section class="showTimes section">
        <h3>Lịch chiếu</h3>

        <div class="tabs">
            <?php foreach($dates as $i => $d): ?>
            <button class="tablink <?= $i==0?'active':'' ?>" onclick="openDay(event,'day<?= $i ?>')">
                <?= date('D, d-m', strtotime($d)) ?>
            </button>
            <?php endforeach; ?>
        </div>

        <?php foreach($dates as $i=>$d): ?>
        <div id="day<?= $i ?>" class="tabcontent <?= $i==0?'show':'' ?>">
            <?php if(isset($lichTheoNgay[$d])): ?>
                <?php foreach($lichTheoNgay[$d] as $lc): ?>
                    <div class="showtime">
                        <?= $lc['GioChieu'] ?> - Phòng <?= $lc['MaPhong'] ?> - <?= number_format($lc['GiaVe'],0,",",".") ?>₫
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Chưa có lịch chiếu</p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <button class="add-btn">Thêm Lịch Chiếu</button>
    </section>
</div>

<!-- Modal thêm lịch chiếu -->
<div id="modalAdd" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Thêm Lịch Chiếu</h3>
        <form method="post" action="add_lichchieu.php">
            <input type="hidden" name="MaPhim" value="<?= $maPhim ?>">

            <label>Ngày chiếu:
                <select name="NgayChieu" required>
                    <?php foreach($dates as $d): ?>
                    <option value="<?= $d ?>"><?= date('D, d-m', strtotime($d)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Giờ chiếu: <input type="time" name="GioChieu" required></label>
            <label>Phòng: <input type="number" name="MaPhong" required></label>

            <!-- Giá vé cố định -->
            <input type="hidden" name="GiaVe" value="90000">

            <button type="submit">Thêm</button>
        </form>
    </div>
</div>

<script>
// 1. Dữ liệu lịch chiếu từ PHP
let lichTheoNgay = <?= json_encode($lichTheoNgay) ?>;
const dates = <?= json_encode($dates) ?>;

// 2. Render lịch chiếu
function renderLichChieu(){
    dates.forEach((day,i)=>{
        const div = document.getElementById('day'+i);
        div.innerHTML = '';
        if(lichTheoNgay[day] && lichTheoNgay[day].length>0){
            lichTheoNgay[day].forEach(lc=>{
                const el = document.createElement('div');
                el.className = 'showtime';
                el.textContent = `${lc.GioChieu} - Phòng ${lc.MaPhong} - ${Number(lc.GiaVe).toLocaleString()}₫`;
                div.appendChild(el);
            });
        } else {
            div.innerHTML = '<p>Chưa có lịch chiếu</p>';
        }
    });
}

// 3. Tab hiển thị
function openDay(evt, dayId){
    document.querySelectorAll('.tabcontent').forEach(d=>d.style.display='none');
    document.querySelectorAll('.tablink').forEach(b=>b.classList.remove('active'));
    document.getElementById(dayId).style.display='block';
    evt.currentTarget.classList.add('active');
}

// 4. Modal
const modal = document.getElementById('modalAdd');
document.querySelector('.add-btn').addEventListener('click', ()=> modal.style.display='flex');
document.querySelector('.close').addEventListener('click', ()=> modal.style.display='none');
window.addEventListener('click', e=>{if(e.target===modal) modal.style.display='none';});

// 5. Xử lý form thêm lịch chiếu qua Ajax
document.getElementById('formAdd').addEventListener('submit', e=>{
    e.preventDefault();
    const form = e.target;
    const ngay = form.NgayChieu.value;
    const gio = form.GioChieu.value;
    const phong = form.MaPhong.value;
    const giaVe = parseInt(form.GiaVe.value);
    const maPhim = form.MaPhim.value;

    // Kiểm tra trùng trên client
    if(lichTheoNgay[ngay] && lichTheoNgay[ngay].some(lc=>lc.GioChieu===gio && lc.MaPhong==phong)){
        alert('Trùng lịch chiếu! Vui lòng chọn giờ hoặc phòng khác.');
        return;
    }

    // Gửi Ajax tới PHP
    const xhr = new XMLHttpRequest();
    xhr.open('POST','add_lichchieu.php',true);
    xhr.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
    xhr.onload = function(){
        if(this.status===200){
            const res = this.responseText;
            if(res==='success'){
                // Thêm vào JS
                if(!lichTheoNgay[ngay]) lichTheoNgay[ngay]=[];
                lichTheoNgay[ngay].push({GioChieu:gio, MaPhong:phong, GiaVe:giaVe});
                lichTheoNgay[ngay].sort((a,b)=>a.GioChieu.localeCompare(b.GioChieu));
                renderLichChieu();
                modal.style.display='none';
                form.reset();
            } else {
                alert(res);
            }
        }
    };
    const data = `MaPhim=${maPhim}&NgayChieu=${ngay}&GioChieu=${gio}&MaPhong=${phong}&GiaVe=${giaVe}`;
    xhr.send(data);
});

// Render lần đầu
renderLichChieu();
</script>

</body>
</html>
