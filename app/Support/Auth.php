<?php

declare(strict_types=1);

namespace App\Support;

final class Auth
{
    public static function user(): ?array
    {
        return self::identity('user');
    }
    public static function admin(): ?array
    {
        return self::identity('admin');
    }

    private static function identity(string $role): ?array
    {
        $session = $_SESSION[$role] ?? null;
        if (!is_array($session) || !isset($session['id'], $session['version'])) {
            return null;
        }
        $table = $role === 'admin' ? 'cinema_admins' : 'cinema_users';
        $row = Database::one("SELECT * FROM $table WHERE id=? AND active=1", [$session['id']]);
        if (!$row || (int) $row['auth_version'] !== (int) $session['version']) {
            unset($_SESSION[$role]);
            return null;
        }
        unset($row['password_hash'], $row['password_scheme']);
        return $row;
    }

    public static function requireUser(): array
    {
        if ($user = self::user()) {
            return $user;
        }
        flash('Vui lòng đăng nhập để tiếp tục.', 'info');
        redirect('auth/login.php');
    }

    public static function requireAdmin(): array
    {
        if ($admin = self::admin()) {
            return $admin;
        }
        redirect('admin/login.php');
    }

    public static function login(string $role, string $identifier, string $password): bool
    {
        $identifier = trim($identifier);
        $key = hash('sha256', $role . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'cli') . '|' . mb_strtolower($identifier));
        $attempt = Database::one('SELECT * FROM cinema_login_attempts WHERE attempt_key=?', [$key]);
        if ($attempt && strtotime($attempt['expires_at']) > time() && $attempt['failures'] >= 5) {
            throw new \DomainException('Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau 15 phút.');
        }
        $row = $role === 'admin'
            ? Database::one('SELECT * FROM cinema_admins WHERE username=? AND active=1', [$identifier])
            : Database::one('SELECT * FROM cinema_users WHERE (email=? OR phone=?) AND active=1', [$identifier, $identifier]);
        $valid = $row && ($row['password_scheme'] === 'legacy_md5'
            ? hash_equals($row['password_hash'], md5($password))
            : password_verify($password, $row['password_hash']));
        if (!$valid) {
            Database::execute('INSERT INTO cinema_login_attempts (attempt_key, failures, expires_at) VALUES (?,1,DATE_ADD(NOW(),INTERVAL 15 MINUTE)) ON DUPLICATE KEY UPDATE failures=IF(expires_at<NOW(),1,failures+1), expires_at=IF(expires_at<NOW(),DATE_ADD(NOW(),INTERVAL 15 MINUTE),expires_at)', [$key]);
            return false;
        }
        $table = $role === 'admin' ? 'cinema_admins' : 'cinema_users';
        if ($row['password_scheme'] !== 'modern' || password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            Database::execute("UPDATE $table SET password_hash=?, password_scheme='modern' WHERE id=?", [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        }
        Database::execute('DELETE FROM cinema_login_attempts WHERE attempt_key=?', [$key]);
        session_regenerate_id(true);
        $_SESSION[$role] = ['id' => (int) $row['id'], 'version' => (int) $row['auth_version']];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        return true;
    }

    public static function register(array $input): void
    {
        self::validateContact($input);
        self::validatePassword($input['password']);
        if ($input['password'] !== $input['confirmation']) {
            throw new \DomainException('Mật khẩu nhập lại không khớp.');
        }
        try {
            Database::execute('INSERT INTO cinema_users (name,email,phone,gender,birthday,password_hash) VALUES (?,?,?,?,?,?)', [
                $input['name'], $input['email'], $input['phone'], $input['gender'], $input['birthday'] ?: null, password_hash($input['password'], PASSWORD_DEFAULT),
            ]);
        } catch (\PDOException $error) {
            if ($error->getCode() === '23000') {
                throw new \DomainException('Email hoặc số điện thoại đã được sử dụng.');
            }
            throw $error;
        }
    }

    public static function validateContact(array $input): void
    {
        if (trim($input['name']) === '' || mb_strlen($input['name']) > 120
            || !filter_var($input['email'], FILTER_VALIDATE_EMAIL) || strlen($input['email']) > 190
            || !preg_match('/^0[0-9]{9,10}$/', $input['phone']) || !in_array((int) $input['gender'], [0, 1, 2], true)) {
            throw new \DomainException('Kiểm tra họ tên, email và số điện thoại (10–11 chữ số, bắt đầu bằng 0).');
        }
        if (!empty($input['birthday'])) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $input['birthday']);
            if (!$date || $date->format('Y-m-d') !== $input['birthday'] || $date > new \DateTimeImmutable('today')) {
                throw new \DomainException('Ngày sinh không hợp lệ.');
            }
        }
    }

    public static function validatePassword(string $password): void
    {
        if (mb_strlen($password) < 8 || strlen($password) > 72) {
            throw new \DomainException('Mật khẩu phải có ít nhất 8 ký tự và không quá 72 byte.');
        }
    }

    public static function changePassword(string $role, int $id, string $current, string $new, string $confirmation): void
    {
        self::validatePassword($new);
        if ($new !== $confirmation) {
            throw new \DomainException('Mật khẩu nhập lại không khớp.');
        }
        $table = $role === 'admin' ? 'cinema_admins' : 'cinema_users';
        $row = Database::one("SELECT * FROM $table WHERE id=?", [$id]);
        $valid = $row && ($row['password_scheme'] === 'legacy_md5' ? hash_equals($row['password_hash'], md5($current)) : password_verify($current, $row['password_hash']));
        if (!$valid) {
            throw new \DomainException('Mật khẩu hiện tại không đúng.');
        }
        Database::execute("UPDATE $table SET password_hash=?, password_scheme='modern', auth_version=auth_version+1 WHERE id=?", [password_hash($new, PASSWORD_DEFAULT), $id]);
        $_SESSION[$role]['version'] = (int) $row['auth_version'] + 1;
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}
