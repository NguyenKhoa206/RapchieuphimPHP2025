<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Support\Database as D;
use App\Services\CinemaService as C;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_action(static function (): void {
        $action = text_input('action', 20);
        if ($action === 'cinema') {
            $phone = text_input('phone', 20);
            if (!preg_match('/^0[0-9]{9,10}$/', $phone)) {
                throw new DomainException('Số điện thoại rạp không hợp lệ.');
            }
            D::execute('INSERT INTO cinema_cinemas (name,address,phone) VALUES (?,?,?)', [text_input('name', 190), text_input('address', 255), $phone]);
        } elseif ($action === 'room') {
            C::createRoom((int)text_input('cinema_id', 10), text_input('name', 120), text_input('room_type', 16), (int)text_input('rows', 2), (int)text_input('columns', 2));
        } elseif ($action === 'archive') {
            C::archive(text_input('kind', 16), request_id());
        } else {
            throw new DomainException('Thao tác không hợp lệ.');
        }
    }, 'admin/cinemas.php');
}
$cinemas = D::all('SELECT * FROM cinema_cinemas WHERE active=1 ORDER BY name');
$rooms = D::all('SELECT r.*,c.name AS cinema_name,(SELECT COUNT(*) FROM cinema_seats s WHERE s.room_id=r.id) AS capacity FROM cinema_rooms r JOIN cinema_cinemas c ON c.id=r.cinema_id WHERE r.active=1 AND c.active=1 ORDER BY c.name,r.name');
admin_header('Rạp & phòng');
?>
<div class="page-heading"><span class="eyebrow">HẠ TẦNG RẠP CHIẾU</span><h1>Rạp & phòng</h1><p class="muted">Sơ đồ ghế được tạo theo số hàng và số ghế mỗi hàng.</p></div>
<div class="two-columns"><section class="panel"><h2>Thêm rạp</h2><form method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="cinema"><label>Tên rạp<input name="name" maxlength="190" required></label><label>Địa chỉ<input name="address" maxlength="255" required></label><label>Số điện thoại<input name="phone" type="tel" pattern="0[0-9]{9,10}" required></label><button class="button" type="submit">Thêm rạp</button></form></section><section class="panel"><h2>Thêm phòng & ghế</h2><form method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="room"><div class="form-grid"><label>Rạp<select name="cinema_id" required><?php foreach ($cinemas as $cinema): ?><option value="<?= (int)$cinema['id'] ?>"><?= e($cinema['name']) ?></option><?php endforeach; ?></select></label><label>Tên phòng<input name="name" maxlength="120" required></label><label>Loại phòng<select name="room_type"><option value="standard">Thường</option><option value="vip">VIP · +20.000 ₫/ghế</option><option value="couple">Couple · +40.000 ₫/ghế</option></select></label><label>Số hàng<input type="number" name="rows" min="1" max="26" value="9" required></label><label>Ghế mỗi hàng<input type="number" name="columns" min="1" max="30" value="20" required></label></div><button class="button" type="submit" <?= !$cinemas ? 'disabled' : '' ?>>Tạo phòng và sơ đồ ghế</button></form></section></div>
<section class="panel section"><h2>Danh sách rạp</h2><div class="table-scroll"><table><thead><tr><th>Rạp</th><th>Địa chỉ</th><th>SĐT</th><th>Thao tác</th></tr></thead><tbody><?php foreach ($cinemas as $cinema): ?><tr><td><?= e($cinema['name']) ?></td><td><?= e($cinema['address']) ?></td><td><?= e($cinema['phone']) ?></td><td><a class="button button-small button-outline" href="<?= e(url('admin/cinema-edit.php', ['id' => $cinema['id']])) ?>">Sửa</a><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="kind" value="cinema"><input type="hidden" name="id" value="<?= (int)$cinema['id'] ?>"><button type="submit" class="button button-small button-danger" data-confirm="Ngừng hoạt động rạp này?">Ngừng hoạt động</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="panel section"><h2>Danh sách phòng</h2><div class="table-scroll"><table><thead><tr><th>Rạp</th><th>Phòng</th><th>Loại</th><th>Số ghế</th><th>Thao tác</th></tr></thead><tbody><?php foreach ($rooms as $room): ?><tr><td><?= e($room['cinema_name']) ?></td><td><?= e($room['name']) ?></td><td><?= e(['standard' => 'Thường', 'vip' => 'VIP', 'couple' => 'Couple'][$room['room_type']]) ?></td><td><?= (int)$room['capacity'] ?></td><td><a class="button button-small button-outline" href="<?= e(url('admin/room-edit.php', ['id' => $room['id']])) ?>">Đổi tên</a><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="kind" value="room"><input type="hidden" name="id" value="<?= (int)$room['id'] ?>"><button class="button button-small button-danger" type="submit" data-confirm="Ngừng hoạt động phòng này?">Ngừng hoạt động</button></form></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php view('layouts/footer'); ?>
