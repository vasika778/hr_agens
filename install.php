<?php
/**
 * Скрипт установки HR Agency System
 * 
 * 1. Создайте базу данных MySQL
 * 2. Импортируйте database.sql
 * 3. Настройте includes/config.php
 * 4. Откройте install.php в браузере или запустите: php install.php
 * 5. Удалите этот файл после установки!
 */

$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Установка - HR Agency</title>
        <link rel="stylesheet" href="assets/css/style.css">
    </head>
    <body>
        <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem;">
            <div class="card" style="max-width: 500px;">
                <div class="card-header">
                    <h2 class="card-title">🔧 Установка HR Agency</h2>
                </div>
                <div class="card-body">
    <?php
}

require_once __DIR__ . '/includes/database.php';

$messages = [];

// Проверяем подключение к БД
try {
    $db = db();
    $messages[] = ['success', '✓ Подключение к базе данных успешно'];
} catch (Exception $e) {
    $messages[] = ['error', '✗ Ошибка подключения к БД: ' . $e->getMessage()];
    outputMessages($messages, $isCli);
    exit;
}

// Создаём/обновляем администратора
$username = 'admin';
$password = 'admin123';
$email = 'admin@hr-agency.ru';
$fullName = 'Главный администратор';

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

try {
    // Проверяем, существует ли админ
    $existing = $db->fetch("SELECT id FROM admins WHERE username = ?", [$username]);

    if ($existing) {
        $db->query(
            "UPDATE admins SET password = ? WHERE username = ?",
            [$hashedPassword, $username]
        );
        $messages[] = ['success', '✓ Пароль администратора обновлён'];
    } else {
        $db->query(
            "INSERT INTO admins (username, email, password, full_name, role, is_active) VALUES (?, ?, ?, ?, 'admin', 1)",
            [$username, $email, $hashedPassword, $fullName]
        );
        $messages[] = ['success', '✓ Администратор создан'];
    }
} catch (Exception $e) {
    $messages[] = ['error', '✗ Ошибка создания админа: ' . $e->getMessage()];
}

// Проверяем директории
$dirs = ['uploads/resumes', 'uploads/about'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) {
        if (mkdir($path, 0755, true)) {
            $messages[] = ['success', "✓ Создана директория $dir"];
        } else {
            $messages[] = ['warning', "⚠ Не удалось создать директорию $dir"];
        }
    }
}

outputMessages($messages, $isCli);

if ($isCli) {
    echo "\n=== Установка завершена! ===\n";
    echo "\nДанные для входа:\n";
    echo "  URL: /admin/login.php\n";
    echo "  Логин: $username\n";
    echo "  Пароль: $password\n";
    echo "\n⚠️  УДАЛИТЕ ЭТОТ ФАЙЛ (install.php) ПОСЛЕ УСТАНОВКИ!\n";
} else {
    ?>
                    <div class="alert alert-success mt-3">
                        <strong>Установка завершена!</strong>
                    </div>
                    
                    <div style="background: var(--bg-secondary); padding: 1rem; border-radius: var(--radius-md); margin-top: 1rem;">
                        <p><strong>Данные для входа:</strong></p>
                        <p>Логин: <code>admin</code></p>
                        <p>Пароль: <code>admin123</code></p>
                    </div>
                    
                    <div class="alert alert-warning mt-3">
                        <strong>⚠️ Удалите файл install.php после установки!</strong>
                    </div>
                    
                    <a href="admin/login.php" class="btn btn-primary btn-block mt-3">
                        Войти в админ-панель
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function outputMessages($messages, $isCli) {
    foreach ($messages as $msg) {
        if ($isCli) {
            echo $msg[1] . "\n";
        } else {
            $class = $msg[0] === 'success' ? 'alert-success' : ($msg[0] === 'error' ? 'alert-danger' : 'alert-warning');
            echo "<div class='alert {$class}' style='margin-bottom: 0.5rem;'>{$msg[1]}</div>";
        }
    }
}
