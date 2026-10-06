<?php
require_once __DIR__ . '/app/bootstrap.php';
$activePage = 'projects';
$pageTitle = 'آرشیو پروژه‌ها';
$metaDescription = 'آرشیو پروژه‌های شرکت مشاوران افق دانش ثریا.';
$allProjects = content_items('projects', [], 100);
$selectedCategory = trim((string) ($_GET['category'] ?? ''));
$projects = $selectedCategory === '' ? $allProjects : content_items('projects', ['category' => $selectedCategory], 100);
$categories = [];
foreach ($allProjects as $project) {
    if ($project['category'] !== '' && !in_array($project['category'], $categories, true)) {
        $categories[] = $project['category'];
    }
}
require __DIR__ . '/app/site-header.php';
?>
<section class="page-hero">
    <div class="container page-hero-content" data-reveal>
        <p class="eyebrow">تجربه‌هایی در مسیر ساختن</p>
        <h1>آرشیو پروژه‌ها</h1>
        <p>روایت همکاری‌ها، راهکارها و تجربه‌هایی که در کنار کارفرمایان و تیم‌های تخصصی شکل گرفته‌اند.</p>
        <div class="breadcrumb"><a href="index.php">خانه</a><span>／</span>پروژه‌ها</div>
    </div>
</section>

<section class="container inner-section">
    <div class="archive-tools" data-reveal>
        <div class="filter-list" aria-label="فیلتر پروژه‌ها">
            <a class="filter-chip <?= $selectedCategory === '' ? 'is-active' : '' ?>" href="projects.php">همه پروژه‌ها</a>
            <?php foreach ($categories as $category): ?>
                <a class="filter-chip <?= $selectedCategory === $category ? 'is-active' : '' ?>" href="projects.php?category=<?= e(rawurlencode($category)) ?>"><?= e($category) ?></a>
            <?php endforeach; ?>
        </div>
        <span class="archive-count"><?= e((string) count($projects)) ?> پروژه</span>
    </div>

    <?php if ($projects): ?>
        <div class="archive-grid">
            <?php foreach ($projects as $project): $image = safe_image_url((string) $project['image_url']); ?>
                <article class="archive-project" data-reveal>
                    <div class="project-card-image">
                        <?php if ($image !== ''): ?><img src="<?= e($image) ?>" alt="<?= e($project['title']) ?>" loading="lazy"><?php endif; ?>
                    </div>
                    <div class="project-card-body">
                        <div class="project-meta"><span><?= e($project['category'] ?: 'پروژه مهندسی') ?></span><span><?= e($project['subtitle']) ?></span></div>
                        <h2><?= e($project['title']) ?></h2>
                        <p><?= e($project['excerpt']) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="archive-empty" data-reveal>
            <span class="archive-empty-symbol" aria-hidden="true">⌁</span>
            <div>
                <h2><?= $selectedCategory !== '' ? 'پروژه‌ای در این دسته ثبت نشده است.' : 'آرشیو پروژه‌ها آماده تکمیل است.' ?></h2>
                <p>جزئیات پروژه‌ها هنوز از طرف شرکت دریافت نشده است. پس از ورود به پنل مدیریت و افزودن عنوان، کارفرما، حوزه و شرح پروژه، کارت‌های آرشیو در همین صفحه نمایش داده خواهند شد.</p>
            </div>
        </div>
    <?php endif; ?>
</section>

<section class="contact-cta">
    <div class="container contact-cta-inner">
        <div><h2>پروژه بعدی می‌تواند آغاز یک همکاری تازه باشد.</h2><p>برای بررسی نیازهای پروژه و دریافت مشاوره اولیه، با ما در تماس باشید.</p></div>
        <a class="button button-light" href="contact.php">درخواست مشاوره <span aria-hidden="true">↖</span></a>
    </div>
</section>
<?php require __DIR__ . '/app/site-footer.php'; ?>
