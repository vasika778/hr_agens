<?php
$pageTitle = 'Настройки';
require_once 'includes/header.php';

// Проверяем права на просмотр настроек
if (!hasPermission('settings', 'view')) {
    header('Location: index.php');
    exit;
}

$canEdit = hasPermission('settings', 'edit');
$success = '';
$error = '';

// Обработка POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    $settings = [
        'company_name' => sanitize($_POST['company_name'] ?? ''),
        'company_email' => sanitize($_POST['company_email'] ?? ''),
        'company_phone' => sanitize($_POST['company_phone'] ?? ''),
        'test_enabled' => isset($_POST['test_enabled']) ? '1' : '0',
        'default_test_attempts' => (int)($_POST['default_test_attempts'] ?? 2),
        'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
        // SMTP
        'smtp_enabled' => isset($_POST['smtp_enabled']) ? '1' : '0',
        'smtp_host' => sanitize($_POST['smtp_host'] ?? ''),
        'smtp_port' => (int)($_POST['smtp_port'] ?? 465),
        'smtp_user' => sanitize($_POST['smtp_user'] ?? ''),
        'smtp_pass' => $_POST['smtp_pass'] ?? '',
        'smtp_encryption' => $_POST['smtp_encryption'] ?? 'ssl',
    ];
    
    foreach ($settings as $key => $value) {
        setSetting($key, $value);
    }
    
    $success = 'Настройки сохранены';
}

// Получаем текущие настройки
$companyName = getSetting('company_name', 'HR Agency');
$companyEmail = getSetting('company_email', 'hr@company.ru');
$companyPhone = getSetting('company_phone', '+7 (999) 123-45-67');
$testEnabled = getSetting('test_enabled', '1');
$defaultTestAttempts = getSetting('default_test_attempts', '2');
$emailNotifications = getSetting('email_notifications', '1');

// SMTP
$smtpEnabled = getSetting('smtp_enabled', '0');
$smtpHost = getSetting('smtp_host', '');
$smtpPort = getSetting('smtp_port', '465');
$smtpUser = getSetting('smtp_user', '');
$smtpPass = getSetting('smtp_pass', '');
$smtpEncryption = getSetting('smtp_encryption', 'ssl');
?>

<?php if ($success): ?>
    <div class="alert alert-success" data-auto-hide="5000">
        <span>✅</span>
        <span><?= $success ?></span>
    </div>
<?php endif; ?>

