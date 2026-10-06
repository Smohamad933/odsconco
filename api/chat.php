<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_api_user();
$me = (int) $user['id'];

function active_chat_peer(int $peerId, int $me): bool
{
    if ($peerId < 1 || $peerId === $me) return false;
    $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE id=:id AND is_active=1');
    $stmt->execute([':id' => $peerId]);
    return (int) $stmt->fetchColumn() === 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = (string) ($_GET['action'] ?? '');
    if ($action === 'users') {
        $stmt = db()->prepare('SELECT id,full_name,role FROM users WHERE is_active=1 AND id<>:me ORDER BY full_name COLLATE NOCASE');
        $stmt->execute([':me' => $me]);
        json_response(['ok' => true, 'users' => $stmt->fetchAll()]);
    }
    $peer = (int) ($_GET['peer'] ?? 0);
    if (!active_chat_peer($peer, $me)) json_response(['ok' => false, 'error' => 'گفت‌وگوی مورد نظر در دسترس نیست.'], 404);
    $after = max(0, (int) ($_GET['after'] ?? 0));
    if ($action === 'messages') {
        if ($after > 0) {
            $stmt = db()->prepare('SELECT id,sender_id,recipient_id,body,attachment_name,attachment_size,attachment_type,created_at FROM chat_messages WHERE id>:after AND ((sender_id=:me1 AND recipient_id=:peer1) OR (sender_id=:peer2 AND recipient_id=:me2)) ORDER BY id ASC LIMIT 100');
            $stmt->execute([':after' => $after, ':me1' => $me, ':peer1' => $peer, ':peer2' => $peer, ':me2' => $me]);
            $messages = $stmt->fetchAll();
        } else {
            $stmt = db()->prepare('SELECT id,sender_id,recipient_id,body,attachment_name,attachment_size,attachment_type,created_at FROM chat_messages WHERE (sender_id=:me1 AND recipient_id=:peer1) OR (sender_id=:peer2 AND recipient_id=:me2) ORDER BY id DESC LIMIT 100');
            $stmt->execute([':me1' => $me, ':peer1' => $peer, ':peer2' => $peer, ':me2' => $me]);
            $messages = array_reverse($stmt->fetchAll());
        }
        json_response(['ok' => true, 'messages' => $messages]);
    }
    if ($action === 'signals') {
        $stmt = db()->prepare('SELECT id,message_id,signal_type,payload,created_at FROM chat_signals WHERE id>:after AND sender_id=:peer AND recipient_id=:me ORDER BY id ASC LIMIT 100');
        $stmt->execute([':after' => $after, ':peer' => $peer, ':me' => $me]);
        $signals = $stmt->fetchAll();
        foreach ($signals as &$signal) {
            $signal['payload'] = json_decode((string) $signal['payload'], true) ?: [];
        }
        unset($signal);
        json_response(['ok' => true, 'signals' => $signals]);
    }
    json_response(['ok' => false, 'error' => 'عملیات ناشناخته است.'], 400);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['ok' => false, 'error' => 'روش درخواست پشتیبانی نمی‌شود.'], 405);
if (!verify_csrf()) json_response(['ok' => false, 'error' => 'درخواست منقضی شده است؛ صفحه را تازه‌سازی کنید.'], 419);
$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) json_response(['ok' => false, 'error' => 'بدنه درخواست معتبر نیست.'], 400);
$action = (string) ($payload['action'] ?? '');
$peer = (int) ($payload['peer'] ?? 0);
if (!active_chat_peer($peer, $me)) json_response(['ok' => false, 'error' => 'حساب مقصد فعال نیست.'], 404);

