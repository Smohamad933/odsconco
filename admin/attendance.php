<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_role(['admin', 'manager']);
$portalRoot = '../';
$portalTitle = 'مدیریت حضور و غیاب';
$activePortal = 'attendance-review';
$extraHead = '<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js" defer></script>';
$extraScripts = ['assets/js/qr-admin.js'];
$pending = db()->query("SELECT a.*, u.full_name, u.email FROM attendance_requests a JOIN users u ON u.id=a.user_id WHERE a.status='pending' ORDER BY a.id ASC LIMIT 200")->fetchAll();
$recent = db()->query('SELECT a.*, u.full_name, reviewer.full_name AS reviewer_name FROM attendance_requests a JOIN users u ON u.id=a.user_id LEFT JOIN users reviewer ON reviewer.id=a.reviewed_by ORDER BY a.id DESC LIMIT 100')->fetchAll();
require __DIR__ . '/../app/portal-header.php';
?>
<div class="portal-page-heading"><div><p class="portal-kicker">بررسی درخواست‌ها</p><h1>حضور و غیاب</h1><p>درخواست‌های ثبت دستی، QR و دورکاری را بازبینی کنید و QR کوتاه‌عمر محل شرکت بسازید.</p></div><a class="portal-button portal-button-secondary" href="../portal.php">بازگشت به پیشخوان</a></div>
<section class="qr-admin-box" id="qr-admin" data-api="../api/attendance.php" data-csrf="<?= e(csrf_token()) ?>">
    <h2>QR موقت ورود / خروج در محل شرکت</h2>
    <p>هر QR فقط ۹۰ ثانیه اعتبار دارد و هر کارمند می‌تواند یک بار از آن استفاده کند. درخواست نهایی همچنان نیازمند تأیید مدیر است.</p>
    <div class="portal-form-actions"><label class="portal-inline">نوع ثبت <select class="portal-input" id="qr-event-type"><option value="check_in">ورود</option><option value="check_out">خروج</option></select></label><button class="portal-button portal-button-primary" id="create-qr" type="button">ساخت QR تازه</button><span class="chat-status" id="qr-status" role="status" aria-live="polite"></span></div>
    <div class="qr-panel" id="qr-result" hidden>
        <div class="qr-canvas-wrap"><div id="qr-code" aria-label="کد QR حضور"></div></div>
        <div><strong>کد را در محل شرکت نمایش دهید.</strong><p class="qr-expiry" id="qr-expiry"></p><p class="qr-result-url" id="qr-url"></p><p class="portal-muted">به دلیل ماهیت موقت کد، از ارسال تصویر یا نشانی آن در گروه‌های عمومی خودداری کنید.</p></div>
    </div>
</section>

<section class="portal-card" style="margin-top:16px">
    <h2>در انتظار بررسی <span class="status-pill pending"><?= e((string) count($pending)) ?></span></h2>
    <?php if ($pending): ?>
        <div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>کارمند</th><th>زمان</th><th>نوع / روش</th><th>موقعیت</th><th>اقدام</th></tr></thead><tbody>
            <?php foreach ($pending as $row): ?>
                <tr data-attendance-row="<?= e((string) $row['id']) ?>">
                    <td><strong><?= e($row['full_name']) ?></strong><small><?= e($row['email']) ?></small></td>
                    <td><?= e(format_local_time($row['occurred_at'])) ?></td>
                    <td><?= e($row['event_type'] === 'check_in' ? 'ورود' : 'خروج') ?><small><?= e(['manual' => 'ثبت دستی', 'qr' => 'QR شرکت', 'remote' => 'دورکاری / GPS'][$row['method']] ?? $row['method']) ?></small></td>
                    <td><?php if ($row['latitude'] !== null && $row['longitude'] !== null): ?><a href="https://www.openstreetmap.org/?mlat=<?= e((string) $row['latitude']) ?>&mlon=<?= e((string) $row['longitude']) ?>#map=16/<?= e((string) $row['latitude']) ?>/<?= e((string) $row['longitude']) ?>" target="_blank" rel="noopener noreferrer">نمایش موقعیت ↗</a><small>دقت گزارش‌شده: <?= e((string) round((float) $row['accuracy'])) ?> متر</small><?php else: ?>—<?php endif; ?></td>
                    <td><div class="table-actions"><button class="portal-button portal-button-primary attendance-review" type="button" data-id="<?= e((string) $row['id']) ?>" data-decision="approved">تأیید</button><button class="portal-button portal-button-danger attendance-review" type="button" data-id="<?= e((string) $row['id']) ?>" data-decision="rejected">رد</button></div></td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
    <?php else: ?><div class="portal-empty">درخواست در انتظار بررسی وجود ندارد.</div><?php endif; ?>
</section>

<section class="portal-card" style="margin-top:16px">
    <h2>آخرین سوابق</h2>
    <?php if ($recent): ?>
        <div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>کارمند</th><th>زمان</th><th>نوع / روش</th><th>وضعیت</th><th>بازبین</th></tr></thead><tbody>
            <?php foreach ($recent as $row): ?>
                <tr><td><?= e($row['full_name']) ?></td><td><?= e(format_local_time($row['occurred_at'])) ?></td><td><?= e($row['event_type'] === 'check_in' ? 'ورود' : 'خروج') ?><small><?= e(['manual' => 'دستی', 'qr' => 'QR شرکت', 'remote' => 'دورکاری / GPS'][$row['method']] ?? $row['method']) ?></small></td><td><span class="status-pill <?= $row['status'] === 'pending' ? 'pending' : ($row['status'] === 'rejected' ? 'rejected' : '') ?>"><?= e(['pending' => 'در انتظار', 'approved' => 'تأییدشده', 'rejected' => 'ردشده'][$row['status']] ?? $row['status']) ?></span><?php if ($row['review_note'] !== ''): ?><small><?= e($row['review_note']) ?></small><?php endif; ?></td><td><?= e($row['reviewer_name'] ?: '—') ?></td></tr>
            <?php endforeach; ?>
        </tbody></table></div>
    <?php else: ?><div class="portal-empty">هنوز سابقه‌ای ثبت نشده است.</div><?php endif; ?>
</section>
<?php require __DIR__ . '/../app/portal-footer.php'; ?>