<form action="" method="POST">
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
        <!-- Company Settings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span>🏢</span>
                    Данные компании
                </h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Название компании</label>
                    <input type="text" name="company_name" class="form-control" value="<?= sanitize($companyName) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="company_email" class="form-control" value="<?= sanitize($companyEmail) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Телефон</label>
                    <input type="text" name="company_phone" class="form-control" value="<?= sanitize($companyPhone) ?>">
                </div>
            </div>
        </div>

        <!-- Test Settings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span>📝</span>
                    Настройки тестирования
                </h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="test_enabled" value="1" <?= $testEnabled === '1' ? 'checked' : '' ?>>
                        <span>Тестирование включено</span>
                    </label>
                    <div class="form-text">Если отключено, кандидаты не смогут проходить тесты</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Попыток по умолчанию</label>
                    <input type="number" name="default_test_attempts" class="form-control" 
                           value="<?= (int)$defaultTestAttempts ?>" min="1" max="10">
                    <div class="form-text">Количество попыток для прохождения теста</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Email Notifications -->
    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title">
                <span>📧</span>
                Email уведомления
            </h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="email_notifications" value="1" <?= $emailNotifications === '1' ? 'checked' : '' ?>>
                    <span>Включить email уведомления</span>
                </label>
                <div class="form-text">Отправка уведомлений кандидатам и HR-менеджерам</div>
            </div>
            
            <div style="background: var(--bg-tertiary); border-radius: var(--radius-md); padding: 1rem; margin-top: 1rem;">
                <div style="font-weight: 500; margin-bottom: 0.75rem; color: var(--text-primary);">📤 Уведомления для кандидатов:</div>
                <ul style="color: var(--text-secondary); font-size: 0.875rem; margin: 0; padding-left: 1.25rem;">
                    <li>Приглашение на тестирование (после подачи резюме)</li>
                    <li>Успешное прохождение теста</li>
                    <li>Исчерпание попыток тестирования</li>
                </ul>
                
                <div style="font-weight: 500; margin: 1rem 0 0.75rem; color: var(--text-primary);">📥 Уведомления для HR:</div>
                <ul style="color: var(--text-secondary); font-size: 0.875rem; margin: 0; padding-left: 1.25rem;">
                    <li>Новая заявка от кандидата</li>
                    <li>Кандидат завершил тестирование (с результатами)</li>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- SMTP Settings -->
    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title">
                <span>📮</span>
                Настройки SMTP
            </h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                    <input type="checkbox" name="smtp_enabled" value="1" <?= $smtpEnabled === '1' ? 'checked' : '' ?>>
                    <span>Использовать SMTP для отправки</span>
                </label>
                <div class="form-text">Если отключено, будет использоваться стандартная функция mail()</div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">SMTP сервер</label>
                    <input type="text" name="smtp_host" class="form-control" 
                           value="<?= sanitize($smtpHost) ?>" placeholder="smtp.gmail.com">
                </div>
                <div class="form-group" style="max-width: 120px;">
                    <label class="form-label">Порт</label>
                    <input type="number" name="smtp_port" class="form-control" 
                           value="<?= (int)$smtpPort ?>" placeholder="465">
                </div>
                <div class="form-group" style="max-width: 120px;">
                    <label class="form-label">Шифрование</label>
                    <select name="smtp_encryption" class="form-control">
                        <option value="ssl" <?= $smtpEncryption === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        <option value="tls" <?= $smtpEncryption === 'tls' ? 'selected' : '' ?>>TLS</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">SMTP логин</label>
                    <input type="text" name="smtp_user" class="form-control" 
                           value="<?= sanitize($smtpUser) ?>" placeholder="your@email.com">
                </div>
                <div class="form-group">
                    <label class="form-label">SMTP пароль</label>
                    <input type="password" name="smtp_pass" class="form-control" 
                           value="<?= sanitize($smtpPass) ?>" placeholder="••••••••">
                </div>
            </div>
            
            <div class="alert alert-info" style="margin-bottom: 0;">
                <span>💡</span>
                <div>
                    <strong>Популярные SMTP серверы:</strong><br>
                    <small>
                        Gmail: smtp.gmail.com, порт 465 (SSL) или 587 (TLS)<br>
                        Яндекс: smtp.yandex.ru, порт 465 (SSL)<br>
                        Mail.ru: smtp.mail.ru, порт 465 (SSL)
                    </small>
                </div>
            </div>
        </div>
    </div>

    <?php if ($canEdit): ?>
    <div class="card mt-3">
        <div class="card-body">
            <button type="submit" class="btn btn-primary btn-lg">
                💾 Сохранить настройки
            </button>
        </div>
    </div>
    <?php else: ?>
    <div class="alert alert-warning mt-3">
        <span>⚠️</span>
        <span>У вас нет прав для редактирования настроек</span>
    </div>
    <?php endif; ?>
</form>

<!-- System Info -->
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title">
            <span>ℹ️</span>
            Информация о системе
        </h3>
    </div>
    <div class="card-body">
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Версия PHP</span>
                <span class="info-value"><?= phpversion() ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Версия системы</span>
                <span class="info-value">1.0.0</span>
            </div>
            <div class="info-item">
                <span class="info-label">Размер загрузок</span>
                <span class="info-value">
                    <?php
                    function getDirSize($path) {
                        $size = 0;
                        if (is_dir($path)) {
                            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
                                if ($file->isFile()) {
                                    $size += $file->getSize();
                                }
                            }
                        }
                        return $size;
                    }
                    $uploadSize = getDirSize(UPLOAD_PATH);
                    echo number_format($uploadSize / 1024 / 1024, 2) . ' МБ';
                    ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Часовой пояс</span>
                <span class="info-value"><?= date_default_timezone_get() ?></span>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
