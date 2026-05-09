<?php
require_once 'includes/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, u.username as creator
    FROM parts p
    LEFT JOIN categories c ON p.category_id = c.id
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

// Преобразуем статус в русский текст и CSS-класс
switch($part['status']) {
    case 'in_stock':
        $status_text = 'В наличии';
        $status_class = 'status-in_stock';
        break;
    case 'under_repair':
        $status_text = 'В ремонте';
        $status_class = 'status-under_repair';
        break;
    case 'installed':
        $status_text = 'Установлена';
        $status_class = 'status-installed';
        break;
    case 'sold':
        $status_text = 'Продана';
        $status_class = 'status-sold';
        break;
    case 'written_off':
        $status_text = 'Списана';
        $status_class = 'status-written_off';
        break;
    default:
        $status_text = $part['status'];
        $status_class = '';
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
        body { background: #f0f2f5; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; padding: 20px; }
        .container { max-width: 550px; margin: 0 auto; }
        
        /* Карточки */
        .card { background: white; border-radius: 24px; padding: 20px; margin-bottom: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.04); border: 1px solid #eef2f6; }
        
        /* ID запчасти */
        .part-id { font-size: 12px; font-weight: 500; color: #6c757d; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        
        /* Название */
        .part-name { font-size: 24px; font-weight: 700; margin-bottom: 12px; color: #1a1a2e; line-height: 1.3; }
        
        /* Бейдж статуса */
        .status-badge { display: inline-block; padding: 6px 16px; border-radius: 30px; font-size: 14px; font-weight: 600; margin-bottom: 16px; }
        .status-in_stock { background: #e8f5e9; color: #2e7d32; border-left: 3px solid #2e7d32; }
        .status-under_repair { background: #fff3e0; color: #e65100; border-left: 3px solid #e65100; }
        .status-installed { background: #e3f2fd; color: #1565c0; border-left: 3px solid #1565c0; }
        .status-sold { background: #eceff1; color: #546e7a; border-left: 3px solid #546e7a; }
        .status-written_off { background: #ffebee; color: #c62828; border-left: 3px solid #c62828; }
        
        /* Строка свойства */
        .prop-label { font-size: 12px; font-weight: 600; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 12px; margin-bottom: 4px; }
        .prop-value { font-size: 15px; font-weight: 500; color: #1a1a2e; word-break: break-word; }
        
        /* Категория */
        .category-path { background: #f8f9fc; padding: 8px 12px; border-radius: 16px; font-size: 13px; color: #2c3e50; margin: 12px 0; }
        
        /* История операций */
        .history-item { border-left: 3px solid #2c3e50; padding-left: 14px; margin-bottom: 20px; }
        .history-date { font-size: 13px; font-weight: 700; color: #2c3e50; margin-bottom: 4px; }
        .history-title { font-size: 15px; font-weight: 600; margin-bottom: 6px; color: #1a1a2e; }
        .history-desc { font-size: 14px; color: #4a5568; line-height: 1.4; }
        
        /* Кнопка QR */
        .btn-qr { background: #2c3e50; color: white; border: none; border-radius: 40px; padding: 12px 20px; font-weight: 600; font-size: 14px; width: 100%; transition: 0.2s; text-decoration: none; display: inline-block; text-align: center; }
        .btn-qr:hover { background: #1a252f; transform: translateY(-1px); color: white; }
        
        /* Разделитель */
        hr { margin: 16px 0; border-color: #eef2f6; }
        
        /* Фотографии */
        .photo-slider img { width: 100%; border-radius: 16px; cursor: pointer; object-fit: cover; max-height: 250px; }
        .photo-dots { text-align: center; margin-top: 10px; }
        .photo-dots span { display: inline-block; width: 8px; height: 8px; background: #ccc; border-radius: 50%; margin: 0 4px; cursor: pointer; }
        .photo-dots span.active { background: #2c3e50; width: 10px; height: 10px; }
        
        /* Навигация */
        .photo-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
        .photo-nav button { background: #f0f2f5; border: none; border-radius: 50%; width: 36px; height: 36px; font-size: 18px; cursor: pointer; }
        .photo-nav button:hover { background: #e0e0e0; }
    </style>
</head>
<body>
<div class="container">
    
    <!-- ОСНОВНАЯ КАРТОЧКА ПАСПОРТА -->
    <div class="card">
        <div class="part-id">ПАСПОРТ ЗАПЧАСТИ #<?= $part['id'] ?></div>
        <div class="part-name"><?= htmlspecialchars($part['name']) ?></div>
        
        <div class="status-badge <?= $status_class ?>"><?= $status_text ?></div>
        
        <div class="prop-label">Каталожный номер</div>
        <div class="prop-value"><?= htmlspecialchars($part['catalog_number'] ?? '—') ?></div>
        
        <?php if ($part['category_name']): ?>
            <div class="prop-label">Категория</div>
            <div class="category-path">📁 <?= htmlspecialchars($part['category_name']) ?></div>
        <?php endif; ?>
        
        <div class="prop-label">Местоположение</div>
        <div class="prop-value"><?= htmlspecialchars($part['location'] ?? '—') ?></div>
        
        <div class="prop-label">Создано</div>
        <div class="prop-value"><?= date('d.m.Y', strtotime($part['created_at'])) ?></div>
        
        <div class="prop-label">Автор</div>
        <div class="prop-value"><?= htmlspecialchars($part['creator'] ?? '—') ?></div>
        
        <hr>
        
        <div class="prop-label">Описание</div>
        <div class="prop-value" style="line-height: 1.5;"><?= nl2br(htmlspecialchars($part['description'] ?? '—')) ?></div>
    </div>
    
    <!-- ФОТОГРАФИИ -->
    <?php if (count($photos) > 0): ?>
    <div class="card">
        <div class="prop-label" style="margin-bottom: 12px;">Фотографии</div>
        <div class="photo-slider text-center" id="photoSlider">
            <?php foreach ($photos as $index => $photo): ?>
                <img src="assets/uploads/parts/<?= htmlspecialchars($photo['file_path']) ?>" 
                     alt="Фото запчасти" 
                     style="<?= $index > 0 ? 'display: none;' : '' ?>"
                     id="photo_<?= $index ?>">
            <?php endforeach; ?>
        </div>
        
        <?php if (count($photos) > 1): ?>
            <div class="photo-nav">
                <button id="prevPhoto" style="visibility: hidden;">◀</button>
                <div class="photo-dots" id="photoDots">
                    <?php for ($i = 0; $i < count($photos); $i++): ?>
                        <span class="<?= $i == 0 ? 'active' : '' ?>" data-index="<?= $i ?>"></span>
                    <?php endfor; ?>
                </div>
                <button id="nextPhoto">▶</button>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- ИСТОРИЯ ОПЕРАЦИЙ -->
    <div class="card">
        <div class="prop-label" style="margin-bottom: 12px;">История операций</div>
        <?php if (count($operations) > 0): ?>
            <?php foreach ($operations as $op): ?>
                <div class="history-item">
                    <div class="history-date"><?= date('d.m.Y', strtotime($op['date'])) ?></div>
                    <div class="history-title">
                        <?php
                            switch($op['operation_type']) {
                                case 'arrival': echo '📦 Поступление'; break;
                                case 'diagnostic': echo '🔍 Диагностика'; break;
                                case 'repair': echo '🔧 Ремонт'; break;
                                case 'install': echo '🔩 Установка'; break;
                                case 'sale': echo '💰 Продажа'; break;
                                case 'warranty': echo '🛡️ Гарантийный случай'; break;
                                case 'write_off': echo '📄 Списание'; break;
                                default: echo htmlspecialchars($op['operation_type']);
                            }
                        ?>
                    </div>
                    <div class="history-desc"><?= nl2br(htmlspecialchars($op['description'])) ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="color: #999; padding: 20px 0; text-align: center;">Нет записей</div>
        <?php endif; ?>
    </div>
    
    <!-- QR-КОД -->
    <div class="card" style="text-align: center;">
        <div class="prop-label" style="margin-bottom: 10px;">QR-код запчасти</div>
        <a href="generate_qr.php?id=<?= $part['id'] ?>" class="btn-qr">
            📷 Скачать QR-код
        </a>
        <p class="text-muted small mt-2" style="font-size: 11px;">Наклейте QR-код на запчасть для быстрого доступа к паспорту</p>
    </div>
    
    <!-- ID ЗАПЧАСТИ (НИЖНИЙ БЛОК) -->
    <div class="card" style="background: #f8f9fc; text-align: center;">
        <div class="prop-label">ID ЗАПЧАСТИ</div>
        <div class="prop-value" style="font-size: 20px; font-weight: 700;">#<?= $part['id'] ?></div>
    </div>

</div>

<?php if (count($photos) > 1): ?>
<script>
    // Слайдер фотографий
    let currentIndex = 0;
    const totalPhotos = <?= count($photos) ?>;
    
    function showPhoto(index) {
        // Скрываем все фото
        for (let i = 0; i < totalPhotos; i++) {
            const img = document.getElementById('photo_' + i);
            if (img) img.style.display = 'none';
            const dot = document.querySelector('.photo-dots span[data-index="' + i + '"]');
            if (dot) dot.classList.remove('active');
        }
        // Показываем выбранное
        const currentImg = document.getElementById('photo_' + index);
        if (currentImg) currentImg.style.display = 'block';
        const currentDot = document.querySelector('.photo-dots span[data-index="' + index + '"]');
        if (currentDot) currentDot.classList.add('active');
        
        // Управляем видимостью кнопки "Назад"
        const prevBtn = document.getElementById('prevPhoto');
        if (prevBtn) prevBtn.style.visibility = index === 0 ? 'hidden' : 'visible';
    }
    
    // Обработчики для кнопок
    document.getElementById('prevPhoto')?.addEventListener('click', function() {
        if (currentIndex > 0) {
            currentIndex--;
            showPhoto(currentIndex);
        }
    });
    
    document.getElementById('nextPhoto')?.addEventListener('click', function() {
        if (currentIndex < totalPhotos - 1) {
            currentIndex++;
            showPhoto(currentIndex);
        }
    });
    
    // Обработчики для точек
    document.querySelectorAll('.photo-dots span').forEach(dot => {
        dot.addEventListener('click', function() {
            currentIndex = parseInt(this.getAttribute('data-index'));
            showPhoto(currentIndex);
        });
    });
    
    // Инициализация
    if (totalPhotos > 1) {
        const prevBtn = document.getElementById('prevPhoto');
        if (prevBtn) prevBtn.style.visibility = 'hidden';
    }
</script>
<?php endif; ?>

</body>
</html>