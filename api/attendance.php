<?php
require_once __DIR__ . '/../app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok' => false, 'error' => 'روش درخواست پشتیبانی نمی‌شود.'], 405);
$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) $payload = [];
if (!verify_csrf()) json_response(['ok' => false, 'error' => 'درخواست منقضی شده است؛ صفحه را تازه‌سازی کنید.'], 419);
$user = require_api_user();
$action = (string) ($payload['action'] ?? 'submit');

if ($action === 'qr-create') {
    if (!is_manager($user)) json_response(['ok' => false, 'error' => 'دسترسی کافی ندارید.'], 403);
    $qrEventType = (string) ($payload['event_type'] ?? 'check_in');
    if (!in_array($qrEventType, ['check_in', 'check_out'], true)) json_response(['ok' => false, 'error' => 'نوع ثبت QR معتبر نیست.'], 422);
    $rawToken = bin2hex(random_bytes(32));
    $created = new DateTimeImmutable('now');
    $expires = $created->modify('+90 seconds');
    $stmt = db()->prepare('INSERT INTO attendance_challenges(token_hash,created_by,event_type,expires_at,created_at) VALUES(:hash,:user,:event,:expires,:created)');
    $stmt->execute([
        ':hash' => hash('sha256', $rawToken),
        ':user' => $user['id'],
        ':event' => $qrEventType,
        ':expires' => $expires->format('Y-m-d H:i:s'),
        ':created' => $created->format('Y-m-d H:i:s'),
    ]);
    db()->exec("DELETE FROM attendance_challenges WHERE expires_at < datetime('now','-1 day')");
    $url = app_base_url() . '/attendance.php?qr=' . rawurlencode($rawToken) . '&event=' . rawurlencode($qrEventType);
    json_response(['ok' => true, 'url' => $url, 'expires_at' => $expires->format('Y-m-d H:i:s'), 'seconds' => 90]);
}

if ($action === 'review') {
    if (!is_manager($user)) json_response(['ok' => false, 'error' => 'دسترسی کافی ندارید.'], 403);
    $id = (int) ($payload['id'] ?? 0);
    $decision = (string) ($payload['decision'] ?? '');
    $note = trim((string) ($payload['note'] ?? ''));
    if ($id < 1 || !in_array($decision, ['approved', 'rejected'], true) || strlen($note) > 1000) {
        json_response(['ok' => false, 'error' => 'اطلاعات بازبینی معتبر نیست.'], 422);
    }
    $stmt = db()->prepare("UPDATE attendance_requests SET status=:status, reviewed_by=:reviewer, reviewed_at=:reviewed, review_note=:note WHERE id=:id AND status='pending'");
    $stmt->execute([':status' => $decision, ':reviewer' => $user['id'], ':reviewed' => now_text(), ':note' => $note, ':id' => $id]);
    if ($stmt->rowCount() === 0) json_response(['ok' => false, 'error' => 'این درخواست قبلاً بررسی شده یا وجود ندارد.'], 409);
    json_response(['ok' => true, 'message' => 'وضعیت درخواست ثبت شد.']);
}

if ($action !== 'submit') json_response(['ok' => false, 'error' => 'عملیات ناشناخته است.'], 400);
$eventType = (string) ($payload['event_type'] ?? '');
$method = (string) ($payload['method'] ?? '');
if (!in_array($eventType, ['check_in', 'check_out'], true)) json_response(['ok' => false, 'error' => 'نوع حضور معتبر نیست.'], 422);
if (!in_array($method, ['manual', 'qr', 'remote'], true)) json_response(['ok' => false, 'error' => 'روش ثبت معتبر نیست.'], 422);
$latitude = null;
$longitude = null;
$accuracy = null;
$challengeId = null;

if ($method === 'remote') {
    if (empty($payload['location_consent'])) json_response(['ok' => false, 'error' => 'برای ثبت دورکاری باید موقعیت مکانی را تأیید کنید.'], 422);
    $latitude = filter_var($payload['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $longitude = filter_var($payload['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $accuracy = filter_var($payload['accuracy'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($latitude === false || $longitude === false || $accuracy === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 || $accuracy < 0 || $accuracy > 5000) {
        json_response(['ok' => false, 'error' => 'مختصات معتبر نیست یا دقت موقعیت بسیار پایین است.'], 422);
    }
    // Reduce retained precision; this is an event location, not continuous tracking.
    $latitude = round((float) $latitude, 4);
    $longitude = round((float) $longitude, 4);
    $accuracy = round((float) $accuracy, 1);
}

if ($method === 'qr') {
    $token = strtolower(trim((string) ($payload['qr_token'] ?? '')));
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) json_response(['ok' => false, 'error' => 'کد QR معتبر نیست.'], 422);
    $stmt = db()->prepare('SELECT id,expires_at,event_type FROM attendance_challenges WHERE token_hash=:hash LIMIT 1');
    $stmt->execute([':hash' => hash('sha256', $token)]);
    $challenge = $stmt->fetch();
    if (!$challenge || strtotime((string) $challenge['expires_at']) < time()) json_response(['ok' => false, 'error' => 'کد QR منقضی شده است. از مدیر بخواهید کد تازه بسازد.'], 410);
    if (!in_array($challenge['event_type'], ['check_in', 'check_out'], true)) json_response(['ok' => false, 'error' => 'نوع ثبت این QR معتبر نیست.'], 422);
    $eventType = (string) $challenge['event_type'];
    try {
        $use = db()->prepare('INSERT INTO attendance_qr_uses(challenge_id,user_id,used_at) VALUES(:challenge,:user,:used)');
        $use->execute([':challenge' => $challenge['id'], ':user' => $user['id'], ':used' => now_text()]);
    } catch (PDOException) {
        json_response(['ok' => false, 'error' => 'این کد QR را قبلاً برای همین کاربر استفاده کرده‌اید.'], 409);
    }
    $challengeId = (int) $challenge['id'];
}

$insert = db()->prepare('INSERT INTO attendance_requests(user_id,event_type,method,occurred_at,latitude,longitude,accuracy,challenge_id,status,created_at) VALUES(:user,:event,:method,:occurred,:lat,:lng,:accuracy,:challenge,\'pending\',:created)');
$now = now_text();
$insert->execute([
    ':user' => $user['id'], ':event' => $eventType, ':method' => $method,
    ':occurred' => $now, ':lat' => $latitude, ':lng' => $longitude,
    ':accuracy' => $accuracy, ':challenge' => $challengeId, ':created' => $now,
]);
json_response(['ok' => true, 'message' => 'درخواست ثبت شد و برای تأیید مدیر ارسال شد.']);
