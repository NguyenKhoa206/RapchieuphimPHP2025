</main>
<footer class="site-footer">
    <div class="container footer-grid">
        <div><h2>GALAXY CINEMA</h2><p>Phim hay, màn ảnh lớn.<br>Hẹn bạn tại rạp.</p></div>
        <div><h2>GÓC ĐIỆN ẢNH</h2><a href="<?= e(url('index.php')) ?>#movies">Phim đang chiếu</a><a href="<?= e(url('index.php', ['status' => 'upcoming'])) ?>#movies">Phim sắp chiếu</a><a href="<?= e(url('index.php')) ?>#tinmoi">Tin mới & ưu đãi</a></div>
        <div><h2>ĐẶT VÉ</h2><a href="<?= e(url('showtimes.php')) ?>">Lịch chiếu tại rạp</a><a href="<?= e(url('account/tickets.php')) ?>">Vé & hóa đơn của tôi</a><a href="<?= e(url('account/tickets.php', ['tab' => 'invoices'])) ?>">Hóa đơn cũ</a></div>
        <div><h2>TÀI KHOẢN</h2><a href="<?= e(url('account/profile.php')) ?>">Thông tin cá nhân</a><a href="<?= e(url('auth/login.php')) ?>">Đăng nhập</a><a href="<?= e(url('admin/login.php')) ?>">Quản trị rạp</a></div>
    </div>
    <div class="container footer-copyright">© <?= date('Y') ?> Galaxy Cinema · Đồ án quản lý rạp chiếu phim</div>
</footer>
</body>
</html>
