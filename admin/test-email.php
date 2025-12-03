<?php
$pageTitle = 'Тест Email';
require_once 'includes/header.php';
require_once __DIR__ . '/../includes/email.php';

// Только админ
if (!hasRole('admin')) {
    header('Location: index.php');
    exit;
}

$result = null;
$testEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testEmail = sanitize($_POST['test_email'] ?? '');
    
    if (!empty($testEmail) && filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        // Создаём тестового кандидата
        $testCandidate = [
            'id' => 0,
            'name' => 'Тестовый Пользователь',
            'email' => $testEmail,
            'phone' => '+7 999 123-45-67',
            'access_token' => 'test_token_' . time(),
            'position_id' => null
        ];
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'test_invitation':
                $result = emailNotifier()->notifyTestInvitation($testCandidate);
                $resultText = 'Приглашение на тестирование';
                break;
            case 'test_passed':
                $result = emailNotifier()->notifyTestPassed($testCandidate, 85, 85);
                $resultText = 'Тест успешно пройден';
                break;
            case 'test_failed':
                $result = emailNotifier()->notifyTestFailed($testCandidate);
                $resultText = 'Попытки закончились';
                break;
            case 'new_resume':
                $result = emailNotifier()->notifyNewResume($testCandidate, $testEmail);
                $resultText = 'Новое резюме (HR)';
                break;
            case 'test_completed':
                $testResult = ['score' => 85, 'max_score' => 100, 'percentage' => 85, 'passed' => true];
                $result = emailNotifier()->notifyTestCompleted($testCandidate, $testResult, $testEmail);
                $resultText = 'Тест завершён (HR)';
                break;
            default:
                $resultText = 'Неизвестное действие';
        }
    }
}

$emailEnabled = getSetting('email_notifications', '1') === '1';
$companyEmail = getSetting('company_email', 'hr@company.ru');
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <span>📧</span>
            Тестирование Email уведомлений
        </h3>
    </div>
    <div class="card-body">
        <?php if (!$emailEnabled): ?>
            <div class="alert alert-warning">
                <span>⚠️</span>
                <span>Email уведомления отключены в <a href="settings.php">настройках</a></span>
            </div>
        <?php endif; ?>
        
        <?php if ($result !== null): ?>
            <?php if ($result): ?>
                <div class="alert alert-success">
                    <span>✅</span>
                    <span>Email "<?= $resultText ?>" отправлен на <?= sanitize($testEmail) ?></span>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <span>❌</span>
                    <span>Ошибка отправки email. Проверьте лог: /uploads/email_log.txt</span>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Email для теста</label>
                <input type="email" name="test_email" class="form-control" 
                       value="<?= sanitize($testEmail ?: $companyEmail) ?>" required>
                <div class="form-text">На этот адрес будет отправлено тестовое письмо</div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1.5rem;">
                <div class="card" style="background: var(--bg-tertiary);">
                    <div class="card-body" style="padding: 1rem;">
                        <div style="font-weight: 500; margin-bottom: 0.5rem;">📤 Для кандидата</div>
                        <button type="submit" name="action" value="test_invitation" class="btn btn-secondary btn-sm btn-block mb-2">
                            Приглашение на тест
                        </button>
                        <button type="submit" name="action" value="test_passed" class="btn btn-secondary btn-sm btn-block mb-2">
                            Тест пройден
                        </button>
                        <button type="submit" name="action" value="test_failed" class="btn btn-secondary btn-sm btn-block">
                            Попытки закончились
                        </button>
                    </div>
                </div>
                
                <div class="card" style="background: var(--bg-tertiary);">
                    <div class="card-body" style="padding: 1rem;">
                        <div style="font-weight: 500; margin-bottom: 0.5rem;">📥 Для HR</div>
                        <button type="submit" name="action" value="new_resume" class="btn btn-secondary btn-sm btn-block mb-2">
                            Новое резюме
                        </button>
                        <button type="submit" name="action" value="test_completed" class="btn btn-secondary btn-sm btn-block">
                            Результат теста
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Лог -->
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title">
            <span>📋</span>
            Лог отправки (последние записи)
        </h3>
    </div>
    <div class="card-body">
        <?php
        $logFile = UPLOAD_PATH . 'email_log.txt';
        if (file_exists($logFile)):
            $lines = file($logFile);
            $lastLines = array_slice($lines, -20);
            $lastLines = array_reverse($lastLines);
        ?>
            <pre style="background: var(--bg-primary); padding: 1rem; border-radius: var(--radius-md); font-size: 0.8125rem; overflow-x: auto; max-height: 300px;"><?php
                foreach ($lastLines as $line) {
                    echo sanitize(trim($line)) . "\n";
                }
            ?></pre>
            <form method="POST" action="" style="margin-top: 1rem;">
                <input type="hidden" name="clear_log" value="1">
            </form>
        <?php else: ?>
            <p class="text-muted">Лог пуст. Отправьте тестовое письмо.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Инфо о настройках PHP mail -->
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title">
            <span>ℹ️</span>
            Информация о настройках почты
        </h3>
    </div>
    <div class="card-body">
        <table class="table">
            <tr>
                <td style="width: 200px;">sendmail_path</td>
                <td><code><?= ini_get('sendmail_path') ?: 'Не настроен' ?></code></td>
            </tr>
            <tr>
                <td>SMTP</td>
                <td><code><?= ini_get('SMTP') ?: 'Не настроен' ?></code></td>
            </tr>
            <tr>
                <td>smtp_port</td>
                <td><code><?= ini_get('smtp_port') ?: 'Не настроен' ?></code></td>
            </tr>
            <tr>
                <td>mail() доступна</td>
                <td><code><?= function_exists('mail') ? 'Да ✅' : 'Нет ❌' ?></code></td>
            </tr>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
