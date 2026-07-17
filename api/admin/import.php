<?php
/**
 * Импорт товаров из Excel (xlsx).
 * Принимает файл .xlsx, читает данные и добавляет товары в каталог.
 *
 * Ожидаемая структура колонок (первая строка — заголовки, регистр не важен):
 *   Название | Артикул | Категория | Подкатегория | Описание | Материалы |
 *   Габариты | Цвета | Назначение | Упаковка | Под заказ (да/нет) |
 *   Назначение-объекты (через запятую) | Фото (URL) | Слаг | Опубликован
 */
require_once __DIR__ . '/auth.php';
require_login();
$pdo = db();

$msg = '';
$msgType = '';
$imported = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file']['name'])) {
    $file = $_FILES['file']['tmp_name'];
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

    if ($ext === 'csv') {
        $rows = read_csv($file);
    } elseif (in_array($ext, ['xlsx', 'xls'], true)) {
        $rows = read_xlsx($file);
    } else {
        $msg = 'Нужен файл .xlsx или .csv'; $msgType = 'err';
        $rows = [];
    }

    if (!empty($rows)) {
        // карта заголовков → поля БД
        $headerMap = [
            'название' => 'name', 'наименование' => 'name', 'name' => 'name',
            'артикул' => 'art', 'арт' => 'art', 'артикул sku' => 'art', 'sku' => 'art',
            'категория' => 'category',
            'подкатегория' => 'subcategory',
            'описание' => 'descr', 'опис' => 'descr',
            'материалы' => 'material', 'материал' => 'material', 'состав' => 'material',
            'габариты' => 'sizes', 'размеры' => 'sizes', 'размер' => 'sizes',
            'цвета' => 'color', 'цвет' => 'color',
            'назначение' => 'destination', 'применение' => 'destination',
            'упаковка' => 'packing',
            'под заказ' => 'custom', 'кастом' => 'custom', 'индивидуальный' => 'custom',
            'объекты' => 'objects', 'назначение-объекты' => 'objects', 'для кого' => 'objects',
            'фото' => 'image', 'изображение' => 'image', 'картинка' => 'image', 'url фото' => 'image', 'ссылка' => 'image',
            'слаг' => 'slug', 'slug' => 'slug',
            'опубликован' => 'is_published', 'публикация' => 'is_published', 'статус' => 'is_published',
        ];

        $headers = array_map('mb_strtolower', array_map('trim', $rows[0]));
        $colMap = [];
        foreach ($headers as $i => $h) {
            $key = isset($headerMap[$h]) ? $headerMap[$h] : null;
            if ($key && !isset($colMap[$key])) $colMap[$key] = $i;
        }

        $st = $pdo->prepare('INSERT INTO products
            (slug,category,subcategory,name,art,descr,material,sizes,color,destination,packing,custom,objects,image,sort_order)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

        $order = 0;
        for ($r = 1; $r < count($rows); $r++) {
            $row = $rows[$r];
            $get = function($key) use ($row, $colMap) {
                $i = $colMap[$key] ?? null;
                return ($i !== null && isset($row[$i])) ? trim((string)$row[$i]) : '';
            };

            $name = $get('name');
            if ($name === '') continue;   // пропускаем пустые строки

            $slug = $get('slug');
            if ($slug === '') {
                $slug = 'item-' . ($r) . '-' . substr(md5($name), 0, 6);
            }
            $slug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
            if (!$slug) $slug = 'item-' . $r;

            $cat = $get('category');
            // нормализуем категории
            $catMap = [
                'диван' => 'sofas', 'диваны' => 'sofas',
                'кровать' => 'beds', 'кровати' => 'beds',
                'шкаф' => 'wardrobes', 'шкафы' => 'wardrobes', 'гардероб' => 'wardrobes',
                'кухня' => 'kitchens', 'кухни' => 'kitchens',
                'сантехника' => 'sanitary', 'сан' => 'sanitary', 'ванная' => 'sanitary',
            ];
            foreach ($catMap as $ru => $en) {
                if (mb_stripos($cat, $ru) !== false) { $cat = $en; break; }
            }
            if (!in_array($cat, ['sofas','beds','wardrobes','kitchens','sanitary'], true)) $cat = 'sofas';

            $custom = strtolower($get('custom'));
            $customVal = in_array($custom, ['да','yes','1','+','true','под заказ','кастом'], true) ? 1 : 0;

            $pub = strtolower($get('is_published'));
            $pubVal = !in_array($pub, ['нет','no','0','-','false','скрыть','черновик'], true) ? 1 : 0;

            try {
                $st->execute([
                    $slug, $cat, $get('subcategory'), $name, $get('art') ?: ('SD-' . $r),
                    $get('descr'), $get('material'), $get('sizes'), $get('color'),
                    $get('destination'), $get('packing'), $customVal, $get('objects'),
                    $get('image'), $order++,
                ]);
                $imported++;
            } catch (Throwable $e) {
                // дубликат slug — пропускаем
            }
        }
        $msg = "Импортировано товаров: $imported";
        $msgType = 'ok';
    }
}

