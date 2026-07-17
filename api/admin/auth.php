<?php
/** Защита сессии + общий layout админки. Подключается в начале каждой страницы. */
require_once __DIR__ . '/../db.php';
session_start();

function is_logged(): bool { return !empty($_SESSION['admin_id']); }
function require_login(): void { if (!is_logged()) { header('Location: login.php'); exit; } }

/** Минимальный безопасный вывод в HTML. */
function h(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** Шапка админки с навигацией. */
function admin_head(string $title): void {
    $cur = basename($_SERVER['PHP_SELF']);
    $nav = [
        'index.php'    => 'Заявки',
        'products.php' => 'Каталог',
        'photos.php'   => 'Фото',
    ];
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . h($title) . ' · Админка Шэнда</title>';
    echo '<link rel="stylesheet" href="admin.css">';
    echo '</head><body><header class="topbar"><div class="topbar__in">';
    echo '<a class="brand" href="index.php"><b>ШЭНДА</b> · админка</a>';
    echo '<nav class="topnav">';
    foreach ($nav as $f => $l) {
        echo '<a href="' . $f . '"' . ($cur === $f ? ' class="active"' : '') . '>' . $l . '</a>';
    }
    echo '</nav>';
    echo '<a class="out" href="logout.php">Выйти</a>';
    echo '</div></header><main class="wrap">';
}

function admin_foot(): void { echo '</main></body></html>'; }
