<?php
/** Детальный просмотр одной заявки. */
require_once __DIR__ . '/auth.php';
require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if (isset($_POST['status'])) {
        $pdo->prepare('UPDATE leads SET status = ? WHERE id = ?')
            ->execute([clean($_POST['status']), $id]);
    } elseif (isset($_POST['resend'])) {
        $l = $pdo->prepare('SELECT * FROM leads WHERE id = ?');
        $l->execute([$id]); $l = $l->fetch();
        if ($l) {
            $lines = ["🔔 <b>Заявка №{$l['id']}</b> (повторно)"];
            foreach (['source'=>'📄 Форма','name'=>'👤 Имя','company'=>'🏢 Компания','phone'=>'📞 Телефон','email'=>'✉️ Email','role'=>'🏷️ Роль'] as $k=>$lab) {
                if ($l[$k]) $lines[] = "$lab: " . $l[$k];
            }
            if ($l['message']) $lines[] = "💬 Сообщение:\n" . $l['message'];
            if ($l['items'])   $lines[] = "🛒 Состав заявки:\n" . $l['items'];
            if (send_telegram(implode("\n", $lines))) {
                $pdo->prepare('UPDATE leads SET tg_sent = 1 WHERE id = ?')->execute([$id]);
            }
        }
    } elseif (isset($_POST['delete'])) {
        $pdo->prepare('DELETE FROM leads WHERE id = ?')->execute([$id]);
        header('Location: index.php'); exit;
    }
    header('Location: lead.php?id=' . $id); exit;
}

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM leads WHERE id = ?');
$st->execute([$id]);
$l = $st->fetch();
if (!$l) { header('Location: index.php'); exit; }

admin_head('Заявка №' . $l['id']);
?>
<div class="btn-row">
  <a class="btn btn-sm btn-out" href="index.php">← К списку заявок</a>
</div>

<div class="lead-detail">
  <dl>
    <dt>Имя</dt><dd><?= h($l['name']) ?: '—' ?></dd>
    <dt>Компания</dt><dd><?= h($l['company']) ?: '—' ?></dd>
    <dt>Телефон</dt><dd><?= h($l['phone']) ?: '—' ?></dd>
    <dt>Email</dt><dd><?= h($l['email']) ?: '—' ?></dd>
    <dt>Роль / способ связи</dt><dd><?= h($l['role']) ?: '—' ?></dd>
    <dt>Форма-источник</dt><dd class="muted"><?= h($l['source']) ?></dd>
    <dt>Сообщение</dt><dd><?= h($l['message']) ?: '—' ?></dd>
    <dt>Состав заявки (товары)</dt><dd><?= h($l['items']) ?: '—' ?></dd>
    <dt>Дата</dt><dd class="muted"><?= date('d.m.Y H:i', strtotime($l['created_at'])) ?></dd>
    <dt>Telegram</dt><dd><?= $l['tg_sent'] ? '<span class="tg-yes">✓ отправлено</span>' : '<span class="tg-no">не отправлено</span>' ?></dd>
  </dl>
</div>

<form method="post" class="btn-row">
  <input type="hidden" name="id" value="<?= $l['id'] ?>">
  <select name="status" style="padding:9px;border:1px solid #C9BFAF;border-radius:4px;font-size:14px">
    <option value="new"<?= $l['status']==='new'?' selected':''?>>Новая</option>
    <option value="processing"<?= $l['status']==='processing'?' selected':''?>>В работе</option>
    <option value="done"<?= $l['status']==='done'?' selected':''?>>Завершена</option>
  </select>
  <button class="btn btn-sm" type="submit">Сохранить статус</button>
  <?php if (!$l['tg_sent']): ?>
    <button class="btn btn-sm btn-out" name="resend" value="1" type="submit">Отправить в Telegram</button>
  <?php endif; ?>
  <button class="btn btn-sm btn-red" name="delete" value="1" type="submit" onclick="return confirm('Удалить заявку безвозвратно?')">Удалить</button>
</form>
<?php admin_foot(); ?>