if ($action === 'send') {
    $body = trim((string) ($payload['body'] ?? ''));
    $fileName = trim((string) ($payload['attachment_name'] ?? ''));
    $fileSize = (int) ($payload['attachment_size'] ?? 0);
    $fileType = trim((string) ($payload['attachment_type'] ?? ''));
    if (strlen($body) > 20000) json_response(['ok' => false, 'error' => 'متن پیام بیش از اندازه طولانی است.'], 422);
    if ($fileName !== '') {
        $fileName = str_replace(['\\', '/'], '_', $fileName);
        $fileName = preg_replace('/[\x00-\x1F\x7F]/u', '', $fileName) ?? '';
        if (strlen($fileName) > 500 || $fileSize < 1 || $fileSize > 10 * 1024 * 1024 * 1024 || strlen($fileType) > 150) {
            json_response(['ok' => false, 'error' => 'مشخصات فایل معتبر نیست یا فایل بیش از حد بزرگ است.'], 422);
        }
    } elseif ($fileSize !== 0) {
        json_response(['ok' => false, 'error' => 'مشخصات پیوست ناقص است.'], 422);
    }
    if ($body === '' && $fileName === '') json_response(['ok' => false, 'error' => 'پیام متنی یا فایل انتخاب کنید.'], 422);
    $stmt = db()->prepare('INSERT INTO chat_messages(sender_id,recipient_id,body,attachment_name,attachment_size,attachment_type,created_at) VALUES(:sender,:recipient,:body,:name,:size,:mime,:created)');
    $stmt->execute([':sender' => $me, ':recipient' => $peer, ':body' => $body, ':name' => $fileName, ':size' => $fileSize, ':mime' => $fileType, ':created' => now_text()]);
    json_response(['ok' => true, 'id' => (int) db()->lastInsertId(), 'message' => 'پیام ثبت شد.']);
}

if ($action === 'signal') {
    $messageId = (int) ($payload['message_id'] ?? 0);
    $signalType = (string) ($payload['signal_type'] ?? '');
    $signalData = $payload['payload'] ?? [];
    if (!in_array($signalType, ['file-request', 'file-offer', 'file-answer', 'file-cancel'], true) || !is_array($signalData) || $messageId < 1) {
        json_response(['ok' => false, 'error' => 'سیگنال انتقال فایل معتبر نیست.'], 422);
    }
    $stmt = db()->prepare('SELECT sender_id,recipient_id,attachment_name,attachment_size FROM chat_messages WHERE id=:id LIMIT 1');
    $stmt->execute([':id' => $messageId]);
    $message = $stmt->fetch();
    if (!$message || $message['attachment_name'] === '' || ((int) $message['sender_id'] !== $me && (int) $message['recipient_id'] !== $me) || ((int) $message['sender_id'] !== $peer && (int) $message['recipient_id'] !== $peer)) {
        json_response(['ok' => false, 'error' => 'این فایل به گفت‌وگوی انتخاب‌شده تعلق ندارد.'], 403);
    }
    if ($signalType === 'file-request' && !((int) $message['sender_id'] === $peer && (int) $message['recipient_id'] === $me)) {
        json_response(['ok' => false, 'error' => 'درخواست دریافت این فایل مجاز نیست.'], 403);
    }
    if (in_array($signalType, ['file-offer', 'file-cancel'], true) && !((int) $message['sender_id'] === $me && (int) $message['recipient_id'] === $peer)) {
        json_response(['ok' => false, 'error' => 'ارسال سیگنال این فایل مجاز نیست.'], 403);
    }
    if ($signalType === 'file-answer' && !((int) $message['sender_id'] === $peer && (int) $message['recipient_id'] === $me)) {
        json_response(['ok' => false, 'error' => 'پاسخ به انتقال این فایل مجاز نیست.'], 403);
    }
    $encoded = json_encode($signalData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || strlen($encoded) > 90000) json_response(['ok' => false, 'error' => 'داده سیگنال بیش از اندازه بزرگ است.'], 413);
    $insert = db()->prepare('INSERT INTO chat_signals(sender_id,recipient_id,message_id,signal_type,payload,created_at) VALUES(:sender,:recipient,:message,:type,:payload,:created)');
    $insert->execute([':sender' => $me, ':recipient' => $peer, ':message' => $messageId, ':type' => $signalType, ':payload' => $encoded, ':created' => now_text()]);
    json_response(['ok' => true]);
}

json_response(['ok' => false, 'error' => 'عملیات ناشناخته است.'], 400);
