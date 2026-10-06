<?php
require_once __DIR__ . '/app/bootstrap.php';
$activePage = 'services';
$pageTitle = 'خدمات ما';
$metaDescription = 'حوزه‌های خدمات شرکت مشاوران افق دانش ثریا.';
$services = content_items('services', [], 50);
require __DIR__ . '/app/site-header.php';
?>
<section class="page-hero">
    <div class="container page-hero-content" data-reveal>
        <p class="eyebrow">همراهی متناسب با نیاز هر پروژه</p>
        <h1>خدمات ما</h1>
        <p><?= e(site_setting('services_intro')) ?></p>
        <div class="breadcrumb"><a href="index.php">خانه</a><span>／</span>خدمات</div>
    </div>
</section>

<section class="container inner-section">
    <div class="page-section-heading" data-reveal>
        <div><p class="section-kicker">حوزه‌های تخصصی</p><h2>راهکارهایی برای مسیر پروژه شما</h2></div>
        <p>ترکیب تجربه، تحلیل و همکاری نزدیک با کارفرما، کمک می‌کند هر مرحله از پروژه با دیدی روشن پیش برود.</p>
    </div>
    <?php if ($services): ?>
        <div class="service-detail-grid">
            <?php foreach ($services as $i => $service): ?>
                <article class="service-detail" data-reveal>
                    <span class="service-index">حوزه ۰<?= e((string) ($i + 1)) ?> · <?= e($service['category'] ?: 'مشاوره مهندسی') ?></span>
                    <h2><?= e($service['title']) ?></h2>
                    <?php if ($service['subtitle'] !== ''): ?><strong><?= e($service['subtitle']) ?></strong><?php endif; ?>
                    <p><?= nl2br(e($service['body'] ?: $service['excerpt'])) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="team-empty">خدمات پس از ثبت و انتشار در پنل مدیریت در این صفحه نمایش داده می‌شوند.</div>
    <?php endif; ?>
</section>

<section class="process-section section">
    <div class="container">
        <div data-reveal>
            <p class="section-kicker">روش همکاری</p>
            <h2 class="section-title">از شناخت مسئله تا همراهی در اجرا</h2>
            <p class="section-description">فرایند همکاری با توجه به ابعاد و الزامات هر پروژه تنظیم می‌شود.</p>
        </div>
        <div class="process-grid">
            <article class="process-step" data-reveal><span>گام ۰۱</span><h3>شنیدن و شناخت</h3><p>تعریف نیازها، اهداف و محدودیت‌های پروژه در گفت‌وگو با کارفرما.</p></article>
            <article class="process-step" data-reveal><span>گام ۰۲</span><h3>تحلیل و برنامه‌ریزی</h3><p>بررسی گزینه‌ها و تنظیم مسیر متناسب با اولویت‌ها و منابع پروژه.</p></article>
            <article class="process-step" data-reveal><span>گام ۰۳</span><h3>طراحی راهکار</h3><p>ارائه راهکارهای فنی و هماهنگی میان حوزه‌های تخصصی مورد نیاز.</p></article>
            <article class="process-step" data-reveal><span>گام ۰۴</span><h3>همراهی و ارزیابی</h3><p>پایش پیشرفت و بازنگری تصمیم‌ها برای دستیابی به نتیجه مورد انتظار.</p></article>
        </div>
    </div>
</section>

<section class="contact-cta">
    <div class="container contact-cta-inner">
        <div><h2>بیایید درباره نیاز پروژه شما صحبت کنیم.</h2><p>کارشناسان ما برای یک گفت‌وگوی اولیه در دسترس هستند.</p></div>
        <a class="button button-light" href="contact.php">ارتباط با کارشناسان <span aria-hidden="true">↖</span></a>
    </div>
</section>
<?php require __DIR__ . '/app/site-footer.php'; ?>
