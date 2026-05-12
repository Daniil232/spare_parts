<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

// Получаем параметры фильтрации
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$category_filter = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

// Базовый запрос
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

if ($category_filter > 0) {
    $sql .= " AND EXISTS (SELECT 1 FROM part_categories pc WHERE pc.part_id = p.id AND pc.category_id = ?)";
    $params[] = $category_filter;
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

// Статусы для отображения
$statuses = [
    'in_stock' => 'В наличии',
    'under_repair' => 'В ремонте',
    'installed' => 'Установлена',
    'sold' => 'Продана',
    'written_off' => 'Списана'
];

// Устанавливаем заголовки для скачивания CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="parts_export_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM для UTF-8

// Заголовки колонок
fputcsv($output, ['ID', 'Наименование', 'Каталожный номер', 'Статус', 'Местоположение', 'Категории', 'Дата создания']);

// Данные
foreach ($parts as $p) {
    fputcsv($output, [
        $p['id'],
        $p['name'],
        $p['catalog_number'] ?? '',
        $statuses[$p['status']] ?? $p['status'],
        $p['location'] ?? '',
        $partCategories[$p['id']] ?? '',
        date('d.m.Y', strtotime($p['created_at']))
    ]);
}

fclose($output);
exit;
?>