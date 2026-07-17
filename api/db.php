<?php
/** Подключение к БД (PDO) + вспомогательные функции. */
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            die(json_encode(['ok' => false, 'error' => 'db'], JSON_UNESCAPED_UNICODE));
        }
    }
    return $pdo;
}

/** Очистка текста от HTML/тэгов для безопасного вывода. */
function clean(?string $v): string {
    return trim(strip_tags((string)$v));
}

/** Отправка уведомления в Telegram-бот. */
function send_telegram(string $text): bool {
    if (strpos(TG_TOKEN, 'Example') !== false) return false; // токен не заполнен
    $url = 'https://api.telegram.org/bot' . TG_TOKEN . '/sendMessage';
    $payload = http_build_query([
        'chat_id'    => TG_CHAT,
        'text'       => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ]);
    $ctx = stream_context_create(['http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $payload,
        'timeout' => 12,
    ]]);
    $res = @file_get_contents($url, false, $ctx);
    return $res !== false && strpos($res, '"ok":true') !== false;
}

/** Ответ JSON и выход. */
function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
