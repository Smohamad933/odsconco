<?php
require_once __DIR__ . '/app/bootstrap.php';
$user = require_login();
$portalTitle = 'پیشخوان';
$activePortal = 'dashboard';
$pendingAttendance = is_manager($user) ? (int) db()->query("SELECT COUNT(*) FROM attendance_requests WHERE status = 'pending'")->fetchColumn() : 0;
$newContactRequests = is_manager($user) ? (int) db()->query("SELECT COUNT(*) FROM contact_requests WHERE status = 'new'")->fetchColumn() : 0;
$myPendingAttendance = db()->prepare("SELECT COUNT(*) FROM attendance_requests WHERE user_id = :user AND status = 'pending'");
$myPendingAttendance->execute([':user' => $user['id']]);
$myPendingCount = (int) $myPendingAttendance->fetchColumn();
$openWork = db()->prepare("SELECT COUNT(*) FROM workflow_items WHERE (creator_id=:user1 OR assignee_id=:user2) AND status NOT IN ('completed','rejected')");
$openWork->execute([':user1' => $user['id'], ':user2' => $user['id']]);
$myOpenWorkCount = (int) $openWork->fetchColumn();
$flash = flash_get();
require __DIR__ . '/app/portal-header.php';
?>
<div class="portal-page-heading">
    <div><p class="portal-kicker">خوش آمدید</p><h1><?= e($user['full_name']) ?></h1><p>از این پیشخوان به پیام‌رسان و ابزارهای حضور و غیاب شرکت دسترسی دارید.</p></div>
    <a class="portal-button portal-button-secondary" href="index.php">مشاهده وب‌سایت عمومی <span aria-hidden="true">↖</span></a>
</div>
<?php if ($flash): ?><div class="portal-alert" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<div class="portal-grid">
    <section class="portal-stat"><span>وضعیت حضورهای در انتظار تأیید شما</span><strong><?= e((string) $myPendingCount) ?></strong></section>
    <section class="portal-stat"><span>درخواست‌ها و کارهای باز شما</span><strong><?= e((string) $myOpenWorkCount) ?></strong></section>
    <section class="portal-stat"><span>نقش شما در سامانه</span><strong class="stat-role"><?= e(['admin' => 'مدیر', 'manager' => 'سرپرست', 'employee' => 'همکار'][$user['role']] ?? 'کاربر') ?></strong></section>
    <section class="portal-stat"><span>اطلاعیه دسترسی</span><strong class="stat-role">فعال</strong></section>
    <?php if (is_manager($user)): ?><section class="portal-stat"><span>حضورهای نیازمند بررسی</span><strong><?= e((string) $pendingAttendance) ?></strong></section><section class="portal-stat"><span>پیام‌های فرم تماس سایت</span><strong><?= e((string) $newContactRequests) ?></strong></section><?php endif; ?>
</div>
<div class="portal-sections">
    <a class="portal-section-link" href="chat.php"><span><strong>پیام‌رسان داخلی</strong><br><small class="portal-muted">گفت‌وگوی متنی و ارسال مستقیم فایل‌های حجیم</small></span><span aria-hidden="true">←</span></a>
    <a class="portal-section-link" href="attendance.php"><span><strong>ثبت حضور و غیاب</strong><br><small class="portal-muted">ثبت دستی، ثبت با QR و ثبت دورکاری با موقعیت مکانی</small></span><span aria-hidden="true">←</span></a>
    <a class="portal-section-link" href="automation.php"><span><strong>اتوماسیون داخلی</strong><br><small class="portal-muted">ثبت درخواست، ارجاع کار، پیگیری و تأیید گردش کار</small></span><span aria-hidden="true">←</span></a>
    <a class="portal-section-link" href="account.php"><span><strong>تنظیمات حساب</strong><br><small class="portal-muted">تغییر گذرواژه و اطلاعات حساب کاربری</small></span><span aria-hidden="true">←</span></a>
    <?php if (is_manager($user)): ?><a class="portal-section-link" href="admin/attendance.php"><span><strong>بازبینی درخواست‌های حضور</strong><br><small class="portal-muted">تأیید یا رد ثبت‌های نیازمند بررسی</small></span><span aria-hidden="true">←</span></a><a class="portal-section-link" href="admin/requests.php"><span><strong>پیام‌های فرم تماس</strong><br><small class="portal-muted">پیگیری درخواست‌های ثبت‌شده در سایت عمومی</small></span><span aria-hidden="true">←</span></a><?php endif; ?>
    <?php if (($user['role'] ?? '') === 'admin'): ?><a class="portal-section-link" href="admin/index.php"><span><strong>مدیریت محتوای سایت</strong><br><small class="portal-muted">ویرایش متن‌ها، خدمات، پروژه‌ها، اعضا و مقالات</small></span><span aria-hidden="true">←</span></a><?php endif; ?>
</div>
<div class="portal-note" style="margin-top:18px">توجه: اطلاعات حضور و پیام‌های داخلی فقط برای کاربران مجاز سامانه قابل مشاهده است. دسترسی‌ها بر اساس نقش کاربری کنترل می‌شود.</div>
<?php require __DIR__ . '/app/portal-footer.php'; ?>
