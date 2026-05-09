<?php
require_once '../includes/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Неверный логин или пароль';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход в админ-панель</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 24px;
            padding: 40px;
            max-width: 400px;
            width: 100%;
        }
        h2 { text-align: center; margin-bottom: 24px; }
        .btn-login { background: #2c3e50; color: white; width: 100%; padding: 12px; border-radius: 40px; border: none; }
        .error { background: #fee2e2; color: #c62828; padding: 12px; border-radius: 12px; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="login-card">
    <h2>🔐 Вход в систему</h2>
    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>
    <form method="POST">
        <input type="text" name="username" class="form-control mb-3" placeholder="Логин" required>
        <input type="password" name="password" class="form-control mb-3" placeholder="Пароль" required>
        <button type="submit" class="btn-login">Войти</button>
    </form>
</div>
</body>
</html>