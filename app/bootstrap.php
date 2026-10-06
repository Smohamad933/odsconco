<?php
declare(strict_types=1);

/* Shared application bootstrap. Requires PHP 8.1+, PDO_SQLite and a writable storage/ directory. */
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

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = getenv('APP_DB_PATH') ?: STORAGE_ROOT . '/odsconco.sqlite';
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    install_schema($pdo);
    return $pdo;
}

function install_schema(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'employee',
    is_active INTEGER NOT NULL DEFAULT 1,
    must_change_password INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key TEXT PRIMARY KEY,
    setting_value TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS content_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL,
    slug TEXT NOT NULL DEFAULT '',
    title TEXT NOT NULL,
    subtitle TEXT NOT NULL DEFAULT '',
    excerpt TEXT NOT NULL DEFAULT '',
    body TEXT NOT NULL DEFAULT '',
    category TEXT NOT NULL DEFAULT '',
    image_url TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_published INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_content_type_published_order ON content_items(type, is_published, sort_order, id);
CREATE TABLE IF NOT EXISTS contact_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    phone TEXT NOT NULL,
    email TEXT NOT NULL DEFAULT '',
    topic TEXT NOT NULL DEFAULT '',
    message TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'new',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_contact_status ON contact_requests(status, created_at);
CREATE TABLE IF NOT EXISTS attendance_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    event_type TEXT NOT NULL,
    method TEXT NOT NULL,
    occurred_at TEXT NOT NULL,
    latitude REAL,
    longitude REAL,
    accuracy REAL,
    challenge_id INTEGER,
    status TEXT NOT NULL DEFAULT 'pending',
    reviewed_by INTEGER,
    reviewed_at TEXT,
    review_note TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY(challenge_id) REFERENCES attendance_challenges(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_attendance_user_time ON attendance_requests(user_id, occurred_at DESC);
CREATE INDEX IF NOT EXISTS idx_attendance_status ON attendance_requests(status, occurred_at DESC);
CREATE TABLE IF NOT EXISTS attendance_challenges (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_hash TEXT NOT NULL UNIQUE,
    created_by INTEGER NOT NULL,
    event_type TEXT NOT NULL DEFAULT 'check_in',
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS attendance_qr_uses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    challenge_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    used_at TEXT NOT NULL,
    UNIQUE(challenge_id, user_id),
    FOREIGN KEY(challenge_id) REFERENCES attendance_challenges(id) ON DELETE CASCADE,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS workflow_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    category TEXT NOT NULL DEFAULT '',
    description TEXT NOT NULL,
    priority TEXT NOT NULL DEFAULT 'normal',
    status TEXT NOT NULL DEFAULT 'submitted',
    creator_id INTEGER NOT NULL,
    assignee_id INTEGER,
    due_date TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY(creator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(assignee_id) REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_workflow_creator ON workflow_items(creator_id, id DESC);
CREATE INDEX IF NOT EXISTS idx_workflow_assignee ON workflow_items(assignee_id, status, id DESC);
CREATE TABLE IF NOT EXISTS workflow_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    workflow_id INTEGER NOT NULL,
    actor_id INTEGER NOT NULL,
    event_type TEXT NOT NULL,
    from_status TEXT NOT NULL DEFAULT '',
    to_status TEXT NOT NULL DEFAULT '',
    note TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL,
    FOREIGN KEY(workflow_id) REFERENCES workflow_items(id) ON DELETE CASCADE,
    FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_workflow_events ON workflow_events(workflow_id, id ASC);
CREATE TABLE IF NOT EXISTS chat_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sender_id INTEGER NOT NULL,
    recipient_id INTEGER NOT NULL,
    body TEXT NOT NULL DEFAULT '',
    attachment_name TEXT NOT NULL DEFAULT '',
    attachment_size INTEGER NOT NULL DEFAULT 0,
    attachment_type TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL,
    FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(recipient_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_chat_pair ON chat_messages(sender_id, recipient_id, id);
CREATE TABLE IF NOT EXISTS chat_signals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    sender_id INTEGER NOT NULL,
    recipient_id INTEGER NOT NULL,
    message_id INTEGER NOT NULL,
    signal_type TEXT NOT NULL,
    payload TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    FOREIGN KEY(sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(message_id) REFERENCES chat_messages(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_chat_signals_receiver ON chat_signals(recipient_id, id);
SQL);

    // Lightweight migrations keep an existing SQLite file compatible with additive schema changes.
    foreach ([
        'users' => ['must_change_password' => 'INTEGER NOT NULL DEFAULT 0'],
        'attendance_challenges' => ['event_type' => "TEXT NOT NULL DEFAULT 'check_in'"],
    ] as $table => $columns) {
        $existingColumns = array_column($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(), 'name');
        foreach ($columns as $column => $definition) {
            if (!in_array($column, $existingColumns, true)) {
                $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
    }

    $defaults = [
        'site_name' => 'شرکت مشاوران افق دانش ثریا',
        'hero_label' => 'مشاوران مهندسی؛ همراه توسعه‌ای پایدار',
        'hero_title' => "مهندسیِ دقیق،\nبرای افق‌های روشن.",
        'hero_subtitle' => 'از نخستین ایده تا تصمیم‌های اجرایی، در کنار شما هستیم تا مسیر پروژه‌ها روشن‌تر، سنجیده‌تر و اثربخش‌تر باشد.',
        'hero_button' => 'گفت‌وگو با کارشناسان',
        'home_story' => 'در افق دانش ثریا، هر پروژه با شنیدن دقیق نیاز کارفرما آغاز می‌شود. ما دانش مهندسی را با نگاهی یکپارچه و مسئولانه همراه می‌کنیم تا تصمیم‌های امروز، پایه‌ای مطمئن برای فردا باشند.',
        'about_story' => 'افق دانش ثریا با این باور شکل گرفت که راه‌حل مهندسی زمانی ارزشمند است که در کنار دقت فنی، به نیاز واقعی انسان‌ها و آینده پروژه نیز پاسخ دهد. این نگاه، پایه همکاری ما با کارفرمایان و هم‌تیمی‌های تخصصی است.',
        'services_intro' => 'از شناخت مسئله و مطالعات اولیه تا طراحی، مدیریت و نظارت؛ خدمات ما متناسب با نیاز هر پروژه و در تعامل نزدیک با کارفرما تعریف می‌شود.',
        'value_1_title' => 'دقت و کیفیت',
        'value_1_text' => 'پایبندی به جزئیات، استانداردهای حرفه‌ای و تصمیم‌گیری مبتنی بر دانش.',
        'value_2_title' => 'مسئولیت‌پذیری',
        'value_2_text' => 'پاسخ‌گویی شفاف و توجه به اثر هر تصمیم در تمام چرخه پروژه.',
        'value_3_title' => 'همکاری',
        'value_3_text' => 'هم‌فکری با کارفرما و متخصصان برای رسیدن به راه‌حل‌های عملی.',
        'value_4_title' => 'نگاه آینده‌نگر',
        'value_4_text' => 'توجه به پایداری، تاب‌آوری و ارزش بلندمدت در انتخاب راهکارها.',
        'office_hours' => 'ساعات اداری شرکت را وارد کنید',
        'contact_phone_display' => 'شماره تماس شرکت (برای تکمیل)',
        'contact_phone_href' => '',
        'contact_email_display' => 'ایمیل شرکت (برای تکمیل)',
        'contact_email' => '',
        'contact_address' => 'نشانی دفتر شرکت را وارد کنید',
        'bale_display' => 'شناسه بله (برای تکمیل)',
        'bale_url' => '',
        'telegram_display' => 'شناسه تلگرام (برای تکمیل)',
        'telegram_url' => '',
    ];
    $insertSetting = $pdo->prepare('INSERT OR IGNORE INTO site_settings(setting_key, setting_value) VALUES(:key, :value)');
    foreach ($defaults as $key => $value) {
        $insertSetting->execute([':key' => $key, ':value' => $value]);
    }

    $seedMarker = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key = '_default_services_seeded'")->fetchColumn();
    if ($seedMarker !== '1') {
        $now = date('Y-m-d H:i:s');
        $services = [
            ['مطالعات و مشاوره مهندسی', 'شناخت دقیق نیازها و ارزیابی گزینه‌ها', 'تهیه مطالعات اولیه، امکان‌سنجی و ارائه مسیرهای اجرایی متناسب با اهداف پروژه.', 'مطالعات'],
            ['طراحی و اسناد فنی', 'از ایده تا نقشه‌های قابل اجرا', 'تدوین راهکارهای طراحی و اسناد فنی با توجه به الزامات پروژه و هماهنگی رشته‌های تخصصی.', 'طراحی'],
            ['مدیریت و کنترل پروژه', 'برنامه‌ریزی، هماهنگی و پایش', 'پشتیبانی از مدیریت زمان، هزینه، ریسک و هماهنگی ذی‌نفعان در مسیر اجرای پروژه.', 'مدیریت'],
            ['نظارت و ارزیابی فنی', 'کیفیت، ایمنی و انطباق', 'ارزیابی روند اجرا و ارائه گزارش‌های فنی برای کمک به تحقق اهداف و الزامات قرارداد.', 'نظارت'],
        ];
        $insertService = $pdo->prepare('INSERT INTO content_items(type, slug, title, subtitle, excerpt, body, category, sort_order, is_published, created_at, updated_at) VALUES(\'services\', :slug, :title, :subtitle, :excerpt, :body, :category, :sort, 1, :created, :updated)');
        foreach ($services as $index => $service) {
            $insertService->execute([
                ':slug' => 'service-' . ($index + 1),
                ':title' => $service[0],
                ':subtitle' => $service[1],
                ':excerpt' => $service[2],
                ':body' => $service[2],
                ':category' => $service[3],
                ':sort' => $index + 1,
                ':created' => $now,
                ':updated' => $now,
            ]);
        }
        $markSeeded = $pdo->prepare('INSERT INTO site_settings(setting_key, setting_value) VALUES(:key, :value) ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value');
        $markSeeded->execute([':key' => '_default_services_seeded', ':value' => '1']);
    }
}

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
    $stmt = db()->prepare('INSERT INTO site_settings(setting_key, setting_value) VALUES(:key, :value) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value');
    $stmt->execute([':key' => $key, ':value' => $value]);
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

// Ensure the database schema and editable default copy are available on every request.
db();
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(self), camera=(), microphone=()');
