<?php
require_once '../includes/config.php';
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$parts = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM parts p
    LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY p.id DESC
")->fetchAll();
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
        .btn-add { background: #2c3e50; color: white; padding: 10px 20px; border-radius: 40px; text-decoration: none; }
        .btn-add:hover { background: #1a252f; color: white; }
        .btn-danger { background: #dc3545; }
        .btn-secondary { background: #6c757d; }
        .table-card { background: white; border-radius: 24px; overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fc; padding: 15px 20px; text-align: left; font-weight: 600; }
        td { padding: 12px 20px; border-top: 1px solid #eee; }
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 12px; font-weight: 600; }
        .status-in_stock { background: #e8f5e9; color: #2e7d32; }
        .status-under_repair { background: #fff3e0; color: #e65100; }
        .status-installed { background: #e3f2fd; color: #1565c0; }
        .status-sold { background: #eceff1; color: #546e7a; }
        .status-written_off { background: #ffebee; color: #c62828; }
        .action-link { margin: 0 5px; text-decoration: none; font-size: 18px; }
        .action-link:hover { opacity: 0.7; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📄 Цифровые паспорта запчастей</h1>
        <div>
            <a href="create.php" class="btn-add">+ Новая запчасть</a>
            <a href="../categories/index.php" class="btn-add btn-secondary">📁 Категории</a>
            <a href="logout.php" class="btn-add btn-danger">🚪 Выйти</a>
        </div>
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
                            // Преобразуем статус в русский текст и CSS-класс
                            switch($p['status']) {
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
                                    $status_text = $p['status'];
                                    $status_class = '';
                            }
                        ?>
                        <tr>
                            <td><?= $p['id'] ?></td>
                            <td><strong><?= htmlspecialchars($p['name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($p['category_name'] ?? '—') ?></small></td>
                            <td><?= htmlspecialchars($p['catalog_number'] ?? '—') ?></td>
                            <td><span class="status-badge <?= $status_class ?>"><?= $status_text ?></span></td>
                            <td><a href="../generate_qr.php?id=<?= $p['id'] ?>" class="action-link">📷</a></td>
                            <td>
                                 <a href="edit.php?id=<?= $p['id'] ?>" class="action-link">✏️</a>
    <button type="button" class="action-link delete-btn" style="background:none; border:none; cursor:pointer;" data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['name']) ?>">🗑️</button>
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
<!-- Модальное окно подтверждения удаления -->
<div id="deleteModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 24px; max-width: 400px; width: 90%; padding: 30px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
        <h3 style="margin-bottom: 15px;">🗑️ Подтверждение удаления</h3>
        <p>Вы действительно хотите удалить запчасть?</p>
        <p id="deletePartName" style="font-weight: 700; color: #c62828; margin: 10px 0;"></p>
        <p class="text-muted small">Это действие невозможно отменить.</p>
        <div style="margin-top: 25px;">
            <a href="#" id="confirmDeleteBtn" class="btn-save" style="background: #dc3545; padding: 8px 25px; text-decoration: none;">Да, удалить</a>
            <button id="cancelDeleteBtn" style="background: #6c757d; color: white; border: none; border-radius: 40px; padding: 8px 25px; margin-left: 10px;">Отмена</button>
        </div>
    </div>
</div>

<style>
    .btn-save { display: inline-block; background: #2c3e50; color: white; border-radius: 40px; padding: 8px 25px; text-decoration: none; border: none; cursor: pointer; }
    .btn-save:hover { opacity: 0.8; }
</style>

<script>
    // Получаем элементы
    const modal = document.getElementById('deleteModal');
    const deletePartNameSpan = document.getElementById('deletePartName');
    const confirmBtn = document.getElementById('confirmDeleteBtn');
    const cancelBtn = document.getElementById('cancelDeleteBtn');
    
    let currentDeleteId = null;
    
    // Находим все кнопки удаления
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const partId = this.getAttribute('data-id');
            const partName = this.getAttribute('data-name');
            currentDeleteId = partId;
            deletePartNameSpan.textContent = partName;
            modal.style.display = 'flex';
        });
    });
    
    // Подтверждение удаления
    confirmBtn.addEventListener('click', function(e) {
        e.preventDefault();
        if (currentDeleteId) {
            window.location.href = 'delete.php?id=' + currentDeleteId;
        }
    });
    
    // Отмена
    cancelBtn.addEventListener('click', function() {
        modal.style.display = 'none';
        currentDeleteId = null;
    });
    
    // Клик вне окна
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
            currentDeleteId = null;
        }
    });
</script>
</body>
</html>