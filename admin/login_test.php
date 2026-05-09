<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
header('Location: index.php');
exit;
?>