<?php
require_once __DIR__ . '/app/bootstrap.php';
$user = require_login();
$portalTitle = 'حضور و غیاب';
$activePortal = 'attendance';
$portalRoot = '';
$qrToken = (string) ($_GET['qr'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/i', $qrToken)) $qrToken = '';
$qrEventType = (string) ($_GET['event'] ?? 'check_in');
if (!in_array($qrEventType, ['check_in', 'check_out'], true)) $qrEventType = 'check_in';
$stmt = db()->prepare('SELECT a.*, reviewer.full_name AS reviewer_name FROM attendance_requests a LEFT JOIN users reviewer ON reviewer.id=a.reviewed_by WHERE a.user_id=:user ORDER BY a.id DESC LIMIT 50');
$stmt->execute([':user' => $user['id']]);
$history = $stmt->fetchAll();
$extraScripts = ['assets/js/attendance.js'];
require __DIR__ . '/app/portal-header.php';
?>
<div class="portal-page-heading"><div><p class="portal-kicker">ثبت روزانه</p><h1>حضور و غیاب</h1><p>روش ثبت را انتخاب کنید. درخواست‌ها برای تأیید مدیر در سامانه نگهداری می‌شوند.</p></div><?php if (is_manager($user)): ?><a class="portal-button portal-button-secondary" href="admin/attendance.php">مدیریت حضورها <span aria-hidden="true">↖</span></a><?php endif; ?></div>
<div class="attendance-layout">
    <section class="portal-card">
        <h2>ثبت درخواست حضور</h2>
        <form id="attendance-form" data-csrf="<?= e(csrf_token()) ?>" data-api="api/attendance.php" data-qr-token="<?= e($qrToken) ?>">
            <div class="portal-form-grid">
                <label>نوع ثبت
                    <select name="event_type" required><option value="check_in" <?= $qrEventType === 'check_in' ? 'selected' : '' ?>>ورود</option><option value="check_out" <?= $qrEventType === 'check_out' ? 'selected' : '' ?>>خروج</option></select>
                </label>
            </div>
            <div class="attendance-methods" role="radiogroup" aria-label="روش ثبت حضور">
                <label class="attendance-method"><input type="radio" name="method" value="manual" checked><span>ثبت دستی</span></label>
                <label class="attendance-method"><input type="radio" name="method" value="qr" <?= $qrToken !== '' ? 'checked' : '' ?>><span>اسکن QR شرکت</span></label>
                <label class="attendance-method"><input type="radio" name="method" value="remote"><span>دورکاری با موقعیت</span></label>
            </div>
            <div class="attendance-method-panel" data-method-panel="manual">
                <h3>ثبت دستی</h3><p>درخواست ثبت می‌شود و پس از بررسی و تأیید مدیر در سابقه حضور شما قرار می‌گیرد.</p>
            </div>
            <div class="attendance-method-panel" data-method-panel="qr" hidden>
                <h3>ثبت با QR محل کار</h3>
                <?php if ($qrToken !== ''): ?><p>کد QR دریافت شد. پس از ثبت، مدیر می‌تواند درخواست را تأیید کند.</p><?php else: ?><p>QR نمایش‌داده‌شده در شرکت را با دوربین تلفن اسکن کنید. پیوند بازشده این صفحه را با کد موقت پر می‌کند.</p><?php endif; ?>
                <label class="portal-form" style="margin-top:12px">کد موقت (در صورت نیاز)<input class="portal-input" name="qr_token" type="text" inputmode="latin" maxlength="64" value="<?= e($qrToken) ?>" placeholder="کد از پیوند QR دریافت می‌شود"></label>
            </div>
            <div class="attendance-method-panel" data-method-panel="remote" hidden>
                <h3>ثبت دورکاری با موقعیت مکانی</h3><p>مختصات فقط هنگام فشردن دکمه و با اجازه مرورگر خوانده می‌شود؛ ردیابی پیوسته انجام نمی‌شود.</p>
                <label class="location-consent"><input type="checkbox" name="location_consent" value="1"><span>اجازه می‌دهم موقعیت مکانی فعلی من فقط برای ثبت همین درخواست دورکاری ارسال شود.</span></label>
            </div>
            <div class="attendance-controls"><button class="portal-button portal-button-primary" type="submit">ثبت درخواست حضور</button><span id="attendance-status" class="chat-status" role="status" aria-live="polite"></span></div>
        </form>
        <div class="portal-note" style="margin-top:17px">هر سه روش به‌صورت «در انتظار تأیید» ثبت می‌شوند. درخواست دستی، QR یا موقعیت مکانی تا بررسی مدیر، حضور قطعی محسوب نمی‌شود.</div>
    </section>
    <aside class="portal-card">
        <h2>راهنمای ثبت</h2>
        <div class="admin-stack">
            <div class="portal-note"><strong>ثبت دستی:</strong> برای مواقعی که اسکن QR یا ثبت موقعیت در دسترس نیست؛ نیازمند بررسی مدیر.</div>
            <div class="portal-note"><strong>QR شرکت:</strong> کد کوتاه‌عمر را در محل شرکت اسکن کنید. از ارسال یا بازنشر کد خودداری کنید.</div>
            <div class="portal-note"><strong>دورکاری:</strong> دسترسی به موقعیت مرورگر نیازمند HTTPS و اجازه صریح شماست. مختصات با دقت محدود ذخیره می‌شود.</div>
        </div>
    </aside>
</div>
<section class="portal-card attendance-history">
    <h2>سوابق من</h2>
    <?php if ($history): ?>
        <div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>زمان</th><th>نوع</th><th>روش</th><th>وضعیت</th><th>بازبینی</th></tr></thead><tbody>
            <?php foreach ($history as $row): ?>
                <tr><td><?= e(format_local_time($row['occurred_at'])) ?></td><td><?= e($row['event_type'] === 'check_in' ? 'ورود' : 'خروج') ?></td><td><?= e(['manual' => 'دستی', 'qr' => 'QR شرکت', 'remote' => 'دورکاری / GPS'][$row['method']] ?? $row['method']) ?></td><td><span class="status-pill <?= $row['status'] === 'pending' ? 'pending' : ($row['status'] === 'rejected' ? 'rejected' : '') ?>"><?= e(['pending' => 'در انتظار تأیید', 'approved' => 'تأییدشده', 'rejected' => 'ردشده'][$row['status']] ?? $row['status']) ?></span></td><td><?= e($row['reviewer_name'] ?: '—') ?><?php if ($row['review_note'] !== ''): ?><small><?= e($row['review_note']) ?></small><?php endif; ?></td></tr>
            <?php endforeach; ?>
        </tbody></table></div>
    <?php else: ?><div class="portal-empty">هنوز سابقه‌ای برای شما ثبت نشده است.</div><?php endif; ?>
</section>
<?php require __DIR__ . '/app/portal-footer.php'; ?>
