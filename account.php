<?php
require_once __DIR__ . '/app/bootstrap.php';
$user = require_login();
$portalTitle = 'تنظیمات حساب';
$activePortal = '';
$error = '';
$forceChange = !empty($user['must_change_password']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = :id');
    $stmt->execute([':id' => $user['id']]);
    $hash = (string) $stmt->fetchColumn();
    if (!$forceChange && !password_verify($currentPassword, $hash)) {
        $error = 'گذرواژه فعلی درست نیست.';
    } elseif (strlen($newPassword) < 12) {
        $error = 'گذرواژه جدید باید دست‌کم ۱۲ نویسه داشته باشد.';
    } elseif ($newPassword !== $confirmation) {
        $error = 'تکرار گذرواژه جدید یکسان نیست.';
    } elseif ($newPassword === $currentPassword && !$forceChange) {
        $error = 'گذرواژه جدید باید با گذرواژه فعلی متفاوت باشد.';
    } else {
        $update = db()->prepare('UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :id');
        $update->execute([':hash' => password_hash($newPassword, PASSWORD_DEFAULT), ':id' => $user['id']]);
        $_SESSION['user']['must_change_password'] = 0;
        session_regenerate_id(true);
        flash_set('گذرواژه با موفقیت تغییر کرد.');
        header('Location: portal.php');
        exit;
    }
}
require __DIR__ . '/app/portal-header.php';
?>
<div class="portal-page-heading"><div><p class="portal-kicker">حساب کاربری</p><h1><?= $forceChange ? 'تغییر گذرواژه اولیه' : 'تنظیمات حساب' ?></h1><p>تغییر گذرواژه دسترسی به سامانه را ایمن‌تر می‌کند.</p></div></div>
<?php if ($forceChange): ?><div class="portal-alert portal-alert-info">برای ادامه استفاده از سامانه، گذرواژه موقت حساب خود را تغییر دهید.</div><?php endif; ?>
<?php if ($error !== ''): ?><div class="portal-alert portal-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="portal-form-card" style="max-width:640px">
    <form method="post" class="portal-form">
        <?= csrf_field() ?>
        <?php if (!$forceChange): ?><label>گذرواژه فعلی<input name="current_password" type="password" required autocomplete="current-password"></label><?php endif; ?>
        <label>گذرواژه جدید <small>(حداقل ۱۲ نویسه)</small><input name="new_password" type="password" minlength="12" required autocomplete="new-password"></label>
        <label>تکرار گذرواژه جدید<input name="password_confirmation" type="password" minlength="12" required autocomplete="new-password"></label>
        <div class="portal-form-actions"><button class="portal-button portal-button-primary" type="submit">ذخیره گذرواژه</button><a class="portal-button portal-button-secondary" href="portal.php">بازگشت</a></div>
    </form>
</section>
<?php require __DIR__ . '/app/portal-footer.php'; ?>
