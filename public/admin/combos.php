<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_ROOT . '/app/Support/admin.php';
use App\Support\Database as D;
use App\Services\CinemaService as C;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_action(static function (): void {
        if (($_POST['action'] ?? '') === 'archive') {
            C::archive('combo', request_id());
            return;
        }
        $name = text_input('name', 120);
        $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_INT);
        if ($price === false || $price < 1000 || $price > 10000000) {
            throw new DomainException('Giá combo không hợp lệ.');
        }
        if (!empty($_POST['id'])) {
            D::execute('UPDATE cinema_combos SET name=?,price=? WHERE id=?', [$name, $price, request_id()]);
        } else {
            D::execute('INSERT INTO cinema_combos (name,price) VALUES (?,?)', [$name, $price]);
        }
    }, 'admin/combos.php');
}
$edit = isset($_GET['id']) ? D::one('SELECT * FROM cinema_combos WHERE id=? AND active=1', [request_id()]) : null;
$combos = D::all('SELECT * FROM cinema_combos WHERE active=1 ORDER BY id');
admin_header('Quản lý combo');
?>
<div class="page-heading"><span class="eyebrow">BẮP & NƯỚC</span><h1>Quản lý combo</h1></div><div class="two-columns"><section class="panel"><h2><?= $edit ? 'Sửa combo' : 'Thêm combo' ?></h2><form method="post" class="form-stack"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>"><label>Tên combo<input name="name" maxlength="120" required value="<?= e($edit['name'] ?? '') ?>"></label><label>Giá (đồng)<input type="number" name="price" min="1000" max="10000000" required value="<?= (int)($edit['price'] ?? 75000) ?>"></label><button class="button" type="submit">Lưu combo</button></form></section><section class="panel table-scroll"><table><thead><tr><th>Combo</th><th>Giá</th><th>Thao tác</th></tr></thead><tbody><?php foreach ($combos as $combo): ?><tr><td><?= e($combo['name']) ?></td><td><?= money($combo['price']) ?></td><td><a class="button button-small button-outline" href="<?= e(url('admin/combos.php', ['id' => $combo['id']])) ?>">Sửa</a><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="id" value="<?= (int)$combo['id'] ?>"><button class="button button-small button-danger" type="submit" data-confirm="Ngừng bán combo này?">Ngừng bán</button></form></td></tr><?php endforeach; ?></tbody></table></section></div>
<?php view('layouts/footer'); ?>
