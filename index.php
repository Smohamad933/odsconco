<?php
require_once __DIR__ . '/app/bootstrap.php';
$activePage = 'home';
$pageTitle = 'خانه';
$metaDescription = 'شرکت مشاوران افق دانش ثریا؛ مشاوره مهندسی، مطالعات، طراحی، مدیریت و نظارت پروژه.';
$services = content_items('services', [], 4);
$projects = content_items('projects', [], 3);
$clients = content_items('clients', [], 8);
$articles = content_items('articles', [], 3);
$titleLines = preg_split('/\r\n|\r|\n/', site_setting('hero_title'), 3) ?: ['مهندسی دقیق', 'برای افق‌های روشن'];
$titleFirst = array_shift($titleLines) ?? '';
$titleRest = implode(' ', $titleLines);
require __DIR__ . '/app/site-header.php';
?>
<section class="container hero">
    <div class="hero-copy" data-reveal>
        <p class="eyebrow"><?= e(site_setting('hero_label')) ?></p>
        <h1><?= e($titleFirst) ?><br><span class="accent-line"><?= e($titleRest) ?></span></h1>
        <p class="hero-lead"><?= e(site_setting('hero_subtitle')) ?></p>
        <div class="hero-actions">
            <a class="button button-primary" href="contact.php">
                <span><?= e(site_setting('hero_button')) ?></span>
                <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M10 5l5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
            <a class="button button-outline" href="services.php">آشنایی با خدمات ما</a>
        </div>
        <div class="hero-meta">
            <span><i class="meta-mark">✓</i> نگاه یکپارچه به پروژه</span>
            <span><i class="meta-mark">↗</i> همراهی از مطالعه تا اجرا</span>
        </div>
    </div>
    <div class="hero-visual" data-reveal>
        <div class="hero-orbit" aria-hidden="true"></div>
        <div class="hero-photo">
            <img src="assets/hero-architecture.jpg" alt="نمای یک ساختمان معاصر با رویکرد مهندسی و طراحی پایدار">
            <span class="hero-caption">نگاهی فراتر از امروز</span>
        </div>
        <span class="hero-vertical-label">پژوهش · طراحی · همراهی</span>
        <div class="hero-stamp">
            <small>افق دانش ثریا</small>
            <strong>راه‌حل‌های مهندسی،<br>با نگاه به آینده</strong>
            <span>تخصص، دقت و همکاری در کنار کارفرما</span>
        </div>
    </div>
</section>

<div class="trust-band">
    <div class="container trust-band-inner">
        <p><strong>از اندیشه تا اثر</strong><br>یک مسیر روشن برای هر پروژه</p>
        <div class="trust-band-items">
            <span>شناخت نیاز</span>
            <span>راهکار تخصصی</span>
            <span>همراهی مسئولانه</span>
            <span>نگاه بلندمدت</span>
        </div>
    </div>
</div>

<section class="container section story-section" data-reveal>
    <div class="story-visual" aria-label="طرح گرافیکی الهام‌گرفته از افق">
        <span class="story-visual-word" aria-hidden="true">افق</span>
        <div class="story-visual-note">افق دانش ثریا<span>مهندسی برای فردا</span></div>
    </div>
    <div class="story-copy">
        <p class="section-kicker">داستان ما</p>
        <h2 class="section-title">هر پروژه، آغاز یک افق تازه است.</h2>
        <p><?= nl2br(e(site_setting('home_story'))) ?></p>
        <div class="principle-row">
            <span class="principle-pill">دانش مهندسی</span>
            <span class="principle-pill">تعامل شفاف</span>
            <span class="principle-pill">آینده‌نگری</span>
        </div>
        <a class="text-link" href="about.php">بیشتر درباره ما <span aria-hidden="true">←</span></a>
    </div>
</section>

<section class="section services-section" id="services">
    <div class="container">
        <div class="section-topline" data-reveal>
            <div>
                <p class="section-kicker">خدمات ما</p>
                <h2 class="section-title">تخصصی در هر گامِ مسیر</h2>
                <p class="section-description"><?= e(site_setting('services_intro')) ?></p>
            </div>
            <a class="text-link" href="services.php">مشاهده همه خدمات <span aria-hidden="true">←</span></a>
        </div>
        <?php if ($services): ?>
            <div class="service-grid">
                <?php foreach ($services as $i => $service): ?>
                    <article class="service-card" data-reveal>
                        <div class="card-top">
                            <span class="card-number">۰<?= e((string) ($i + 1)) ?> / خدمات</span>
                            <span class="card-icon" aria-hidden="true">
                                <?php if ($i % 4 === 0): ?><svg viewBox="0 0 24 24" fill="none"><path d="M4 19V8l8-4 8 4v11M8 19v-6h8v6M2.5 19.5h19" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <?php elseif ($i % 4 === 1): ?><svg viewBox="0 0 24 24" fill="none"><path d="M5 19 19 5M7 5h12v12M4 4h3M4 4v3M20 20h-3m3 0v-3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <?php elseif ($i % 4 === 2): ?><svg viewBox="0 0 24 24" fill="none"><path d="M4 18V6m0 12h16M7 15l3-4 3 2 5-7M17 6h1v1" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <?php else: ?><svg viewBox="0 0 24 24" fill="none"><path d="m12 3 7 3v5c0 4.6-3 8-7 10-4-2-7-5.4-7-10V6l7-3Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg><?php endif; ?>
                            </span>
                        </div>
                        <h3><?= e($service['title']) ?></h3>
                        <p><?= e($service['excerpt'] ?: $service['subtitle']) ?></p>
                        <a class="card-more" href="services.php">جزئیات خدمات <span aria-hidden="true">←</span></a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="team-empty">حوزه‌های خدمات پس از ثبت در پنل مدیریت، در این بخش نمایش داده می‌شود.</div>
        <?php endif; ?>
    </div>
