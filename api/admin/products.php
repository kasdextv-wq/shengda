<?php
/** Управление каталогом: список, добавление, редактирование, удаление, загрузка фото. */
require_once __DIR__ . '/auth.php';
require_login();
$pdo = db();

$UPLOAD_DIR = __DIR__ . '/../../assets/uploads/';
$UPLOAD_URL = '../assets/uploads/';

// Гарантируем существование папки uploads с правами на запись
if (!is_dir($UPLOAD_DIR)) { @mkdir($UPLOAD_DIR, 0775, true); }

// ── Сохранение (создание/обновление) ────────────────────────────
function save_product(PDO $pdo, string $uploadDir, string $uploadUrl): string {
    $id = (int)($_POST['id'] ?? 0);
    $f = $_POST;

    // уникальный slug
    $slug = preg_replace('/[^a-z0-9-]/', '', strtolower(clean($f['slug'] ?? '')));
    if ($slug === '') $slug = 'item-' . time();

    // обработка фото
    $image = clean($f['image_existing'] ?? '');
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) {
            $name = $slug . '-' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $name)) {
                $image = $uploadUrl . $name;
            }
        }
    }

    $fields = [
        'slug'        => $slug,
        'category'    => clean($f['category'] ?? 'sofas'),
        'subcategory' => clean($f['subcategory'] ?? ''),
        'name'        => clean($f['name'] ?? ''),
        'art'         => clean($f['art'] ?? ''),
        'descr'       => clean($f['descr'] ?? ''),
        'material'    => clean($f['material'] ?? ''),
        'sizes'       => clean($f['sizes'] ?? ''),
        'color'       => clean($f['color'] ?? ''),
        'destination' => clean($f['destination'] ?? ''),
        'packing'     => clean($f['packing'] ?? ''),
        'custom'      => !empty($f['custom']) ? 1 : 0,
        'objects'     => implode(',', array_filter(array_map('trim', explode(',', clean($f['objects'] ?? ''))))),
        'image'       => $image,
        'is_published'=> !empty($f['is_published']) ? 1 : 0,
    ];

    try {
        if ($id > 0) {
            $set = implode(',', array_map(fn($k) => "$k=:$k", array_keys($fields)));
            $fields['id'] = $id;
            $pdo->prepare("UPDATE products SET $set WHERE id=:id")->execute($fields);
            return 'updated';
        }
        $cols = implode(',', array_keys($fields));
        $ph   = implode(',', array_map(fn($k) => ":$k", array_keys($fields)));
        $pdo->prepare("INSERT INTO products ($cols) VALUES ($ph)")->execute($fields);
        return 'created';
    } catch (Throwable $e) {
        // дубликат slug или иная ошибка БД
        return 'error';
    }
}

// ── Обработка POST: сохраняем → редирект (PRG паттерн) ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([(int)$_POST['id']]);
        $_SESSION['admin_msg'] = 'Товар удалён.';
        $_SESSION['admin_msg_type'] = 'ok';
    } else {
        $r = save_product($pdo, $UPLOAD_DIR, $UPLOAD_URL);
        if ($r === 'created') { $_SESSION['admin_msg'] = '✓ Товар добавлен.'; $_SESSION['admin_msg_type'] = 'ok'; }
        elseif ($r === 'updated') { $_SESSION['admin_msg'] = '✓ Изменения сохранены.'; $_SESSION['admin_msg_type'] = 'ok'; }
        else { $_SESSION['admin_msg'] = '✗ Ошибка сохранения (возможно, такой артикул уже есть).'; $_SESSION['admin_msg_type'] = 'err'; }
    }
    header('Location: products.php');
    exit;
}

// ── Сообщение из сессии (после редиректа) ──────────────────────
$msg = '';
$msgType = '';
if (!empty($_SESSION['admin_msg'])) {
    $msg = $_SESSION['admin_msg'];
    $msgType = $_SESSION['admin_msg_type'] ?? 'ok';
    unset($_SESSION['admin_msg'], $_SESSION['admin_msg_type']);
}

// ── Редактируемый товар ────────────────────────────────────────
$edit = null;
if (isset($_GET['edit'])) {
    if ($_GET['edit'] === 'new') {
        $edit = [];   // пустой массив = новый товар
    } else {
        $st = $pdo->prepare('SELECT * FROM products WHERE id = ?');
        $st->execute([(int)$_GET['edit']]);
        $edit = $st->fetch();
        if (!$edit) { header('Location: products.php'); exit; }
    }
}

// ── Список товаров ─────────────────────────────────────────────
$products = $pdo->query('SELECT * FROM products ORDER BY sort_order, id')->fetchAll();

