<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

// Получаем параметры поиска и фильтрации
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$category_filter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Если выбрана категория, получаем все её подкатегории включительно
$category_ids = [];
if ($category_filter > 0) {
    // Рекурсивно получаем все ID подкатегорий
    $stmt = $pdo->prepare("
        WITH RECURSIVE cat_tree AS (
            SELECT id FROM categories WHERE id = ?
            UNION ALL
            SELECT c.id FROM categories c
            INNER JOIN cat_tree ct ON c.parent_id = ct.id
        )
        SELECT id FROM cat_tree
    ");
    $stmt->execute([$category_filter]);
    $category_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

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

if (!empty($category_ids)) {
    $placeholders = implode(',', array_fill(0, count($category_ids), '?'));
    $sql .= " AND p.category_id IN ($placeholders)";
    $params = array_merge($params, $category_ids);
}

$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$parts = $stmt->fetchAll();

// Получаем все категории для дерева
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Статусы для фильтра
$statuses = [
    'in_stock' => 'В наличии',
    'under_repair' => 'В ремонте',
    'installed' => 'Установлена',
    'sold' => 'Продана',
    'written_off' => 'Списана'
];

// Функция построения дерева категорий
function buildCategoryTree($categories, $parentId = null, $level = 0) {
    $html = '';
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == $parentId) {
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
            $icon = $level == 0 ? '📁 ' : '📂 ';
            $html .= '<div class="category-item" data-cat-id="' . $cat['id'] . '">';
            $html .= '<span class="category-name">' . $indent . $icon . htmlspecialchars($cat['name']) . '</span>';
            $html .= '</div>';
            $html .= buildCategoryTree($categories, $cat['id'], $level + 1);
        }
    }
    return $html;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Админ-панель</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', system-ui, sans-serif;
            overflow-x: hidden;
        }
        
        /* Шапка */
        .top-header {
            background: #1a1a2e;
            color: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .logo {
            font-size: 16px;
            font-weight: 600;
        }
        .logo span { font-size: 22px; margin-right: 6px; }
        .nav-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        .nav-buttons a {
            color: white;
            text-decoration: none;
            padding: 5px 12px;
            border-radius: 40px;
            background: rgba(255,255,255,0.1);
            font-size: 13px;
            transition: 0.2s;
        }
        .nav-buttons a:hover { background: rgba(255,255,255,0.2); }
        
        /* Кнопка для мобильного меню */
        .menu-toggle {
            display: none;
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 40px;
            cursor: pointer;
            font-size: 14px;
        }
        
        /* Основной макет */
        .main-layout {
            display: flex;
            min-height: calc(100vh - 56px);
        }
        
        /* Левое меню (категории) */
        .sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid #eef2f6;
            padding: 16px 0;
            height: calc(100vh - 56px);
            overflow-y: auto;
            position: sticky;
            top: 56px;
            transition: transform 0.3s ease;
        }
        .sidebar-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            color: #6c757d;
            padding: 0 16px 12px;
            border-bottom: 1px solid #eef2f6;
            margin-bottom: 12px;
        }
        .category-item {
            padding: 8px 16px;
            cursor: pointer;
            transition: background 0.2s;
            border-left: 3px solid transparent;
        }
        .category-item:hover {
            background: #e8f4f8;
        }
        .category-item.active {
            background: #e8f4f8;
            border-left-color: #2c3e50;
        }
        .category-name {
            font-size: 14px;
            color: #1a1a2e;
        }
        
        /* Правая часть (контент) */
        .content {
            flex: 1;
            padding: 20px;
            overflow-x: auto;
        }
        
        /* Кнопки */
        .btn-add {
            background: #2c3e50;
            color: white;
            padding: 6px 16px;
            border-radius: 40px;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
        }
        .btn-add:hover { background: #1a252f; color: white; }
        
        /* Таблица */
        .table-card {
            background: white;
            border-radius: 20px;
            overflow-x: auto;
            margin-top: 16px;
        }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th { background: #f8f9fc; padding: 12px 14px; text-align: left; font-weight: 600; font-size: 13px; }
        td { padding: 10px 14px; border-top: 1px solid #eef2f6; font-size: 13px; }
        tbody tr { cursor: pointer; transition: background 0.2s; }
        tbody tr:hover { background: #e8f4f8; }
        
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 30px; font-size: 11px; font-weight: 600; }
        .status-in_stock { background: #e8f5e9; color: #2e7d32; }
        .status-under_repair { background: #fff3e0; color: #e65100; }
        .status-installed { background: #e3f2fd; color: #1565c0; }
        .status-sold { background: #eceff1; color: #546e7a; }
        .status-written_off { background: #ffebee; color: #c62828; }
        
        .action-link { margin: 0 4px; text-decoration: none; font-size: 16px; }
        
        /* Фильтры */
        .filter-bar {
            background: white;
            border-radius: 20px;
            padding: 14px 16px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-bar input, .filter-bar select {
            padding: 8px 14px;
            border: 1px solid #ddd;
            border-radius: 40px;
            font-size: 13px;
            flex: 1;
            min-width: 150px;
        }
        .filter-btn {
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 40px;
            padding: 8px 18px;
            cursor: pointer;
        }
        .reset-btn {
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 40px;
            padding: 8px 18px;
            cursor: pointer;
        }
        
        /* Мобильная версия */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 56px;
                z-index: 99;
                transform: translateX(-100%);
                width: 260px;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .menu-toggle {
                display: inline-block;
            }
            .content {
                padding: 12px;
            }
            .filter-bar input, .filter-bar select {
                width: 100%;
            }
            .nav-buttons a {
                font-size: 11px;
                padding: 4px 10px;
            }
        }
    </style>
</head>
<body>

<!-- Шапка -->
<div class="top-header">
    <div class="logo">
        <span>🔧</span> Цифровые паспорта
    </div>
    <button class="menu-toggle" onclick="toggleSidebar()">📁 Категории</button>
    <div class="nav-buttons">
        <a href="create.php">+ Новая запчасть</a>
        <?php if (isAdmin()): ?>
            <a href="../categories/index.php">📁 Категории</a>
            <a href="users.php">👥 Пользователи</a>
        <?php endif; ?>
        <a href="export.php?<?= $_SERVER['QUERY_STRING'] ?>" style="background: #27ae60;">📎 Экспорт</a>
        <a href="logout.php" style="background: #dc3545;">🚪 Выход</a>
    </div>
</div>

<!-- Основной макет -->
<div class="main-layout">
    <!-- Левое меню с категориями -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-title">📁 КАТАЛОГ ЗАПЧАСТЕЙ</div>
        <div class="category-item" data-cat-id="0" onclick="filterByCategory(0)">
            <span class="category-name">📂 Все запчасти</span>
        </div>
        <?= buildCategoryTree($categories) ?>
    </div>
    
    <!-- Правая часть -->
    <div class="content">
        <!-- Фильтры -->
        <div class="filter-bar">
            <input type="text" id="searchInput" placeholder="🔍 Поиск по названию или артикулу" value="<?= htmlspecialchars($search) ?>">
            <select id="statusFilter">
                <option value="">Все статусы</option>
                <?php foreach ($statuses as $val => $text): ?>
                    <option value="<?= $val ?>" <?= $status_filter == $val ? 'selected' : '' ?>><?= $text ?></option>
                <?php endforeach; ?>
            </select>
            <button class="filter-btn" onclick="applyFilters()">🔍 Фильтровать</button>
            <button class="reset-btn" onclick="resetFilters()">Сбросить</button>
        </div>
        
        <!-- Таблица запчастей -->
        <div class="table-card">
            <table>
                <thead>
                    <tr><th>ID</th><th>Наименование</th><th>Кат.номер</th><th>Статус</th><th>QR</th><th>Действия</th></tr>
                </thead>
                <tbody id="partsTable">
                    <?php if (count($parts) > 0): ?>
                        <?php foreach ($parts as $p): 
                            switch($p['status']) {
                                case 'in_stock': $status_text = 'В наличии'; $status_class = 'status-in_stock'; break;
                                case 'under_repair': $status_text = 'В ремонте'; $status_class = 'status-under_repair'; break;
                                case 'installed': $status_text = 'Установлена'; $status_class = 'status-installed'; break;
                                case 'sold': $status_text = 'Продана'; $status_class = 'status-sold'; break;
                                case 'written_off': $status_text = 'Списана'; $status_class = 'status-written_off'; break;
                                default: $status_text = $p['status']; $status_class = '';
                            }
                        ?>
                            <tr onclick="viewPart(<?= $p['id'] ?>)">
                                <td><?= $p['id'] ?></td>
                                <td><strong><?= htmlspecialchars($p['name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($p['category_name'] ?? '—') ?></small></td>
                                <td><?= htmlspecialchars($p['catalog_number'] ?? '—') ?></td>
                                <td><span class="status-badge <?= $status_class ?>"><?= $status_text ?></span></td>
                                <td><a href="../generate_qr.php?id=<?= $p['id'] ?>" class="action-link" onclick="event.stopPropagation()">📷</a></td>
                                <td>
                                    <a href="edit.php?id=<?= $p['id'] ?>" class="action-link" onclick="event.stopPropagation()">✏️</a>
                                    <?php if (isAdmin()): ?>
                                        <button class="action-link delete-btn" style="background:none; border:none; cursor:pointer;" data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>" onclick="event.stopPropagation(); deletePart(this)">🗑️</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center;">Нет запчастей</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Функция фильтрации по категории
    function filterByCategory(categoryId) {
        const url = new URL(window.location.href);
        if (categoryId > 0) {
            url.searchParams.set('category_id', categoryId);
        } else {
            url.searchParams.delete('category_id');
        }
        window.location.href = url.toString();
    }
    
    // Применение фильтров
    function applyFilters() {
        const search = document.getElementById('searchInput').value;
        const status = document.getElementById('statusFilter').value;
        const url = new URL(window.location.href);
        if (search) url.searchParams.set('search', search);
        else url.searchParams.delete('search');
        if (status) url.searchParams.set('status', status);
        else url.searchParams.delete('status');
        window.location.href = url.toString();
    }
    
    // Сброс фильтров
    function resetFilters() {
        window.location.href = window.location.pathname;
    }
    
    // Просмотр запчасти
    function viewPart(partId) {
        window.open('../part.php?id=' + partId, '_blank');
    }
    
    // Удаление запчасти
    function deletePart(btn) {
        const partId = btn.getAttribute('data-id');
        const partName = btn.getAttribute('data-name');
        if (confirm('Удалить запчасть "' + partName + '"?')) {
            window.location.href = 'delete.php?id=' + partId;
        }
    }
    
    // Подсветка активной категории и установка обработчиков
    document.addEventListener('DOMContentLoaded', function() {
        const currentCatId = '<?= $category_filter ?>';
        document.querySelectorAll('.category-item').forEach(item => {
            const catId = item.getAttribute('data-cat-id');
            if (catId == currentCatId) {
                item.classList.add('active');
            }
            // Добавляем обработчик, если его нет
            if (!item.hasAttribute('data-listener')) {
                item.setAttribute('data-listener', 'true');
                item.addEventListener('click', function(e) {
                    e.stopPropagation();
                    filterByCategory(parseInt(this.getAttribute('data-cat-id')));
                });
            }
        });
    });
    
    // Мобильное меню
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }
    
    // Закрыть меню при клике вне его на мобильных
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const menuBtn = document.querySelector('.menu-toggle');
        if (window.innerWidth <= 768 && sidebar.classList.contains('open') && 
            !sidebar.contains(event.target) && event.target !== menuBtn) {
            sidebar.classList.remove('open');
        }
    });
</script>
</body>
</html>