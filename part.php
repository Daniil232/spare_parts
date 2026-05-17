<?php
require_once 'includes/config.php';

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

// Получаем ВСЕ категории запчасти
$stmt = $pdo->prepare("
    SELECT c.name, c.id, c.parent_id
    FROM categories c
    JOIN part_categories pc ON c.id = pc.category_id
    WHERE pc.part_id = ?
");
$stmt->execute([$id]);
$categoriesList = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categoryPaths = [];
foreach ($categoriesList as $cat) {
    $categoryPaths[] = getCategoryFullPath($pdo, $cat['id']);
}

switch($part['status']) {
    case 'in_stock': $status_text = 'В наличии'; $status_class = 'status-in_stock'; break;
    case 'under_repair': $status_text = 'В ремонте'; $status_class = 'status-under_repair'; break;
    case 'installed': $status_text = 'Установлена'; $status_class = 'status-installed'; break;
    case 'sold': $status_text = 'Продана'; $status_class = 'status-sold'; break;
    case 'written_off': $status_text = 'Списана'; $status_class = 'status-written_off'; break;
    default: $status_text = $part['status']; $status_class = '';
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Цифровой паспорт: <?= htmlspecialchars($part['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Segoe UI', system-ui, sans-serif;}
        
        /* Контейнер */
        .container { max-width: 1200px; margin: 0 auto; }
        
        /* Кнопка назад */
        .back-button {
            margin-top: 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            padding: 8px 20px;
            border-radius: 40px;
            text-decoration: none;
            color: #2c3e50;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: 0.2s;
        }
        .back-button:hover { background: #e9ecef; }
        
        /* Двухколоночный макет для ПК */
        .two-columns {
            display: flex;
            gap: 24px;
            align-items: flex-start;
        }
        .left-column {
            flex: 1.2;
        }
        .right-column {
            flex: 0.8;
        }
        
        /* Карточки */
        .card {
            margin-top: 12px;
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #eef2f6;
        }
        
        /* Статус */
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .status-in_stock { background: #e8f5e9; color: #2e7d32; }
        .status-under_repair { background: #fff3e0; color: #e65100; }
        .status-installed { background: #e3f2fd; color: #1565c0; }
        .status-sold { background: #eceff1; color: #546e7a; }
        .status-written_off { background: #ffebee; color: #c62828; }
        
        /* Название */
        .part-name {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 16px;
        }
        
        /* Строка свойства */
        .prop-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #f0f2f5;
        }
        .prop-label {
            width: 140px;
            font-size: 14px;
            font-weight: 600;
            color: #6c757d;
        }
        .prop-value {
            flex: 1;
            font-size: 14px;
            color: #1a1a2e;
            word-break: break-word;
        }
        
        /* Категория (хлебные крошки) */
        .category-path {
            background: #f8f9fc;
            padding: 8px 12px;
            border-radius: 12px;
            font-size: 13px;
            color: #2c3e50;
            margin: 8px 0;
        }
        
        /* История операций */
        .history-item {
            border-left: 3px solid #2c3e50;
            padding-left: 14px;
            margin-bottom: 20px;
        }
        .history-date {
            font-size: 13px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 4px;
        }
        .history-title {
            font-size: 14px;
            font-weight: 600;
            margin: 4px 0;
        }
        .history-desc {
            font-size: 13px;
            color: #4a5568;
            line-height: 1.4;
        }
        
        /* Слайдер */
        .photo-slider { text-align: center; }
        .photo-slider img {
            width: 100%;
            border-radius: 16px;
            cursor: pointer;
            max-height: 300px;
            object-fit: contain;
        }
        .photo-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 12px;
        }
        .photo-nav button {
            background: #f0f2f5;
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            font-size: 18px;
            cursor: pointer;
        }
        .photo-dots {
            text-align: center;
            margin-top: 10px;
        }
        .photo-dots span {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #ccc;
            border-radius: 50%;
            margin: 0 4px;
            cursor: pointer;
        }
        .photo-dots span.active {
            background: #2c3e50;
            width: 10px;
            height: 10px;
        }
        
        /* Модальное окно */
        .modal-photo {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-photo img { max-width: 85%; max-height: 85%; object-fit: contain; }
        .modal-photo .close-btn {
            position: absolute;
            top: 20px;
            right: 35px;
            font-size: 40px;
            color: white;
            cursor: pointer;
        }
        .modal-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0,0,0,0.6);
            color: white;
            border: none;
            font-size: 40px;
            width: 60px;
            height: 80px;
            cursor: pointer;
            border-radius: 12px;
        }
        .modal-nav.prev { left: 20px; }
        .modal-nav.next { right: 20px; }
        
        hr { margin: 20px 0; border-color: #eef2f6; }
        
        /* ========== МОБИЛЬНАЯ ВЕРСИЯ ========== */
        @media (max-width: 768px) {
            body { padding: 16px; }
            .two-columns { flex-direction: column; }
            .left-column, .right-column { flex: auto; }
            .part-name { font-size: 22px; }
            .prop-row { flex-direction: column; }
            .prop-label { width: auto; margin-bottom: 4px; }
            .prop-value { margin-left: 0; }
            .modal-nav { width: 45px; height: 60px; font-size: 28px; }
            .modal-nav.prev { left: 10px; }
            .modal-nav.next { right: 10px; }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Кнопка назад (только для авторизованных) -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="admin/index.php" class="back-button">← Назад в админ-панель</a>
    <?php endif; ?>
    
    <!-- Двухколоночный макет (на ПК) -->
    <div class="two-columns">
        
        <!-- ЛЕВАЯ КОЛОНКА: основная информация и фото -->
        <div class="left-column">
            <div class="card">
                <div class="part-name"><?= htmlspecialchars($part['name']) ?></div>
                <div class="status-badge <?= $status_class ?>"><?= $status_text ?></div>
                
                <div class="prop-row">
                    <div class="prop-label">Каталожный номер</div>
                    <div class="prop-value"><?= htmlspecialchars($part['catalog_number'] ?? '—') ?></div>
                </div>
                
                <?php if (count($categoryPaths) > 0): ?>
                    <div class="prop-row">
                        <div class="prop-label">Категория</div>
                        <div class="prop-value">
                            <?php foreach ($categoryPaths as $path): ?>
                                <div class="category-path">📁 <?= htmlspecialchars($path) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="prop-row">
                    <div class="prop-label">Местоположение</div>
                    <div class="prop-value"><?= htmlspecialchars($part['location'] ?? '—') ?></div>
                </div>
                
                <div class="prop-row">
                    <div class="prop-label">Создано</div>
                    <div class="prop-value"><?= date('d.m.Y', strtotime($part['created_at'])) ?></div>
                </div>
                
                <div class="prop-row">
                    <div class="prop-label">Автор</div>
                    <div class="prop-value"><?= htmlspecialchars($part['creator'] ?? '—') ?></div>
                </div>
                
                <hr>
                
                <div class="prop-row">
                    <div class="prop-label">Описание</div>
                    <div class="prop-value"><?= nl2br(htmlspecialchars($part['description'] ?? '—')) ?></div>
                </div>
            </div>
            
            <!-- Фотографии -->
            <?php if(count($photos) > 0): ?>
            <div class="card">
                <div class="prop-label" style="margin-bottom: 12px;">Фотографии</div>
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
        </div>
        
        <!-- ПРАВАЯ КОЛОНКА: история операций -->
        <div class="right-column">
            <div class="card">
                <div class="prop-label" style="margin-bottom: 16px;">📜 История операций</div>
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
                    <div style="color:#999; padding: 20px 0; text-align:center">Нет записей</div>
                <?php endif; ?>
            </div>
            
            <!-- ID запчасти -->
            <div class="card" style="text-align:center; background:#f8f9fc;">
                <div class="prop-label">ID ЗАПЧАСТИ</div>
                <div style="font-size: 24px; font-weight: 700;">#<?= $part['id'] ?></div>
            </div>
        </div>
        
    </div>
</div>

<!-- Модальное окно -->
<div id="photoModal" class="modal-photo" onclick="closeModal()">
    <span class="close-btn" onclick="closeModal()">✕</span>
    <button class="modal-nav prev" id="modalPrev">◀</button>
    <img id="modalImage" src="">
    <button class="modal-nav next" id="modalNext">▶</button>
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

let modalCurrentIndex = 0;
const modalTotalPhotos = <?= count($photos) ?>;
let modalImages = [];
<?php foreach($photos as $index => $photo): ?>
modalImages[<?= $index ?>] = "assets/uploads/parts/<?= htmlspecialchars($photo['file_path']) ?>";
<?php endforeach; ?>

function showModalPhoto(index) {
    if (index < 0) index = 0;
    if (index >= modalTotalPhotos) index = modalTotalPhotos - 1;
    modalCurrentIndex = index;
    document.getElementById('modalImage').src = modalImages[modalCurrentIndex];
    const prevBtn = document.getElementById('modalPrev');
    const nextBtn = document.getElementById('modalNext');
    if (prevBtn) prevBtn.style.visibility = modalCurrentIndex === 0 ? 'hidden' : 'visible';
    if (nextBtn) nextBtn.style.visibility = modalCurrentIndex === modalTotalPhotos - 1 ? 'hidden' : 'visible';
}

document.querySelectorAll('.photo-img').forEach((img, idx) => {
    img.addEventListener('click', function(e) {
        e.stopPropagation();
        modalCurrentIndex = idx;
        showModalPhoto(idx);
        document.getElementById('photoModal').style.display = 'flex';
    });
});

document.getElementById('modalPrev')?.addEventListener('click', function(e) {
    e.stopPropagation();
    if (modalCurrentIndex > 0) showModalPhoto(modalCurrentIndex - 1);
});
document.getElementById('modalNext')?.addEventListener('click', function(e) {
    e.stopPropagation();
    if (modalCurrentIndex < modalTotalPhotos - 1) showModalPhoto(modalCurrentIndex + 1);
});

function closeModal() {
    document.getElementById('photoModal').style.display = 'none';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
    if (e.key === 'ArrowLeft' && document.getElementById('photoModal').style.display === 'flex') {
        if (modalCurrentIndex > 0) showModalPhoto(modalCurrentIndex - 1);
    }
    if (e.key === 'ArrowRight' && document.getElementById('photoModal').style.display === 'flex') {
        if (modalCurrentIndex < modalTotalPhotos - 1) showModalPhoto(modalCurrentIndex + 1);
    }
});
document.getElementById('photoModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>