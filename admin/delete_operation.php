<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

$operation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$redirect_part_id = isset($_GET['part_id']) ? (int)$_GET['part_id'] : 0;

if (!$operation_id) {
    die("Не указан ID операции");
}

// Получаем информацию об операции, чтобы знать part_id для редиректа
$stmt = $pdo->prepare("SELECT part_id, operation_type FROM operations WHERE id = ?");
$stmt->execute([$operation_id]);
$operation = $stmt->fetch();

if (!$operation) {
    die("Операция не найдена");
}

$part_id = $redirect_part_id ?: $operation['part_id'];

// Удаляем операцию
$stmt = $pdo->prepare("DELETE FROM operations WHERE id = ?");
$stmt->execute([$operation_id]);

// После удаления операции нужно пересчитать статус запчасти
// Определяем последнюю оставшуюся операцию
$stmt = $pdo->prepare("
    SELECT operation_type FROM operations 
    WHERE part_id = ? 
    ORDER BY date DESC, created_at DESC 
    LIMIT 1
");
$stmt->execute([$part_id]);
$lastOperation = $stmt->fetch();

// Устанавливаем статус на основе последней операции
if ($lastOperation) {
    switch($lastOperation['operation_type']) {
        case 'arrival':
            $new_status = 'in_stock';
            break;
        case 'repair':
            $new_status = 'under_repair';
            break;
        case 'install':
            $new_status = 'installed';
            break;
        case 'sale':
            $new_status = 'sold';
            break;
        case 'write_off':
            $new_status = 'written_off';
            break;
        default:
            $new_status = 'in_stock';
    }
} else {
    // Если операций не осталось, ставим статус "В наличии"
    $new_status = 'in_stock';
}

// Обновляем статус запчасти
$stmt = $pdo->prepare("UPDATE parts SET status = ? WHERE id = ?");
$stmt->execute([$new_status, $part_id]);

// Перенаправляем обратно на страницу операций
header("Location: add_operation.php?part_id=" . $part_id . "&deleted=1");
exit;
?>