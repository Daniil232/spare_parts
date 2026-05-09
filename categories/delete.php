<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin(); // Только администратор

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    die("Категория не найдена");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    
    header('Location: index.php?deleted=1');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Удаление категории</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; padding: 50px; }
        .container { max-width: 500px; margin: 0 auto; }
        .card { background: white; border-radius: 24px; padding: 30px; text-align: center; }
        .btn-danger { background: #dc3545; border-radius: 40px; padding: 10px 25px; border: none; color: white; }
        .btn-secondary { background: #6c757d; border-radius: 40px; padding: 10px 25px; text-decoration: none; color: white; display: inline-block; margin-left: 10px; }
        .category-name { font-weight: 700; color: #c62828; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <h1>🗑️ Подтверждение удаления</h1>
        <p>Вы действительно хотите удалить категорию?</p>
        <p class="category-name">«<?= htmlspecialchars($category['name']) ?>»</p>
        <p class="text-muted small">Запчасти в этой категории останутся, но категория у них будет сброшена.</p>
        
        <form method="POST" style="display: inline;">
            <button type="submit" class="btn-danger">Да, удалить</button>
            <a href="index.php" class="btn-secondary">Отмена</a>
        </form>
    </div>
</div>
</body>
</html>