<?php
/** Страница входа в админку. */
require_once __DIR__ . '/auth.php';   // подключает db.php, session_start, is_logged(), h()
if (is_logged()) { header('Location: index.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = clean($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');
    $st = db()->prepare('SELECT * FROM admins WHERE username = ?');
    $st->execute([$u]);
    $row = $st->fetch();
    if ($row && password_verify($p, $row['password_hash'])) {
        $_SESSION['admin_id'] = $row['id'];
        $_SESSION['admin_user'] = $row['username'];
        header('Location: index.php'); exit;
    }
    $err = 'Неверный логин или пароль';
}
?>
<!doctype html><html lang="ru"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Вход · Админка Шэнда</title>
<link rel="stylesheet" href="admin.css">
<style>
.login{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{width:100%;max-width:380px;background:#fff;border:1px solid rgba(11,31,59,.12);border-radius:10px;padding:38px 32px}
.box h1{font-size:22px;margin-bottom:4px}.box p{color:#4A607B;font-size:14px;margin-bottom:22px}
.box label{display:block;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;margin:14px 0 6px;color:#4A607B}
.box input{width:100%;padding:12px 14px;border:1px solid #C9BFAF;border-radius:5px;font:inherit;box-sizing:border-box}
.box button{width:100%;margin-top:20px;background:#D0AE6F;color:#0B1F3B;border:0;padding:14px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;border-radius:5px;cursor:pointer;font-size:13px}
.err{color:#a52a2a;background:#fbeeee;padding:10px;border-radius:6px;margin-bottom:14px;font-size:14px;text-align:center}
</style></head>
<body class="login"><div class="box">
<h1>Админ-панель</h1><p>Шэнда · каталог и заявки</p>
<?php if ($err) echo '<div class="err">' . h($err) . '</div>'; ?>
<form method="post">
  <label>Логин</label>
  <input name="username" required autofocus>
  <label>Пароль</label>
  <input name="password" type="password" required>
  <button type="submit">Войти</button>
</form>
</div></body></html>
