<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$part_id = isset($_GET['part_id']) ? (int)$_GET['part_id'] : 0;

$stmt = $pdo->prepare("SELECT name FROM parts WHERE id = ?");
$stmt->execute([$part_id]);
$part = $stmt->fetch();

if (!$part) {
    die("Запчасть не найдена");
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $operation_type = $_POST['operation_type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $date = $_POST['date'] ?? date('Y-m-d');
    $performer = trim($_POST['performer'] ?? '');
    
    if (empty($operation_type) || empty($description)) {
        $error = 'Тип операции и описание обязательны';
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO operations (part_id, operation_type, description, date, performer, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$part_id, $operation_type, $description, $date, $performer ?: null]);
        $success = "Операция добавлена!";
    }
}

// Получаем список операций
$stmt = $pdo->prepare("SELECT * FROM operations WHERE part_id = ? ORDER BY date DESC");
$stmt->execute([$part_id]);
$operations = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>История операций</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; padding: 30px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 24px; padding: 30px; margin-bottom: 20px; }
        h1, h2 { font-size: 24px; margin-bottom: 20px; }
        .back-link { margin-bottom: 20px; display: inline-block; }
        .btn-save { background: #2c3e50; color: white; border-radius: 40px; padding: 10px 25px; border: none; }
        .btn-delete { background: #dc3545; }
        .form-control, .form-select { border-radius: 12px; }
        .history-item { border-left: 3px solid #2c3e50; padding-left: 15px; margin-bottom: 15px; }
        .history-date { font-size: 13px; font-weight: 700; color: #2c3e50; }
        .history-title { font-size: 15px; font-weight: 600; }
        .history-desc { font-size: 13px; color: #666; }
    </style>
</head>
<body>
<div class="container">
    <a href="edit.php?id=<?= $part_id ?>" class="back-link">← Назад к запчасти</a>
    
    <!-- Форма добавления операции -->
    <div class="card">
        <h1>📜 Добавление операции</h1>
        <p>Запчасть: <strong><?= htmlspecialchars($part['name']) ?></strong></p>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Тип операции *</label>
                    <select name="operation_type" class="form-select" required>
                        <option value="arrival">📦 Поступление</option>
                        <option value="diagnostic">🔍 Диагностика</option>
                        <option value="repair">🔧 Ремонт</option>
                        <option value="install">🔩 Установка</option>
                        <option value="sale">💰 Продажа</option>
                        <option value="warranty">🛡️ Гарантийный случай</option>
                        <option value="write_off">📄 Списание</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label>Дата</label>
                    <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="mb-3">
                <label>Описание *</label>
                <textarea name="description" class="form-control" rows="3" required placeholder="Подробное описание операции..."></textarea>
            </div>
            <div class="mb-3">
                <label>Исполнитель</label>
                <input type="text" name="performer" class="form-control" placeholder="ФИО или должность">
            </div>
            <button type="submit" class="btn-save">➕ Добавить операцию</button>
        </form>
    </div>
    
    <!-- Список операций -->
    <?php if (count($operations) > 0): ?>
    <div class="card">
        <h2>📋 История операций</h2>
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
                            default: echo $op['operation_type'];
                        }
                    ?>
                    <?php if ($op['performer']): ?>
                        <small class="text-muted">(<?= htmlspecialchars($op['performer']) ?>)</small>
                    <?php endif; ?>
                </div>
                <div class="history-desc"><?= nl2br(htmlspecialchars($op['description'])) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <a href="edit.php?id=<?= $part_id ?>" class="btn-save" style="background: #6c757d; text-decoration: none;">← Вернуться</a>
</div>
</body>
</html>