admin_head('Каталог');
?>
<?php if ($msg): ?>
<div class="msg <?= $msgType === 'err' ? 'msg-err' : 'msg-ok' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<div class="btn-row">
  <a class="btn" href="products.php?edit=new">+ Добавить товар</a>
  <a class="btn btn-out" href="import.php">Импорт из Excel</a>
  <a class="btn btn-out" href="photos.php">📷 Фотографии</a>
</div>

<?php if ($edit !== null): ?>
<div class="card">
  <h2><?= !empty($edit['id']) ? 'Редактирование товара' : 'Новый товар' ?></h2>
  <form method="post" enctype="multipart/form-data" class="grid grid2">
    <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div>
      <label>Название *</label>
      <input name="name" required value="<?= h($edit['name'] ?? '') ?>">
      <label>Артикул *</label>
      <input name="art" required value="<?= h($edit['art'] ?? '') ?>">
      <label>Слаг (латиницей, для ссылки product.html?id=...)</label>
      <input name="slug" value="<?= h($edit['slug'] ?? '') ?>" placeholder="sofa-01">
      <label>Категория</label>
      <select name="category">
        <?php foreach (['sofas'=>'Диваны','beds'=>'Кровати','wardrobes'=>'Шкафы','kitchens'=>'Кухни','sanitary'=>'Сантехника'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= ($edit['category'] ?? '')===$k?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
      <label>Подкатегория (для сантехники: toilets, faucets...)</label>
      <input name="subcategory" value="<?= h($edit['subcategory'] ?? '') ?>">
      <label>Назначение (через запятую: hotel,apartment,office)</label>
      <input name="objects" value="<?= h($edit['objects'] ?? '') ?>">
    </div>
    <div>
      <label>Габариты</label>
      <input name="sizes" value="<?= h($edit['sizes'] ?? '') ?>">
      <label>Цвета</label>
      <input name="color" value="<?= h($edit['color'] ?? '') ?>">
      <label>Назначение объекта (текст)</label>
      <input name="destination" value="<?= h($edit['destination'] ?? '') ?>">
      <label>Упаковка</label>
      <input name="packing" value="<?= h($edit['packing'] ?? '') ?>">
      <label style="display:flex;align-items:center;gap:8px;margin-top:18px"><input type="checkbox" name="custom" style="width:auto" <?= !empty($edit['custom'])?'checked':'' ?>> Доступен под заказ</label>
      <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="is_published" style="width:auto" <?= (!isset($edit['id']) || !empty($edit['is_published']))?'checked':'' ?>> Опубликован на сайте</label>
    </div>
    <div class="field-full"><label>Описание</label><textarea name="descr"><?= h($edit['descr'] ?? '') ?></textarea></div>
    <div class="field-full"><label>Материалы</label><textarea name="material"><?= h($edit['material'] ?? '') ?></textarea></div>
    <div class="field-full">
      <label>Изображение товара</label>
      <input type="file" name="image" accept="image/*">
      <?php if (!empty($edit['image'])): ?>
        <p class="muted" style="margin-top:8px">Текущее фото: <img src="<?= h($edit['image']) ?>" class="thumb" style="vertical-align:middle"></p>
        <input type="hidden" name="image_existing" value="<?= h($edit['image']) ?>">
      <?php else: ?>
        <p class="muted" style="margin-top:6px;font-size:12px">Можно оставить без фото или вставить URL картинки в поле ниже.</p>
      <?php endif; ?>
    </div>
    <div class="field-full btn-row">
      <button class="btn" type="submit"><?= !empty($edit['id']) ? 'Сохранить изменения' : 'Добавить товар' ?></button>
      <a class="btn btn-out" href="products.php">Отмена</a>
    </div>
  </form>
</div>
<?php endif; ?>

<table>
  <tr><th></th><th>Название</th><th>Артикул</th><th>Категория</th><th>Под заказ</th><th>Опубл.</th><th></th></tr>
  <?php foreach ($products as $p): ?>
  <tr>
    <td><?php if ($p['image']): ?><img src="<?= h($p['image']) ?>" class="thumb"><?php endif; ?></td>
    <td><b><?= h($p['name']) ?></b><br><span class="muted"><?= h($p['art']) ?></span></td>
    <td class="muted"><?= h($p['art']) ?></td>
    <td class="muted"><?= h($p['category']) ?></td>
    <td><?= $p['custom'] ? '✓' : '—' ?></td>
    <td><?= $p['is_published'] ? '✓' : '—' ?></td>
    <td>
      <a class="btn btn-sm btn-out" href="products.php?edit=<?= $p['id'] ?>">Изменить</a>
      <form method="post" style="display:inline" onsubmit="return confirm('Удалить товар?')">
        <input type="hidden" name="id" value="<?= $p['id'] ?>">
        <button class="btn btn-sm btn-red" name="delete" value="1">Удалить</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php admin_foot(); ?>
