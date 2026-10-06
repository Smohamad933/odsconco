<?php
require_once __DIR__ . '/app/bootstrap.php';
$user = require_login();
$portalTitle = 'پیام‌رسان داخلی';
$activePortal = 'chat';
$portalRoot = '';
$stmt = db()->prepare('SELECT id,full_name,role FROM users WHERE is_active=1 AND id<>:self ORDER BY full_name COLLATE NOCASE');
$stmt->execute([':self' => $user['id']]);
$colleagues = $stmt->fetchAll();
$requestedPeer = (int) ($_GET['peer'] ?? 0);
$selectedPeer = null;
foreach ($colleagues as $colleague) {
    if ((int) $colleague['id'] === $requestedPeer) $selectedPeer = $colleague;
}
if (!$selectedPeer && $colleagues) $selectedPeer = $colleagues[0];
$extraScripts = ['assets/js/chat.js'];
require __DIR__ . '/app/portal-header.php';
?>
<div class="portal-page-heading"><div><p class="portal-kicker">گفت‌وگوهای شرکت</p><h1>پیام‌رسان داخلی</h1><p>پیام‌های متنی روی سرور ذخیره می‌شوند؛ فایل‌های حجیم با ارتباط مستقیم بین دستگاه‌ها منتقل می‌شوند.</p></div><a class="portal-button portal-button-secondary" href="portal.php">بازگشت به پیشخوان</a></div>
<div id="messenger-app" class="chat-layout" data-api="api/chat.php" data-csrf="<?= e(csrf_token()) ?>" data-current-user="<?= e((string) $user['id']) ?>" data-peer="<?= e((string) ($selectedPeer['id'] ?? 0)) ?>">
    <aside class="chat-sidebar">
        <h2>همکاران</h2>
        <div class="chat-user-list" id="chat-user-list">
            <?php foreach ($colleagues as $colleague): ?>
                <button type="button" class="chat-user <?= (int) ($selectedPeer['id'] ?? 0) === (int) $colleague['id'] ? 'is-active' : '' ?>" data-peer="<?= e((string) $colleague['id']) ?>" data-name="<?= e($colleague['full_name']) ?>">
                    <span class="chat-user-avatar" aria-hidden="true">✳</span><span><strong><?= e($colleague['full_name']) ?></strong><small><?= e(['admin' => 'مدیر سامانه', 'manager' => 'مدیر', 'employee' => 'همکار'][$colleague['role']] ?? 'همکار') ?></small></span>
                </button>
            <?php endforeach; ?>
        </div>
        <?php if (!$colleagues): ?><div class="portal-empty">برای آغاز گفت‌وگو، مدیر باید حساب همکاران را در پنل بسازد.</div><?php endif; ?>
        <div class="chat-privacy-note">گفت‌وگو خصوصی بین دو حساب است. از ارسال اطلاعات بسیار محرمانه در پیام‌رسان داخلی خودداری کنید.</div>
    </aside>
    <section class="chat-main" aria-label="گفت‌وگو">
        <div class="chat-topbar">
            <div><h2 id="chat-peer-name"><?= e($selectedPeer['full_name'] ?? 'گفت‌وگویی انتخاب نشده') ?></h2><p>پیام متنی در پایگاه‌داده داخلی ذخیره می‌شود.</p></div>
            <span class="chat-peer-status" id="chat-connection-status">متصل به سامانه</span>
        </div>
        <div class="chat-messages" id="chat-messages" role="log" aria-live="polite" aria-relevant="additions">
            <?php if (!$selectedPeer): ?><p class="chat-empty">برای شروع، یک همکار را از فهرست انتخاب کنید.</p><?php else: ?><p class="chat-empty">در حال دریافت پیام‌ها…</p><?php endif; ?>
        </div>
        <form class="chat-compose" id="chat-form" <?= !$selectedPeer ? 'hidden' : '' ?>>
            <textarea name="body" maxlength="4000" placeholder="پیام خود را بنویسید…" aria-label="متن پیام"></textarea>
            <div class="chat-compose-row">
                <div class="chat-compose-tools">
                    <label class="chat-attach-label" for="chat-file">پیوست فایل</label>
                    <input class="chat-file-input" id="chat-file" name="file" type="file">
                    <span class="chat-size-note">فایل در مرورگر شما می‌ماند؛ انتقال مستقیم نیازمند آنلاین بودن هر دو نفر است.</span>
                </div>
                <button class="portal-button portal-button-primary" type="submit">ارسال پیام <span aria-hidden="true">←</span></button>
            </div>
            <div class="chat-file-progress" id="chat-file-progress" hidden><span></span></div>
            <div class="chat-status" id="chat-status" role="status" aria-live="polite"></div>
        </form>
    </section>
</div>
<div class="portal-note" style="margin-top:14px">فایل حجیم در فضای سرور آپلود نمی‌شود؛ نسخه فرستنده در فضای محلی مرورگر (IndexedDB) نگهداری می‌شود و با WebRTC مستقیماً به گیرنده می‌رسد. برای عبور مطمئن از فایروال‌ها و NAT، در محیط واقعی TURN اختصاصی لازم است؛ اگر ارتباط مستقیم برقرار نشود، ارسال فایل انجام نمی‌شود.</div>
<?php require __DIR__ . '/app/portal-footer.php'; ?>
