<?php
/**
 * Массовая загрузка фотографий товаров.
 * Способ 1: Загрузить несколько файлов сразу — система сопоставит по имени файла
 *           (имя файла должно содержать артикул товара, например YSF25056.jpg)
 * Способ 2: Выбрать фото для каждого товара вручную
 */
require_once __DIR__ . '/auth.php';
require_login();
$pdo = db();

$UPLOAD_DIR = __DIR__ . '/../../assets/uploads/';
$UPLOAD_URL = '../assets/uploads/';
if (!is_dir($UPLOAD_DIR)) @mkdir($UPLOAD_DIR, 0775, true);

$msg = '';
$msgType = '';

// ── Обработка загрузки ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Способ 1: массовая загрузка (несколько файлов в одном input)
    if (!empty($_FILES['files']['name'][0])) {
        $count = 0;
        $products = $pdo->query('SELECT id, slug, art, name, image FROM products')->fetchAll();
        $byArt = [];
        foreach ($products as $p) {
            $byArt[mb_strtolower(trim($p['art']))] = $p;
        }

        $files = $_FILES['files'];
        for ($i = 0; $i < count($files['name']); $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            $origName = $files['name'][$i];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) continue;

            // Имя файла без расширения
            $baseName = mb_strtolower(pathinfo($origName, PATHINFO_FILENAME));
            $baseName = preg_replace('/[^a-z0-9-]/', '', $baseName);

            // Ищем товар по артикулу
            $matched = null;
            foreach ($byArt as $art => $p) {
                $artClean = preg_replace('/[^a-z0-9-]/', '', $art);
                if ($artClean && (strpos($baseName, $artClean) !== false || $artClean === $baseName)) {
                    $matched = $p;
                    break;
                }
            }

            if ($matched) {
                $fileName = $matched['slug'] . '-' . time() . '-' . $i . '.' . $ext;
                if (move_uploaded_file($files['tmp_name'][$i], $UPLOAD_DIR . $fileName)) {
                    $imgUrl = $UPLOAD_URL . $fileName;
                    $pdo->prepare('UPDATE products SET image = ? WHERE id = ?')->execute([$imgUrl, $matched['id']]);
                    $count++;
                }
            }
        }
        $msg = "Загружено и сопоставлено фото: $count";
        $msgType = 'ok';
    }

    // Способ 2: одиночное фото для конкретного товара
    if (isset($_POST['product_id']) && !empty($_FILES['single_image']['name'])) {
        $pid = (int)$_POST['product_id'];
        $p = $pdo->prepare('SELECT slug FROM products WHERE id = ?');
        $p->execute([$pid]);
        $prod = $p->fetch();
        if ($prod) {
            $ext = strtolower(pathinfo($_FILES['single_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) {
                $fileName = $prod['slug'] . '-' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['single_image']['tmp_name'], $UPLOAD_DIR . $fileName)) {
                    $pdo->prepare('UPDATE products SET image = ? WHERE id = ?')
                        ->execute([$UPLOAD_URL . $fileName, $pid]);
                    $msg = '✓ Фото добавлено';
                    $msgType = 'ok';
                }
            }
        }
    }

    // Удалить фото
    if (isset($_POST['remove_photo'])) {
        $pid = (int)$_POST['product_id'];
        $pdo->prepare('UPDATE products SET image = "" WHERE id = ?')->execute([$pid]);
        $msg = 'Фото удалено';
        $msgType = 'ok';
    }
}

// ── Список товаров ──────────────────────────────────────────────
$noPhoto = $pdo->query('SELECT * FROM products WHERE image IS NULL OR image = "" ORDER BY category, name')->fetchAll();
$hasPhoto = $pdo->query('SELECT * FROM products WHERE image IS NOT NULL AND image != "" ORDER BY category, name')->fetchAll();

admin_head('Фотографии товаров');
?>
<?php if ($msg): ?>
<div class="msg <?= $msgType === 'err' ? 'msg-err' : 'msg-ok' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<div class="btn-row">
  <a class="btn btn-sm btn-out" href="products.php">← Назад к каталогу</a>
</div>

<!-- ===== МАССОВАЯ ЗАГРУЗКА ===== -->
<div class="card">
  <h2>Массовая загрузка фото</h2>
  <p class="sub">Выберите сразу несколько файлов. <b>Имя файла должно содержать артикул товара</b> (например: <code>YSF25056.jpg</code>, <code>YSC2509-120×200.png</code>).<br>Система автоматически найдёт нужный товар по артикулу и прикрепит фото.</p>

  <form method="post" enctype="multipart/form-data">
    <input type="file" name="files[]" multiple accept="image/*" style="padding:10px;border:1px solid #C9BFAF;border-radius:4px;width:100%;box-sizing:border-box;margin-bottom:12px">
    <p class="muted" style="font-size:12px;margin-bottom:10px">Можно выбрать до 50 файлов за раз (зажми Ctrl или Shift при выборе).</p>
    <button class="btn" type="submit">Загрузить и сопоставить</button>
  </form>

  <div style="margin-top:18px;padding:14px;background:#f6f4ef;border-radius:6px;font-size:13px">
    <b>💡 Как назвать файлы:</b><br>
    Файл <code>YSF25056.jpg</code> → товар с артикулом YSF25056<br>
    Файл <code>21PB-49.jpg</code> → товар с артикулом 21PB-49<br>
    Файл <code>матрас_lavender.jpg</code> → товар LAVENDER (если в имени есть артикул)
  </div>
</div>

<!-- ===== ТОВАРЫ БЕЗ ФОТО ===== -->
<div class="card" style="margin-top:20px">
  <h2>Товары без фото (<?= count($noPhoto) ?>)</h2>
  <?php if (empty($noPhoto)): ?>
    <p class="sub">✓ У всех товаров есть фотографии!</p>
  <?php else: ?>
  <table>
    <tr><th>Артикул</th><th>Название</th><th>Категория</th><th>Загрузить фото</th></tr>
    <?php foreach ($noPhoto as $p): ?>
    <tr>
      <td><b><?= h($p['art']) ?></b></td>
      <td><?= h($p['name']) ?></td>
      <td class="muted"><?= h($p['category']) ?></td>
      <td>
        <form method="post" enctype="multipart/form-data" style="display:flex;gap:6px;align-items:center">
          <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
          <input type="file" name="single_image" accept="image/*" style="font-size:11px;padding:4px;border:1px solid #C9BFAF;border-radius:4px" onchange="if(this.value)this.form.submit()">
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<!-- ===== ТОВАРЫ С ФОТО ===== -->
<?php if (!empty($hasPhoto)): ?>
<div class="card" style="margin-top:20px">
  <h2>Товары с фото (<?= count($hasPhoto) ?>)</h2>
  <table>
    <tr><th>Фото</th><th>Артикул</th><th>Название</th><th></th></tr>
    <?php foreach ($hasPhoto as $p): ?>
    <tr>
      <td><img src="<?= h($p['image']) ?>" class="thumb"></td>
      <td><b><?= h($p['art']) ?></b></td>
      <td><?= h($p['name']) ?></td>
      <td>
        <form method="post" onsubmit="return confirm('Удалить фото?')">
          <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
          <button class="btn btn-sm btn-red" name="remove_photo" value="1">Удалить</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>
<?php admin_foot(); ?>
