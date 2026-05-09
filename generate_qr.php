<?php
require_once 'includes/config.php';
require_once 'includes/phpqrcode.php';

// Проверка авторизации
session_start();
if (!isset($_SESSION['user_id'])) {
    // Если не авторизован — показываем страницу с ошибкой
    header('HTTP/1.0 403 Forbidden');
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Доступ запрещён</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .modal-container {
                background: white;
                border-radius: 32px;
                max-width: 400px;
                width: 100%;
                padding: 40px 32px;
                text-align: center;
            }
            .icon { font-size: 64px; margin-bottom: 20px; }
            h1 { font-size: 28px; margin-bottom: 12px; color: #c62828; }
            .message { font-size: 16px; color: #555; margin-bottom: 20px; }
            .btn-back { background: #2c3e50; color: white; border: none; border-radius: 40px; padding: 12px 28px; text-decoration: none; display: inline-block; }
            .btn-back:hover { background: #1a252f; }
        </style>
    </head>
    <body>
        <div class="modal-container">
            <div class="icon">🔒</div>
            <h1>Доступ запрещён</h1>
            <div class="message">
                Скачивание QR-кода доступно только авторизованным сотрудникам.
            </div>
            <a href="javascript:history.back()" class="btn-back">← Вернуться назад</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT name, catalog_number FROM parts WHERE id = ?");
$stmt->execute([$id]);
$part = $stmt->fetch();

if (!$part) {
    die("Запчасть не найдена");
}

// Формируем URL для публичной страницы
$url = "http://" . $_SERVER['HTTP_HOST'] . "/spare_parts/part.php?id=" . $id;

// Устанавливаем заголовки для скачивания PNG
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="qr_part_' . $id . '.png"');

// Генерируем QR-код
QRcode::png($url, null, QR_ECLEVEL_L, 8);
exit;
?>