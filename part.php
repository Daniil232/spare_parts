<?php
require_once 'includes/config.php';

// Функция для получения полного пути категории
function getCategoryFullPath($pdo, $categoryId) {
    if (!$categoryId) return '';
    
    $path = [];
    $currentId = $categoryId;
    
    while ($currentId) {
        $stmt = $pdo->prepare("SELECT id, name, parent_id FROM categories WHERE id = ?");
        $stmt->execute([$currentId]);
        $cat = $stmt->fetch();
        if (!$cat) break;
        
        $path[] = $cat['name'];
        $currentId = $cat['parent_id'];
    }
    
    return implode(' → ', array_reverse($path));
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT p.*, u.username as creator
    FROM parts p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$part = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$part) {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>Запчасть не найдена</h1>";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM operations WHERE part_id = ? ORDER BY date DESC");
$stmt->execute([$id]);
$operations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM photos WHERE part_id = ? ORDER BY sort_order");
$stmt->execute([$id]);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем полный путь категории
$categoryPath = getCategoryFullPath($pdo, $part['category_id']);

// Преобразуем статус
switch($part['status']) {
    case 'in_stock': $status_text = 'В наличии'; $status_class = 'status-in_stock'; break;
    case 'under_repair': $status_text = 'В ремонте'; $status_class = 'status-under_repair'; break;
    case 'installed': $status_text = 'Установлена'; $status_class = 'status-installed'; break;
    case 'sold': $status_text = 'Продана'; $status_class = 'status-sold'; break;
    case 'written_off': $status_text = 'Списана'; $status_class = 'status-written_off'; break;
    default: $status_text = $part['status']; $status_class = '';
}

