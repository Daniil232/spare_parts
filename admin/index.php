<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

// Получаем параметры поиска и фильтрации
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$category_filter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Базовый запрос
$sql = "
    SELECT p.*, c.name as category_name 
    FROM parts p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE 1=1
";
$params = [];

if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.catalog_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

if ($category_filter > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_filter;
}

$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$parts = $stmt->fetchAll();

// Получаем категории для фильтра
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Статусы для фильтра
$statuses = [
    'in_stock' => 'В наличии',
    'under_repair' => 'В ремонте',
    'installed' => 'Установлена',
    'sold' => 'Продана',
    'written_off' => 'Списана'
];
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; padding: 24px; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px; }
        h1 { margin: 0; font-size: 24px; }
        .btn-add { background: #2c3e50; color: white; padding: 10px 20px; border-radius: 40px; text-decoration: none; display: inline-block; }
        .btn-add:hover { background: #1a252f; color: white; }
        .btn-danger { background: #dc3545; }
        .btn-secondary { background: #6c757d; }
        .btn-view { background: #17a2b8; color: white; padding: 4px 12px; border-radius: 20px; text-decoration: none; font-size: 12px; display: inline-block; }
        .btn-view:hover { background: #138496; color: white; }
        
        .table-card { background: white; border-radius: 24px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fc; padding: 15px 20px; text-align: left; font-weight: 600; }
        td { padding: 12px 20px; border-top: 1px solid #eee; transition: background 0.2s ease; }
        
        /* Выделение строки при наведении */
        tbody tr:hover { background: #e8f4f8; cursor: pointer; }
        tbody tr:hover td { background: #e8f4f8; }
        
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 12px; font-weight: 600; }
        .status-in_stock { background: #e8f5e9; color: #2e7d32; }
        .status-under_repair { background: #fff3e0; color: #e65100; }
        .status-installed { background: #e3f2fd; color: #1565c0; }
        .status-sold { background: #eceff1; color: #546e7a; }
        .status-written_off { background: #ffebee; color: #c62828; }
        
        .action-link { margin: 0 5px; text-decoration: none; font-size: 18px; cursor: pointer; }
        .filter-form { background: white; border-radius: 24px; padding: 20px; margin-bottom: 20px; }
        .filter-form .row { align-items: flex-end; }
        
        /* Тултип при наведении */
        .part-name { font-weight: 600; }
        .part-name small { font-weight: normal; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📄 Цифровые паспорта запчастей</h1>
        <div>
            <a href="create.php" class="btn-add">+ Новая запчасть</a>
            
            <?php if (isAdmin()): ?>
                <a href="../categories/index.php" class="btn-add btn-secondary">📁 Категории</a>
                <a href="users.php" class="btn-add btn-secondary">👥 Пользователи</a>
            <?php endif; ?>
            
            <a href="export.php?<?= $_SERVER['QUERY_STRING'] ?>" class="btn-add" style="background: #27ae60;">📎 Экспорт в Excel</a>
            <a href="logout.php" class="btn-add btn-danger">🚪 Выйти</a>
        </div>
    </div>
    
    <!-- Форма поиска и фильтрации -->
    <div class="filter-form">
        <form method="GET">
            <div class="row">
                <div class="col-md-4 mb-2">
                    <input type="text" name="search" class="form-control" placeholder="🔍 Поиск по названию или артикулу" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3 mb-2">
                    <select name="status" class="form-select">
                        <option value="">Все статусы</option>
                        <?php foreach ($statuses as $val => $text): ?>
                            <option value="<?= $val ?>" <?= $status_filter == $val ? 'selected' : '' ?>><?= $text ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <select name="category_id" class="form-select">
                        <option value="0">Все категории</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category_filter == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <button type="submit" class="btn-save" style="background: #2c3e50; color: white; border: none; border-radius: 40px; padding: 8px 20px; width: 100%;">🔍 Фильтровать</button>
                </div>
            </div>
        </form>
    </div>
    
    <div class="table-card">
        <table class="table">
            <thead>
                <tr><th>ID</th><th>Наименование</th><th>Кат.номер</th><th>Статус</th><th>QR</th><th>Действия</th></tr>
            </thead>
            <tbody>
                <?php if (count($parts) > 0): ?>
                    <?php foreach ($parts as $p): ?>
                        <?php
                            switch($p['status']) {
                                case 'in_stock': $status_text = 'В наличии'; $status_class = 'status-in_stock'; break;
                                case 'under_repair': $status_text = 'В ремонте'; $status_class = 'status-under_repair'; break;
                                case 'installed': $status_text = 'Установлена'; $status_class = 'status-installed'; break;
                                case 'sold': $status_text = 'Продана'; $status_class = 'status-sold'; break;
                                case 'written_off': $status_text = 'Списана'; $status_class = 'status-written_off'; break;
                                default: $status_text = $p['status']; $status_class = '';
                            }
                        ?>
                        <tr data-id="<?= $p['id'] ?>">
                            <td><?= $p['id'] ?></td>
                            <td class="part-name">
                                <strong><?= htmlspecialchars($p['name']) ?></strong>
                                <?php if ($p['category_name']): ?>
                                    <br><small class="text-muted">📁 <?= htmlspecialchars($p['category_name']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['catalog_number'] ?? '—') ?></td>
                            <td><span class="status-badge <?= $status_class ?>"><?= $status_text ?></span></td>
                            <td><a href="../generate_qr.php?id=<?= $p['id'] ?>" class="action-link" onclick="event.stopPropagation();">📷</a></td>
                            <td class="actions-cell">
                                <a href="../part.php?id=<?= $p['id'] ?>" target="_blank" class="btn-view" onclick="event.stopPropagation();">👁️ Просмотр</a>
                                <a href="edit.php?id=<?= $p['id'] ?>" class="action-link" onclick="event.stopPropagation();">✏️</a>
                                <?php if (isAdmin()): ?>
                                    <button type="button" class="action-link delete-btn" style="background:none; border:none; cursor:pointer;" data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>" onclick="event.stopPropagation();">🗑️</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted">Нет запчастей. Создайте первую!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Модальное окно удаления -->
<div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 24px; max-width: 400px; width: 90%; padding: 30px; text-align: center;">
        <h3>🗑️ Подтверждение удаления</h3>
        <p>Вы действительно хотите удалить запчасть?</p>
        <p id="deletePartName" style="font-weight: 700; color: #c62828;"></p>
        <div style="margin: 25px 0;">
            <a href="#" id="confirmDeleteBtn" class="btn-save" style="background: #dc3545; padding: 8px 25px; text-decoration: none;">Да, удалить</a>
            <button id="cancelDeleteBtn" style="background: #6c757d; color: white; border: none; border-radius: 40px; padding: 8px 25px;">Отмена</button>
        </div>
    </div>
</div>

<style>
    .btn-save { display: inline-block; background: #2c3e50; color: white; border-radius: 40px; padding: 8px 25px; text-decoration: none; border: none; cursor: pointer; }
    
    /* Клик по строке для просмотра */
    tbody tr { cursor: pointer; }
    .actions-cell { white-space: nowrap; }
</style>

<script>
    // Клик по строке для просмотра запчасти
    document.querySelectorAll('tbody tr').forEach(row => {
        row.addEventListener('click', function(e) {
            // Если клик не по ссылке или кнопке
            if (!e.target.closest('a') && !e.target.closest('button')) {
                const partId = this.getAttribute('data-id');
                if (partId) {
                    window.open('../part.php?id=' + partId, '_blank');
                }
            }
        });
    });
    
    // Модальное окно удаления (только для админа)
    <?php if (isAdmin()): ?>
    const modal = document.getElementById('deleteModal');
    const deletePartNameSpan = document.getElementById('deletePartName');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    const cancelBtn = document.getElementById('cancelDeleteBtn');
    let currentDeleteId = null;
    
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            currentDeleteId = this.getAttribute('data-id');
            deletePartNameSpan.textContent = this.getAttribute('data-name');
            modal.style.display = 'flex';
        });
    });
    
    confirmBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (currentDeleteId) {
            window.location.href = 'delete.php?id=' + currentDeleteId;
        }
    });
    
    cancelBtn.addEventListener('click', function() {
        modal.style.display = 'none';
        currentDeleteId = null;
    });
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
            currentDeleteId = null;
        }
    });
    <?php endif; ?>
</script>
</body>
</html>