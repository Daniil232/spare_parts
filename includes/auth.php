<?php
// includes/auth.php

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /spare_parts/admin/login.php');
        exit;
    }
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isStaff() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'staff';
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        // Показываем красивую страницу с ошибкой доступа
        showAccessDenied('Администратор');
        exit;
    }
}

function requireStaff() {
    requireLogin();
    if (!isStaff() && !isAdmin()) {
        showAccessDenied('Сотрудник');
        exit;
    }
}

// Функция для отображения страницы с ошибкой доступа
function showAccessDenied($requiredRole) {
    $userRole = $_SESSION['user_role'] ?? 'не авторизован';
    $userName = $_SESSION['username'] ?? 'Гость';
    
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Доступ запрещён</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                font-family: 'Segoe UI', system-ui, sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .modal-container {
                background: white;
                border-radius: 32px;
                max-width: 450px;
                width: 100%;
                padding: 40px 32px;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0,0,0,0.2);
                animation: fadeIn 0.3s ease;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .icon {
                font-size: 64px;
                margin-bottom: 20px;
            }
            h1 {
                font-size: 28px;
                font-weight: 700;
                margin-bottom: 12px;
                color: #c62828;
            }
            .message {
                font-size: 16px;
                color: #555;
                margin-bottom: 20px;
                line-height: 1.5;
            }
            .user-info {
                background: #f0f2f5;
                padding: 12px;
                border-radius: 16px;
                margin: 20px 0;
                font-size: 14px;
            }
            .user-info strong {
                color: #2c3e50;
            }
            .btn-back {
                background: #2c3e50;
                color: white;
                border: none;
                border-radius: 40px;
                padding: 12px 28px;
                font-weight: 600;
                text-decoration: none;
                display: inline-block;
                margin-top: 10px;
                transition: 0.2s;
            }
            .btn-back:hover {
                background: #1a252f;
                transform: translateY(-1px);
            }
            .btn-logout {
                background: #6c757d;
                color: white;
                border: none;
                border-radius: 40px;
                padding: 10px 24px;
                font-weight: 500;
                text-decoration: none;
                display: inline-block;
                margin-left: 10px;
                transition: 0.2s;
            }
            .btn-logout:hover {
                background: #5a6268;
            }
        </style>
    </head>
    <body>
        <div class="modal-container">
            <div class="icon">🔒</div>
            <h1>Доступ запрещён</h1>
            <div class="message">
                У вашей учётной записи недостаточно прав для доступа к этой странице.
            </div>
            <div class="user-info">
                <strong>Ваша роль:</strong> 
                <?php 
                    switch($userRole) {
                        case 'admin': echo 'Администратор'; break;
                        case 'staff': echo 'Сотрудник'; break;
                        default: echo 'Не авторизован';
                    }
                ?><br>
                <strong>Требуется роль:</strong> <?= $requiredRole ?>
            </div>
            <div>
                <a href="javascript:history.back()" class="btn-back">← Вернуться назад</a>
                <a href="/admin/logout.php" class="btn-logout">🚪 Выйти</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>