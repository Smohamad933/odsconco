<?php
$activePage = $activePage ?? '';
$pageTitle = $pageTitle ?? site_setting('site_name');
$metaDescription = $metaDescription ?? 'شرکت مشاوران افق دانش ثریا؛ همراهی تخصصی در مسیر مطالعه، طراحی و اجرای پروژه‌های مهندسی.';
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e($metaDescription) ?>">
    <meta name="theme-color" content="#f5f5f7">
    <title><?= e($pageTitle) ?> | <?= e(site_setting('site_name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/site.css">
    <script defer src="assets/js/site.js"></script>
</head>
<body class="site-body">
<a class="skip-link" href="#main-content">رفتن به محتوای اصلی</a>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="index.php" aria-label="صفحه اصلی شرکت مشاوران افق دانش ثریا">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 48 48" fill="none">
                    <path d="M5 34.5 18.8 15l6.7 9.4 5.1-7.1L43 34.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M8 39h32M15.1 29.8h17.7M24 8v8M20 12h8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>
                    <circle cx="24" cy="12" r="2" fill="currentColor"/>
                </svg>
            </span>
            <span class="brand-copy">
                <strong>افق دانش ثریا</strong>
                <small>مشاوران مهندسی</small>
            </span>
        </a>

        <button class="menu-toggle" type="button" aria-label="باز کردن منو" aria-expanded="false" aria-controls="main-navigation">
            <span></span><span></span><span></span>
        </button>

        <nav class="main-nav" id="main-navigation" aria-label="ناوبری اصلی">
            <a href="index.php" <?= $activePage === 'home' ? 'aria-current="page"' : '' ?>>خانه</a>
            <a href="about.php" <?= $activePage === 'about' ? 'aria-current="page"' : '' ?>>درباره ما</a>
            <a href="projects.php" <?= $activePage === 'projects' ? 'aria-current="page"' : '' ?>>پروژه‌ها</a>
            <a href="services.php" <?= $activePage === 'services' ? 'aria-current="page"' : '' ?>>خدمات</a>
            <a href="contact.php" <?= $activePage === 'contact' ? 'aria-current="page"' : '' ?>>تماس با ما</a>
        </nav>

        <a class="button button-small header-action" href="contact.php">
            <span>درخواست مشاوره</span>
            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M10 5l5 5-5 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
    </div>
</header>
<main id="main-content">
