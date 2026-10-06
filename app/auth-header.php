<?php $pageTitle = $pageTitle ?? 'ورود'; ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f1eb">
    <title><?= e($pageTitle) ?> | <?= e(site_setting('site_name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/portal.css">
</head>
<body class="auth-body">
<main class="auth-wrap">
    <a class="auth-brand" href="index.php"><span class="auth-brand-mark">✳</span><span><strong>افق دانش ثریا</strong><small>سامانه داخلی شرکت</small></span></a>