</section>

<section class="section projects-section" id="projects">
    <div class="container">
        <div class="section-topline" data-reveal>
            <div>
                <p class="section-kicker">پروژه‌های اخیر</p>
                <h2 class="section-title">نگاهی به مسیرهای ساخته‌شده</h2>
                <p class="section-description">هر همکاری، تجربه‌ای تازه و فرصتی برای خلق ارزشی ماندگار است.</p>
            </div>
            <a class="text-link" href="projects.php">رفتن به آرشیو پروژه‌ها <span aria-hidden="true">←</span></a>
        </div>
        <?php if ($projects): ?>
            <div class="project-grid">
                <?php foreach ($projects as $project): $image = safe_image_url((string) $project['image_url']); ?>
                    <article class="project-card" data-reveal>
                        <div class="project-card-image">
                            <?php if ($image !== ''): ?><img src="<?= e($image) ?>" alt="<?= e($project['title']) ?>" loading="lazy"><?php endif; ?>
                        </div>
                        <div class="project-card-body">
                            <div class="project-meta"><span><?= e($project['category'] ?: 'پروژه مهندسی') ?></span><span><?= e($project['subtitle']) ?></span></div>
                            <h3><?= e($project['title']) ?></h3>
                            <p><?= e($project['excerpt']) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state" data-reveal>
                <span class="empty-state-mark" aria-hidden="true">⌁</span>
                <div><h3>آرشیو پروژه‌ها در انتظار روایت شماست.</h3><p>ساختار نمایش آماده است؛ پس از افزودن پروژه‌ها در پنل مدیریت، تازه‌ترین تجربه‌های افق دانش ثریا در این بخش دیده می‌شوند.</p></div>
                <a class="button button-light" href="projects.php">مشاهده آرشیو</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="container clients-section" id="clients" data-reveal>
    <div class="clients-heading">
        <h2>کارفرمایان ما</h2>
        <p>اعتماد، نقطه آغاز هر همکاری ارزشمند است.</p>
    </div>
    <?php if ($clients): ?>
        <div class="client-list">
            <?php foreach ($clients as $client): ?><div class="client-wordmark" title="<?= e($client['subtitle']) ?>"><?= e($client['title']) ?></div><?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="client-empty">فهرست نام و نشان کارفرمایان پس از دریافت اطلاعات رسمی شرکت، در این قسمت قرار می‌گیرد.</div>
    <?php endif; ?>
</section>

<section class="container section" id="articles">
    <div class="section-topline" data-reveal>
        <div>
            <p class="section-kicker">دانش و دیدگاه</p>
            <h2 class="section-title">یادداشت‌هایی برای نگاه عمیق‌تر</h2>
            <p class="section-description">اندیشه‌ها و تجربه‌هایی در زمینه مهندسی، مدیریت پروژه و توسعه پایدار.</p>
        </div>
        <a class="text-link" href="contact.php">گفت‌وگو با کارشناسان <span aria-hidden="true">←</span></a>
    </div>
    <?php if ($articles): ?>
        <div class="article-grid">
            <?php foreach ($articles as $article): ?>
                <article class="article-card" data-reveal>
                    <span class="article-category"><?= e($article['category'] ?: 'یادداشت تخصصی') ?></span>
                    <h3><a href="article.php?id=<?= e((string) $article['id']) ?>"><?= e($article['title']) ?></a></h3>
                    <p><?= e($article['excerpt']) ?></p>
                    <a class="article-arrow" href="article.php?id=<?= e((string) $article['id']) ?>" aria-label="خواندن مقاله: <?= e($article['title']) ?>">↙</a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="article-empty">مقاله‌ای منتشر نشده است. یادداشت‌های جدید پس از ثبت و انتشار در پنل، اینجا نمایش داده می‌شوند.</div>
    <?php endif; ?>
</section>

<section class="contact-cta">
    <div class="container contact-cta-inner">
        <div>
            <h2>برای آغاز یک مسیر تازه، با ما گفت‌وگو کنید.</h2>
            <p>کارشناسان افق دانش ثریا آماده شنیدن نیازهای پروژه شما هستند.</p>
        </div>
        <a class="button button-light" href="contact.php">تماس با ما <span aria-hidden="true">↖</span></a>
    </div>
</section>
<?php require __DIR__ . '/app/site-footer.php'; ?>
