<?php
require_once __DIR__ . '/app/bootstrap.php';
$user = require_login();
$manager = is_manager($user);
$portalTitle = 'اتوماسیون داخلی';
$activePortal = 'automation';
$selectedId = (int) ($_GET['id'] ?? 0);
$error = '';
$statuses = [
    'submitted' => 'ثبت‌شده',
    'in_progress' => 'در حال انجام',
    'awaiting_review' => 'در انتظار تأیید',
    'returned' => 'بازگشت برای اصلاح',
    'completed' => 'تکمیل‌شده',
    'rejected' => 'ردشده',
];
$priorities = ['low' => 'عادی', 'normal' => 'معمولی', 'high' => 'فوری'];

$recordEvent = static function (PDO $pdo, int $workflowId, int $actorId, string $eventType, string $fromStatus, string $toStatus, string $note): void {
    $stmt = $pdo->prepare('INSERT INTO workflow_events(workflow_id,actor_id,event_type,from_status,to_status,note,created_at) VALUES(:workflow,:actor,:event,:from,:to,:note,:created)');
    $stmt->execute([
        ':workflow' => $workflowId,
        ':actor' => $actorId,
        ':event' => $eventType,
        ':from' => $fromStatus,
        ':to' => $toStatus,
        ':note' => $note,
        ':created' => now_text(),
    ]);
};

