<?php
require_once '../includes/config.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Получаем название запчасти для сообщения
$stmt = $pdo->prepare("SELECT name FROM parts WHERE id = ?");
$stmt->execute([$id]);
$part = $stmt->fetch();

if (!$part) {
    die("Запчасть не найдена");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Удаляем запчасть (операции и фото удалятся автоматически благодаря CASCADE)
    $stmt = $pdo->prepare("DELETE FROM parts WHERE id = ?");
    $stmt->execute([$id]);
    
    header('Location: index.php?deleted=1');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Удаление запчасти</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; padding: 50px; }
        .container { max-width: 500px; margin: 0 auto; }
        .card { background: white; border-radius: 24px; padding: 30px; text-align: center; }
        .btn-danger { background: #dc3545; border-radius: 40px; padding: 10px 25px; }
        .btn-secondary { background: #6c757d; border-radius: 40px; padding: 10px 25px; }
        .part-name { font-weight: 700; color: #c62828; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>🗑️ Подтверждение удаления</h1>
        <p>Вы действительно хотите удалить запчасть?</p>
        <p class="part-name">«<?= htmlspecialchars($part['name']) ?>»</p>
        <p class="text-muted small">Это действие невозможно отменить. Все операции и фотографии будут удалены.</p>
        
        <form method="POST" style="display: inline;">
            <button type="submit" class="btn-danger">Да, удалить</button>
            <a href="index.php" class="btn-secondary">Отмена</a>
        </form>
    </div>
</div>
</body>
</html>