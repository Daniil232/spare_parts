<?php
require_once 'includes/config.php';

$stmt = $pdo->query("SELECT * FROM parts");
$parts = $stmt->fetchAll();

echo "Найдено запчастей: " . count($parts);
?>