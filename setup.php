<?php
require_once __DIR__ . '/app/bootstrap.php';
if ((int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
    header('Location: login.php');
    exit;
}
$setupKey = (string) getenv('APP_SETUP_KEY');
$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$localRequest = in_array($remoteAddress, ['127.0.0.1', '::1'], true);
$setupAllowed = $setupKey !== '' || $localRequest;
$error = $setupAllowed ? '' : 'برای امنیت، راه‌اندازی اولیه فقط از خود سرور یا با تنظیم موقت APP_SETUP_KEY در IIS مجاز است.';
$fullName = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if ((int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
        header('Location: login.php');
        exit;
    }
    if (!$setupAllowed) {
        $error = 'دسترسی راه‌اندازی مجاز نیست. APP_SETUP_KEY را در تنظیمات محیطی IIS تعریف کنید.';
    } elseif ($setupKey !== '' && !hash_equals($setupKey, (string) ($_POST['install_key'] ?? ''))) {
        $error = 'کلید راه‌اندازی درست نیست.';
    } else {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        if ($fullName === '' || strlen($fullName) > 300) {
            $error = 'نام و نام خانوادگی را وارد کنید.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180) {
            $error = 'نشانی ایمیل معتبر نیست.';
        } elseif (strlen($password) < 12) {
            $error = 'گذرواژه باید دست‌کم ۱۲ نویسه داشته باشد.';
        } elseif ($password !== $confirmation) {
            $error = 'تکرار گذرواژه با گذرواژه یکسان نیست.';
        } else {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
                    $pdo->rollBack();
                    header('Location: login.php');
                    exit;
                }
                $stmt = $pdo->prepare('INSERT INTO users(full_name, email, password_hash, role, is_active, created_at) VALUES(:name, :email, :password, \'admin\', 1, :created)');
                $stmt->execute([
                    ':name' => $fullName,
                    ':email' => $email,
                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                    ':created' => now_text(),
                ]);
                $pdo->commit();
                flash_set('حساب مدیر ساخته شد. اکنون با ایمیل و گذرواژه خود وارد شوید.');
                header('Location: login.php');
                exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log($exception->getMessage());
                $error = 'ساخت حساب انجام نشد. اگر نصب هم‌زمان انجام شده، صفحه ورود را باز کنید.';
            }
        }
    }
}
$pageTitle = 'راه‌اندازی اولیه';
require __DIR__ . '/app/auth-header.php';
?>
<div class="auth-card">
    <div class="auth-emblem" aria-hidden="true">✳</div>
    <p class="auth-kicker">راه‌اندازی امن</p>
    <h1>ساخت حساب مدیر</h1>
    <p class="auth-description">این مرحله فقط یک‌بار و پیش از ساخت نخستین کاربر نمایش داده می‌شود. گذرواژه‌ای قوی و یکتا انتخاب کنید.</p>
    <?php if ($error !== ''): ?><div class="portal-alert portal-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($setupAllowed): ?>
        <form method="post" action="setup.php" class="portal-form">
            <?= csrf_field() ?>
            <?php if ($setupKey !== ''): ?><label>کلید راه‌اندازی موقت<input name="install_key" type="password" required autocomplete="off"></label><?php endif; ?>
            <label>نام و نام خانوادگی<input name="full_name" type="text" maxlength="100" required autocomplete="name" value="<?= e($fullName) ?>"></label>
            <label>ایمیل مدیر<input name="email" type="email" maxlength="180" required autocomplete="email" value="<?= e($email) ?>"></label>
            <label>گذرواژه <small>(حداقل ۱۲ نویسه)</small><input name="password" type="password" minlength="12" required autocomplete="new-password"></label>
            <label>تکرار گذرواژه<input name="password_confirmation" type="password" minlength="12" required autocomplete="new-password"></label>
            <button class="portal-button portal-button-primary" type="submit">ساخت حساب مدیر</button>
        </form>
    <?php endif; ?>
    <p class="auth-security-note">اطلاعات کاربری با هش امن ذخیره می‌شود؛ گذرواژه پیش‌فرضی در پروژه وجود ندارد. پس از ساخت مدیر، APP_SETUP_KEY را حذف کنید.</p>
</div>
<?php require __DIR__ . '/app/auth-footer.php'; ?>
