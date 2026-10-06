<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_role(['admin']);
$portalRoot = '../';
$portalTitle = 'مدیریت محتوا';
$activePortal = 'admin';
$counts = [];
foreach (['projects' => 'پروژه', 'articles' => 'مقاله', 'team' => 'عضو تیم', 'services' => 'خدمت', 'clients' => 'کارفرما'] as $type => $label) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM content_items WHERE type = :type AND is_published = 1');
    $stmt->execute([':type' => $type]);
    $counts[] = ['label' => $label . ' منتشرشده', 'count' => (int) $stmt->fetchColumn()];
}
$newRequests = (int) db()->query("SELECT COUNT(*) FROM contact_requests WHERE status = 'new'")->fetchColumn();
$totalUsers = (int) db()->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn();
$flash = flash_get();
require __DIR__ . '/../app/portal-header.php';
?>
<div class="portal-page-heading">
    <div><p class="portal-kicker">پنل مدیریت سایت</p><h1>مدیریت محتوا</h1><p>متن‌ها، اعضای تیم، پروژه‌ها، خدمات و مقالات را از این قسمت به‌روز کنید.</p></div>
    <a class="portal-button portal-button-secondary" href="../index.php">بازدید از سایت <span aria-hidden="true">↖</span></a>
</div>
<?php if ($flash): ?><div class="portal-alert" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<div class="admin-stat-grid">
    <?php foreach ($counts as $count): ?><div class="portal-stat"><span><?= e($count['label']) ?></span><strong><?= e((string) $count['count']) ?></strong></div><?php endforeach; ?>
    <div class="portal-stat"><span>درخواست تماس جدید</span><strong><?= e((string) $newRequests) ?></strong></div>
    <div class="portal-stat"><span>حساب‌های فعال سامانه</span><strong><?= e((string) $totalUsers) ?></strong></div>
</div>
<div class="admin-two-col">
    <section class="portal-card">
        <h2>مدیریت محتوای وب‌سایت</h2>
        <p class="portal-muted">برای به‌روزرسانی صفحه اصلی و اطلاعات تماس، تنظیمات سایت را باز کنید. آیتم‌های فهرست‌ها از صفحات جداگانه قابل مدیریت‌اند.</p>
        <div class="admin-stack" style="margin-top:14px">
            <a class="admin-shortcut" href="settings.php"><strong>متن‌ها و اطلاعات شرکت</strong><span>صفحه اصلی، داستان ما، ارزش‌ها و راه‌های ارتباطی</span></a>
            <a class="admin-shortcut" href="items.php?type=projects"><strong>پروژه‌ها</strong><span>عنوان، کارفرما، حوزه، توضیح و تصویر پروژه</span></a>
            <a class="admin-shortcut" href="items.php?type=team"><strong>اعضای تیم و هیئت‌مدیره</strong><span>نام، سمت و رزومه خلاصه اعضا</span></a>
            <a class="admin-shortcut" href="items.php?type=articles"><strong>مقالات و یادداشت‌ها</strong><span>پیش‌نویس، انتشار و ویرایش مطالب</span></a>
            <a class="admin-shortcut" href="items.php?type=services"><strong>خدمات</strong><span>حوزه‌های خدمات و توضیحات تخصصی</span></a>
            <a class="admin-shortcut" href="items.php?type=clients"><strong>کارفرمایان</strong><span>افزودن نام و نشان کارفرمایان</span></a>
        </div>
    </section>
    <section class="portal-card">
        <h2>ابزارهای شرکت</h2>
        <p class="portal-muted">مدیریت کاربران داخلی، بازبینی درخواست‌ها و ثبت حضورها.</p>
        <div class="admin-stack" style="margin-top:14px">
            <a class="admin-shortcut" href="users.php"><strong>حساب‌های کاربری</strong><span>ساخت حساب کارمند و مدیر، فعال‌سازی یا غیرفعال‌سازی</span></a>
            <a class="admin-shortcut" href="attendance.php"><strong>حضور و غیاب</strong><span>ساخت QR موقت و بررسی درخواست‌های حضور</span></a>
            <a class="admin-shortcut" href="requests.php"><strong>درخواست‌های تماس سایت</strong><span>مشاهده و پیگیری پیام‌های فرم تماس</span></a>
            <a class="admin-shortcut" href="../chat.php"><strong>پیام‌رسان داخلی</strong><span>گفت‌وگو با اعضای شرکت</span></a>
        </div>
        <div class="portal-note portal-danger-note" style="margin-top:16px">پیش از انتشار نهایی، نام اعضا، سوابق، پروژه‌ها، کارفرمایان و راه‌های تماس واقعی شرکت را در پنل تکمیل کنید.</div>
    </section>
</div>
<?php require __DIR__ . '/../app/portal-footer.php'; ?>
