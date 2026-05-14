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

// Базовый запрос (без LEFT JOIN категорий)
$sql = "SELECT p.* FROM parts p WHERE 1=1";
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
    $sql .= " AND EXISTS (SELECT 1 FROM part_categories pc WHERE pc.part_id = p.id AND pc.category_id IN ($placeholders))";
    $params = array_merge($params, $category_ids);
}

$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$parts = $stmt->fetchAll();

// Для каждой запчасти получаем её категории
$partCategories = [];
foreach ($parts as $part) {
    $stmt = $pdo->prepare("
        SELECT c.name FROM categories c 
        JOIN part_categories pc ON c.id = pc.category_id 
        WHERE pc.part_id = ?
    ");
    $stmt->execute([$part['id']]);
    $cats = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $partCategories[$part['id']] = implode(', ', $cats);
}

// Получаем все категории для дерева
$categories = $pdo->query("SELECT id, parent_id, name FROM categories ORDER BY name")->fetchAll();

$statuses = [
    'in_stock' => 'В наличии',
    'under_repair' => 'В ремонте',
    'installed' => 'Установлена',
    'sold' => 'Продана',
    'written_off' => 'Списана'
];

function buildCategoryTree($categories, $parentId = null, $level = 0) {
    $html = '';
    foreach ($categories as $cat) {
        if ($cat['parent_id'] == $parentId) {
            $hasChildren = false;
            foreach ($categories as $c) {
                if ($c['parent_id'] == $cat['id']) {
                    $hasChildren = true;
                    break;
                }
            }
            
            $icon = ($level == 0) ? '📁' : '';
            $toggleIcon = $hasChildren ? '<span class="toggle-icon">►</span>' : '<span class="toggle-icon empty">►</span>';
            
            $html .= '<div class="category-item" data-cat-id="' . $cat['id'] . '">';
            $html .= '<div class="category-row">';
            $html .= $toggleIcon;
            $html .= '<span class="category-link">' . $icon . htmlspecialchars($cat['name']) . '</span>';
            $html .= '</div>';
            
            if ($hasChildren) {
                $html .= '<div class="subcategories" data-parent="' . $cat['id'] . '" style="display: none;">';
                $html .= buildCategoryTree($categories, $cat['id'], $level + 1);
                $html .= '</div>';
            }
            $html .= '</div>';
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
        /* Ваши стили остаются без изменений */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Segoe UI', sans-serif; overflow-x: hidden; }
        
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
        .logo { font-size: 16px; font-weight: 600; }
        .logo span { font-size: 22px; margin-right: 6px; }
        .nav-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        .nav-buttons a {
            color: white;
            text-decoration: none;
            padding: 5px 12px;
            border-radius: 40px;
            background: rgba(255,255,255,0.1);
            font-size: 13px;
        }
        .nav-buttons a:hover { background: rgba(255,255,255,0.2); }
        .menu-toggle {
            display: none;
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 40px;
            cursor: pointer;
        }
        
        .main-layout { display: flex; min-height: calc(100vh - 56px); }
        
        .sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid #eef2f6;
            padding: 12px 0;
            height: calc(100vh - 56px);
            overflow-y: auto;
            position: sticky;
            top: 56px;
        }
        .sidebar-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: #6c757d;
            padding: 0 16px 12px;
            border-bottom: 1px solid #eef2f6;
            margin-bottom: 8px;
        }
        
        .category-item { list-style: none; }
        .category-row {
            display: flex;
            align-items: center;
            padding: 5px 16px;
            gap: 6px;
        }
        .toggle-icon {
            width: 18px;
            font-size: 11px;
            color: #8b92a5;
            cursor: pointer;
            text-align: center;
            flex-shrink: 0;
        }
        .toggle-icon:hover { color: #2c3e50; }
        .toggle-icon.empty { opacity: 0; cursor: default; pointer-events: none; }
        .category-link {
            font-size: 13px;
            color: #2c3e50;
            text-decoration: none;
            cursor: pointer;
            flex: 1;
        }
        .category-link:hover {
            color: #1a252f;
            text-decoration: underline;
        }
        .category-item.active .category-link {
            font-weight: 600;
            text-decoration: underline;
        }
        .subcategories { margin-left: 14px; }
        
        .content { flex: 1; padding: 20px; overflow-x: auto; }
        .btn-add {
            background: #2c3e50;
            color: white;
            padding: 6px 16px;
            border-radius: 40px;
            text-decoration: none;
            font-size: 13px;
            display: inline-block;
        }
        .btn-add:hover { background: #1a252f; color: white; }
        
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
        
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-in_stock { background: #e8f5e9; color: #2e7d32; }
        .status-under_repair { background: #fff3e0; color: #e65100; }
        .status-installed { background: #e3f2fd; color: #1565c0; }
        .status-sold { background: #eceff1; color: #546e7a; }
        .status-written_off { background: #ffebee; color: #c62828; }
        
        .action-link { margin: 0 4px; text-decoration: none; font-size: 16px; }
        
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
        .filter-btn, .reset-btn {
            border: none;
            border-radius: 40px;
            padding: 8px 18px;
            cursor: pointer;
        }
        .filter-btn { background: #2c3e50; color: white; }
        .reset-btn { background: #6c757d; color: white; }
        
        .category-list {
            font-size: 12px;
            color: #6c757d;
            max-width: 200px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: 0;
                top: 56px;           /* ← ровно под шапкой */
                bottom: 0;
                z-index: 99;
                transform: translateX(-100%);
                width: 280px;
                background: white;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
                padding: 12px 0;
                overflow-y: auto;
                transition: transform 0.3s ease;
            }
            .sidebar.open {
                transform: translateX(0);
                margin-top: 80px;
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
            .sidebar-title {
                padding: 0 16px 12px;
                margin-bottom: 8px;
            }
            .category-row {
                padding: 8px 16px;
            }
            .category-link {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>

<div class="top-header">
    <div class="logo"><span>🔧</span> Цифровые паспорта</div>
    <button class="menu-toggle" onclick="toggleSidebar()">📁 Каталог</button>
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

<div class="main-layout">
    <div class="sidebar" id="sidebar">
        <div class="sidebar-title">📁 КАТАЛОГ ЗАПЧАСТЕЙ</div>
        <div class="category-item" data-cat-id="0">
            <div class="category-row">
                <span class="toggle-icon empty">►</span>
                <span class="category-link">📂 Все запчасти</span>
            </div>
        </div>
        <?= buildCategoryTree($categories) ?>
    </div>
    
    <div class="content">
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
            <?php if ($category_filter > 0): ?>
                <button class="filter-btn" onclick="filterByCategory(0)" style="background: #27ae60;">📋 Показать все</button>
            <?php endif; ?>
        </div>
        
        <div class="table-card">
            <table>
                <thead>
                    <tr><th>ID</th><th>Наименование</th><th>Категории</th><th>Кат.номер</th><th>Статус</th><th>QR</th><th>Действия</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($parts as $p): 
                        switch($p['status']) {
                            case 'in_stock': $st = 'В наличии'; $sc = 'status-in_stock'; break;
                            case 'under_repair': $st = 'В ремонте'; $sc = 'status-under_repair'; break;
                            case 'installed': $st = 'Установлена'; $sc = 'status-installed'; break;
                            case 'sold': $st = 'Продана'; $sc = 'status-sold'; break;
                            default: $st = 'Списана'; $sc = 'status-written_off';
                        }
                    ?>
                        <tr onclick="viewPart(<?= $p['id'] ?>)">
                            <td><?= $p['id'] ?></td>
                            <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                            <td class="category-list"><?= htmlspecialchars($partCategories[$p['id']] ?? '—') ?></td>
                            <td><?= htmlspecialchars($p['catalog_number'] ?? '—') ?></td>
                            <td><span class="status-badge <?= $sc ?>"><?= $st ?></span></td>
                            <td><a href="../generate_qr.php?id=<?= $p['id'] ?>" class="action-link" onclick="event.stopPropagation()">📷</a></td>
                            <td>
                                <a href="edit.php?id=<?= $p['id'] ?>" class="action-link" onclick="event.stopPropagation()">✏️</a>
                                <?php if (isAdmin()): ?>
                                    <button class="action-link delete-btn" style="background:none; border:none; cursor:pointer;" data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>" onclick="event.stopPropagation(); deletePart(this)">🗑️</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function filterByCategory(catId) {
    let url = new URL(window.location.href);
    if (catId > 0) url.searchParams.set('category_id', catId);
    else url.searchParams.delete('category_id');
    window.location.href = url.toString();
}
function applyFilters() {
    let url = new URL(window.location.href);
    let s = document.getElementById('searchInput').value;
    let st = document.getElementById('statusFilter').value;
    if (s) url.searchParams.set('search', s);
    else url.searchParams.delete('search');
    if (st) url.searchParams.set('status', st);
    else url.searchParams.delete('status');
    window.location.href = url.toString();
}
function resetFilters() { window.location.href = window.location.pathname; }
function viewPart(id) { window.open('../part.php?id=' + id, '_blank'); }
function deletePart(btn) {
    window.location.href = 'delete.php?id=' + btn.dataset.id;
}
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('open');
    if (sidebar.classList.contains('open')) {
        // Прокручиваем меню к первому элементу
        setTimeout(() => {
            const firstItem = sidebar.querySelector('.category-item');
            if (firstItem) {
                firstItem.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }, 100);
    }
}

function saveOpenCategories() {
    const openCategories = [];
    document.querySelectorAll('.subcategories').forEach(sub => {
        if (sub.style.display === 'block') {
            const parentId = sub.getAttribute('data-parent');
            if (parentId) openCategories.push(parentId);
        }
    });
    localStorage.setItem('openCategories', JSON.stringify(openCategories));
}

function restoreOpenCategories() {
    const saved = localStorage.getItem('openCategories');
    if (!saved) return;
    const openCategories = JSON.parse(saved);
    openCategories.forEach(parentId => {
        const sub = document.querySelector(`.subcategories[data-parent="${parentId}"]`);
        if (sub) {
            sub.style.display = 'block';
            const toggle = sub.parentElement?.querySelector('.toggle-icon');
            if (toggle && toggle.textContent === '►') toggle.textContent = '▼';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    let currentCat = '<?= $category_filter ?>';
    document.querySelectorAll('.category-item').forEach(item => {
        let catId = item.dataset.catId;
        if (catId == currentCat && catId != 0) item.classList.add('active');
        
        let toggle = item.querySelector('.toggle-icon');
        let link = item.querySelector('.category-link');
        
        if (toggle && !toggle.classList.contains('empty')) {
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                let sub = item.querySelector('.subcategories');
                if (sub) {
                    if (sub.style.display === 'none') {
                        sub.style.display = 'block';
                        toggle.textContent = '▼';
                    } else {
                        sub.style.display = 'none';
                        toggle.textContent = '►';
                    }
                    saveOpenCategories();
                }
            });
        }
        if (link) {
            link.addEventListener('click', function(e) {
                e.stopPropagation();
                filterByCategory(catId);
            });
        }
    });
    
    restoreOpenCategories();
});

document.addEventListener('click', function(e) {
    let sidebar = document.getElementById('sidebar');
    let btn = document.querySelector('.menu-toggle');
    if (window.innerWidth <= 768 && sidebar.classList.contains('open') && 
        !sidebar.contains(e.target) && e.target !== btn) {
        sidebar.classList.remove('open');
    }
});
</script>
<?php include '../includes/footer.php'; ?>
</body>
</html>