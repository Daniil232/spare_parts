<?php
// includes/config.php

$host = 'localhost';
$dbname = 'spare_parts';
$username = 'root';
$password = '';  // пустой, как вы вошли в Adminer

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

session_start();
?>