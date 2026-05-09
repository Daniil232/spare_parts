<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Получаем параметры фильтрации (как в index.php)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$category_filter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

$sql = "
    SELECT p.id, p.name, p.catalog_number, p.status, p.location, 
           p.created_at, u.username as creator, c.name as category_name
    FROM parts p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.created_by = u.id
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
if ($category_filter > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category_filter;
}
$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$parts = $stmt->fetchAll();

// Устанавливаем заголовки для скачивания CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="parts_export_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM для UTF-8

// Заголовки колонок
fputcsv($output, ['ID', 'Наименование', 'Каталожный номер', 'Статус', 'Местоположение', 'Категория', 'Автор', 'Дата создания']);

// Статусы для отображения
$statuses = [
    'in_stock' => 'В наличии',
    'under_repair' => 'В ремонте',
    'installed' => 'Установлена',
    'sold' => 'Продана',
    'written_off' => 'Списана'
];

// Данные
foreach ($parts as $p) {
    fputcsv($output, [
        $p['id'],
        $p['name'],
        $p['catalog_number'] ?? '',
        $statuses[$p['status']] ?? $p['status'],
        $p['location'] ?? '',
        $p['category_name'] ?? '',
        $p['creator'] ?? '',
        date('d.m.Y', strtotime($p['created_at']))
    ]);
}

fclose($output);
exit;
?>