$validDueDate = static function (string $value) {
    if ($value === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : false;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $selectedId = (int) ($_POST['workflow_id'] ?? 0);
    $pdo = db();

    if ($action === 'create') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $priority = (string) ($_POST['priority'] ?? 'normal');
        $dueDateInput = trim((string) ($_POST['due_date'] ?? ''));
        $dueDate = $validDueDate($dueDateInput);
        if ($title === '' || strlen($title) > 300) {
            $error = 'عنوان درخواست را وارد کنید.';
        } elseif ($category === '' || strlen($category) > 100) {
            $error = 'دسته‌بندی درخواست را انتخاب کنید.';
        } elseif ($description === '' || strlen($description) > 12000) {
            $error = 'شرح درخواست را وارد کنید (حداکثر ۱۲ هزار نویسه).';
        } elseif (!isset($priorities[$priority])) {
            $error = 'اولویت درخواست معتبر نیست.';
        } elseif ($dueDate === false) {
            $error = 'تاریخ سررسید معتبر نیست.';
        } else {
            try {
                $pdo->beginTransaction();
                $now = now_text();
                $insert = $pdo->prepare('INSERT INTO workflow_items(title,category,description,priority,status,creator_id,assignee_id,due_date,created_at,updated_at) VALUES(:title,:category,:description,:priority,\'submitted\',:creator,NULL,:due,:created,:updated)');
                $insert->execute([
                    ':title' => $title,
                    ':category' => $category,
                    ':description' => $description,
                    ':priority' => $priority,
                    ':creator' => $user['id'],
                    ':due' => $dueDate,
                    ':created' => $now,
                    ':updated' => $now,
                ]);
                $selectedId = (int) $pdo->lastInsertId();
                $recordEvent($pdo, $selectedId, (int) $user['id'], 'created', '', 'submitted', 'درخواست برای بررسی و ارجاع ثبت شد.');
                $pdo->commit();
                flash_set('درخواست داخلی ثبت شد و در فهرست پیگیری قرار گرفت.');
                header('Location: automation.php?id=' . $selectedId);
                exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log($exception->getMessage());
                $error = 'ثبت درخواست انجام نشد. دوباره تلاش کنید.';
            }
        }
    } else {
        $lookup = $pdo->prepare('SELECT * FROM workflow_items WHERE id=:id LIMIT 1');
        $lookup->execute([':id' => $selectedId]);
        $item = $lookup->fetch();
        if (!$item) {
            $error = 'درخواست مورد نظر پیدا نشد.';
        } else {
            $isParticipant = (int) $item['creator_id'] === (int) $user['id'] || (int) ($item['assignee_id'] ?? 0) === (int) $user['id'];
            if (!$manager && !$isParticipant) {
                http_response_code(403);
                $error = 'به این درخواست دسترسی ندارید.';
            } else {
                $oldStatus = (string) $item['status'];
                $newStatus = $oldStatus;
                $eventType = '';
                $note = trim((string) ($_POST['note'] ?? ''));
                try {
                    $pdo->beginTransaction();
                    if (strlen($note) > 3000) throw new RuntimeException('یادداشت بیش از اندازه طولانی است.');
                    if ($action === 'comment') {
                        if ($note === '' || strlen($note) > 3000) {
                            throw new RuntimeException('یادداشت را وارد کنید (حداکثر ۳ هزار نویسه).');
                        }
                        $recordEvent($pdo, $selectedId, (int) $user['id'], 'comment', $oldStatus, $oldStatus, $note);
                        $update = $pdo->prepare('UPDATE workflow_items SET updated_at=:updated WHERE id=:id');
                        $update->execute([':updated' => now_text(), ':id' => $selectedId]);
                        $pdo->commit();
                        flash_set('یادداشت ثبت شد.');
                        header('Location: automation.php?id=' . $selectedId . '#workflow-history');
                        exit;
                    }

                    if ($action === 'assign') {
                        if (!$manager) throw new RuntimeException('فقط مدیر یا سرپرست می‌تواند کار را ارجاع دهد.');
                        if (!in_array($oldStatus, ['submitted', 'in_progress', 'returned'], true)) throw new RuntimeException('وضعیت فعلی امکان ارجاع مجدد ندارد.');
                        $assigneeId = (int) ($_POST['assignee_id'] ?? 0);
                        $dueDate = $validDueDate(trim((string) ($_POST['due_date'] ?? '')));
                        if ($assigneeId < 1) throw new RuntimeException('مسئول پیگیری را انتخاب کنید.');
                        if ($dueDate === false) throw new RuntimeException('تاریخ سررسید معتبر نیست.');
                        $person = $pdo->prepare('SELECT full_name FROM users WHERE id=:id AND is_active=1 LIMIT 1');
                        $person->execute([':id' => $assigneeId]);
                        $assigneeName = $person->fetchColumn();
                        if (!$assigneeName) throw new RuntimeException('کاربر انتخاب‌شده فعال نیست.');
                        $newStatus = 'in_progress';
                        $eventType = 'assigned';
                        $note = 'مسئول پیگیری: ' . (string) $assigneeName . ($note !== '' ? ' · ' . $note : '');
                        $update = $pdo->prepare('UPDATE workflow_items SET assignee_id=:assignee,due_date=:due,status=:status,updated_at=:updated WHERE id=:id AND status=:old');
                        $update->execute([':assignee' => $assigneeId, ':due' => $dueDate, ':status' => $newStatus, ':updated' => now_text(), ':id' => $selectedId, ':old' => $oldStatus]);
                    } elseif ($action === 'submit_review') {
                        $isAssignedWorker = (int) ($item['assignee_id'] ?? 0) === (int) $user['id'];
                        $isUnassignedCreator = empty($item['assignee_id']) && (int) $item['creator_id'] === (int) $user['id'];
                        if (!$isAssignedWorker && !$isUnassignedCreator) throw new RuntimeException('فقط مسئول انجام کار می‌تواند آن را برای تأیید بفرستد.');
                        if (!in_array($oldStatus, ['in_progress', 'returned'], true)) throw new RuntimeException('این درخواست در وضعیت فعلی آماده ارسال برای تأیید نیست.');
                        $newStatus = 'awaiting_review';
                        $eventType = 'sent_for_review';
                        if (strlen($note) > 3000) throw new RuntimeException('یادداشت بیش از اندازه طولانی است.');
                        if ($note === '') $note = 'کار برای بازبینی مدیر ارسال شد.';
                        $update = $pdo->prepare('UPDATE workflow_items SET status=:status,updated_at=:updated WHERE id=:id AND status=:old');
                        $update->execute([':status' => $newStatus, ':updated' => now_text(), ':id' => $selectedId, ':old' => $oldStatus]);
                    } elseif (in_array($action, ['approve', 'return', 'reject'], true)) {
                        if (!$manager) throw new RuntimeException('فقط مدیر یا سرپرست می‌تواند نتیجه را تأیید کند.');
                        if ($action === 'approve') {
                            if ($oldStatus !== 'awaiting_review') throw new RuntimeException('درخواست در انتظار تأیید نیست.');
                            $newStatus = 'completed';
                            $eventType = 'approved';
                            if ($note === '') $note = 'انجام کار تأیید شد.';
                        } elseif ($action === 'return') {
                            if ($oldStatus !== 'awaiting_review') throw new RuntimeException('فقط کار در انتظار تأیید قابل بازگشت است.');
                            if ($note === '') throw new RuntimeException('برای بازگشت کار، توضیح اصلاحات را بنویسید.');
                            $newStatus = 'returned';
                            $eventType = 'returned';
                        } else {
                            if (!in_array($oldStatus, ['submitted', 'in_progress', 'awaiting_review', 'returned'], true)) throw new RuntimeException('این درخواست قابل رد کردن نیست.');
                            if ($note === '') throw new RuntimeException('برای رد درخواست، دلیل را بنویسید.');
                            $newStatus = 'rejected';
                            $eventType = 'rejected';
                        }
                        if (strlen($note) > 3000) throw new RuntimeException('یادداشت بیش از اندازه طولانی است.');
                        $update = $pdo->prepare('UPDATE workflow_items SET status=:status,updated_at=:updated WHERE id=:id AND status=:old');
                        $update->execute([':status' => $newStatus, ':updated' => now_text(), ':id' => $selectedId, ':old' => $oldStatus]);
                    } else {
                        throw new RuntimeException('عملیات درخواستی شناخته‌شده نیست.');
                    }

                    if ($update->rowCount() === 0) throw new RuntimeException('درخواست هم‌زمان تغییر کرده است؛ صفحه را تازه کنید.');
                    $recordEvent($pdo, $selectedId, (int) $user['id'], $eventType, $oldStatus, $newStatus, $note);
                    $pdo->commit();
                    flash_set('گردش کار به‌روزرسانی شد.');
                    header('Location: automation.php?id=' . $selectedId . '#workflow-details');
                    exit;
                } catch (RuntimeException $exception) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    $error = $exception->getMessage();
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log($exception->getMessage());
                    $error = 'به‌روزرسانی گردش کار انجام نشد.';
                }
            }
        }
    }
}

