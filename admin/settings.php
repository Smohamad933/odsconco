<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_role(['admin']);
$portalRoot = '../';
$portalTitle = 'متن‌ها و تنظیمات سایت';
$activePortal = 'admin';
$fields = [
    'site_name' => ['نام نمایشی شرکت', 'input'],
    'hero_label' => ['متن کوتاه بالای تیتر صفحه اصلی', 'input'],
    'hero_title' => ['تیتر اصلی (برای رفتن به خط بعد، Enter بزنید)', 'textarea'],
    'hero_subtitle' => ['زیرتیتر صفحه اصلی', 'textarea'],
    'hero_button' => ['متن دکمه اصلی صفحه اصلی', 'input'],
    'home_story' => ['داستان کوتاه صفحه اصلی', 'textarea'],
    'about_story' => ['داستان شکل‌گیری شرکت', 'textarea'],
    'services_intro' => ['معرفی کوتاه خدمات', 'textarea'],
    'value_1_title' => ['ارزش محوری ۱ · عنوان', 'input'],
    'value_1_text' => ['ارزش محوری ۱ · توضیح', 'textarea'],
    'value_2_title' => ['ارزش محوری ۲ · عنوان', 'input'],
    'value_2_text' => ['ارزش محوری ۲ · توضیح', 'textarea'],
    'value_3_title' => ['ارزش محوری ۳ · عنوان', 'input'],
    'value_3_text' => ['ارزش محوری ۳ · توضیح', 'textarea'],
    'value_4_title' => ['ارزش محوری ۴ · عنوان', 'input'],
    'value_4_text' => ['ارزش محوری ۴ · توضیح', 'textarea'],
    'office_hours' => ['ساعات اداری', 'input'],
    'contact_phone_display' => ['شماره تلفن نمایشی', 'input'],
    'contact_phone_href' => ['لینک تماس (با tel: شروع شود)', 'input'],
    'bale_display' => ['شناسه یا شماره بله', 'input'],
    'bale_url' => ['لینک بله (https://...)', 'input'],
    'telegram_display' => ['شناسه تلگرام', 'input'],
    'telegram_url' => ['لینک تلگرام (https://...)', 'input'],
    'contact_email_display' => ['ایمیل نمایشی', 'input'],
    'contact_email' => ['نشانی ایمیل برای لینک تماس', 'input'],
    'contact_address' => ['نشانی دفتر', 'textarea'],
];
$values = [];
foreach ($fields as $key => $_field) $values[$key] = site_setting($key);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach ($fields as $key => $_field) {
        $value = trim((string) ($_POST[$key] ?? ''));
        if (strlen($value) > 8000) $error = 'یکی از متن‌ها بیش از اندازه طولانی است.';
        $values[$key] = $value;
    }
    if ($error === '' && $values['contact_phone_href'] !== '' && !preg_match('/^tel:[0-9+().\-\s]+$/', $values['contact_phone_href'])) {
        $error = 'لینک تماس باید خالی باشد یا با tel: و شماره تلفن شروع شود.';
    }
    foreach (['bale_url', 'telegram_url'] as $urlKey) {
        if ($error === '' && $values[$urlKey] !== '' && (!filter_var($values[$urlKey], FILTER_VALIDATE_URL) || !str_starts_with(strtolower($values[$urlKey]), 'https://'))) {
            $error = 'لینک پیام‌رسان باید یک نشانی معتبر https باشد.';
        }
    }
    if ($error === '' && $values['contact_email'] !== '' && !filter_var($values['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'نشانی ایمیل واردشده معتبر نیست.';
    }
    if ($error === '') {
        foreach ($values as $key => $value) save_setting($key, $value);
        flash_set('تنظیمات سایت ذخیره شد.');
        header('Location: settings.php');
        exit;
    }
}
require __DIR__ . '/../app/portal-header.php';
?>
<div class="portal-page-heading"><div><p class="portal-kicker">مدیریت سایت</p><h1>متن‌ها و اطلاعات شرکت</h1><p>متن‌ها و راه‌های ارتباطی را از این فرم تغییر دهید؛ سپس نتیجه را در وب‌سایت عمومی بررسی کنید.</p></div><a class="portal-button portal-button-secondary" href="../index.php">پیش‌نمایش سایت <span aria-hidden="true">↖</span></a></div>
<?php if ($error !== ''): ?><div class="portal-alert portal-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="portal-note" style="margin-bottom:15px">مقادیر اولیه متن‌های نمونه هستند. شماره تلفن، شناسه‌های بله و تلگرام، ساعات کاری، داستان واقعی و اطلاعات شرکت را با داده‌های تأییدشده جایگزین کنید.</div>
<form method="post" class="admin-stack">
    <?= csrf_field() ?>
    <?php
    $groups = [
        'صفحه اصلی و معرفی' => ['site_name','hero_label','hero_title','hero_subtitle','hero_button','home_story','about_story','services_intro'],
        'ارزش‌های محوری' => ['value_1_title','value_1_text','value_2_title','value_2_text','value_3_title','value_3_text','value_4_title','value_4_text'],
        'راه‌های ارتباطی' => ['office_hours','contact_phone_display','contact_phone_href','bale_display','bale_url','telegram_display','telegram_url','contact_email_display','contact_email','contact_address'],
    ];
    foreach ($groups as $groupTitle => $keys): ?>
        <section class="portal-form-card">
            <h2><?= e($groupTitle) ?></h2>
            <div class="portal-form-grid">
                <?php foreach ($keys as $key): [$label, $type] = $fields[$key]; ?>
                    <label class="<?= $type === 'textarea' ? 'portal-form-full' : '' ?>"><?= e($label) ?>
                        <?php if ($type === 'textarea'): ?><textarea name="<?= e($key) ?>" rows="<?= $key === 'hero_title' ? '2' : '3' ?>"><?= e($values[$key]) ?></textarea>
                        <?php else: ?><input name="<?= e($key) ?>" type="text" value="<?= e($values[$key]) ?>" maxlength="500"><?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
    <div class="portal-form-actions"><button class="portal-button portal-button-primary" type="submit">ذخیره تغییرات</button><a class="portal-button portal-button-secondary" href="index.php">بازگشت به مدیریت</a></div>
</form>
<?php require __DIR__ . '/../app/portal-footer.php'; ?>
