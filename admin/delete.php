<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT name FROM parts WHERE id = ?");
$stmt->execute([$id]);
$part = $stmt->fetch();

if (!$part) {
    die("Запчасть не найдена");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Получаем все фотографии запчасти
    $stmt = $pdo->prepare("SELECT file_path FROM photos WHERE part_id = ?");
    $stmt->execute([$id]);
    $photos = $stmt->fetchAll();
    
    // 2. Удаляем файлы с сервера
    foreach ($photos as $photo) {
        $filePath = '../assets/uploads/parts/' . $photo['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath); // удаляем файл
        }
    }
    
    // 3. Удаляем запись о фотографиях из БД (CASCADE сделает это автоматически)
    // 4. Удаляем запчасть (операции удалятся по CASCADE)
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
        body {
            background: rgba(0,0,0,0.5);
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-card {
            background: white;
            border-radius: 24px;
            max-width: 400px;
            width: 90%;
            padding: 30px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .modal-card h3 {
            font-size: 22px;
            margin-bottom: 15px;
        }
        .modal-card p {
            font-size: 14px;
            color: #555;
            margin-bottom: 10px;
        }
        .part-name {
            font-weight: 700;
            color: #c62828;
            margin: 10px 0;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 40px;
            padding: 10px 25px;
            margin-right: 10px;
            cursor: pointer;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 40px;
            padding: 10px 25px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
    </style>
</head>
<body>
<div class="modal-card">
    <h3>🗑️ Подтверждение удаления</h3>
    <p>Вы действительно хотите удалить запчасть?</p>
    <p class="part-name">«<?= htmlspecialchars($part['name']) ?>»</p>
    <p class="text-muted small">Это действие невозможно отменить. Все фотографии будут удалены.</p>
    
    <form method="POST" style="display: inline;">
        <button type="submit" class="btn-danger">Да, удалить</button>
        <a href="index.php" class="btn-secondary">Отмена</a>
    </form>
</div>
</body>
</html>