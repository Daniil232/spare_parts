<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin();

$error = '';
$success = '';

// Создание нового пользователя
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'staff';
    
    if (empty($username) || empty($password)) {
        $error = 'Логин и пароль обязательны';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, ?, NOW())");
        
        try {
            $stmt->execute([$username, $hash, $role]);
            $success = "Пользователь '$username' создан! Пароль: $password";
        } catch (PDOException $e) {
            $error = "Ошибка: логин '$username' уже существует";
        }
    }
}

// Удаление пользователя
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Пользователь удалён";
    } else {
        $error = "Нельзя удалить самого себя";
    }
}

// Изменение роли
if (isset($_GET['make_admin'])) {
    $id = (int)$_GET['make_admin'];
    $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
    $stmt->execute([$id]);
    $success = "Пользователь стал администратором";
}

if (isset($_GET['make_staff'])) {
    $id = (int)$_GET['make_staff'];
    if ($id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("UPDATE users SET role = 'staff' WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Пользователь стал сотрудником";
    } else {
        $error = "Нельзя понизить самого себя";
    }
}

// Получаем список пользователей
$users = $pdo->query("SELECT * FROM users ORDER BY id")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Управление пользователями</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #f0f2f5; font-family: 'Segoe UI', system-ui, sans-serif; }
        
        /* Шапка */
        .top-header {
            background: #1a1a2e;
            color: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .logo { font-size: 16px; font-weight: 600; }
        .logo span { font-size: 22px; margin-right: 6px; }
        .nav-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        .nav-buttons a {
            color: white;
            text-decoration: none;
            padding: 5px 12px;
            border-radius: 40px;
            background: rgba(255,255,255,0.1);
            font-size: 13px;
        }
        .nav-buttons a:hover { background: rgba(255,255,255,0.2); }
        
        /* Основной контент */
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Карточки */
        .card {
            background: white;
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        h1 { font-size: 24px; margin-bottom: 8px; }
        h2 { font-size: 18px; margin-bottom: 16px; }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #6c757d;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }
        
        /* Форма */
        .form-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .form-group {
            flex: 1;
            min-width: 150px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 5px;
        }
        .form-control, .form-select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 12px;
            font-size: 14px;
        }
        .btn-save {
            background: #2c3e50;
            color: white;
            border: none;
            border-radius: 40px;
            padding: 10px 24px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-save:hover { background: #1a252f; }
        
        /* Список пользователей */
        .users-list { margin-top: 20px; }
        .user-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid #eef2f6;
            flex-wrap: wrap;
            gap: 10px;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .user-name {
            font-weight: 600;
            font-size: 15px;
        }
        .user-role {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 20px;
        }
        .role-admin { background: #c62828; color: white; }
        .role-staff { background: #2c3e50; color: white; }
        .badge-current { background: #27ae60; color: white; font-size: 10px; padding: 2px 8px; border-radius: 20px; }
        
        .user-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .action-btn {
            padding: 6px 14px;
            border-radius: 40px;
            text-decoration: none;
            font-size: 12px;
            transition: 0.2s;
        }
        .action-make-admin { background: #e67e22; color: white; }
        .action-make-staff { background: #6c757d; color: white; }
        .action-delete { background: #dc3545; color: white; }
        .action-btn:hover { opacity: 0.8; color: white; }
        
        .alert {
            border-radius: 16px;
            padding: 12px 16px;
            margin-bottom: 20px;
        }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .alert-danger { background: #ffebee; color: #c62828; }
        
        /* Мобильная версия */
        @media (max-width: 768px) {
            .container { padding: 16px; }
            .card { padding: 20px; }
            h1 { font-size: 22px; }
            .form-row { flex-direction: column; }
            .form-group { width: 100%; }
            .btn-save { width: 100%; margin-top: 8px; }
            .user-item { flex-direction: column; align-items: flex-start; }
            .user-actions { width: 100%; justify-content: flex-start; }
            .action-btn { flex: 1; text-align: center; }
        }
    </style>
</head>
<body>

<!-- Шапка -->
<div class="top-header">
    <div class="logo"><span>🔧</span> Цифровые паспорта</div>
    <div class="nav-buttons">
        <a href="index.php">📋 Запчасти</a>
        <a href="logout.php" style="background: #dc3545;">🚪 Выход</a>
    </div>
</div>

<div class="container">
    <a href="index.php" class="back-link">← Назад в админ-панель</a>
    
    <!-- Создание пользователя -->
    <div class="card">
        <h1>➕ Создать пользователя</h1>
        <p class="text-muted mb-4">Добавьте нового сотрудника или администратора</p>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label>Логин *</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Пароль *</label>
                    <input type="text" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Роль</label>
                    <select name="role" class="form-select">
                        <option value="staff">Сотрудник</option>
                        <option value="admin">Администратор</option>
                    </select>
                </div>
                <div>
                    <button type="submit" name="create_user" class="btn-save">➕ Создать</button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Список пользователей -->
    <div class="card">
        <h2>👥 Пользователи системы</h2>
        <div class="users-list">
            <?php foreach ($users as $u): ?>
                <div class="user-item">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($u['username']) ?></span>
                        <span class="user-role <?= $u['role'] == 'admin' ? 'role-admin' : 'role-staff' ?>">
                            <?= $u['role'] == 'admin' ? 'Администратор' : 'Сотрудник' ?>
                        </span>
                        <?php if ($u['id'] == $_SESSION['user_id']): ?>
                            <span class="badge-current">Вы</span>
                        <?php endif; ?>
                    </div>
                    <div class="user-actions">
                        <?php if ($u['role'] != 'admin'): ?>
                            <a href="?make_admin=<?= $u['id'] ?>" class="action-btn action-make-admin">👑 Сделать админом</a>
                        <?php elseif ($u['role'] == 'admin' && $u['id'] != $_SESSION['user_id']): ?>
                            <a href="?make_staff=<?= $u['id'] ?>" class="action-btn action-make-staff">🔽 Сделать сотрудником</a>
                        <?php endif; ?>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <a href="?delete=<?= $u['id'] ?>" class="action-btn action-delete" onclick="return confirm('Удалить пользователя?')">🗑️ Удалить</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>