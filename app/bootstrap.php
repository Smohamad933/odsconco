<?php
declare(strict_types=1);

/* Shared application bootstrap. Requires PHP 8.1+, PDO_MYSQL and a writable storage/ directory. */
error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Tehran');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');

$https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('odsconco_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const APP_ROOT = __DIR__ . '/..';
const STORAGE_ROOT = APP_ROOT . '/storage';
if (!is_dir(STORAGE_ROOT) && !mkdir(STORAGE_ROOT, 0770, true) && !is_dir(STORAGE_ROOT)) {
    throw new RuntimeException('The storage directory could not be created.');
}

require_once __DIR__ . '/database.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now_text(): string
{
    return date('Y-m-d H:i:s');
}

function safe_local_return(string $target, string $fallback = 'portal.php'): string
{
    $target = trim($target);
    if ($target === '' || str_contains($target, '..') || str_starts_with($target, '//') || preg_match('~[^A-Za-z0-9_/.%?=&+-]~', $target)) {
        return $fallback;
    }
    return $target;
}

function site_setting(string $key, string $fallback = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = db()->prepare('SELECT setting_value FROM site_settings WHERE setting_key = :key');
    $stmt->execute([':key' => $key]);
    $value = $stmt->fetchColumn();
    $cache[$key] = ($value === false) ? $fallback : (string) $value;
    return $cache[$key];
}

function save_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO site_settings(setting_key, setting_value) VALUES(:key, :value) ON DUPLICATE KEY UPDATE setting_value = :updated_value');
    $stmt->execute([':key' => $key, ':value' => $value, ':updated_value' => $value]);
}

