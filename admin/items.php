<?php
require_once __DIR__ . '/../app/bootstrap.php';
$user = require_role(['admin']);
$portalRoot = '../';
$activePortal = 'admin';
$typeLabels = [
    'projects' => 'پروژه‌ها',
    'articles' => 'مقالات',
    'team' => 'اعضای تیم',
    'services' => 'خدمات',
    'clients' => 'کارفرمایان',
];
$type = (string) ($_GET['type'] ?? 'projects');
if (!isset($typeLabels[$type])) $type = 'projects';
$portalTitle = 'مدیریت ' . $typeLabels[$type];
$error = '';
$blank = ['id' => 0, 'title' => '', 'slug' => '', 'subtitle' => '', 'excerpt' => '', 'body' => '', 'category' => '', 'image_url' => '', 'sort_order' => 0, 'is_published' => 0];
$record = $blank;
$editId = (int) ($_GET['edit'] ?? 0);
if ($editId > 0) $record = get_content_item($editId, $type) ?? $blank;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? 'save');
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete' && $id > 0) {
        $stmt = db()->prepare('DELETE FROM content_items WHERE id = :id AND type = :type');
        $stmt->execute([':id' => $id, ':type' => $type]);
        flash_set('مورد انتخاب‌شده حذف شد.');
        header('Location: items.php?type=' . rawurlencode($type));
        exit;
    }
    $record = [
        'id' => $id,
        'title' => trim((string) ($_POST['title'] ?? '')),
        'slug' => trim((string) ($_POST['slug'] ?? '')),
        'subtitle' => trim((string) ($_POST['subtitle'] ?? '')),
        'excerpt' => trim((string) ($_POST['excerpt'] ?? '')),
        'body' => trim((string) ($_POST['body'] ?? '')),
        'category' => trim((string) ($_POST['category'] ?? '')),
        'image_url' => trim((string) ($_POST['image_url'] ?? '')),
        'sort_order' => max(0, min(9999, (int) ($_POST['sort_order'] ?? 0))),
        'is_published' => isset($_POST['is_published']) ? 1 : 0,
    ];
    if ($record['title'] === '' || strlen($record['title']) > 300) {
        $error = 'عنوان را وارد کنید (حداکثر ۳۰۰ نویسه).';
    } elseif (strlen($record['subtitle']) > 600 || strlen($record['excerpt']) > 3000 || strlen($record['body']) > 30000 || strlen($record['category']) > 180) {
        $error = 'یکی از فیلدهای متن بیش از اندازه طولانی است.';
    } elseif ($record['image_url'] !== '' && safe_image_url($record['image_url']) === '') {
        $error = 'نشانی تصویر باید از نوع HTTPS یا مسیر محلی assets/ باشد.';
    } else {
        $record['slug'] = strtolower(preg_replace('/[^a-z0-9-]+/', '-', $record['slug']) ?? '');
        $record['slug'] = trim($record['slug'], '-');
        if ($record['slug'] === '') $record['slug'] = 'item-' . bin2hex(random_bytes(5));
        $now = now_text();
        if ($id > 0) {
            $stmt = db()->prepare('UPDATE content_items SET slug=:slug,title=:title,subtitle=:subtitle,excerpt=:excerpt,body=:body,category=:category,image_url=:image,sort_order=:sort,is_published=:published,updated_at=:updated WHERE id=:id AND type=:type');
            $stmt->execute([
                ':slug' => $record['slug'], ':title' => $record['title'], ':subtitle' => $record['subtitle'],
                ':excerpt' => $record['excerpt'], ':body' => $record['body'], ':category' => $record['category'],
                ':image' => $record['image_url'], ':sort' => $record['sort_order'], ':published' => $record['is_published'],
                ':updated' => $now, ':id' => $id, ':type' => $type,
            ]);
        } else {
            $stmt = db()->prepare('INSERT INTO content_items(type,slug,title,subtitle,excerpt,body,category,image_url,sort_order,is_published,created_at,updated_at) VALUES(:type,:slug,:title,:subtitle,:excerpt,:body,:category,:image,:sort,:published,:created,:updated)');
            $stmt->execute([
                ':type' => $type, ':slug' => $record['slug'], ':title' => $record['title'], ':subtitle' => $record['subtitle'],
                ':excerpt' => $record['excerpt'], ':body' => $record['body'], ':category' => $record['category'],
                ':image' => $record['image_url'], ':sort' => $record['sort_order'], ':published' => $record['is_published'],
                ':created' => $now, ':updated' => $now,
            ]);
        }
        flash_set('محتوا ذخیره شد.');
        header('Location: items.php?type=' . rawurlencode($type));
        exit;
    }
}

