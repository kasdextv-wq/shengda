<?php
/** Приём заявок из любой формы сайта.
 *  Принимает POST (application/x-www-form-urlencoded или FormData).
 *  Сохраняет заявку в БД и дублирует в Telegram. */
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'error' => 'method'], 405);
}

$src = [
    'source'  => clean($_POST['source']  ?? ''),
    'name'    => clean($_POST['name']    ?? ''),
    'company' => clean($_POST['company'] ?? ''),
    'phone'   => clean($_POST['phone']   ?? ''),
    'email'   => clean($_POST['email']   ?? ''),
    'role'    => clean($_POST['role']    ?? ''),
    'message' => clean($_POST['message'] ?? ''),
    'items'   => clean($_POST['items']   ?? ''),
];

// Минимальная валидация: нужен хотя бы телефон или email
if ($src['phone'] === '' && $src['email'] === '') {
    json_out(['ok' => false, 'error' => 'no_contact'], 422);
}

try {
    $st = db()->prepare('INSERT INTO leads
        (source,name,company,phone,email,role,message,items)
        VALUES (?,?,?,?,?,?,?,?)');
    $st->execute([
        $src['source'], $src['name'], $src['company'], $src['phone'],
        $src['email'], $src['role'], $src['message'], $src['items'],
    ]);
    $leadId = (int)db()->lastInsertId();
} catch (Throwable $e) {
    json_out(['ok' => false, 'error' => 'db'], 500);
}

// ── Формирование сообщения в Telegram ───────────────────────────
$lines = ["🔔 <b>Новая заявка №{$leadId}</b>"];
if ($src['source'])  $lines[] = "📄 Форма: " . $src['source'];
if ($src['name'])    $lines[] = "👤 Имя: " . $src['name'];
if ($src['company']) $lines[] = "🏢 Компания: " . $src['company'];
if ($src['phone'])   $lines[] = "📞 Телефон: " . $src['phone'];
if ($src['email'])   $lines[] = "✉️ Email: " . $src['email'];
if ($src['role'])    $lines[] = "🏷️ Роль/способ связи: " . $src['role'];
if ($src['message']) $lines[] = "💬 Сообщение:\n" . $src['message'];
if ($src['items'])   $lines[] = "🛒 Состав заявки:\n" . $src['items'];
$lines[] = "🕐 " . date('d.m.Y H:i');
$text = implode("\n", $lines);

$tg = send_telegram($text);
if ($tg) {
    db()->prepare('UPDATE leads SET tg_sent = 1 WHERE id = ?')->execute([$leadId]);
}

json_out(['ok' => true, 'id' => $leadId, 'tg' => $tg]);
