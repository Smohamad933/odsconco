<?php
require_once __DIR__ . '/app/bootstrap.php';
$activePage = 'about';
$pageTitle = 'درباره ما';
$metaDescription = 'داستان شکل‌گیری، ارزش‌های محوری و اعضای شرکت مشاوران افق دانش ثریا.';
$boardMembers = content_items('team', ['category' => 'board'], 30);
$teamMembers = content_items('team', ['category' => 'team'], 100);
$values = [];
for ($i = 1; $i <= 4; $i++) {
    $values[] = [
        'title' => site_setting('value_' . $i . '_title'),
        'text' => site_setting('value_' . $i . '_text'),
    ];
}
require __DIR__ . '/app/site-header.php';
?>
<section class="page-hero">
    <div class="container page-hero-content" data-reveal>
        <p class="eyebrow">شناخت افق دانش ثریا</p>
        <h1>درباره ما</h1>
        <p>نگاهی کوتاه به مسیری که با یک باور مهندسی آغاز شد: آینده بهتر، از تصمیم‌های دقیق امروز ساخته می‌شود.</p>
        <div class="breadcrumb"><a href="index.php">خانه</a><span>／</span>درباره ما</div>
    </div>
</section>

<section class="container inner-section">
    <div class="page-section-heading" data-reveal>
        <div><p class="section-kicker">داستان شکل‌گیری ما</p><h2>از یک ایده تا همراهیِ ماندگار</h2></div>
        <p>هر همکاری با شناخت درست مسئله آغاز می‌شود؛ و هر راه‌حل خوب، به تجربه، گفت‌وگو و نگاه چندرشته‌ای نیاز دارد.</p>
    </div>
    <div class="story-long" data-reveal>
        <p><?= nl2br(e(site_setting('about_story'))) ?></p>
        <p><?= nl2br(e(site_setting('home_story'))) ?></p>
    </div>
</section>

<section class="values-wrap">
    <div class="container inner-section">
        <div class="page-section-heading" data-reveal>
            <div><p class="section-kicker">ارزش‌های محوری ما</p><h2>اصولی که مسیرمان را روشن می‌کنند</h2></div>
            <p>این ارزش‌ها در نحوه همکاری، کیفیت تصمیم‌ها و مسئولیت‌پذیری ما در برابر هر پروژه جاری هستند.</p>
        </div>
        <div class="value-grid">
            <?php foreach ($values as $i => $value): ?>
                <article class="value-card" data-reveal>
                    <span>اصل ۰<?= e((string) ($i + 1)) ?></span>
                    <h3><?= e($value['title']) ?></h3>
                    <p><?= e($value['text']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="container inner-section">
    <div class="page-section-heading" data-reveal>
        <div><p class="section-kicker">رهبری و راهبری</p><h2>هیئت‌مدیره</h2></div>
        <p>آشنایی با تجربه و دیدگاه مدیرانی که مسیر شرکت را همراهی می‌کنند.</p>
    </div>
    <?php if ($boardMembers): ?>
        <div class="board-grid">
            <?php foreach ($boardMembers as $member): ?>
                <article class="board-card" data-reveal>
                    <span class="member-monogram" aria-hidden="true">ث</span>
                    <div>
                        <h3><?= e($member['title']) ?></h3>
                        <span class="member-role"><?= e($member['subtitle']) ?></span>
                        <?php if ($member['excerpt'] !== ''): ?><p><?= e($member['excerpt']) ?></p><?php endif; ?>
                        <?php if ($member['body'] !== '' && $member['body'] !== $member['excerpt']): ?><p><?= e($member['body']) ?></p><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="team-empty">نام اعضای هیئت‌مدیره، سمت و خلاصه رزومه آن‌ها هنوز در پنل مدیریت ثبت نشده است. پس از افزودن اطلاعات، کارت‌های معرفی در این بخش نمایش داده می‌شوند.</div>
    <?php endif; ?>
</section>

<section class="values-wrap">
    <div class="container inner-section">
        <div class="page-section-heading" data-reveal>
            <div><p class="section-kicker">همراهان افق دانش ثریا</p><h2>اعضای تیم</h2></div>
            <p>تخصص‌های مکمل، گفت‌وگوی باز و همکاری حرفه‌ای، سرمایه اصلی تیم ماست.</p>
        </div>
        <?php if ($teamMembers): ?>
            <ul class="team-list">
                <?php foreach ($teamMembers as $member): ?>
                    <li data-reveal><span><?= e($member['title']) ?></span><span><?= e($member['subtitle']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="team-empty">اسامی سایر اعضای شرکت پس از ثبت در پنل مدیریت در این بخش نمایش داده می‌شود. نام همکاران به‌صورت ساده و غیر برجسته ارائه خواهد شد.</div>
        <?php endif; ?>
    </div>
</section>

<section class="contact-cta">
    <div class="container contact-cta-inner">
        <div><h2>به دنبال همراهی برای یک پروژه تازه هستید؟</h2><p>با تیم ما درباره نیازها و هدف‌های پروژه‌تان صحبت کنید.</p></div>
        <a class="button button-light" href="contact.php">تماس با ما <span aria-hidden="true">↖</span></a>
    </div>
</section>
<?php require __DIR__ . '/app/site-footer.php'; ?>