if ($manager) {
    $items = db()->query('SELECT w.*, creator.full_name AS creator_name, assignee.full_name AS assignee_name FROM workflow_items w JOIN users creator ON creator.id=w.creator_id LEFT JOIN users assignee ON assignee.id=w.assignee_id ORDER BY CASE w.status WHEN \'submitted\' THEN 0 WHEN \'awaiting_review\' THEN 1 WHEN \'returned\' THEN 2 WHEN \'in_progress\' THEN 3 ELSE 4 END, w.updated_at DESC LIMIT 200')->fetchAll();
} else {
    $stmt = db()->prepare('SELECT w.*, creator.full_name AS creator_name, assignee.full_name AS assignee_name FROM workflow_items w JOIN users creator ON creator.id=w.creator_id LEFT JOIN users assignee ON assignee.id=w.assignee_id WHERE w.creator_id=:creator OR w.assignee_id=:assignee ORDER BY w.updated_at DESC LIMIT 100');
    $stmt->execute([':creator' => $user['id'], ':assignee' => $user['id']]);
    $items = $stmt->fetchAll();
}
$selectedItem = null;
foreach ($items as $candidate) {
    if ((int) $candidate['id'] === $selectedId) {
        $selectedItem = $candidate;
        break;
    }
}
if ($selectedId > 0 && !$selectedItem && $error === '') {
    http_response_code(404);
    $error = 'درخواست مورد نظر پیدا نشد یا به آن دسترسی ندارید.';
}
$events = [];
if ($selectedItem) {
    $stmt = db()->prepare('SELECT e.*, u.full_name AS actor_name FROM workflow_events e JOIN users u ON u.id=e.actor_id WHERE e.workflow_id=:workflow ORDER BY e.id DESC LIMIT 100');
    $stmt->execute([':workflow' => $selectedId]);
    $events = $stmt->fetchAll();
}
$activeUsers = $manager ? db()->query('SELECT id,full_name,role FROM users WHERE is_active=1 ORDER BY full_name COLLATE NOCASE')->fetchAll() : [];
$mineOpen = 0;
$waitingReview = 0;
$submittedCount = 0;
foreach ($items as $row) {
    if ((int) $row['creator_id'] === (int) $user['id'] || (int) ($row['assignee_id'] ?? 0) === (int) $user['id']) {
        if (!in_array($row['status'], ['completed', 'rejected'], true)) $mineOpen++;
    }
    if ($row['status'] === 'awaiting_review' && $manager) $waitingReview++;
    if ($row['status'] === 'submitted' && $manager) $submittedCount++;
}
$flash = flash_get();
require __DIR__ . '/app/portal-header.php';
?>
<div class="portal-page-heading">
    <div><p class="portal-kicker">گردش کار و پیگیری داخلی</p><h1>اتوماسیون داخلی</h1><p>درخواست ثبت کنید، مسئول انجام را مشخص کنید و مسیر بررسی تا تأیید را در یک جا پیگیری کنید.</p></div>
    <a class="portal-button portal-button-secondary" href="portal.php">بازگشت به پیشخوان</a>
