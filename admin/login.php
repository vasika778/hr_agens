<?php
require_once __DIR__ . '/../includes/functions.php';

// Если уже авторизован - редирект
if (isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Заполните все поля';
    } else {
        $admin = db()->fetch(
            "SELECT * FROM admins WHERE username = ? OR email = ?",
            [$username, $username]
        );
        
        if ($admin && password_verify($password, $admin['password'])) {
            // Проверяем активен ли пользователь
            if (!$admin['is_active']) {
                $error = 'Ваш аккаунт деактивирован. Обратитесь к администратору.';
            } else {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['full_name'];
                $_SESSION['admin_role'] = $admin['role'];
                
                // Записываем время входа
                db()->query("UPDATE admins SET last_login = NOW() WHERE id = ?", [$admin['id']]);
                
                header('Location: index.php');
                exit;
            }
        } else {
            $error = 'Неверный логин или пароль';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему - HR Agency</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💼</text></svg>">
    <style>
        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo {
            width: 80px;
            height: 80px;
            background: var(--accent-gradient);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem;
            box-shadow: var(--shadow-glow);
        }
        .login-title {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }
        .login-subtitle {
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="login-page">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">💼</div>
                <h1 class="login-title">HR Agency</h1>
                <p class="login-subtitle">Вход в панель управления</p>
            </div>

            <div class="card animate-fade-in">
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <span>⚠️</span>
                            <span><?= $error ?></span>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST">
                        <div class="form-group">
                            <label class="form-label" for="username">Логин или Email</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="username" 
                                   name="username" 
                                   placeholder="admin"
                                   value="<?= sanitize($_POST['username'] ?? '') ?>"
                                   autofocus
                                   required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password">Пароль</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   placeholder="••••••••"
                                   required>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg btn-block mt-3">
                            Войти в систему
                        </button>
                    </form>
                </div>
            </div>

            <p class="text-center text-muted mt-3" style="font-size: 0.875rem;">
                <a href="../index.php">← Вернуться на главную</a>
            </p>

            <p class="text-center text-muted mt-2" style="font-size: 0.75rem;">
                Демо: admin / admin123
            </p>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>
