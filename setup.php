<?php
define('APP_INSTALLER_REQUEST', true);
require_once __DIR__ . '/app/bootstrap.php';

$setupKey = (string) getenv('APP_SETUP_KEY');
$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$localRequest = in_array($remoteAddress, ['127.0.0.1', '::1'], true);
$setupAllowed = $setupKey !== '' || $localRequest;
$error = '';
$fullName = trim((string) ($_POST['full_name'] ?? ''));
$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$storedConfig = database_config();
$databaseValues = [
    'host' => trim((string) ($_POST['db_host'] ?? ($storedConfig['host'] ?? 'localhost'))),
    'port' => trim((string) ($_POST['db_port'] ?? ($storedConfig['port'] ?? '3306'))),
    'database' => trim((string) ($_POST['db_name'] ?? ($storedConfig['database'] ?? ''))),
    'username' => trim((string) ($_POST['db_user'] ?? ($storedConfig['username'] ?? ''))),
];
$pdo = null;
$dbConfigured = false;

if ($storedConfig !== null) {
    try {
        $pdo = connect_mysql($storedConfig);
        try {
            if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
                header('Location: login.php');
                exit;
            }
        } catch (PDOException) {
            // A missing users table is expected before the first installation step.
        }
        if ($setupAllowed) {
            install_schema($pdo);
            $dbConfigured = true;
        }
    } catch (Throwable $exception) {
        error_log('Installer could not connect to the configured MySQL database: ' . $exception->getMessage());
        $pdo = null;
        $dbConfigured = false;
    }
}

$requestedStep = (string) ($_GET['step'] ?? '');
$step = ($requestedStep === 'database' || !$dbConfigured) ? 'database' : 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!$setupAllowed) {
        $error = 'برای نصب از راه دور، ابتدا APP_SETUP_KEY را موقتاً در تنظیمات محیطی IIS تعریف کنید. نصب محلی از خود سرور مجاز است.';
    } elseif ($setupKey !== '' && !hash_equals($setupKey, (string) ($_POST['install_key'] ?? ''))) {
        $error = 'کلید موقت نصب درست نیست.';
    } else {
        $action = (string) ($_POST['install_step'] ?? 'database');
        if ($action === 'database') {
            $candidate = normalize_database_config([
                'host' => $_POST['db_host'] ?? '',
                'port' => $_POST['db_port'] ?? '3306',
                'database' => $_POST['db_name'] ?? '',
                'username' => $_POST['db_user'] ?? '',
                'password' => $_POST['db_password'] ?? '',
            ]);
            if ($candidate === null) {
                $error = 'میزبان، پورت، نام دیتابیس یا نام کاربری معتبر نیست. نام دیتابیس فقط می‌تواند شامل حروف انگلیسی، عدد، خط تیره و زیرخط باشد.';
            } else {
                try {
                    $connection = connect_mysql($candidate);
                    install_schema($connection);
                    save_database_config($candidate);
                    flash_set('اتصال MySQL برقرار شد و جدول‌های سامانه آماده شدند.');
                    header('Location: setup.php?step=admin');
                    exit;
                } catch (Throwable $exception) {
                    error_log('MySQL installation step failed: ' . $exception->getMessage());
                    $error = 'اتصال به MySQL یا ساخت جدول‌ها انجام نشد. مشخصات اتصال، وجود دیتابیس و دسترسی ساخت جدول را بررسی کنید؛ افزونه PDO MySQL نیز باید فعال باشد.';
                }
            }
            $step = 'database';
        } elseif ($action === 'admin') {
            if (!$dbConfigured || !($pdo instanceof PDO)) {
                $error = 'ابتدا اتصال MySQL را در مرحله قبل ذخیره و آزمایش کنید.';
                $step = 'database';
            } else {
                $password = (string) ($_POST['password'] ?? '');
                $confirmation = (string) ($_POST['password_confirmation'] ?? '');
                if ($fullName === '' || strlen($fullName) > 300) {
                    $error = 'نام و نام خانوادگی مدیر را وارد کنید.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180) {
                    $error = 'نشانی ایمیل معتبر نیست.';
                } elseif (strlen($password) < 12) {
                    $error = 'گذرواژه باید دست‌کم ۱۲ نویسه داشته باشد.';
                } elseif ($password !== $confirmation) {
                    $error = 'تکرار گذرواژه با گذرواژه یکسان نیست.';
                } else {
                    $lockAcquired = false;
                    $alreadyInstalled = false;
                    try {
                        $lockAcquired = (int) $pdo->query("SELECT GET_LOCK('odsconco_initial_install', 10)")->fetchColumn() === 1;
                        if (!$lockAcquired) {
                            throw new RuntimeException('Could not acquire the one-time installation lock.');
                        }
                        if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
                            $alreadyInstalled = true;
                        } else {
                            $pdo->beginTransaction();
                            if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
                                $pdo->rollBack();
                                $alreadyInstalled = true;
                            } else {
                                $insert = $pdo->prepare("INSERT INTO users(full_name, email, password_hash, role, is_active, must_change_password, created_at) VALUES(:name, :email, :password, 'admin', 1, 0, :created)");
                                $insert->execute([
                                    ':name' => $fullName,
                                    ':email' => $email,
                                    ':password' => password_hash($password, PASSWORD_DEFAULT),
                                    ':created' => now_text(),
                                ]);
                                $pdo->commit();
                            }
                        }
                    } catch (Throwable $exception) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        error_log('Admin account installation failed: ' . $exception->getMessage());
                        $error = 'ساخت حساب مدیر انجام نشد. ایمیل و دسترسی نوشتن دیتابیس را بررسی کنید.';
                    } finally {
                        if ($lockAcquired) {
                            try {
                                $pdo->query("SELECT RELEASE_LOCK('odsconco_initial_install')");
                            } catch (Throwable) {
                                // MySQL releases named locks when the connection closes.
                            }
                        }
                    }

                    if ($alreadyInstalled) {
                        header('Location: login.php');
                        exit;
                    }
                    if ($error === '') {
                        flash_set('نصب کامل شد. اکنون با حساب مدیر وارد شوید.');
                        header('Location: login.php');
                        exit;
                    }
                    $step = 'admin';
                }
            }
        } else {
            $error = 'مرحله نصب معتبر نیست. صفحه را تازه‌سازی کنید.';
        }
    }
}

