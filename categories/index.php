<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin(); // Только администратор

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Управление категориями</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; padding: 30px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 24px; padding: 30px; }
        h1 { font-size: 24px; }
        .btn-add { background: #2c3e50; color: white; border-radius: 40px; padding: 8px 20px; text-decoration: none; display: inline-block; margin-bottom: 20px; }
        .category-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #eee; }
        .category-name { font-size: 16px; }
        .category-name::before { content: "📁 "; }
        .actions a { margin-left: 15px; text-decoration: none; font-size: 18px; }
        .back-link { margin-top: 20px; display: inline-block; color: #6c757d; }
        .empty { text-align: center; padding: 40px; color: #999; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
            <h1>📁 Управление категориями</h1>
            <a href="create.php" class="btn-add">+ Новая категория</a>
        </div>
        
        <?php if (count($categories) > 0): ?>
            <?php foreach ($categories as $cat): ?>
                <div class="category-item">
                    <span class="category-name"><?= htmlspecialchars($cat['name']) ?></span>
                    <div class="actions">
                        <a href="edit.php?id=<?= $cat['id'] ?>" title="Редактировать">✏️</a>
                        <a href="delete.php?id=<?= $cat['id'] ?>" title="Удалить" onclick="return confirm('Удалить категорию? Запчасти останутся без категории.')">🗑️</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty">
                📂 Категорий пока нет<br>
                <a href="create.php" style="margin-top: 10px; display: inline-block;">Создать первую категорию</a>
            </div>
        <?php endif; ?>
        
        <hr>
        <a href="../admin/index.php" class="back-link">← Назад в админ-панель</a>
    </div>
</div>
</body>
</html>