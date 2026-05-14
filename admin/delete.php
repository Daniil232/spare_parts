<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Удаление категории</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: rgba(0, 0, 0, 0.5);
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-card {
            background: white;
            border-radius: 28px;
            max-width: 450px;
            width: 100%;
            padding: 30px 24px;
            text-align: center;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            animation: fadeIn 0.2s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal-card h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #1a1a2e;
        }
        .modal-card .icon {
            font-size: 56px;
            margin-bottom: 16px;
        }
        .modal-card p {
            font-size: 15px;
            color: #555;
            margin-bottom: 8px;
        }
        .category-name {
            font-weight: 700;
            color: #c62828;
            font-size: 18px;
            margin: 12px 0;
            padding: 10px;
            background: #ffebee;
            border-radius: 16px;
            word-break: break-word;
        }
        .warning-text {
            font-size: 13px;
            color: #6c757d;
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid #eef2f6;
        }
        .button-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 24px;
            flex-wrap: wrap;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 40px;
            padding: 12px 28px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            flex: 1;
            min-width: 120px;
        }
        .btn-danger:hover { background: #c82333; transform: translateY(-1px); }
        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 40px;
            padding: 12px 28px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            flex: 1;
            min-width: 120px;
        }
        .btn-secondary:hover { background: #5a6268; transform: translateY(-1px); color: white; }
        
        @media (max-width: 480px) {
            body { padding: 16px; }
            .modal-card { padding: 24px 20px; border-radius: 24px; }
            .modal-card h3 { font-size: 20px; }
            .modal-card .icon { font-size: 48px; }
            .category-name { font-size: 16px; padding: 8px 12px; }
            .btn-danger, .btn-secondary { padding: 10px 20px; font-size: 14px; min-width: 100px; }
        }
        @media (max-width: 360px) {
            .button-group { flex-direction: column; }
            .btn-danger, .btn-secondary { width: 100%; }
        }
    </style>
</head>
<body>
<div class="modal-card">
    <div class="icon">🗑️</div>
    <h3>Подтверждение удаления</h3>
    <p>Вы действительно хотите удалить категорию?</p>
    <div class="category-name">«<?= htmlspecialchars($category['name']) ?>»</div>
    <p class="warning-text">⚠️ Запчасти в этой категории останутся, но категория у них будет сброшена.</p>
    
    <div class="button-group">
        <form method="POST" style="flex: 1;">
            <button type="submit" class="btn-danger">Да, удалить</button>
        </form>
        <a href="index.php" class="btn-secondary">Отмена</a>
    </div>
</div>
</body>
</html>