$pageTitle = 'نصب سامانه';
$flash = flash_get();
require __DIR__ . '/app/auth-header.php';
?>
<div class="auth-card install-card">
    <div class="auth-emblem" aria-hidden="true">✳</div>
    <p class="auth-kicker">راه‌اندازی امن</p>
    <h1>نصب سامانه</h1>
    <p class="auth-description">ابتدا اتصال MySQL را تنظیم کنید؛ سپس حساب مدیر اولیه ساخته می‌شود.</p>

    <?php if ($flash): ?><div class="portal-alert" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="portal-alert portal-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if (!$setupAllowed): ?>
        <div class="portal-alert portal-alert-error" role="alert">برای امنیت، نصب از راه دور غیرفعال است. APP_SETUP_KEY را در IIS موقتاً تنظیم کنید؛ یا صفحه را از خود سرور باز کنید.</div>
    <?php elseif ($step === 'database'): ?>
        <div class="portal-note install-step-note"><strong>مرحله ۱ از ۲ — اتصال دیتابیس</strong><br>یک دیتابیس خالی MySQL در پنل هاست بسازید و اطلاعات آن را اینجا وارد کنید. نصب‌کننده جدول‌های موردنیاز را ایجاد می‌کند.</div>
        <form method="post" action="setup.php?step=database" class="portal-form">
            <?= csrf_field() ?>
            <input type="hidden" name="install_step" value="database">
            <?php if ($setupKey !== ''): ?><label>کلید موقت نصب<input name="install_key" type="password" required autocomplete="off"></label><?php endif; ?>
            <label>میزبان MySQL<input name="db_host" type="text" maxlength="255" required autocomplete="off" value="<?= e($databaseValues['host']) ?>" placeholder="مثلاً localhost"></label>
            <div class="install-db-row">
                <label>پورت<input name="db_port" type="number" min="1" max="65535" required value="<?= e((string) $databaseValues['port']) ?>"></label>
                <label>نام دیتابیس<input name="db_name" type="text" maxlength="64" required autocomplete="off" value="<?= e($databaseValues['database']) ?>"></label>
            </div>
            <label>نام کاربری دیتابیس<input name="db_user" type="text" maxlength="128" required autocomplete="username" value="<?= e($databaseValues['username']) ?>"></label>
            <label>گذرواژه دیتابیس<input name="db_password" type="password" maxlength="4096" autocomplete="new-password"></label>
            <p class="auth-security-note">گذرواژه دیتابیس در پوشه محافظت‌شده <code>storage/</code> ذخیره می‌شود و در Git قرار نمی‌گیرد. اگر دیتابیس ساخته نشده، ابتدا آن را در کنترل‌پنل هاست ایجاد کنید.</p>
            <button class="portal-button portal-button-primary" type="submit">بررسی اتصال و ساخت جدول‌ها</button>
        </form>
    <?php else: ?>
        <div class="portal-note install-step-note"><strong>مرحله ۲ از ۲ — ساخت مدیر</strong><br>اتصال MySQL برقرار است و جدول‌های سامانه آماده‌اند. مشخصات اولین مدیر را وارد کنید.</div>
        <form method="post" action="setup.php?step=admin" class="portal-form">
            <?= csrf_field() ?>
            <input type="hidden" name="install_step" value="admin">
            <?php if ($setupKey !== ''): ?><label>کلید موقت نصب<input name="install_key" type="password" required autocomplete="off"></label><?php endif; ?>
            <label>نام و نام خانوادگی مدیر<input name="full_name" type="text" maxlength="100" required autocomplete="name" value="<?= e($fullName) ?>"></label>
            <label>ایمیل مدیر<input name="email" type="email" maxlength="180" required autocomplete="email" value="<?= e($email) ?>"></label>
            <label>گذرواژه <small>(حداقل ۱۲ نویسه)</small><input name="password" type="password" minlength="12" required autocomplete="new-password"></label>
            <label>تکرار گذرواژه<input name="password_confirmation" type="password" minlength="12" required autocomplete="new-password"></label>
            <button class="portal-button portal-button-primary" type="submit">ساخت حساب مدیر و پایان نصب</button>
        </form>
        <p><a href="setup.php?step=database">تغییر تنظیمات اتصال MySQL</a></p>
    <?php endif; ?>
    <p class="auth-security-note">پس از نصب، مقدار موقت APP_SETUP_KEY را از تنظیمات IIS حذف کنید. گذرواژه پیش‌فرضی وجود ندارد.</p>
</div>
<?php require __DIR__ . '/app/auth-footer.php'; ?>
