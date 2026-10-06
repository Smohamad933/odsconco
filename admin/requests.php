<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_role(['admin', 'manager']);
$portalRoot = '../';
$portalTitle = 'درخواست‌های تماس';
$activePortal = 'admin';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');
    if ($id > 0 && in_array($status, ['new', 'in_progress', 'closed'], true)) {
        $stmt = db()->prepare('UPDATE contact_requests SET status=:status,updated_at=:updated WHERE id=:id');
        $stmt->execute([':status' => $status, ':updated' => now_text(), ':id' => $id]);
        flash_set('وضعیت درخواست به‌روزرسانی شد.');
    }
    header('Location: requests.php');
    exit;
}
$requests = db()->query('SELECT * FROM contact_requests ORDER BY CASE status WHEN \'new\' THEN 0 WHEN \'in_progress\' THEN 1 ELSE 2 END, id DESC LIMIT 200')->fetchAll();
$flash = flash_get();
require __DIR__ . '/../app/portal-header.php';
?>
<div class="portal-page-heading"><div><p class="portal-kicker">فرم تماس وب‌سایت</p><h1>درخواست‌های تماس</h1><p>پیام‌های ثبت‌شده در صفحه تماس با ما را پیگیری کنید.</p></div><a class="portal-button portal-button-secondary" href="index.php">بازگشت به مدیریت</a></div>
<?php if ($flash): ?><div class="portal-alert" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($requests): ?>
    <div class="admin-stack">
        <?php foreach ($requests as $request): ?>
            <article class="portal-card request-card">
                <div class="request-heading"><div><p class="portal-kicker"><?= e($request['topic'] ?: 'درخواست عمومی') ?> · <?= e(format_local_time($request['created_at'])) ?></p><h2><?= e($request['full_name']) ?></h2></div><span class="status-pill <?= $request['status'] === 'new' ? 'new' : ($request['status'] === 'in_progress' ? 'pending' : '') ?>"><?= e(['new' => 'جدید', 'in_progress' => 'در حال پیگیری', 'closed' => 'بسته‌شده'][$request['status']] ?? $request['status']) ?></span></div>
                <div class="request-contact"><a href="tel:<?= e(preg_replace('/[^\p{N}+]/u', '', $request['phone']) ?? '') ?>">☎ <?= e($request['phone']) ?></a><?php if ($request['email'] !== ''): ?><a href="mailto:<?= e($request['email']) ?>">✉ <?= e($request['email']) ?></a><?php endif; ?></div>
                <p class="request-message"><?= nl2br(e($request['message'])) ?></p>
                <form method="post" class="request-status-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e((string) $request['id']) ?>"><label>وضعیت <select name="status"><option value="new" <?= $request['status'] === 'new' ? 'selected' : '' ?>>جدید</option><option value="in_progress" <?= $request['status'] === 'in_progress' ? 'selected' : '' ?>>در حال پیگیری</option><option value="closed" <?= $request['status'] === 'closed' ? 'selected' : '' ?>>بسته‌شده</option></select></label><button class="portal-button portal-button-primary" type="submit">ذخیره وضعیت</button></form>
            </article>
        <?php endforeach; ?>
    </div>
<?php else: ?><div class="portal-empty">درخواستی ثبت نشده است.</div><?php endif; ?>
<?php require __DIR__ . '/../app/portal-footer.php'; ?>
