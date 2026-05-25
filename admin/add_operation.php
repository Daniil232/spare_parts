<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

$part_id = isset($_GET['part_id']) ? (int)$_GET['part_id'] : 0;
$deleted = isset($_GET['deleted']) ? true : false;

$stmt = $pdo->prepare("SELECT name, status FROM parts WHERE id = ?");
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
        // Определяем новый статус в зависимости от операции
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
        
        // Обновляем статус, если нужно
        if ($new_status) {
            $stmt = $pdo->prepare("UPDATE parts SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $part_id]);
        }
        
        // Добавляем операцию
        $stmt = $pdo->prepare("
            INSERT INTO operations (part_id, operation_type, description, date, performer, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$part_id, $operation_type, $description, $date, $performer ?: null]);
        
        $success = "Операция добавлена! Статус запчасти обновлён.";
    }
}

// Получаем список операций (хронологический порядок: сначала старые)
$stmt = $pdo->prepare("SELECT * FROM operations WHERE part_id = ? ORDER BY date ASC, created_at ASC");
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
        .container{max-width:900px;margin:0 auto}
        .card{background:white;border-radius:24px;padding:32px;margin-bottom:24px;box-shadow:0 2px 8px rgba(0,0,0,0.04)}
        h1{font-size:24px;margin-bottom:8px}
        h2{font-size:18px;margin-bottom:16px}
        .back-link{margin-bottom:20px;display:inline-block;color:#6c757d;text-decoration:none}
        .back-link:hover{color:#2c3e50}
        .btn-save{background:#2c3e50;color:white;border:none;border-radius:40px;padding:12px 30px;cursor:pointer;transition:0.2s}
        .btn-save:hover{background:#1a252f}
        .btn-delete{background:#dc3545;color:white;border:none;border-radius:20px;padding:4px 12px;font-size:12px;cursor:pointer;transition:0.2s;margin-left:10px}
        .btn-delete:hover{background:#c82333}
        .form-control,.form-select{border-radius:12px;padding:10px 14px;border:1px solid #ddd;width:100%}
        label{font-weight:500;margin-bottom:6px;display:block}
        .alert{border-radius:16px;padding:12px 16px;margin-bottom:20px}
        .history-item{
            border-left:3px solid #2c3e50;
            padding-left:14px;
            margin-bottom:20px;
            position:relative;
        }
        .history-header{
            display:flex;
            align-items:center;
            justify-content:space-between;
            flex-wrap:wrap;
        }
        .history-date{font-size:13px;font-weight:700;color:#2c3e50}
        .history-title{font-size:15px;font-weight:600;margin:4px 0}
        .history-desc{font-size:14px;color:#4a5568}
        .history-performer{font-size:12px;color:#6c757d;margin-top:4px}
        .delete-form{display:inline}
        .current-status{margin-top:16px;padding:12px;background:#f8f9fc;border-radius:16px;text-align:center}
    </style>
</head>
<body>
<div class="container">
    <a href="edit.php?id=<?= $part_id ?>" class="back-link">← Назад к запчасти</a>
    
    <?php if($deleted): ?>
        <div class="alert alert-success">Операция успешно удалена! Статус запчасти обновлён.</div>
    <?php endif; ?>
    
    <div class="card">
        <h1>📜 Добавление операции</h1>
        <p>Запчасть: <strong><?= htmlspecialchars($part['name']) ?></strong></p>
        
        <div class="current-status">
            📍 Текущий статус: 
            <?php
                switch($part['status']) {
                    case 'in_stock': echo '✅ В наличии'; break;
                    case 'under_repair': echo '🔧 В ремонте'; break;
                    case 'installed': echo '🔩 Установлена'; break;
                    case 'sold': echo '💰 Продана'; break;
                    case 'written_off': echo '📄 Списана'; break;
                    default: echo $part['status'];
                }
            ?>
        </div>
        
        <?php if($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap">
                <div style="flex:1">
                    <label>Тип операции *</label>
                    <select name="operation_type" class="form-select" required>
                        <option value="arrival">📦 Поступление (→ В наличии)</option>
                        <option value="repair">🔧 Ремонт (→ В ремонте)</option>
                        <option value="install">🔩 Установка (→ Установлена)</option>
                        <option value="sale">💰 Продажа (→ Продана)</option>
                        <option value="write_off">📄 Списание (→ Списана)</option>
                    </select>
                </div>
                <div style="flex:1">
                    <label>Дата</label>
                    <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="mb-3">
                <label>Описание *</label>
                <textarea name="description" class="form-control" rows="3" required placeholder="Например: Отдано в ремонт: восстановление вала..."></textarea>
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
        <?php 
$operationIndex = 0;
$totalOperations = count($operations);
foreach($operations as $op): 
    $operationIndex++;
    
    $isFirstArrival = false;
    if ($op['operation_type'] == 'arrival') {
        $hasEarlierArrival = false;
        $tempIndex = 0;
        foreach($operations as $tempOp) {
            $tempIndex++;
            if ($tempIndex >= $operationIndex) break;
            if ($tempOp['operation_type'] == 'arrival') {
                $hasEarlierArrival = true;
                break;
            }
        }
        $isFirstArrival = !$hasEarlierArrival;
    }
?>
<div class="history-item">
    <div class="history-date">
        <?= date('d.m.Y', strtotime($op['date'])) ?>
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="delete_operation.php?id=<?= $op['id'] ?>&part_id=<?= $part_id ?>" 
               onclick="return confirm('Удалить операцию?')" 
               style="float:right; color:#dc3545; text-decoration:none; font-size:12px;">🗑️</a>
        <?php endif; ?>
    </div>
    <div class="history-title">
        <?php
            if ($op['operation_type'] == 'arrival') {
                if ($isFirstArrival) {
                    echo '📦 Поступление';
                } else {
                    echo '✅ В наличии';
                }
            } else {
                switch($op['operation_type']) {
                    case 'repair': echo '🔧 Ремонт'; break;
                    case 'install': echo '🔩 Установка'; break;
                    case 'sale': echo '💰 Продажа'; break;
                    case 'write_off': echo '📄 Списание'; break;
                    default: echo $op['operation_type'];
                }
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

<script>
function confirmDelete(event) {
    event.preventDefault();
    const form = event.target;
    const confirmed = confirm('⚠️ Вы уверены, что хотите удалить эту операцию?\n\nСтатус запчасти будет автоматически пересчитан на основе оставшихся операций.');
    if (confirmed) {
        window.location.href = 'delete_operation.php?id=' + form.querySelector('input[name="id"]').value + '&part_id=' + form.querySelector('input[name="part_id"]').value;
    }
    return false;
}
</script>
</body>
</html>