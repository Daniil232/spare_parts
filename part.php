<?php
// part.php
require_once 'includes/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM parts WHERE id = ?");
$stmt->execute([$id]);
$part = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$part) {
    die("Запчасть не найдена");
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Паспорт: <?= htmlspecialchars($part['name']) ?></title>
</head>
<body>
    <h1><?= htmlspecialchars($part['name']) ?></h1>
    <p>Каталожный номер: <?= htmlspecialchars($part['catalog_number'] ?? '—') ?></p>
    <p>Статус: <?= $part['status'] ?></p>
    <p>Местоположение: <?= htmlspecialchars($part['location'] ?? '—') ?></p>
    <p><a href="generate_qr.php?id=<?= $part['id'] ?>">Скачать QR-код</a></p>
</body>
</html>