<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

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
        // Обновляем статус запчасти в зависимости от операции
        $new_status = null;
        switch($operation_type) {
            case 'sale':
                $new_status = 'sold';
                break;
            case 'install':
                $new_status = 'installed';
                break;
            case 'repair':
                $new_status = 'under_repair';
                break;
            case 'write_off':
                $new_status = 'written_off';
                break;
            case 'arrival':
                $new_status = 'in_stock';
                break;
        }
        
        if ($new_status) {
            $stmt = $pdo->prepare("UPDATE parts SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $part_id]);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO operations (part_id, operation_type, description, date, performer, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$part_id, $operation_type, $description, $date, $performer ?: null]);
        $success = "Операция добавлена! Статус запчасти обновлён.";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>История операций</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#f0f2f5;font-family:'Segoe UI',system-ui,sans-serif;padding:30px}
        .container{max-width:800px;margin:0 auto}
        .card{background:white;border-radius:24px;padding:32px;margin-bottom:24px}
        h1{font-size:24px;margin-bottom:8px}
        h2{font-size:18px;margin-bottom:16px}
        .back-link{margin-bottom:20px;display:inline-block;color:#6c757d;text-decoration:none}
        .btn-save{background:#2c3e50;color:white;border:none;border-radius:40px;padding:12px30px;cursor:pointer}
        .form-control,.form-select{border-radius:12px;padding:10px14px;border:1px solid #ddd;width:100%}
        label{font-weight:500;margin-bottom:6px;display:block}
        .alert{border-radius:16px;padding:12px16px;margin-bottom:20px}
        .history-item{border-left:3px solid #2c3e50;padding-left:14px;margin-bottom:20px}
        .history-date{font-size:13px;font-weight:700;color:#2c3e50}
        .history-title{font-size:15px;font-weight:600;margin:4px0}
        .history-desc{font-size:14px;color:#4a5568}
        .history-performer{font-size:12px;color:#6c757d;margin-top:4px}
    </style>
</head>
<body>
<div class="container">
    <a href="edit.php?id=<?= $part_id ?>" class="back-link">← Назад к запчасти</a>
    
    <div class="card">
        <h1>📜 Добавление операции</h1>
        <p>Запчасть: <strong><?= htmlspecialchars($part['name']) ?></strong></p>
        
        <?php if($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row" style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap">
                <div class="col-md-6" style="flex:1">
                    <label>Тип операции *</label>
                    <select name="operation_type" class="form-select" required>
                        <option value="arrival">📦 Поступление</option>
                        <option value="repair">🔧 Ремонт</option>
                        <option value="install">🔩 Установка</option>
                        <option value="sale">💰 Продажа</option>
                        <option value="write_off">📄 Списание</option>
                    </select>
                </div>
                <div class="col-md-6" style="flex:1">
                    <label>Дата</label>
                    <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="mb-3">
                <label>Описание *</label>
                <textarea name="description" class="form-control" rows="3" required placeholder="Например: Поступление с разборки Komatsu 830.3..."></textarea>
            </div>
            <div class="mb-3">
                <label>Исполнитель (ФИО)</label>
                <input type="text" name="performer" class="form-control" placeholder="Иванов И.И.">
            </div>
            <button type="submit" class="btn-save">➕ Добавить операцию</button>
        </form>
    </div>
    
    <?php if(count($operations) > 0): ?>
    <div class="card">
        <h2>📋 История операций</h2>
        <?php foreach($operations as $op): ?>
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
                    <?php if($op['performer']): ?>
                        <span class="history-performer">(<?= htmlspecialchars($op['performer']) ?>)</span>
                    <?php endif; ?>
                </div>
                <div class="history-desc"><?= nl2br(htmlspecialchars($op['description'])) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>