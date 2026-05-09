<?php
require_once 'includes/config.php';
require_once 'includes/phpqrcode.php';

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