$items = content_items($type, [], 100, false);
$flash = flash_get();
require __DIR__ . '/../app/portal-header.php';
?>
<div class="portal-page-heading"><div><p class="portal-kicker">مدیریت محتوای سایت</p><h1><?= e($typeLabels[$type]) ?></h1><p>محتوا را ایجاد کنید، ویرایش کنید یا برای انتشار در وب‌سایت فعال کنید.</p></div><a class="portal-button portal-button-secondary" href="index.php">بازگشت به مدیریت</a></div>
<div class="editor-type-tabs">
    <?php foreach ($typeLabels as $key => $label): ?><a class="<?= $type === $key ? 'is-active' : '' ?>" href="items.php?type=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach; ?>
</div>
<?php if ($flash): ?><div class="portal-alert" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="portal-alert portal-alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="portal-form-card editor-form">
    <h2><?= $record['id'] > 0 ? 'ویرایش محتوا' : 'افزودن مورد جدید' ?></h2>
    <form method="post" action="items.php?type=<?= e($type) ?><?= $record['id'] > 0 ? '&edit=' . e((string) $record['id']) : '' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= e((string) $record['id']) ?>">
        <div class="portal-form-grid">
            <label>عنوان / نام *<input name="title" type="text" maxlength="300" required value="<?= e($record['title']) ?>" placeholder="عنوان نمایشی"></label>
            <label>زیرعنوان / سمت / کارفرما<input name="subtitle" type="text" maxlength="600" value="<?= e($record['subtitle']) ?>" placeholder="اطلاعات کوتاه تکمیلی"></label>
            <?php if ($type === 'team'): ?>
                <label>نوع عضو
                    <select name="category"><option value="board" <?= $record['category'] === 'board' ? 'selected' : '' ?>>هیئت‌مدیره</option><option value="team" <?= $record['category'] === 'team' ? 'selected' : '' ?>>سایر اعضای شرکت</option></select>
                </label>
            <?php else: ?>
                <label>دسته‌بندی<input name="category" type="text" maxlength="180" value="<?= e($record['category']) ?>" placeholder="مثلاً مطالعات، طراحی یا نظارت"></label>
            <?php endif; ?>
            <label>ترتیب نمایش<input name="sort_order" type="number" min="0" max="9999" value="<?= e((string) $record['sort_order']) ?>"></label>
            <label class="portal-form-full">خلاصه / رزومه کوتاه / شرح کوتاه<textarea name="excerpt" maxlength="3000" rows="3"><?= e($record['excerpt']) ?></textarea></label>
            <label class="portal-form-full">متن کامل / جزئیات<textarea name="body" maxlength="30000" rows="6"><?= e($record['body']) ?></textarea></label>
            <?php if (in_array($type, ['projects', 'articles'], true)): ?>
                <label class="portal-form-full">نشانی تصویر (اختیاری)<input name="image_url" type="text" inputmode="url" maxlength="1000" value="<?= e($record['image_url']) ?>" placeholder="https://... یا assets/image.jpg"></label>
            <?php endif; ?>
        </div>
        <div class="portal-form-actions">
            <label class="portal-checkbox"><input type="checkbox" name="is_published" value="1" <?= (int) $record['is_published'] === 1 ? 'checked' : '' ?>> انتشار در وب‌سایت عمومی</label>
            <button class="portal-button portal-button-primary" type="submit">ذخیره</button>
            <?php if ($record['id'] > 0): ?><a class="portal-button portal-button-secondary" href="items.php?type=<?= e($type) ?>">انصراف از ویرایش</a><?php endif; ?>
        </div>
    </form>
</section>
<section class="admin-stack">
    <div class="portal-page-heading"><div><h2 style="font-size:17px">فهرست <?= e($typeLabels[$type]) ?></h2><p>موارد پیش‌نویس برای عموم نمایش داده نمی‌شوند.</p></div></div>
    <?php if ($items): ?>
        <div class="portal-table-wrap"><table class="portal-table"><thead><tr><th>عنوان</th><th>دسته / زیرعنوان</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><strong><?= e($item['title']) ?></strong><?php if ($item['excerpt'] !== ''): ?><small><?= e(preg_match('/^.{0,100}/us', (string) $item['excerpt'], $shortExcerpt) ? $shortExcerpt[0] : $item['excerpt']) ?></small><?php endif; ?></td>
                    <td><?= e($item['category']) ?><small><?= e($item['subtitle']) ?></small></td>
                    <td><span class="status-pill <?= (int) $item['is_published'] === 1 ? '' : 'pending' ?>"><?= (int) $item['is_published'] === 1 ? 'منتشرشده' : 'پیش‌نویس' ?></span></td>
                    <td><div class="table-actions"><a class="portal-button portal-button-secondary" href="items.php?type=<?= e($type) ?>&edit=<?= e((string) $item['id']) ?>">ویرایش</a><form method="post" action="items.php?type=<?= e($type) ?>" onsubmit="return confirm('این مورد حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e((string) $item['id']) ?>"><button class="portal-button portal-button-danger" type="submit">حذف</button></form></div></td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
    <?php else: ?><div class="portal-empty">موردی ثبت نشده است. از فرم بالا برای افزودن محتوای تازه استفاده کنید.</div><?php endif; ?>
</section>
<?php require __DIR__ . '/../app/portal-footer.php'; ?>
