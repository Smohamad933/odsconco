<?php
require_once __DIR__ . '/../app/bootstrap.php';
$admin = require_role(['admin']);
$portalRoot = '../';
$portalTitle = 'حساب‌های کاربری';
$activePortal = 'admin';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $name = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $role = (string) ($_POST['role'] ?? 'employee');
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || strlen($name) > 300) $error = 'نام و نام خانوادگی را وارد کنید.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180) $error = 'نشانی ایمیل معتبر نیست.';
        elseif (!in_array($role, ['employee', 'manager'], true)) $error = 'نقش انتخاب‌شده معتبر نیست.';
        elseif (strlen($password) < 12) $error = 'گذرواژه اولیه باید دست‌کم ۱۲ نویسه داشته باشد.';
        else {
            try {
                $stmt = db()->prepare('INSERT INTO users(full_name,email,password_hash,role,is_active,must_change_password,created_at) VALUES(:name,:email,:hash,:role,1,1,:created)');
                $stmt->execute([':name' => $name, ':email' => $email, ':hash' => password_hash($password, PASSWORD_DEFAULT), ':role' => $role, ':created' => now_text()]);
                flash_set('حساب ساخته شد. کاربر در نخستین ورود باید گذرواژه اولیه را تغییر دهد.');
                header('Location: users.php');
                exit;
            } catch (PDOException $exception) {
                error_log($exception->getMessage());
                $error = 'این ایمیل ممکن است قبلاً ثبت شده باشد.';
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $target = db()->prepare('SELECT id, role, is_active FROM users WHERE id = :id');
        $target->execute([':id' => $id]);
        $account = $target->fetch();
        if (!$account) $error = 'حساب مورد نظر پیدا نشد.';
        elseif ($id === (int) $admin['id']) $error = 'غیرفعال کردن حسابی که با آن وارد شده‌اید مجاز نیست.';
        elseif ($account['role'] === 'admin' && (int) $account['is_active'] === 1 && (int) db()->query("SELECT COUNT(*) FROM users WHERE role='admin' AND is_active=1")->fetchColumn() <= 1) $error = 'حداقل یک مدیر فعال باید در سامانه باقی بماند.';
        else {
            $next = (int) $account['is_active'] === 1 ? 0 : 1;
            $update = db()->prepare('UPDATE users SET is_active = :active WHERE id = :id');
            $update->execute([':active' => $next, ':id' => $id]);
            flash_set($next ? 'حساب فعال شد.' : 'حساب غیرفعال شد.');
            header('Location: users.php');
            exit;
        }
    }
}
$users = db()->query('SELECT id,full_name,email,role,is_active,must_change_password,created_at FROM users ORDER BY id ASC')->fetchAll();
$flash = flash_get();
require __DIR__ . '/../app/portal-header.php';
?>
<div class="portal-page-heading"><div><p class="portal-kicker">مدیریت دسترسی</p><h1>حساب‌های کاربری</h1><p>برای همکاران حساب ایجاد کنید تا بتوانند از پیام‌رسان و ثبت حضور استفاده کنند.</p></div><a class="portal-button portal-button-secondary" href="index.php">بازگشت</a></div>
<?php if ($flash): ?><div class="portal-alert" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="portal-alert portal-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="admin-two-col">
    <section class="portal-form-card">
        <h2>ساخت حساب کاربری</h2>
        <p class="portal-muted">گذرواژه اولیه را از مسیر امن به همکار بدهید؛ سامانه در نخستین ورود او را مجبور به تغییر گذرواژه می‌کند.</p>
        <form method="post" class="portal-form" autocomplete="off">
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <label>نام و نام خانوادگی<input name="full_name" maxlength="100" required></label>
            <label>ایمیل سازمانی<input name="email" type="email" maxlength="180" required autocomplete="off"></label>
            <label>نقش<select name="role"><option value="employee">همکار</option><option value="manager">مدیر / سرپرست</option></select></label>
            <label>گذرواژه اولیه <small>(حداقل ۱۲ نویسه)</small><input name="password" type="password" minlength="12" required autocomplete="new-password"></label>
            <button class="portal-button portal-button-primary" type="submit">ساخت حساب</button>
        </form>
    </section>
    <section class="portal-card">
        <h2>نکات دسترسی</h2>
        <ul class="portal-muted">
            <li>مدیران می‌توانند درخواست‌های حضور را بررسی کنند.</li>
            <li>مدیر سامانه به ویرایش سایت و مدیریت کاربران دسترسی دارد.</li>
            <li>غیرفعال کردن حساب، ورود بعدی را مسدود می‌کند؛ پیام‌ها و سوابق حفظ می‌شوند.</li>
            <li>گذرواژه کاربران به شکل هش ذخیره می‌شود و قابل مشاهده نیست.</li>
        </ul>
    </section>
</div>
<section class="admin-stack" style="margin-top:18px">
    <div class="portal-page-heading"><div><h2 style="font-size:17px">فهرست کاربران</h2></div></div>
    <div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>نام</th><th>ایمیل</th><th>نقش</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
        <?php foreach ($users as $account): ?>
            <tr><td><strong><?= e($account['full_name']) ?></strong><small>عضویت: <?= e(format_local_time($account['created_at'])) ?></small></td><td><?= e($account['email']) ?></td><td><?= e(['admin' => 'مدیر سامانه', 'manager' => 'مدیر / سرپرست', 'employee' => 'همکار'][$account['role']] ?? $account['role']) ?></td><td><span class="status-pill <?= (int) $account['is_active'] === 1 ? '' : 'rejected' ?>"><?= (int) $account['is_active'] === 1 ? 'فعال' : 'غیرفعال' ?></span><?php if ((int) $account['must_change_password'] === 1): ?><small>تغییر گذرواژه در ورود بعدی</small><?php endif; ?></td><td><form method="post" onsubmit="return confirm('وضعیت حساب تغییر کند؟')"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= e((string) $account['id']) ?>"><button class="portal-button <?= (int) $account['is_active'] === 1 ? 'portal-button-danger' : 'portal-button-secondary' ?>" type="submit" <?= (int) $account['id'] === (int) $admin['id'] ? 'disabled' : '' ?>><?= (int) $account['is_active'] === 1 ? 'غیرفعال‌سازی' : 'فعال‌سازی' ?></button></form></td></tr>
        <?php endforeach; ?>
    </tbody></table></div>
</section>
<?php require __DIR__ . '/../app/portal-footer.php'; ?>
