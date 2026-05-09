<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();
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

// Получаем фотографии
$stmt = $pdo->prepare("SELECT * FROM photos WHERE part_id = ? ORDER BY sort_order");
$stmt->execute([$id]);
$photos = $stmt->fetchAll();

$success = '';
$error = '';

// Обработка загрузки нескольких фото
if (isset($_POST['upload_photos']) && isset($_FILES['photos'])) {
    $files = $_FILES['photos'];
    $uploaded = 0;
    $errors = [];
    
    // Получаем текущее количество фото
    $currentCount = count($photos);
    $maxPhotos = 5;
    
    // Проверяем, сколько фото можно загрузить
    $availableSlots = $maxPhotos - $currentCount;
    
    if ($availableSlots <= 0) {
        $error = "Максимум $maxPhotos фотографий. Удалите лишние перед загрузкой новых.";
    } else {
        // Ограничиваем количество загружаемых файлов доступными слотами
        $fileCount = min(count($files['name']), $availableSlots);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (!in_array($ext, $allowed)) {
                    $errors[] = "Файл {$files['name'][$i]} имеет недопустимый формат";
                    continue;
                }
                
                $filename = time() . '_' . rand(1000, 9999) . '_' . $i . '.' . $ext;
                $uploadPath = '../assets/uploads/parts/' . $filename;
                
                if (move_uploaded_file($files['tmp_name'][$i], $uploadPath)) {
                    $sortOrder = $currentCount + $uploaded;
                    $stmt = $pdo->prepare("INSERT INTO photos (part_id, file_path, sort_order, uploaded_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$id, $filename, $sortOrder]);
                    $uploaded++;
                } else {
                    $errors[] = "Ошибка загрузки файла {$files['name'][$i]}";
                }
            }
        }
        
        if ($uploaded > 0) {
            $success = "Загружено фотографий: $uploaded";
            // Обновляем список фото
            $stmt = $pdo->prepare("SELECT * FROM photos WHERE part_id = ? ORDER BY sort_order");
            $stmt->execute([$id]);
            $photos = $stmt->fetchAll();
        }
        
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        }
    }
}

// Удаление фото
if (isset($_GET['delete_photo'])) {
    $photoId = (int)$_GET['delete_photo'];
    $stmt = $pdo->prepare("SELECT file_path FROM photos WHERE id = ? AND part_id = ?");
    $stmt->execute([$photoId, $id]);
    $photo = $stmt->fetch();
    if ($photo) {
        $filePath = '../assets/uploads/parts/' . $photo['file_path'];
        if (file_exists($filePath)) unlink($filePath);
        $stmt = $pdo->prepare("DELETE FROM photos WHERE id = ?");
        $stmt->execute([$photoId]);
        $success = "Фотография удалена";
        // Обновляем порядок сортировки
        $stmt = $pdo->prepare("SELECT * FROM photos WHERE part_id = ? ORDER BY sort_order");
        $stmt->execute([$id]);
        $newPhotos = $stmt->fetchAll();
        foreach ($newPhotos as $idx => $p) {
            $stmt = $pdo->prepare("UPDATE photos SET sort_order = ? WHERE id = ?");
            $stmt->execute([$idx, $p['id']]);
        }
        // Обновляем список фото
        $stmt = $pdo->prepare("SELECT * FROM photos WHERE part_id = ? ORDER BY sort_order");
        $stmt->execute([$id]);
        $photos = $stmt->fetchAll();
    }
}

// Сортировка фото (вверх/вниз)
if (isset($_GET['move_up'])) {
    $photoId = (int)$_GET['move_up'];
    foreach ($photos as $idx => $p) {
        if ($p['id'] == $photoId && $idx > 0) {
            // Меняем местами с предыдущим
            $prevId = $photos[$idx-1]['id'];
            $stmt = $pdo->prepare("UPDATE photos SET sort_order = ? WHERE id = ?");
            $stmt->execute([$idx, $prevId]);
            $stmt->execute([$idx-1, $photoId]);
            break;
        }
    }
    // Обновляем список
    $stmt = $pdo->prepare("SELECT * FROM photos WHERE part_id = ? ORDER BY sort_order");
    $stmt->execute([$id]);
    $photos = $stmt->fetchAll();
    header("Location: edit.php?id=$id");
    exit;
}

