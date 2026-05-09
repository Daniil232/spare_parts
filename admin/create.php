<?php
require_once '../includes/config.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $catalog_number = trim($_POST['catalog_number'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'in_stock';
    $location = trim($_POST['location'] ?? '');
    $category_id = $_POST['category_id'] ?? null;
    
    if (empty($name)) {
        $error = 'Наименование обязательно для заполнения';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO parts (name, catalog_number, description, status, location, category_id, created_at, updated_at, created_by)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
        ");
        $stmt->execute([$name, $catalog_number, $description, $status, $location, $category_id ?: null, $_SESSION['user_id']]);
        $partId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("
            INSERT INTO operations (part_id, operation_type, description, date, created_at)
            VALUES (?, 'arrival', ?, NOW(), NOW())
        ");
        $stmt->execute([$partId, "Создание цифрового паспорта: " . $name]);
        
        $success = "Запчасть успешно создана! <a href='index.php'>Вернуться к списку</a>";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Создание запчасти</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; padding: 30px; }
        .container { max-width: 700px; margin: 0 auto; }
        .card { background: white; border-radius: 24px; padding: 30px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); }
        h1 { font-size: 24px; margin-bottom: 8px; }
        .back-link { margin-bottom: 20px; display: inline-block; }
        .btn-save { background: #2c3e50; color: white; border: none; border-radius: 40px; padding: 12px 30px; }
        .form-control, .form-select { border-radius: 12px; padding: 10px 15px; }
        label { font-weight: 500; margin-bottom: 6px; }
        .alert { border-radius: 16px; }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link">← Назад к списку</a>
    
    <div class="card">
        <h1>➕ Создание цифрового паспорта</h1>
        <p class="text-muted mb-4">Заполните информацию о запчасти</p>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label>Наименование *</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Каталожный номер</label>
                    <input type="text" name="catalog_number" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label>Статус</label>
                    <select name="status" class="form-select">
                        <option value="in_stock">В наличии</option>
                        <option value="under_repair">В ремонте</option>
                        <option value="installed">Установлена</option>
                        <option value="sold">Продана</option>
                        <option value="written_off">Списана</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Местоположение</label>
                    <input type="text" name="location" class="form-control" placeholder="Склад А-1, Стеллаж 3">
                </div>
                <div class="col-md-6 mb-3">
                    <label>Категория</label>
                    <select name="category_id" class="form-select">
                        <option value="">— Без категории —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="mb-4">
                <label>Описание</label>
                <textarea name="description" class="form-control" rows="4" placeholder="Дополнительная информация о запчасти..."></textarea>
            </div>
            
            <button type="submit" class="btn-save">💾 Сохранить</button>
        </form>
    </div>
</div>
</body>
</html>