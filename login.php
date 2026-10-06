<?php
require_once __DIR__ . '/app/bootstrap.php';
if ((int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
    header('Location: setup.php');
    exit;
}
$user = current_user();
if ($user) {
    header('Location: ' . (!empty($user['must_change_password']) ? 'account.php' : 'portal.php'));
    exit;
}
$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $attempts = array_values(array_filter((array) ($_SESSION['_login_attempts'] ?? []), static fn($time) => is_int($time) && time() - $time < 900));
    if (count($attempts) >= 8) {
        $error = 'تلاش‌های ورود بیش از حد بوده است. کمی بعد دوباره امتحان کنید.';
    } else {
        $stmt = db()->prepare('SELECT id, full_name, email, password_hash, role, is_active, must_change_password FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $record = $stmt->fetch();
        if ($record && (int) $record['is_active'] === 1 && password_verify($password, (string) $record['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
            $_SESSION['_login_attempts'] = [];
            $_SESSION['user'] = [
                'id' => (int) $record['id'],
                'full_name' => (string) $record['full_name'],
                'email' => (string) $record['email'],
                'role' => (string) $record['role'],
                'must_change_password' => (int) $record['must_change_password'],
            ];
            $fallback = !empty($record['must_change_password']) ? 'account.php' : 'portal.php';
            $return = !empty($record['must_change_password']) ? 'account.php' : safe_local_return((string) ($_POST['return'] ?? ''), $fallback);
            header('Location: ' . $return);
            exit;
        }
        $attempts[] = time();
        $_SESSION['_login_attempts'] = $attempts;
        $error = 'ایمیل یا گذرواژه درست نیست، یا حساب غیرفعال شده است.';
    }
}
$return = safe_local_return((string) ($_GET['return'] ?? ''), '');
$pageTitle = 'ورود همکاران';
require __DIR__ . '/app/auth-header.php';
?>
<div class="auth-card">
    <div class="auth-emblem" aria-hidden="true">✳</div>
    <p class="auth-kicker">سامانه داخلی شرکت</p>
    <h1>ورود همکاران</h1>
    <p class="auth-description">برای استفاده از پیام‌رسان و حضور و غیاب، با حساب سازمانی وارد شوید.</p>
    <?php if ($message = flash_get()): ?><div class="portal-alert" role="status"><?= e($message['message']) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="portal-alert portal-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="login.php" class="portal-form">
        <?= csrf_field() ?>
        <input type="hidden" name="return" value="<?= e($return) ?>">
        <label>ایمیل سازمانی<input name="email" type="email" maxlength="180" required autocomplete="username" value="<?= e($email) ?>"></label>
        <label>گذرواژه<input name="password" type="password" required autocomplete="current-password"></label>
        <button class="portal-button portal-button-primary" type="submit">ورود به سامانه</button>
    </form>
    <p class="auth-security-note">حساب کاربری را مدیر سامانه ایجاد می‌کند. اگر دسترسی ندارید، با مدیر شرکت هماهنگ کنید.</p>
</div>
<?php require __DIR__ . '/app/auth-footer.php'; ?>