/** Чтение CSV */
function read_csv($file): array {
    $rows = [];
    if (($h = fopen($file, 'r')) !== false) {
        // определяем разделитель
        $first = fgets($h);
        rewind($h);
        $delim = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';
        while (($data = fgetcsv($h, 0, $delim)) !== false) $rows[] = $data;
        fclose($h);
    }
    return $rows;
}

/** Чтение XLSX (чистый PHP, без внешних библиотек) */
function read_xlsx($file): array {
    if (!class_exists('ZipArchive')) {
        throw new Exception('На сервере не установлено расширение PHP ZipArchive. Используйте CSV-формат или обратитесь к хостингу.');
    }
    $zip = new ZipArchive();
    if (!$zip->open($file)) throw new Exception('Не удалось открыть xlsx файл.');
    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    $zip->close();

    if (!$xml) throw new Exception('Не удалось прочитать данные из xlsx.');

    // читаем общие строки
    $shared = [];
    if ($sharedXml) {
        $sx = simplexml_load_string($sharedXml);
        if ($sx) foreach ($sx->si as $si) {
            $val = '';
            foreach ($si->t as $t) $val .= (string)$t;
            foreach ($si->r as $r) foreach ($r->t as $t) $val .= (string)$t;
            $shared[] = $val;
        }
    }

    // читаем ячейки
    $sheet = simplexml_load_string($xml);
    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $rowData = [];
        $maxCol = 0;
        foreach ($row->c as $cell) {
            $ref = (string)$cell['r'];   // например "A1", "B1"
            $colStr = preg_replace('/[0-9]/', '', $ref);
            $col = colToIndex($colStr);
            $maxCol = max($maxCol, $col);
            $val = '';
            if (isset($cell->v)) {
                $val = (string)$cell->v;
                if ((string)$cell['t'] === 's') $val = $shared[(int)$val] ?? '';
            }
            $rowData[$col] = $val;
        }
        // заполняем пропуски
        $full = [];
        for ($i = 0; $i <= $maxCol; $i++) $full[$i] = $rowData[$i] ?? '';
        $rows[] = $full;
    }
    return $rows;
}

function colToIndex($col): int {
    $col = strtoupper($col);
    $n = 0;
    for ($i = 0; $i < strlen($col); $i++) $n = $n * 26 + (ord($col[$i]) - 64);
    return $n - 1;
}

admin_head('Импорт каталога');
?>
<div class="btn-row">
  <a class="btn btn-sm btn-out" href="products.php">← Назад к каталогу</a>
</div>

<?php if ($msg): ?>
<div class="msg <?= $msgType === 'err' ? 'msg-err' : 'msg-ok' ?>"><?= h($msg) ?></div>
<?php endif; ?>

<div class="card">
  <h2>Импорт товаров из Excel</h2>
  <p class="sub">Загрузите файл .xlsx или .csv. Первая строка должна содержать заголовки колонок.</p>

  <h3 style="font-size:14px;margin:20px 0 10px">Поддерживаемые колонки:</h3>
  <table style="font-size:13px">
    <tr><td><b>Название</b></td><td>— название товара (обязательно)</td></tr>
    <tr><td><b>Артикул</b></td><td>— артикул (SD-SOF-001)</td></tr>
    <tr><td><b>Категория</b></td><td>— диваны / кровати / шкафы / кухни / сантехника</td></tr>
    <tr><td>Подкатегория</td><td>— для сантехники (toilets, faucets...)</td></tr>
    <tr><td>Описание</td><td>— текст описания</td></tr>
    <tr><td>Материалы</td><td>— из чего сделан</td></tr>
    <tr><td>Габариты</td><td>— размеры</td></tr>
    <tr><td>Цвета</td><td>— доступные цвета</td></tr>
    <tr><td>Назначение</td><td>— где используется</td></tr>
    <tr><td>Упаковка</td><td>— тип упаковки</td></tr>
    <tr><td>Под заказ</td><td>— да / нет</td></tr>
    <tr><td>Фото</td><td>— URL изображения (https://...)</td></tr>
  </table>

  <form method="post" enctype="multipart/form-data" style="margin-top:24px">
    <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#4A607B;margin-bottom:10px">Файл .xlsx или .csv</label>
    <input type="file" name="file" accept=".xlsx,.xls,.csv" style="padding:10px;border:1px solid #C9BFAF;border-radius:4px;width:100%;box-sizing:border-box">
    <p class="muted" style="font-size:12px;margin-top:8px">Если характеристики (материалы, цвета и т.д.) не заполнены — они просто не будут отображаться на сайте.</p>
    <button class="btn" type="submit" style="margin-top:16px">Импортировать</button>
  </form>
</div>
<?php admin_foot(); ?>
