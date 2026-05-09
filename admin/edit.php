<?php
require_once '../includes/config.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM parts WHERE id = ?");
$stmt->execute([$id]);
$part = $stmt->fetch();

if (!$part) {
    die("Запчасть не найдена");
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
        $error = 'Наименование обязательно';
    } else {
        $stmt = $pdo->prepare("
            UPDATE parts 
            SET name = ?, catalog_number = ?, description = ?, status = ?, location = ?, category_id = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$name, $catalog_number, $description, $status, $location, $category_id ?: null, $id]);
        
        $success = "Изменения сохранены!";
        
        $stmt = $pdo->prepare("SELECT * FROM parts WHERE id = ?");
        $stmt->execute([$id]);
        $part = $stmt->fetch();
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование запчасти</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; padding: 30px; }
        .container { max-width: 700px; margin: 0 auto; }
        .card { background: white; border-radius: 24px; padding: 30px; }
        h1 { font-size: 24px; }
        .back-link { margin-bottom: 20px; display: inline-block; }
        .btn-save { background: #2c3e50; color: white; border-radius: 40px; padding: 12px 30px; border: none; }
        .form-control, .form-select { border-radius: 12px; }
        label { font-weight: 500; }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link">← Назад к списку</a>
    
    <div class="card">
        <h1>✏️ Редактирование запчасти</h1>
        <p class="text-muted mb-4">ID: <?= $part['id'] ?></p>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label>Наименование *</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($part['name']) ?>" required>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Каталожный номер</label>
                    <input type="text" name="catalog_number" class="form-control" value="<?= htmlspecialchars($part['catalog_number'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label>Статус</label>
                    <select name="status" class="form-select">
                        <option value="in_stock" <?= $part['status'] == 'in_stock' ? 'selected' : '' ?>>В наличии</option>
                        <option value="under_repair" <?= $part['status'] == 'under_repair' ? 'selected' : '' ?>>В ремонте</option>
                        <option value="installed" <?= $part['status'] == 'installed' ? 'selected' : '' ?>>Установлена</option>
                        <option value="sold" <?= $part['status'] == 'sold' ? 'selected' : '' ?>>Продана</option>
                        <option value="written_off" <?= $part['status'] == 'written_off' ? 'selected' : '' ?>>Списана</option>
                    </select>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Местоположение</label>
                    <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($part['location'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label>Категория</label>
                    <select name="category_id" class="form-select">
                        <option value="">— Без категории —</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $part['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="mb-4">
                <label>Описание</label>
                <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($part['description'] ?? '') ?></textarea>
            </div>
            
            <button type="submit" class="btn-save">💾 Сохранить изменения</button>
        </form>
    </div>
</div>
</body>
</html>