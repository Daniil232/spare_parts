<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireAdmin(); // Только администратор

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
    if ($id != $_SESSION['user_id']) { // Нельзя удалить себя
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
    <title>Управление пользователями</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f0f2f5; padding: 24px; }
        .container { max-width: 800px; margin: 0 auto; }
        .card { background: white; border-radius: 24px; padding: 30px; margin-bottom: 20px; }
        h1, h2 { font-size: 24px; margin-bottom: 20px; }
        .btn-save { background: #2c3e50; color: white; border-radius: 40px; padding: 10px 25px; border: none; }
        .btn-danger { background: #dc3545; }
        .btn-warning { background: #e67e22; color: white; }
        .user-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #eee; }
        .user-role { font-size: 12px; padding: 4px 10px; border-radius: 20px; }
        .role-admin { background: #c62828; color: white; }
        .role-staff { background: #2c3e50; color: white; }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link" style="display: inline-block; margin-bottom: 20px;">← Назад в админ-панель</a>
    
    <!-- Создание пользователя -->
    <div class="card">
        <h1>➕ Создать сотрудника</h1>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row">
                <div class="col-md-5 mb-3">
                    <label>Логин</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label>Пароль</label>
                    <input type="text" name="password" class="form-control" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label>Роль</label>
                    <select name="role" class="form-select">
                        <option value="staff">Сотрудник</option>
                        <option value="admin">Администратор</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="create_user" class="btn-save">➕ Создать</button>
        </form>
    </div>
    
    <!-- Список пользователей -->
    <div class="card">
        <h2>👥 Пользователи системы</h2>
        <?php foreach ($users as $u): ?>
            <div class="user-item">
                <div>
                    <strong><?= htmlspecialchars($u['username']) ?></strong>
                    <span class="user-role <?= $u['role'] == 'admin' ? 'role-admin' : 'role-staff' ?>">
                        <?= $u['role'] == 'admin' ? 'Администратор' : 'Сотрудник' ?>
                    </span>
                    <?php if ($u['id'] == $_SESSION['user_id']): ?>
                        <span class="badge bg-info">Вы</span>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($u['role'] != 'admin'): ?>
                        <a href="?make_admin=<?= $u['id'] ?>" class="btn-save" style="background: #e67e22; padding: 5px 15px; text-decoration: none;">👑 Сделать админом</a>
                    <?php elseif ($u['role'] == 'admin' && $u['id'] != $_SESSION['user_id']): ?>
                        <a href="?make_staff=<?= $u['id'] ?>" class="btn-save" style="background: #6c757d; padding: 5px 15px; text-decoration: none;">🔽 Сделать сотрудником</a>
                    <?php endif; ?>
                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <a href="?delete=<?= $u['id'] ?>" class="btn-save" style="background: #dc3545; padding: 5px 15px; text-decoration: none;" onclick="return confirm('Удалить пользователя?')">🗑️ Удалить</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>