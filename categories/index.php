<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

// Обработка создания категории
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_category'])) {
    $name = trim($_POST['name'] ?? '');
    $parent_id = $_POST['parent_id'] ?? null;
    
    if (empty($name)) {
        $error = 'Название категории обязательно';
    } else {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        
        $stmt = $pdo->prepare("INSERT INTO categories (parent_id, name, slug, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$parent_id ?: null, $name, $slug]);
        
        $success = "Категория создана!";
        // Обновляем список категорий
        $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
    }
}

// Удаление категории
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
    $stmt->execute([$id]);
    $hasChildren = $stmt->fetchColumn() > 0;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM parts WHERE category_id = ?");
    $stmt->execute([$id]);
    $hasParts = $stmt->fetchColumn() > 0;
    
    if (!$hasChildren && !$hasParts) {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        $success = "Категория удалена";
    } else {
        $error = "Нельзя удалить категорию, у которой есть подкатегории или запчасти";
    }
}

// Получаем все категории
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Функция построения дерева категорий для отображения
function buildCategoryTreeDisplay($categories, $parentId = null, $level = 0) {
    $html = '';
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == $parentId) {
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
            $icon = $level == 0 ? '📁 ' : '📂 ';
            $html .= '
            <div class="category-item">
                <div class="category-row">
                    <span class="category-name">' . $indent . $icon . htmlspecialchars($cat['name']) . '</span>
                    <div class="category-actions">
                        <a href="edit.php?id=' . $cat['id'] . '" class="action-link" title="Редактировать">✏️</a>
                        <a href="?delete=' . $cat['id'] . '" class="action-link delete-link" title="Удалить" onclick="return confirm(\'Удалить категорию? Запчасти останутся без категории.\')">🗑️</a>
                    </div>
                </div>
            </div>';
            $html .= buildCategoryTreeDisplay($categories, $cat['id'], $level + 1);
        }
    }
    return $html;
}

// Функция построения дерева для выпадающего списка (выбор родителя)
function buildCategoryTreeSelect($categories, $selectedId = null, $parentId = null, $level = 0) {
    $html = '';
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == $parentId) {
            $indent = str_repeat('--', $level) . ($level > 0 ? ' ' : '');
            $selected = ($selectedId == $cat['id']) ? 'selected' : '';
            $html .= '<option value="' . $cat['id'] . '" ' . $selected . '>' . $indent . htmlspecialchars($cat['name']) . '</option>';
            $html .= buildCategoryTreeSelect($categories, $selectedId, $cat['id'], $level + 1);
        }
    }
    return $html;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление категориями</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Segoe UI', system-ui, sans-serif}
        .container { max-width: 900px; margin: 0 auto; }
        
        .card { background: white; border-radius: 24px; padding: 24px; margin-bottom: 24px; }
        h1 { font-size: 24px; margin-bottom: 8px; }
        .back-link { margin-bottom: 20px; display: inline-block; color: #6c757d; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        
        /* Форма создания */
        .create-form { background: #f8f9fc; border-radius: 16px; padding: 20px; margin-bottom: 24px; }
        .create-form h3 { font-size: 18px; margin-bottom: 16px; }
        .form-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
        .form-group { flex: 1; min-width: 200px; }
        .form-group label { display: block; font-size: 13px; margin-bottom: 5px; font-weight: 500; }
        .form-control, .form-select { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 12px; font-size: 14px; }
        .btn-save { background: #2c3e50; color: white; border: none; border-radius: 40px; padding: 10px 24px; cursor: pointer; font-size: 14px; }
        .btn-save:hover { background: #1a252f; }
        
        /* Список категорий */
        .categories-list { max-height: 500px; overflow-y: auto; }
        .category-item { border-bottom: 1px solid #eef2f6; }
        .category-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 8px;
        }
        .category-name { font-size: 14px; }
        .category-actions { display: flex; gap: 12px; }
        .action-link { text-decoration: none; font-size: 18px; color: #6c757d; }
        .action-link:hover { color: #2c3e50; }
        .delete-link:hover { color: #dc3545; }
        
        .alert { border-radius: 16px; padding: 12px 16px; margin-bottom: 20px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .alert-danger { background: #ffebee; color: #c62828; }
        
        hr { margin: 20px 0; border-color: #eef2f6; }
        
        /* Подсказка */
        .hint { font-size: 12px; color: #6c757d; margin-top: 8px; }
    </style>
</head>
<body>
<div class="container">
    <a href="../admin/index.php" class="back-link">← Назад в админ-панель</a>
    
    <div class="card">
        <h1>📁 Управление категориями</h1>
        <p class="text-muted">Организуйте запчасти по иерархическим категориям</p>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <!-- Форма создания категории -->
        <div class="create-form">
            <h3>➕ Добавить новую категорию</h3>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>Название категории *</label>
                        <input type="text" name="name" class="form-control" required placeholder="Например: Корпус редуктора">
                    </div>
                    <div class="form-group">
                        <label>Родительская категория</label>
                        <select name="parent_id" class="form-select">
                            <option value="">— Корневая категория (без родителя) —</option>
                            <?= buildCategoryTreeSelect($categories) ?>
                        </select>
                    </div>
                    <div class="form-group-btn">
                        <button type="submit" name="create_category" class="btn-save">💾 Сохранить</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Список категорий (дерево) -->
        <h3 style="font-size: 18px; margin-bottom: 16px;">📋 Текущие категории</h3>
        <div class="categories-list">
            <?php if (count($categories) > 0): ?>
                <?= buildCategoryTreeDisplay($categories) ?>
            <?php else: ?>
                <p class="text-muted">Категорий пока нет. Создайте первую!</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>