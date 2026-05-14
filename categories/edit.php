<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    die("Категория не найдена");
}

// Получаем все категории для выпадающего списка (исключая текущую и её потомков)
$stmt = $pdo->query("SELECT id, parent_id, name FROM categories ORDER BY name");
$allCategories = $stmt->fetchAll();

function buildCategoryTreeSelect($categories, $selectedId = null, $currentId = null, $parentId = null, $level = 0) {
    $html = '';
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == $parentId && $cat['id'] != $currentId) {
            $indent = str_repeat('—', $level) . ($level > 0 ? ' ' : '');
            $selected = ($selectedId == $cat['id']) ? 'selected' : '';
            $html .= '<option value="' . $cat['id'] . '" ' . $selected . '>' . $indent . htmlspecialchars($cat['name']) . '</option>';
            $html .= buildCategoryTreeSelect($categories, $selectedId, $currentId, $cat['id'], $level + 1);
        }
    }
    return $html;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $parent_id = $_POST['parent_id'] ?? null;
    
    if (empty($name)) {
        $error = 'Название категории обязательно';
    } else {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        
        $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, parent_id = ? WHERE id = ?");
        $stmt->execute([$name, $slug, $parent_id ?: null, $id]);
        
        $success = "Категория обновлена";
        
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $category = $stmt->fetch();
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Редактирование категории</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        /* Дополнительные стили для этой страницы */
        body {
            background: #f0f2f5;
        }
        .container {
            max-width: 550px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 24px;
            padding: 28px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            border: 1px solid #eef2f6;
        }
        h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #1a1a2e;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #6c757d;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
            color: #2c3e50;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 500;
            font-size: 14px;
            margin-bottom: 8px;
            color: #1a1a2e;
        }
        .form-control, .form-select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 14px;
            transition: border 0.2s;
        }
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #2c3e50;
        }
        .btn-save {
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 40px;
            padding: 10px 24px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            transition: 0.2s;
        }
        .btn-save:hover {
            background: #1a252f;
            transform: translateY(-1px);
        }
        .alert {
            border-radius: 16px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .alert-danger {
            background: #ffebee;
            color: #c62828;
        }
        .hint {
            font-size: 12px;
            color: #6c757d;
            margin-top: 6px;
        }
        
        /* Мобильная адаптация */
        @media (max-width: 768px) {
            .card {
                padding: 20px;
                border-radius: 20px;
            }
            h1 {
                font-size: 22px;
            }
            .form-control, .form-select {
                padding: 10px 12px;
                font-size: 14px;
            }
            .btn-save {
                padding: 12px 20px;
                font-size: 14px;
            }
        }
        
        @media (max-width: 480px) {
            .card {
                padding: 16px;
                border-radius: 18px;
            }
            h1 {
                font-size: 20px;
            }
            .back-link {
                font-size: 13px;
                margin-bottom: 16px;
            }
            .form-group {
                margin-bottom: 16px;
            }
            label {
                font-size: 13px;
                margin-bottom: 6px;
            }
            .form-control, .form-select {
                padding: 8px 12px;
                font-size: 13px;
            }
            .btn-save {
                padding: 10px 16px;
                font-size: 13px;
            }
            .hint {
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link">← Назад к категориям</a>
    
    <div class="card">
        <h1>✏️ Редактирование категории</h1>
        <p class="text-muted mb-4">ID: <?= $category['id'] ?></p>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Название категории *</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($category['name']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>Родительская категория</label>
                <select name="parent_id" class="form-select">
                    <option value="">— Корневая категория (без родителя) —</option>
                    <?= buildCategoryTreeSelect($allCategories, $category['parent_id'], $category['id']) ?>
                </select>
                <div class="hint">Выберите родительскую категорию, чтобы сделать эту категорию подкатегорией</div>
            </div>
            
            <button type="submit" class="btn-save">💾 Сохранить изменения</button>
        </form>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>