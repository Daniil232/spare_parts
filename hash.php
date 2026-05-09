<?php
// hash.php - генерация хэша пароля

$password = 'admin123'; // тот пароль, который хотите использовать

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Пароль: " . $password . "<br>";
echo "Хэш: " . $hash . "<br>";
echo "<hr>";
echo "Скопируйте этот хэш и вставьте в базу данных:<br>";
echo "<textarea rows='3' cols='80'>" . $hash . "</textarea>";
?>