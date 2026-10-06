<?php
require_once __DIR__ . '/app/bootstrap.php';
$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM content_items WHERE id=:id AND type='articles' AND is_published=1 LIMIT 1");
$stmt->execute([':id' => $id]);
$article = $stmt->fetch();
if (!$article) http_response_code(404);
$activePage = '';
$pageTitle = $article ? (string) $article['title'] : 'مقاله پیدا نشد';
$metaDescription = $article ? (string) $article['excerpt'] : 'مقاله مورد نظر در دسترس نیست.';
require __DIR__ . '/app/site-header.php';
?>
<section class="page-hero">
    <div class="container page-hero-content" data-reveal>
        <p class="eyebrow"><?= e($article['category'] ?? 'دانش و دیدگاه') ?></p>
        <h1><?= e($article['title'] ?? 'این مقاله پیدا نشد') ?></h1>
        <?php if ($article && $article['subtitle'] !== ''): ?><p><?= e($article['subtitle']) ?></p><?php endif; ?>
        <div class="breadcrumb"><a href="index.php">خانه</a><span>／</span><a href="index.php#articles">مقالات</a><span>／</span><?= e($article ? $article['title'] : 'یافت نشد') ?></div>
    </div>
</section>
<section class="container inner-section">
    <?php if ($article): ?>
        <?php if ($article['excerpt'] !== ''): ?><p class="article-detail-lead" data-reveal><?= e($article['excerpt']) ?></p><?php endif; ?>
        <div class="article-detail-body" data-reveal><?= nl2br(e($article['body'] ?: $article['excerpt'])) ?></div>
        <a class="text-link article-back-link" href="index.php#articles">بازگشت به مقالات <span aria-hidden="true">←</span></a>
    <?php else: ?>
        <div class="team-empty">مقاله مورد نظر منتشر نشده یا در دسترس نیست. <a href="index.php#articles">بازگشت به صفحه اصلی</a></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/app/site-footer.php'; ?>