if (isset($_GET['move_down'])) {
    $photoId = (int)$_GET['move_down'];
    foreach ($photos as $idx => $p) {
        if ($p['id'] == $photoId && $idx < count($photos)-1) {
            // Меняем местами со следующим
            $nextId = $photos[$idx+1]['id'];
            $stmt = $pdo->prepare("UPDATE photos SET sort_order = ? WHERE id = ?");
            $stmt->execute([$idx, $nextId]);
            $stmt->execute([$idx+1, $photoId]);
            break;
        }
    }
    // Обновляем список
    $stmt = $pdo->prepare("SELECT * FROM photos WHERE part_id = ? ORDER BY sort_order");
    $stmt->execute([$id]);
    $photos = $stmt->fetchAll();
    header("Location: edit.php?id=$id");
    exit;
}

// Обновление информации о запчасти
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_part'])) {
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
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 24px; padding: 30px; margin-bottom: 20px; }
        h1 { font-size: 24px; }
        h2 { font-size: 18px; margin-bottom: 15px; }
        .back-link { margin-bottom: 20px; display: inline-block; }
        .btn-save { background: #2c3e50; color: white; border-radius: 40px; padding: 10px 25px; border: none; }
        .form-control, .form-select { border-radius: 12px; }
        label { font-weight: 500; }
        .photo-item { display: inline-block; margin: 10px; text-align: center; vertical-align: top; }
        .photo-item img { width: 100px; height: 100px; object-fit: cover; border-radius: 12px; }
        .photo-item .photo-actions { margin-top: 5px; }
        .photo-item .photo-actions a { margin: 0 3px; text-decoration: none; font-size: 14px; }
        .current-photos { margin-top: 20px; }
        .photo-limit { color: #6c757d; font-size: 12px; margin-top: 5px; }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link">← Назад к списку</a>
    
    <!-- Редактирование информации -->
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
            
            <button type="submit" name="update_part" class="btn-save">💾 Сохранить изменения</button>
            <a href="add_operation.php?part_id=<?= $id ?>" class="btn-save" style="background: #17a2b8; text-decoration: none; display: inline-block; margin-left: 10px;">📜 История операций</a>
        </form>
    </div>
    
    <!-- Загрузка нескольких фотографий -->
    <div class="card">
        <h2>📷 Загрузить фотографии</h2>
        <p class="text-muted">Можно загрузить до 5 фотографий. Поддерживаются JPG, PNG, GIF, WEBP.</p>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <input type="file" name="photos[]" class="form-control" accept="image/*" multiple>
                <div class="photo-limit">Выберите несколько файлов (Ctrl+клик или Shift+клик для выбора нескольких)</div>
            </div>
            <button type="submit" name="upload_photos" class="btn-save" style="background: #27ae60;">📤 Загрузить выбранные</button>
        </form>
        
        <?php if (count($photos) > 0): ?>
            <div class="current-photos">
                <h2>📸 Текущие фотографии (<?= count($photos) ?>/5)</h2>
                <div class="photo-list">
                    <?php foreach ($photos as $idx => $photo): ?>
                        <div class="photo-item">
                            <img src="../assets/uploads/parts/<?= htmlspecialchars($photo['file_path']) ?>" alt="Фото">
                            <div class="photo-actions">
                                <?php if ($idx > 0): ?>
                                    <a href="?id=<?= $id ?>&move_up=<?= $photo['id'] ?>">⬆️</a>
                                <?php endif; ?>
                                <?php if ($idx < count($photos) - 1): ?>
                                    <a href="?id=<?= $id ?>&move_down=<?= $photo['id'] ?>">⬇️</a>
                                <?php endif; ?>
                                <a href="?id=<?= $id ?>&delete_photo=<?= $photo['id'] ?>" class="delete-photo" onclick="return confirm('Удалить фото?')">🗑️</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>