<?php
require_once __DIR__ . '/app/bootstrap.php';
$activePage = 'contact';
$pageTitle = 'تماس با ما';
$metaDescription = 'با کارشناسان شرکت مشاوران افق دانش ثریا در ارتباط باشید.';
$notice = '';
$noticeType = 'success';
$old = ['full_name' => '', 'phone' => '', 'email' => '', 'topic' => '', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach ($old as $key => $_) {
        $old[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $website = trim((string) ($_POST['website'] ?? ''));
    $errors = [];
    if ($website !== '') {
        $errors[] = 'درخواست قابل ثبت نیست.';
    }
    if ($old['full_name'] === '' || strlen($old['full_name']) > 300) {
        $errors[] = 'لطفاً نام و نام خانوادگی را وارد کنید.';
    }
    if ($old['phone'] === '' || strlen($old['phone']) > 100 || !preg_match('/^[\p{N}\p{L}\s\+\-\(\)\.]{6,60}$/u', $old['phone'])) {
        $errors[] = 'لطفاً شماره تماس معتبر وارد کنید.';
    }
    if ($old['email'] !== '' && (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || strlen($old['email']) > 180)) {
        $errors[] = 'نشانی ایمیل معتبر نیست.';
    }
    if (strlen($old['topic']) > 240 || $old['message'] === '' || strlen($old['message']) > 12000) {
        $errors[] = 'لطفاً موضوع و شرح پیام را کامل کنید.';
    }
    if (!isset($_POST['consent'])) {
        $errors[] = 'برای ثبت درخواست، تأیید کنید که اطلاعات تماس واردشده صحیح است.';
    }
    $lastSubmit = (int) ($_SESSION['_contact_last_submit'] ?? 0);
    if (time() - $lastSubmit < 8) {
        $errors[] = 'لطفاً چند لحظه صبر کنید و دوباره تلاش کنید.';
    }

    if ($errors) {
        $notice = implode(' ', $errors);
        $noticeType = 'error';
    } else {
        $stmt = db()->prepare('INSERT INTO contact_requests(full_name, phone, email, topic, message, status, created_at, updated_at) VALUES(:name, :phone, :email, :topic, :message, \'new\', :created, :updated)');
        $now = now_text();
        $stmt->execute([
            ':name' => $old['full_name'],
            ':phone' => $old['phone'],
            ':email' => $old['email'],
            ':topic' => $old['topic'],
            ':message' => $old['message'],
            ':created' => $now,
            ':updated' => $now,
        ]);
        $_SESSION['_contact_last_submit'] = time();
        flash_set('درخواست شما با موفقیت ثبت شد. کارشناسان شرکت پس از بررسی با شما تماس می‌گیرند.');
        header('Location: contact.php#contact-form');
        exit;
    }
}

$flash = $_SERVER['REQUEST_METHOD'] === 'GET' ? flash_get() : null;
$phoneHref = site_setting('contact_phone_href');
if (!preg_match('/^tel:[0-9+().\-\s]+$/', $phoneHref)) $phoneHref = '';
$emailAddress = site_setting('contact_email');
if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) $emailAddress = '';
$baleUrl = site_setting('bale_url');
if (!preg_match('~^https://~i', $baleUrl)) $baleUrl = '';
$telegramUrl = site_setting('telegram_url');
if (!preg_match('~^https://~i', $telegramUrl)) $telegramUrl = '';
require __DIR__ . '/app/site-header.php';
?>
<section class="page-hero">
    <div class="container page-hero-content" data-reveal>
        <p class="eyebrow">گفت‌وگو را آغاز کنیم</p>
        <h1>تماس با ما</h1>
        <p>برای دریافت مشاوره، طرح پرسش یا آغاز همکاری، پیام خود را برای ما بفرستید. کارشناسان افق دانش ثریا در اولین فرصت با شما تماس می‌گیرند.</p>
        <div class="breadcrumb"><a href="index.php">خانه</a><span>／</span>تماس با ما</div>
    </div>
</section>

<section class="container inner-section">
    <div class="contact-layout">
        <div class="contact-form-card" id="contact-form" data-reveal>
            <h2>پیام برای کارشناسان</h2>
            <p>ستاره‌دار بودن فیلدها به معنای الزامی بودن آن‌هاست.</p>
            <?php if ($notice !== '' || $flash): ?><div class="form-notice <?= $noticeType === 'error' ? 'error' : '' ?>" role="status"><?= e($notice !== '' ? $notice : $flash['message']) ?></div><?php endif; ?>
            <form action="contact.php" method="post" novalidate>
                <?= csrf_field() ?>
                <div class="honeypot" aria-hidden="true"><label>وب‌سایت<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <div class="form-grid">
                    <div class="form-field">
                        <label for="full_name">نام و نام خانوادگی *</label>
                        <input id="full_name" name="full_name" type="text" maxlength="100" required autocomplete="name" value="<?= e($old['full_name']) ?>" placeholder="نام شما">
                    </div>
                    <div class="form-field">
                        <label for="phone">شماره تماس *</label>
                        <input id="phone" name="phone" type="tel" maxlength="40" required autocomplete="tel" value="<?= e($old['phone']) ?>" placeholder="شماره‌ای برای تماس">
                    </div>
                    <div class="form-field">
                        <label for="email">ایمیل</label>
                        <input id="email" name="email" type="email" maxlength="180" autocomplete="email" value="<?= e($old['email']) ?>" placeholder="name@example.com">
                    </div>
                    <div class="form-field">
                        <label for="topic">موضوع درخواست</label>
                        <select id="topic" name="topic">
                            <option value="">انتخاب موضوع</option>
                            <?php foreach (['مشاوره مهندسی', 'همکاری در پروژه', 'درخواست اطلاعات', 'سایر موارد'] as $topic): ?>
                                <option value="<?= e($topic) ?>" <?= $old['topic'] === $topic ? 'selected' : '' ?>><?= e($topic) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field full">
                        <label for="message">شرح پیام *</label>
                        <textarea id="message" name="message" maxlength="4000" required placeholder="کمی درباره نیاز یا پرسش خود بنویسید..."><?= e($old['message']) ?></textarea>
                    </div>
                </div>
                <label class="consent-row"><input type="checkbox" name="consent" value="1" required><span>اطلاعات تماس واردشده صحیح است و اجازه می‌دهم برای پاسخ به این درخواست با من ارتباط گرفته شود.</span></label>
                <div class="form-footer">
                    <button class="button button-primary" type="submit">ثبت درخواست <span aria-hidden="true">←</span></button>
                    <span class="form-footnote">اطلاعات این فرم فقط برای پیگیری درخواست شما استفاده می‌شود.</span>
                </div>
            </form>
        </div>

        <aside class="contact-info" data-reveal>
            <h2>راه‌های ارتباطی</h2>
            <p>در ساعات اداری با دفتر تماس بگیرید؛ خارج از ساعات اداری می‌توانید پیام خود را در بله یا تلگرام بگذارید.</p>
            <div class="contact-info-list">
                <div class="contact-info-item">
                    <span class="contact-info-icon" aria-hidden="true">☎</span>
                    <div><small>ساعات اداری · تلفن</small>
                        <?php if ($phoneHref !== ''): ?><a href="<?= e($phoneHref) ?>"><?= e(site_setting('contact_phone_display')) ?></a><?php else: ?><strong><?= e(site_setting('contact_phone_display')) ?></strong><?php endif; ?>
                        <small class="contact-subnote"><?= e(site_setting('office_hours')) ?></small>
                    </div>
                </div>
                <div class="contact-info-item">
                    <span class="contact-info-icon" aria-hidden="true">◉</span>
                    <div><small>ساعات غیراداری · بله</small>
                        <?php if ($baleUrl !== ''): ?><a href="<?= e($baleUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e(site_setting('bale_display')) ?></a><?php else: ?><strong><?= e(site_setting('bale_display')) ?></strong><?php endif; ?>
                    </div>
                </div>
                <div class="contact-info-item">
                    <span class="contact-info-icon" aria-hidden="true">↗</span>
                    <div><small>ساعات غیراداری · تلگرام</small>
                        <?php if ($telegramUrl !== ''): ?><a href="<?= e($telegramUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e(site_setting('telegram_display')) ?></a><?php else: ?><strong><?= e(site_setting('telegram_display')) ?></strong><?php endif; ?>
                    </div>
                </div>
                <?php if ($emailAddress !== ''): ?>
                    <div class="contact-info-item"><span class="contact-info-icon" aria-hidden="true">@</span><div><small>ایمیل</small><a href="mailto:<?= e($emailAddress) ?>"><?= e(site_setting('contact_email_display')) ?></a></div></div>
                <?php endif; ?>
                <div class="contact-info-item"><span class="contact-info-icon" aria-hidden="true">⌖</span><div><small>نشانی دفتر</small><strong><?= e(site_setting('contact_address')) ?></strong></div></div>
            </div>
            <div class="contact-location" aria-label="جایگاه نمایشی نشانی دفتر">
                <span class="location-pin"><span>⌖</span></span>
                <span class="location-caption">نشانی دقیق پس از ثبت در پنل مدیریت نمایش داده می‌شود</span>
            </div>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/app/site-footer.php'; ?>
