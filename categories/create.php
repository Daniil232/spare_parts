<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin(); // Только администратор

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    
    if (empty($name)) {
        $error = 'Название категории обязательно';
    } else {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$name, $slug]);
        
        $success = "Категория создана! <a href='index.php'>Вернуться к списку</a>";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Создание категории</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; padding: 30px; }
        .container { max-width: 500px; margin: 0 auto; }
        .card { background: white; border-radius: 24px; padding: 30px; }
        h1 { font-size: 24px; }
        .back-link { margin-bottom: 20px; display: inline-block; }
        .btn-save { background: #2c3e50; color: white; border: none; border-radius: 40px; padding: 10px 25px; }
        .form-control { border-radius: 12px; }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link">← Назад к категориям</a>
    
    <div class="card">
        <h1>➕ Новая категория</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label>Название категории</label>
                <input type="text" name="name" class="form-control" required autofocus>
                <div class="form-text">Пример: Двигатель, Коробка передач, Гидравлика</div>
            </div>
            <button type="submit" class="btn-save">💾 Сохранить</button>
        </form>
    </div>
</div>
</body>
</html>