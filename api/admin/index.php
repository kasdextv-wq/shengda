<?php
/** Дашборд: статистика + список заявок. */
require_once __DIR__ . '/auth.php';
require_login();
$pdo = db();

// смена статуса / удаление
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (isset($_POST['status'])) {
        $pdo->prepare('UPDATE leads SET status = ? WHERE id = ?')
            ->execute([clean($_POST['status']), $id]);
    } elseif (isset($_POST['delete'])) {
        $pdo->prepare('DELETE FROM leads WHERE id = ?')->execute([$id]);
    }
    header('Location: index.php'); exit;
}

$filter = clean($_GET['status'] ?? '');
$sql = 'SELECT * FROM leads';
$params = [];
if (in_array($filter, ['new', 'processing', 'done'], true)) {
    $sql .= ' WHERE status = ?'; $params[] = $filter;
}
$sql .= ' ORDER BY id DESC LIMIT 200';
$st = $pdo->prepare($sql); $st->execute($params);
$leads = $st->fetchAll();

$cnt = $pdo->query("SELECT
    COUNT(*) total,
    SUM(status='new') n_new,
    SUM(status='processing') n_proc,
    SUM(status='done') n_done
    FROM leads")->fetch();

admin_head('Заявки');
?>
<div class="btn-row">
  <a class="btn <?= $filter===''?'':'btn-out' ?>" href="index.php">Все</a>
  <a class="btn <?= $filter==='new'?'':'btn-out' ?>" href="index.php?status=new">Новые</a>
  <a class="btn <?= $filter==='processing'?'':'btn-out' ?>" href="index.php?status=processing">В работе</a>
  <a class="btn <?= $filter==='done'?'':'btn-out' ?>" href="index.php?status=done">Завершены</a>
</div>

<div class="stats">
  <div class="stat"><div class="n"><?= $cnt['total'] ?></div><div class="l">Всего заявок</div></div>
  <div class="stat"><div class="n"><?= $cnt['n_new'] ?></div><div class="l">Новые</div></div>
  <div class="stat"><div class="n"><?= $cnt['n_proc'] ?></div><div class="l">В работе</div></div>
  <div class="stat"><div class="n"><?= $cnt['n_done'] ?></div><div class="l">Завершено</div></div>
</div>

<?php if (!$leads): ?>
  <div class="card"><p class="sub">Заявок пока нет. Отправьте тестовую заявку с сайта — она появится здесь и (если настроен бот) в Telegram.</p></div>
<?php else: ?>
<table>
  <tr><th>№</th><th>Дата</th><th>Имя / Компания</th><th>Контакты</th><th>Форма</th><th>Telegram</th><th>Статус</th><th></th></tr>
  <?php foreach ($leads as $l): ?>
  <tr>
    <td>#<?= $l['id'] ?></td>
    <td class="muted"><?= date('d.m.Y H:i', strtotime($l['created_at'])) ?></td>
    <td><?= h($l['name']) ?><br><span class="muted"><?= h($l['company']) ?></span></td>
    <td><?= h($l['phone']) ?><br><span class="muted"><?= h($l['email']) ?></span></td>
    <td class="muted"><?= h($l['source']) ?></td>
    <td><?= $l['tg_sent'] ? '<span class="tg-yes">✓ отправлено</span>' : '<span class="tg-no">—</span>' ?></td>
    <td>
      <form method="post" style="display:flex;gap:6px;align-items:center">
        <input type="hidden" name="id" value="<?= $l['id'] ?>">
        <select name="status" onchange="this.form.submit()" style="padding:5px;border:1px solid #C9BFAF;border-radius:4px;font-size:12px">
          <option value="new"<?= $l['status']==='new'?' selected':''?>>Новая</option>
          <option value="processing"<?= $l['status']==='processing'?' selected':''?>>В работе</option>
          <option value="done"<?= $l['status']==='done'?' selected':''?>>Завершена</option>
        </select>
      </form>
    </td>
    <td>
      <a class="btn btn-sm btn-out" href="lead.php?id=<?= $l['id'] ?>">Открыть</a>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php admin_foot(); ?>