</div>
<?php if ($flash): ?><div class="portal-alert" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="portal-alert portal-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="portal-grid automation-stats">
    <section class="portal-stat"><span>درخواست‌ها و کارهای باز من</span><strong><?= e((string) $mineOpen) ?></strong></section>
    <?php if ($manager): ?><section class="portal-stat"><span>در انتظار ارجاع</span><strong><?= e((string) $submittedCount) ?></strong></section><section class="portal-stat"><span>در انتظار تأیید مدیر</span><strong><?= e((string) $waitingReview) ?></strong></section><?php endif; ?>
</div>

<div class="workflow-layout">
    <aside class="workflow-sidebar">
        <section class="portal-form-card" id="new-work">
            <h2>ثبت درخواست / کار جدید</h2>
            <p class="portal-muted">درخواست پس از ثبت در فهرست مدیران قرار می‌گیرد تا مسئول آن مشخص شود.</p>
            <form method="post" class="portal-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="create">
                <label>عنوان<input type="text" name="title" maxlength="300" required placeholder="مثلاً بررسی نقشه‌های فاز دو"></label>
                <label>حوزه درخواست
                    <select name="category" required>
                        <option value="">انتخاب حوزه</option>
                        <option>فنی و مهندسی</option><option>امور اداری</option><option>مالی</option><option>خرید و تدارکات</option><option>منابع انسانی</option><option>سایر</option>
                    </select>
                </label>
                <div class="automation-field-row">
                    <label>اولویت<select name="priority"><option value="low">عادی</option><option value="normal" selected>معمولی</option><option value="high">فوری</option></select></label>
                    <label>سررسید (اختیاری)<input type="date" name="due_date"></label>
                </div>
                <label>شرح درخواست<textarea name="description" rows="5" maxlength="12000" required placeholder="نیاز، هدف و اطلاعات لازم را بنویسید…"></textarea></label>
                <button class="portal-button portal-button-primary" type="submit">ثبت در گردش کار</button>
            </form>
        </section>
        <section class="workflow-list-panel">
            <div class="workflow-list-heading"><h2><?= $manager ? 'همه درخواست‌ها' : 'درخواست‌های من' ?></h2><span><?= e((string) count($items)) ?></span></div>
            <?php if ($items): ?>
                <div class="workflow-list">
                    <?php foreach ($items as $row): ?>
                        <a class="workflow-list-item <?= (int) $row['id'] === $selectedId ? 'is-selected' : '' ?>" href="automation.php?id=<?= e((string) $row['id']) ?>">
                            <div class="workflow-list-top"><span class="workflow-status status-<?= e($row['status']) ?>"><?= e($statuses[$row['status']] ?? $row['status']) ?></span><small>#<?= e((string) $row['id']) ?></small></div>
                            <strong><?= e($row['title']) ?></strong>
                            <span class="workflow-list-meta"><?= e($row['category']) ?> · <?= e($row['creator_name']) ?></span>
                            <span class="workflow-list-meta">مسئول: <?= e($row['assignee_name'] ?: 'هنوز تعیین نشده') ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?><div class="portal-empty">هنوز کاری ثبت نشده است. اولین درخواست را از فرم بالا ایجاد کنید.</div><?php endif; ?>
        </section>
    </aside>

    <section class="workflow-detail-column" id="workflow-details">
        <?php if ($selectedItem): ?>
            <?php
            $isParticipant = (int) $selectedItem['creator_id'] === (int) $user['id'] || (int) ($selectedItem['assignee_id'] ?? 0) === (int) $user['id'];
            $canSubmitReview = ((int) ($selectedItem['assignee_id'] ?? 0) === (int) $user['id']) || (empty($selectedItem['assignee_id']) && (int) $selectedItem['creator_id'] === (int) $user['id']);
            ?>
            <article class="workflow-detail-card">
                <div class="workflow-detail-head">
                    <div><p class="portal-kicker">درخواست #<?= e((string) $selectedItem['id']) ?> · <?= e($selectedItem['category']) ?></p><h2><?= e($selectedItem['title']) ?></h2></div>
                    <span class="workflow-status status-<?= e($selectedItem['status']) ?>"><?= e($statuses[$selectedItem['status']] ?? $selectedItem['status']) ?></span>
                </div>
                <div class="workflow-meta-grid">
                    <div><small>ثبت‌کننده</small><strong><?= e($selectedItem['creator_name']) ?></strong></div>
                    <div><small>مسئول انجام</small><strong><?= e($selectedItem['assignee_name'] ?: 'تعیین نشده') ?></strong></div>
                    <div><small>اولویت</small><strong class="priority-<?= e($selectedItem['priority']) ?>"><?= e($priorities[$selectedItem['priority']] ?? 'معمولی') ?></strong></div>
                    <div><small>سررسید</small><strong><?= e($selectedItem['due_date'] ?: 'ثبت نشده') ?></strong></div>
                </div>
                <div class="workflow-description"><?= nl2br(e($selectedItem['description'])) ?></div>
                <p class="workflow-timestamp">آخرین به‌روزرسانی: <?= e(format_local_time($selectedItem['updated_at'])) ?></p>

                <?php if ($manager && in_array($selectedItem['status'], ['submitted', 'in_progress', 'returned'], true)): ?>
                    <section class="workflow-action-block">
                        <h3>ارجاع / تغییر مسئول</h3>
                        <form method="post" class="workflow-assign-form">
                            <?= csrf_field() ?><input type="hidden" name="action" value="assign"><input type="hidden" name="workflow_id" value="<?= e((string) $selectedId) ?>">
                            <label>مسئول<select name="assignee_id" required><option value="">انتخاب همکار</option><?php foreach ($activeUsers as $person): ?><option value="<?= e((string) $person['id']) ?>" <?= (int) ($selectedItem['assignee_id'] ?? 0) === (int) $person['id'] ? 'selected' : '' ?>><?= e($person['full_name']) ?> · <?= e(['admin' => 'مدیر سامانه', 'manager' => 'مدیر', 'employee' => 'همکار'][$person['role']] ?? 'کاربر') ?></option><?php endforeach; ?></select></label>
                            <label>سررسید<input type="date" name="due_date" value="<?= e($selectedItem['due_date'] ?? '') ?>"></label>
                            <label class="workflow-note-field">یادداشت ارجاع (اختیاری)<input name="note" maxlength="1000" placeholder="مثلاً اولویت‌بندی با پروژه الف"></label>
                            <button class="portal-button portal-button-primary" type="submit">ارجاع و شروع پیگیری</button>
                        </form>
                    </section>
                <?php endif; ?>

                <?php if ($canSubmitReview && in_array($selectedItem['status'], ['in_progress', 'returned'], true)): ?>
                    <form method="post" class="workflow-submit-review">
                        <?= csrf_field() ?><input type="hidden" name="action" value="submit_review"><input type="hidden" name="workflow_id" value="<?= e((string) $selectedId) ?>">
                        <label>پیام به مدیر (اختیاری)<input name="note" maxlength="3000" placeholder="شرح کوتاهی از نتیجه کار"></label>
                        <button class="portal-button portal-button-primary" type="submit">ارسال برای تأیید مدیر</button>
                    </form>
                <?php endif; ?>

                <?php if ($manager && $selectedItem['status'] === 'awaiting_review'): ?>
                    <section class="workflow-action-block workflow-review-block">
                        <h3>بازبینی مدیر</h3>
                        <form method="post" class="workflow-review-form">
                            <?= csrf_field() ?><input type="hidden" name="workflow_id" value="<?= e((string) $selectedId) ?>">
                            <label>یادداشت / توضیح<input name="note" maxlength="3000" placeholder="برای بازگشت یا رد، توضیح لازم است"></label>
                            <div class="portal-form-actions"><button class="portal-button portal-button-primary" name="action" value="approve" type="submit">تأیید و تکمیل</button><button class="portal-button portal-button-secondary" name="action" value="return" type="submit">بازگشت برای اصلاح</button><button class="portal-button portal-button-danger" name="action" value="reject" type="submit">رد درخواست</button></div>
                        </form>
                    </section>
                <?php elseif ($manager && in_array($selectedItem['status'], ['submitted', 'in_progress', 'returned'], true)): ?>
                    <form method="post" class="workflow-reject-form">
                        <?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="workflow_id" value="<?= e((string) $selectedId) ?>">
                        <label>دلیل رد (برای رد کردن لازم است)<input name="note" maxlength="3000" placeholder="دلیل رد درخواست را بنویسید"></label>
                        <button class="portal-button portal-button-danger" type="submit">رد درخواست</button>
                    </form>
                <?php endif; ?>

                <?php if ($isParticipant || $manager): ?>
                    <section class="workflow-history" id="workflow-history">
                        <h3>تاریخچه و یادداشت‌ها</h3>
                        <?php if ($events): ?>
                            <ol class="workflow-event-list">
                                <?php foreach ($events as $event): ?>
                                    <li class="workflow-event <?= $event['event_type'] === 'comment' ? 'is-comment' : '' ?>">
                                        <span class="workflow-event-dot" aria-hidden="true"></span>
                                        <div class="workflow-event-content"><div class="workflow-event-title"><strong><?= e($event['actor_name']) ?></strong><span><?= e(['created' => 'درخواست ثبت شد', 'assigned' => 'ارجاع / تغییر مسئول', 'sent_for_review' => 'ارسال برای تأیید', 'approved' => 'تأیید و تکمیل', 'returned' => 'بازگشت برای اصلاح', 'rejected' => 'رد درخواست', 'comment' => 'یادداشت'][$event['event_type']] ?? 'به‌روزرسانی') ?></span><time><?= e(format_local_time($event['created_at'])) ?></time></div>
                                            <?php if ($event['note'] !== ''): ?><p><?= nl2br(e($event['note'])) ?></p><?php endif; ?>
                                            <?php if ($event['from_status'] !== '' && $event['to_status'] !== '' && $event['from_status'] !== $event['to_status']): ?><small><?= e($statuses[$event['from_status']] ?? $event['from_status']) ?> ← <?= e($statuses[$event['to_status']] ?? $event['to_status']) ?></small><?php endif; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        <?php else: ?><div class="portal-empty">تاریخچه‌ای ثبت نشده است.</div><?php endif; ?>
                    </section>
                    <form method="post" class="workflow-comment-form">
                        <?= csrf_field() ?><input type="hidden" name="action" value="comment"><input type="hidden" name="workflow_id" value="<?= e((string) $selectedId) ?>">
                        <label>افزودن یادداشت<input name="note" maxlength="3000" required placeholder="پیام یا توضیح مرتبط با این کار…"></label>
                        <button class="portal-button portal-button-secondary" type="submit">ثبت یادداشت</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php else: ?>
            <div class="workflow-empty-detail"><span aria-hidden="true">↗</span><h2>یک درخواست را انتخاب کنید</h2><p>از فهرست کنار صفحه، جزئیات و تاریخچه گردش کار را ببینید؛ یا از فرم بالا درخواست تازه‌ای بسازید.</p></div>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/app/portal-footer.php'; ?>