// Получаем все категории запчасти
$stmt = $pdo->prepare("
    SELECT c.name, c.id 
    FROM categories c
    JOIN part_categories pc ON c.id = pc.category_id
    WHERE pc.part_id = ?
");
$stmt->execute([$id]);
$categories = $stmt->fetchAll();

// Отображаем категории
if (count($categories) > 0): ?>
    <div class="prop-label">Категории</div>
    <?php foreach ($categories as $cat): ?>
        <div class="category-path">📁 <?= htmlspecialchars($cat['name']) ?></div>
    <?php endforeach; ?>
<?php endif;
?>



<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Цифровой паспорт: <?= htmlspecialchars($part['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f0f2f5;font-family:'Segoe UI',system-ui,sans-serif}
        .container{max-width:550px;margin:0 auto}
        .card{background:white;border-radius:24px;padding:20px;margin-bottom:16px}
        .part-id{font-size:12px;color:#6c757d;margin-bottom:8px}
        .part-name{font-size:24px;font-weight:700;margin-bottom:12px}
        .status-badge{display:inline-block;padding:10px 24px;border-radius:40px;font-size:16px;font-weight:700;margin-bottom:20px}
        .status-in_stock { background: #e8f5e9; color: #2e7d32; }
        .status-under_repair { background: #fff3e0; color: #e65100; }
        .status-installed { background: #e3f2fd; color: #1565c0; }
        .status-sold { background: #eceff1; color: #546e7a; }
        .status-written_off { background: #ffebee; color: #c62828; }
        .prop-label{font-size:12px;font-weight:600;color:#6c757d;margin-top:12px;margin-bottom:4px}
        .prop-value{font-size:15px;font-weight:500}
        .category-path{background:#f8f9fc;padding:8px 12px;border-radius:16px;font-size:13px;color:#2c3e50;margin:12px 0}
        .history-item{border-left:3px solid #2c3e50;padding-left:14px;margin-bottom:20px}
        .history-date{font-size:13px;font-weight:700;color:#2c3e50}
        .history-title{font-size:15px;font-weight:600;margin:4px0}
        .history-desc{font-size:14px;color:#4a5568}
        hr{margin:16px0;border-color:#eef2f6}
        .photo-slider{text-align:center}
        .photo-slider img{width:100%;border-radius:16px;cursor:pointer;max-height:250px;object-fit:contain}
        .photo-nav{display:flex;justify-content:space-between;align-items:center;margin-top:12px}
        .photo-nav button{background:#f0f2f5;border:none;border-radius:50%;width:36px;height:36px;font-size:18px;cursor:pointer}
        .photo-dots{text-align:center;margin-top:10px}
        .photo-dots span{display:inline-block;width:8px;height:8px;background:#ccc;border-radius:50%;margin:0 4px;cursor:pointer}
        .photo-dots span.active{background:#2c3e50;width:10px;height:10px}
        .modal-photo{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.9);z-index:1000;align-items:center;justify-content:center;cursor:pointer}
        .modal-photo img{max-width:90%;max-height:90%;object-fit:contain}
        .modal-photo .close-btn{position:absolute;top:20px;right:35px;font-size:40px;color:white;cursor:pointer}
        .back-button{display:inline-flex;align-items:center;gap:8px;background:#f0f2f5;padding:8px16px;border-radius:40px;text-decoration:none;color:#2c3e50;font-size:14px;font-weight:500;margin-top:24px;padding: 0 12px;}
    </style>
</head>
<body>

<!-- Кнопка назад (только для авторизованных) -->
<?php if (isset($_SESSION['user_id'])): ?>
    <div style="max-width:550px;margin:0 auto 15px auto;">
        <a href="admin/index.php" class="back-button">← Назад в админ-панель</a>
    </div>
<?php endif; ?>

<div class="container">
    <div class="card">
        <div class="part-id">ПАСПОРТ ЗАПЧАСТИ #<?= $part['id'] ?></div>
        <div class="part-name"><?= htmlspecialchars($part['name']) ?></div>
        <div class="status-badge <?= $status_class ?>"><?= $status_text ?></div>
        
        <div class="prop-label">Каталожный номер</div>
        <div class="prop-value"><?= htmlspecialchars($part['catalog_number'] ?? '—') ?></div>
        
        <?php if ($categoryPath): ?>
            <div class="prop-label">Категория</div>
            <div class="category-path">📁 <?= htmlspecialchars($categoryPath) ?></div>
        <?php endif; ?>
        
        <div class="prop-label">Местоположение</div>
        <div class="prop-value"><?= htmlspecialchars($part['location'] ?? '—') ?></div>
        
        <div class="prop-label">Создано</div>
        <div class="prop-value"><?= date('d.m.Y', strtotime($part['created_at'])) ?></div>
        
        <div class="prop-label">Автор</div>
        <div class="prop-value"><?= htmlspecialchars($part['creator'] ?? '—') ?></div>
        
        <hr>
        <div class="prop-label">Описание</div>
        <div class="prop-value"><?= nl2br(htmlspecialchars($part['description'] ?? '—')) ?></div>
    </div>
    
    <?php if(count($photos) > 0): ?>
    <div class="card">
        <div class="prop-label" style="margin-bottom:12px">Фотографии</div>
        <div class="photo-slider text-center" id="photoSlider">
            <?php foreach($photos as $index => $photo): ?>
                <img src="assets/uploads/parts/<?= htmlspecialchars($photo['file_path']) ?>" 
                     alt="Фото запчасти" 
                     style="<?= $index > 0 ? 'display:none' : '' ?>"
                     data-full="assets/uploads/parts/<?= htmlspecialchars($photo['file_path']) ?>"
                     class="photo-img"
                     id="photo_<?= $index ?>">
            <?php endforeach; ?>
        </div>
        <?php if(count($photos) > 1): ?>
            <div class="photo-nav">
                <button id="prevPhoto">◀</button>
                <div class="photo-dots" id="photoDots">
                    <?php for($i = 0; $i < count($photos); $i++): ?>
                        <span class="<?= $i == 0 ? 'active' : '' ?>" data-index="<?= $i ?>"></span>
                    <?php endfor; ?>
                </div>
                <button id="nextPhoto">▶</button>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="prop-label" style="margin-bottom:12px">История операций</div>
        <?php if(count($operations) > 0): ?>
            <?php foreach($operations as $op): ?>
                <div class="history-item">
                    <div class="history-date"><?= date('d.m.Y', strtotime($op['date'])) ?></div>
                    <div class="history-title">
                        <?php
                            switch($op['operation_type']) {
                                case 'arrival': echo '📦 Поступление'; break;
                                case 'repair': echo '🔧 Ремонт'; break;
                                case 'install': echo '🔩 Установка'; break;
                                case 'sale': echo '💰 Продажа'; break;
                                case 'write_off': echo '📄 Списание'; break;
                                default: echo $op['operation_type'];
                            }
                        ?>
                    </div>
                    <div class="history-desc"><?= nl2br(htmlspecialchars($op['description'])) ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="color:#999;padding:20px0;text-align:center">Нет записей</div>
        <?php endif; ?>
    </div>
    
    <div class="card" style="text-align:center;background:#f8f9fc">
        <div class="prop-label">ID ЗАПЧАСТИ</div>
        <div class="prop-value" style="font-size:20px;font-weight:700">#<?= $part['id'] ?></div>
    </div>
</div>

<div id="photoModal" class="modal-photo" onclick="closeModal()">
    <span class="close-btn" onclick="closeModal()">✕</span>
    <img id="modalImage" src="">
</div>

<?php include 'includes/footer.php'; ?>

<script>
<?php if(count($photos) > 1): ?>
    let currentIndex = 0;
    const totalPhotos = <?= count($photos) ?>;
    function showPhoto(index) {
        for(let i = 0; i < totalPhotos; i++) {
            const img = document.getElementById('photo_' + i);
            if(img) img.style.display = 'none';
            const dot = document.querySelector('.photo-dots span[data-index="' + i + '"]');
            if(dot) dot.classList.remove('active');
        }
        document.getElementById('photo_' + index).style.display = 'block';
        const activeDot = document.querySelector('.photo-dots span[data-index="' + index + '"]');
        if(activeDot) activeDot.classList.add('active');
        const prevBtn = document.getElementById('prevPhoto');
        if(prevBtn) prevBtn.style.visibility = index === 0 ? 'hidden' : 'visible';
    }
    document.getElementById('prevPhoto')?.addEventListener('click', function() {
        if(currentIndex > 0) { currentIndex--; showPhoto(currentIndex); }
    });
    document.getElementById('nextPhoto')?.addEventListener('click', function() {
        if(currentIndex < totalPhotos - 1) { currentIndex++; showPhoto(currentIndex); }
    });
    document.querySelectorAll('.photo-dots span').forEach(dot => {
        dot.addEventListener('click', function() { currentIndex = parseInt(this.dataset.index); showPhoto(currentIndex); });
    });
    showPhoto(0);
<?php endif; ?>

document.querySelectorAll('.photo-img').forEach(img => {
    img.addEventListener('click', function(e) {
        e.stopPropagation();
        document.getElementById('modalImage').src = this.dataset.full || this.src;
        document.getElementById('photoModal').style.display = 'flex';
    });
});

function closeModal() {
    document.getElementById('photoModal').style.display = 'none';
}
document.addEventListener('keydown', function(e) { if(e.key === 'Escape') closeModal(); });
</script>
</body>
</html>