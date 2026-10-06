<?php
$portalRoot = $portalRoot ?? '';
$portalTitle = $portalTitle ?? 'سامانه داخلی';
$activePortal = $activePortal ?? '';
$portalUser = current_user();
$portalRoleNames = ['admin' => 'مدیر سامانه', 'manager' => 'مدیر', 'employee' => 'همکار'];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f1eb">
    <title><?= e($portalTitle) ?> | <?= e(site_setting('site_name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e($portalRoot) ?>assets/css/portal.css">
    <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body class="portal-body">
<header class="portal-header">
    <div class="portal-header-inner">
        <a class="portal-brand" href="<?= e($portalRoot) ?>portal.php">
            <span class="portal-brand-mark" aria-hidden="true">✳</span>
            <span><strong>افق دانش ثریا</strong><small>سامانه داخلی شرکت</small></span>
        </a>
        <nav class="portal-nav" aria-label="ناوبری سامانه">
            <a href="<?= e($portalRoot) ?>portal.php" <?= $activePortal === 'dashboard' ? 'aria-current="page"' : '' ?>>پیشخوان</a>
            <a href="<?= e($portalRoot) ?>chat.php" <?= $activePortal === 'chat' ? 'aria-current="page"' : '' ?>>پیام‌رسان</a>
            <a href="<?= e($portalRoot) ?>attendance.php" <?= $activePortal === 'attendance' ? 'aria-current="page"' : '' ?>>حضور و غیاب</a>
            <a href="<?= e($portalRoot) ?>automation.php" <?= $activePortal === 'automation' ? 'aria-current="page"' : '' ?>>اتوماسیون</a>
            <?php if (is_manager($portalUser)): ?><a href="<?= e($portalRoot) ?>admin/attendance.php" <?= $activePortal === 'attendance-review' ? 'aria-current="page"' : '' ?>>تأیید حضورها</a><?php endif; ?>
            <?php if (($portalUser['role'] ?? '') === 'admin'): ?><a href="<?= e($portalRoot) ?>admin/index.php" <?= $activePortal === 'admin' ? 'aria-current="page"' : '' ?>>مدیریت محتوا</a><?php endif; ?>
        </nav>
        <div class="portal-user">
            <a class="portal-account-link" href="<?= e($portalRoot) ?>account.php" title="تنظیمات حساب"><span class="portal-avatar" aria-hidden="true">✳</span><span class="portal-user-copy"><strong><?= e($portalUser['full_name'] ?? '') ?></strong><small><?= e($portalRoleNames[$portalUser['role'] ?? ''] ?? 'کاربر') ?></small></span></a>
            <form action="<?= e($portalRoot) ?>logout.php" method="post"><?= csrf_field() ?><button class="portal-logout" type="submit" aria-label="خروج">خروج</button></form>
        </div>
    </div>
</header>
<main class="portal-main">
    <div class="portal-container">
