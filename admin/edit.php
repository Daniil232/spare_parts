<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM parts WHERE id = ?");
$stmt->execute([$id]);
$part = $stmt->fetch();

if (!$part) {
    die("Запчасть не найдена");
}

// Получаем ВСЕ категории для выпадающего списка
$allCategories = $pdo->query("SELECT id, parent_id, name FROM categories ORDER BY parent_id, name")->fetchAll();

// Функция построения дерева
function buildCategorySelect($categories, $selectedId = null, $parentId = null, $level = 0) {
    $html = '';
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == $parentId) {
            $indent = str_repeat('—', $level) . ($level > 0 ? ' ' : '');
            $selected = ($selectedId == $cat['id']) ? 'selected="selected"' : '';
            $html .= '<option value="' . $cat['id'] . '" ' . $selected . '>' . $indent . htmlspecialchars($cat['name']) . '</option>';
            $html .= buildCategorySelect($categories, $selectedId, $cat['id'], $level + 1);
        }
    }
    return $html;
}

// Получаем текущие фотографии
$stmt = $pdo->prepare("SELECT * FROM photos WHERE part_id = ? ORDER BY sort_order");
$stmt->execute([$id]);
$photos = $stmt->fetchAll();

$success = '';
$error = '';

// Загрузка новых фотографий
if (isset($_POST['upload_photos']) && isset($_FILES['photos'])) {
    $files = $_FILES['photos'];
    $currentCount = count($photos);
    $uploaded = 0;
    $maxPhotos = 5;
    $availableSlots = $maxPhotos - $currentCount;
    
    if ($availableSlots <= 0) {
        $error = "Максимум $maxPhotos фотографий. Удалите лишние перед загрузкой новых.";
    } else {
        $fileCount = min(count($files['name']), $availableSlots);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($ext, $allowed)) {
                    $filename = time() . '_' . rand(1000, 9999) . '_' . $i . '.' . $ext;
                    $uploadPath = '../assets/uploads/parts/' . $filename;
                    if (move_uploaded_file($files['tmp_name'][$i], $uploadPath)) {
                        $sortOrder = $currentCount + $uploaded;
                        $stmt = $pdo->prepare("INSERT INTO photos (part_id, file_path, sort_order, uploaded_at) VALUES (?, ?, ?, NOW())");
                        $stmt->execute([$id, $filename, $sortOrder]);
                        $uploaded++;
                    }
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
    }
}

// Удаление фотографии
if (isset($_GET['delete_photo'])) {
    $photoId = (int)$_GET['delete_photo'];
    
    // Получаем путь к файлу
    $stmt = $pdo->prepare("SELECT file_path FROM photos WHERE id = ? AND part_id = ?");
    $stmt->execute([$photoId, $id]);
    $photo = $stmt->fetch();
    
    if ($photo) {
        // 1. Удаляем файл с сервера
        $filePath = '../assets/uploads/parts/' . $photo['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // 2. Удаляем запись из базы данных
        $stmt = $pdo->prepare("DELETE FROM photos WHERE id = ?");
        $stmt->execute([$photoId]);
        
        // 3. Обновляем порядок сортировки оставшихся фотографий
        $stmt = $pdo->prepare("SELECT id FROM photos WHERE part_id = ? ORDER BY sort_order");
        $stmt->execute([$id]);
        $remainingPhotos = $stmt->fetchAll();
        
        foreach ($remainingPhotos as $index => $row) {
            $stmt = $pdo->prepare("UPDATE photos SET sort_order = ? WHERE id = ?");
            $stmt->execute([$index, $row['id']]);
        }
        
        $success = "Фотография удалена";
        
        // 4. Обновляем список фотографий для отображения
        $stmt = $pdo->prepare("SELECT * FROM photos WHERE part_id = ? ORDER BY sort_order");
        $stmt->execute([$id]);
        $photos = $stmt->fetchAll();
    }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование запчасти</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f0f2f5;font-family:'Segoe UI',system-ui,sans-serif;padding:30px}
        .container{max-width:800px;margin:0 auto}
        .card{background:white;border-radius:24px;padding:32px;margin-bottom:24px}
        h1{font-size:24px;margin-bottom:8px}
        h2{font-size:18px;margin-bottom:16px}
        .back-link{margin-bottom:20px;display:inline-block;color:#6c757d;text-decoration:none}
        .btn-save{background:#2c3e50;color:white;border:none;border-radius:40px;padding:12px30px;cursor:pointer; font-size: 15px;padding: 8px 20px;}
        .btn-history{background:#17a2b8;text-decoration:none;display:inline-block;margin-left:10px}
        .btn-upload{background:#27ae60;padding:10px20px}
        .form-control,.form-select{border-radius:12px;padding:10px14px;border:1px solid #ddd;width:100%}
        label{font-weight:500;margin-bottom:6px;display:block}
        .form-text{font-size:12px;color:#6c757d;margin-top:4px}
        .alert{border-radius:16px;padding:12px16px;margin-bottom:20px}
        .row{display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap}
        .col-md-6{flex:1;min-width:200px}
        .photo-item{display:inline-block;margin:10px;text-align:center;vertical-align:top}
        .photo-item img{width:100px;height:100px;object-fit:cover;border-radius:12px}
        .photo-actions{margin-top:5px}
        .photo-actions a{margin:0 3px;text-decoration:none;font-size:14px}
        .current-photos{margin-top:20px}
        .delete-photo{color:#dc3545}
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link">← Назад к списку</a>
    
    <!-- Редактирование информации -->
    <div class="card">
        <h1>✏️ Редактирование запчасти</h1>
        <p class="text-muted mb-4">ID: <?= $part['id'] ?></p>
        
        <?php if($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if($error): ?>
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
                        <?= buildCategorySelect($allCategories, $part['category_id']) ?>
                    </select>
                    <div class="form-text">Выберите категорию из иерархического списка</div>
                </div>
            </div>
            
            <div class="mb-4">
                <label>Описание</label>
                <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($part['description'] ?? '') ?></textarea>
            </div>
            
            <div>
                <button type="submit" name="update_part" class="btn-save">💾 Сохранить изменения</button>
                <a href="add_operation.php?part_id=<?= $id ?>" class="btn-save btn-history">📜 История операций</a>
            </div>
        </form>
    </div>
    
    <!-- Загрузка фотографий -->
    <div class="card">
        <h2>📷 Загрузить фотографии</h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <input type="file" name="photos[]" class="form-control" accept="image/*" multiple>
                <div class="form-text">Можно выбрать до 5 фотографий. Всего может быть максимум 5 фото.</div>
            </div>
            <button type="submit" name="upload_photos" class="btn-save btn-upload">📤 Загрузить</button>
        </form>
        
        <?php if(count($photos) > 0): ?>
            <div class="current-photos">
                <h2>📸 Текущие фотографии (<?= count($photos) ?>/5)</h2>
                <?php foreach ($photos as $photo): ?>
                    <div class="photo-item">
                        <img src="../assets/uploads/parts/<?= htmlspecialchars($photo['file_path']) ?>" alt="Фото">
                        <div class="photo-actions">
                            <a href="?id=<?= $id ?>&delete_photo=<?= $photo['id'] ?>" class="delete-photo" onclick="return confirm('Удалить фото?')">🗑️ Удалить</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>