function content_items(string $type, array $filters = [], ?int $limit = null, bool $publishedOnly = true): array
{
    $allowedTypes = ['projects', 'articles', 'team', 'services', 'clients'];
    if (!in_array($type, $allowedTypes, true)) {
        return [];
    }
    $where = ['type = :type'];
    $params = [':type' => $type];
    if ($publishedOnly) {
        $where[] = 'is_published = 1';
    }
    if (isset($filters['category']) && $filters['category'] !== '') {
        $where[] = 'category = :category';
        $params[':category'] = (string) $filters['category'];
    }
    $sql = 'SELECT * FROM content_items WHERE ' . implode(' AND ', $where) . ' ORDER BY sort_order ASC, id DESC';
    if ($limit !== null) {
        $sql .= ' LIMIT ' . max(1, min(100, $limit));
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_content_item(int $id, string $type): ?array
{
    $stmt = db()->prepare('SELECT * FROM content_items WHERE id = :id AND type = :type LIMIT 1');
    $stmt->execute([':id' => $id, ':type' => $type]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function safe_image_url(string $value): string
{
    $value = trim($value);
    if (preg_match('~^(https://|assets/|/assets/)~i', $value)) {
        return $value;
    }
    return '';
}

function current_user(): ?array
{
    if (!isset($_SESSION['user']['id'])) {
        return null;
    }
    $stmt = db()->prepare('SELECT id, full_name, email, role, is_active, must_change_password FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $_SESSION['user']['id']]);
    $fresh = $stmt->fetch();
    if (!$fresh || (int) $fresh['is_active'] !== 1) {
        unset($_SESSION['user']);
        return null;
    }
    $_SESSION['user'] = [
        'id' => (int) $fresh['id'],
        'full_name' => (string) $fresh['full_name'],
        'email' => (string) $fresh['email'],
        'role' => (string) $fresh['role'],
        'must_change_password' => (int) $fresh['must_change_password'],
    ];
    return $_SESSION['user'];
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $return = $_SERVER['REQUEST_URI'] ?? 'portal.php';
        $isAdminPath = str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/');
        header('Location: ' . ($isAdminPath ? '../login.php' : 'login.php') . '?return=' . rawurlencode($return));
        exit;
    }
    if (!empty($user['must_change_password']) && basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'account.php' && basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) !== 'logout.php') {
        $isAdminPath = str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/');
        header('Location: ' . ($isAdminPath ? '../account.php' : 'account.php'));
        exit;
    }
    return $user;
}

function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'] ?? '', $roles, true)) {
        http_response_code(403);
        $portalTitle = 'دسترسی مجاز نیست';
        $portalRoot = str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/') ? '../' : '';
        require APP_ROOT . '/app/portal-header.php';
        echo '<section class="portal-card"><h1>دسترسی به این بخش برای شما فعال نیست.</h1><p>اگر به این صفحه نیاز دارید، با مدیر سامانه تماس بگیرید.</p><a class="portal-button portal-button-primary" href="' . $portalRoot . 'portal.php">بازگشت به پنل</a></section>';
        require APP_ROOT . '/app/portal-footer.php';
        exit;
    }
    return $user;
}

function is_manager(?array $user = null): bool
{
    $user ??= current_user();
    return $user !== null && in_array($user['role'] ?? '', ['admin', 'manager'], true);
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $provided = null): bool
{
    $provided ??= (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return $provided !== '' && !empty($_SESSION['_csrf']) && hash_equals((string) $_SESSION['_csrf'], $provided);
}

function require_csrf(): void
{
    if (!verify_csrf()) {
        http_response_code(419);
        exit('درخواست منقضی شده است. صفحه را تازه‌سازی و دوباره تلاش کنید.');
    }
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_api_user(): array
{
    $user = current_user();
    if (!$user) {
        json_response(['ok' => false, 'error' => 'برای ادامه وارد حساب کاربری شوید.'], 401);
    }
    if (!empty($user['must_change_password'])) {
        json_response(['ok' => false, 'error' => 'پیش از استفاده، گذرواژه حساب را تغییر دهید.'], 403);
    }
    return $user;
}

function app_base_url(): string
{
    $configured = trim((string) getenv('APP_BASE_URL'));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $host = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
    if ($host === '' || in_array($host, ['0.0.0.0', '::', '[::]'], true)) {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
        $host = 'localhost';
    }
    $scheme = $GLOBALS['https'] ? 'https' : 'http';
    $port = (int) ($_SERVER['SERVER_PORT'] ?? 0);
    if ($port > 0 && !(($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) && !str_contains($host, ':')) {
        $host .= ':' . $port;
    }
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
    $basePath = rtrim(dirname($scriptName), '/.');
    // API scripts live in /api; remove that folder when deriving the application base path.
    if (str_ends_with($basePath, '/api')) {
        $basePath = substr($basePath, 0, -4);
    }
    return $scheme . '://' . $host . ($basePath !== '' ? $basePath : '');
}

function flash_set(string $message, string $type = 'success'): void
{
    $_SESSION['_flash'] = ['message' => $message, 'type' => $type];
}

function flash_get(): ?array
{
    $value = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return is_array($value) ? $value : null;
}

function format_local_time(string $value): string
{
    try {
        return (new DateTimeImmutable($value))->format('Y/m/d  H:i');
    } catch (Throwable) {
        return $value;
    }
}

// Route an unconfigured site to its one-time installer; normal requests check MySQL connectivity.
if (!defined('APP_INSTALLER_REQUEST')) {
    if (!database_configured()) {
        header('Location: setup.php');
        exit;
    }
    try {
        $connection = db();
        try {
            $hasUsers = $connection->query('SELECT 1 FROM users LIMIT 1')->fetchColumn() !== false;
            if (!$hasUsers) {
                header('Location: setup.php');
                exit;
            }
        } catch (PDOException $exception) {
            if ((int) ($exception->errorInfo[1] ?? 0) === 1146) {
                header('Location: setup.php');
                exit;
            }
            throw $exception;
        }
    } catch (Throwable $exception) {
        error_log('Application database initialization failed: ' . $exception->getMessage());
        http_response_code(503);
        exit('پایگاه‌داده MySQL در دسترس نیست. تنظیمات اتصال و فعال بودن PDO MySQL را بررسی کنید.');
    }
}
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(self), camera=(), microphone=()');
