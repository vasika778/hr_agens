<?php
/**
 * Класс для отправки email через SMTP
 */
class SmtpMailer {
    
    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption;
    private $timeout = 30;
    private $socket;
    private $debug = false;
    private $log = [];
    
    public function __construct($host, $port, $username, $password, $encryption = 'ssl') {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->encryption = $encryption;
    }
    
    public function send($from, $fromName, $to, $subject, $body) {
        try {
            // Подключение
            $host = ($this->encryption === 'ssl') ? 'ssl://' . $this->host : $this->host;
            $this->socket = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout);
            
            if (!$this->socket) {
                throw new Exception("Не удалось подключиться к SMTP серверу: $errstr ($errno)");
            }
            
            stream_set_timeout($this->socket, $this->timeout);
            
            $this->getResponse(); // Приветствие сервера
            
            // EHLO
            $this->sendCommand("EHLO " . gethostname());
            
            // STARTTLS для TLS
            if ($this->encryption === 'tls') {
                $this->sendCommand("STARTTLS");
                stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->sendCommand("EHLO " . gethostname());
            }
            
            // Авторизация
            $this->sendCommand("AUTH LOGIN");
            $this->sendCommand(base64_encode($this->username));
            $this->sendCommand(base64_encode($this->password));
            
            // Отправитель и получатель
            $this->sendCommand("MAIL FROM:<{$from}>");
            $this->sendCommand("RCPT TO:<{$to}>");
            
            // Данные письма
            $this->sendCommand("DATA");
            
            // Заголовки
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            
            $message = $headers . "\r\n" . $body . "\r\n.";
            $this->sendCommand($message, false);
            
            // Завершение
            $this->sendCommand("QUIT");
            
            fclose($this->socket);
            return true;
            
        } catch (Exception $e) {
            $this->log[] = "Ошибка: " . $e->getMessage();
            if ($this->socket) {
                fclose($this->socket);
            }
            return false;
        }
    }
    
    private function sendCommand($command, $expectResponse = true) {
        fputs($this->socket, $command . "\r\n");
        $this->log[] = ">>> " . (strpos($command, 'AUTH') !== false ? '[AUTH DATA]' : substr($command, 0, 100));
        
        if ($expectResponse) {
            return $this->getResponse();
        }
        return true;
    }
    
    private function getResponse() {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        
        $this->log[] = "<<< " . trim($response);
        
        $code = substr($response, 0, 3);
        if ($code >= 400) {
            throw new Exception("SMTP ошибка: $response");
        }
        
        return $response;
    }
    
    public function getLog() {
        return $this->log;
    }
}

/**
 * Класс для отправки email уведомлений
 */
class EmailNotifier {
    
    private $fromEmail;
    private $fromName;
    private $enabled;
    private $useSmtp;
    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;
    private $smtpEncryption;
    
    public function __construct() {
        $this->fromEmail = getSetting('company_email', 'hr@company.ru');
        $this->fromName = getSetting('company_name', 'HR Agency');
        $this->enabled = getSetting('email_notifications', '1') === '1';
        
        // SMTP настройки
        $this->useSmtp = getSetting('smtp_enabled', '0') === '1';
        $this->smtpHost = getSetting('smtp_host', '');
        $this->smtpPort = (int)getSetting('smtp_port', '465');
        $this->smtpUser = getSetting('smtp_user', '');
        $this->smtpPass = getSetting('smtp_pass', '');
        $this->smtpEncryption = getSetting('smtp_encryption', 'ssl');
    }
    
    /**
     * Отправка email
     */
    private function send($to, $subject, $body) {
        if (!$this->enabled) {
            $this->log("Email отключён в настройках");
            return false;
        }
        
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->log("Некорректный email: $to");
            return false;
        }
        
        // Используем SMTP если настроен
        if ($this->useSmtp && !empty($this->smtpHost)) {
            return $this->sendViaSmtp($to, $subject, $body);
        }
        
        // Иначе используем mail()
        return $this->sendViaMail($to, $subject, $body);
    }
    
    /**
     * Отправка через SMTP
     */
    private function sendViaSmtp($to, $subject, $body) {
        $mailer = new SmtpMailer(
            $this->smtpHost,
            $this->smtpPort,
            $this->smtpUser,
            $this->smtpPass,
            $this->smtpEncryption
        );
        
        $result = $mailer->send($this->fromEmail, $this->fromName, $to, $subject, $body);
        
        foreach ($mailer->getLog() as $logLine) {
            $this->log("[SMTP] $logLine");
        }
        
        if ($result) {
            $this->log("Email отправлен через SMTP: $to - $subject");
        } else {
            $this->log("Ошибка отправки через SMTP: $to");
        }
        
        return $result;
    }
    
    /**
     * Отправка через mail()
     */
    private function sendViaMail($to, $subject, $body) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . $this->fromName . " <" . $this->fromEmail . ">\r\n";
        $headers .= "Reply-To: " . $this->fromEmail . "\r\n";
        
        $subjectEncoded = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        
        $result = @mail($to, $subjectEncoded, $body, $headers);
        
        if ($result) {
            $this->log("Email отправлен через mail(): $to - $subject");
        } else {
            $this->log("Ошибка отправки через mail(): $to");
        }
        
        return $result;
    }
    
    /**
     * Логирование
     */
    private function log($message) {
        $logFile = UPLOAD_PATH . 'email_log.txt';
        $date = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[$date] $message\n", FILE_APPEND);
    }
    
    /**
     * Шаблон письма
     */
    private function template($title, $content, $buttonText = null, $buttonUrl = null) {
        $companyName = getSetting('company_name', 'HR Agency');
        $button = '';
        if ($buttonText && $buttonUrl) {
            $button = '
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . $buttonUrl . '" style="display: inline-block; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); color: white; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px;">
                    ' . $buttonText . '
                </a>
            </div>';
        }
        
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #0a0a0f; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">
    <div style="max-width: 600px; margin: 0 auto; padding: 40px 20px;">
        <div style="text-align: center; margin-bottom: 30px;">
            <div style="display: inline-block; background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); width: 60px; height: 60px; border-radius: 16px; line-height: 60px; font-size: 30px;">💼</div>
            <h1 style="color: #f0f0f5; margin: 20px 0 0; font-size: 24px;">' . $companyName . '</h1>
        </div>
        <div style="background: #15151f; border-radius: 16px; padding: 32px; border: 1px solid #2a2a3a;">
            <h2 style="color: #f0f0f5; margin: 0 0 20px; font-size: 20px;">' . $title . '</h2>
            <div style="color: #a0a0b0; font-size: 16px; line-height: 1.6;">' . $content . '</div>
            ' . $button . '
        </div>
        <div style="text-align: center; margin-top: 30px; color: #606070; font-size: 14px;">
            <p>Это автоматическое уведомление от ' . $companyName . '</p>
        </div>
    </div>
</body>
</html>';
    }
    
    // =====================================================
    // УВЕДОМЛЕНИЯ ДЛЯ КАНДИДАТОВ
    // =====================================================
    
    public function notifyTestInvitation($candidate) {
        $subject = 'Приглашение на тестирование';
        $testUrl = SITE_URL . 'candidate/?token=' . $candidate['access_token'];
        
        $content = '
            <p>Здравствуйте, <strong>' . sanitize($candidate['name']) . '</strong>!</p>
            <p>Вы успешно зарегистрированы в нашей системе подбора персонала.</p>
            <p>Для продолжения вам необходимо пройти входное тестирование.</p>
            <p style="color: #f59e0b; margin-top: 20px;">⚠️ Количество попыток ограничено.</p>
        ';
        
        $body = $this->template($subject, $content, 'Пройти тестирование', $testUrl);
        return $this->send($candidate['email'], $subject, $body);
    }
    
    public function notifyTestPassed($candidate, $score, $percentage) {
        $subject = 'Поздравляем! Тест успешно пройден';
        $cabinetUrl = SITE_URL . 'candidate/?token=' . $candidate['access_token'];
        
        $content = '
            <p>Здравствуйте, <strong>' . sanitize($candidate['name']) . '</strong>!</p>
            <p>Поздравляем! Вы успешно прошли тестирование.</p>
            <div style="background: #1a1a25; padding: 20px; border-radius: 10px; margin: 20px 0; text-align: center;">
                <div style="color: #10b981; font-size: 32px; font-weight: 700;">' . $percentage . '%</div>
                <div style="color: #606070;">Ваш результат</div>
            </div>
            <p>Наш HR-специалист свяжется с вами в ближайшее время.</p>
        ';
        
        $body = $this->template($subject, $content, 'Открыть личный кабинет', $cabinetUrl);
        return $this->send($candidate['email'], $subject, $body);
    }
    
    public function notifyTestFailed($candidate) {
        $subject = 'Результаты тестирования';
        
        $content = '
            <p>Здравствуйте, <strong>' . sanitize($candidate['name']) . '</strong>!</p>
            <p>К сожалению, все попытки прохождения тестирования исчерпаны.</p>
            <p>Вы можете подать заявку повторно через некоторое время.</p>
            <div style="background: #1a1a25; padding: 20px; border-radius: 10px; margin: 20px 0; text-align: center;">
                <p style="color: #606070; margin: 0;">Свяжитесь с нами:</p>
                <p style="color: #f0f0f5; margin: 10px 0 0; font-size: 18px;">' . getSetting('company_email') . '</p>
            </div>
        ';
        
        $body = $this->template($subject, $content);
        return $this->send($candidate['email'], $subject, $body);
    }
    
    // =====================================================
    // УВЕДОМЛЕНИЯ ДЛЯ HR
    // =====================================================
    
    public function notifyNewResume($candidate, $hrEmail = null) {
        $subject = 'Новая заявка от кандидата';
        
        if (!$hrEmail) {
            $hrEmail = getSetting('company_email', 'hr@company.ru');
        }
        
        $candidateUrl = SITE_URL . 'admin/candidate.php?id=' . $candidate['id'];
        
        $content = '
            <p>Поступила новая заявка:</p>
            <div style="background: #1a1a25; padding: 20px; border-radius: 10px; margin: 20px 0;">
                <p style="margin: 0 0 10px;"><strong style="color: #f0f0f5;">' . sanitize($candidate['name']) . '</strong></p>
                <p style="margin: 0; color: #a0a0b0;">📧 ' . sanitize($candidate['email']) . '</p>
                <p style="margin: 5px 0 0; color: #a0a0b0;">📱 ' . sanitize($candidate['phone']) . '</p>
            </div>
        ';
        
        $body = $this->template($subject, $content, 'Посмотреть заявку', $candidateUrl);
        return $this->send($hrEmail, $subject, $body);
    }
    
    public function notifyTestCompleted($candidate, $result, $hrEmail = null) {
        if (!$hrEmail) {
            $hrEmail = getSetting('company_email', 'hr@company.ru');
        }
        
        $passed = $result['passed'];
        $subject = $passed ? 'Кандидат прошёл тестирование' : 'Кандидат не прошёл тестирование';
        $statusColor = $passed ? '#10b981' : '#ef4444';
        $statusText = $passed ? '✅ ПРОЙДЕН' : '❌ НЕ ПРОЙДЕН';
        
        $candidateUrl = SITE_URL . 'admin/candidate.php?id=' . $candidate['id'];
        
        $content = '
            <p>Кандидат <strong>' . sanitize($candidate['name']) . '</strong> завершил тестирование.</p>
            <div style="background: #1a1a25; padding: 20px; border-radius: 10px; margin: 20px 0; text-align: center;">
                <div style="color: ' . $statusColor . '; font-size: 18px; font-weight: 600;">' . $statusText . '</div>
                <div style="color: #f0f0f5; font-size: 32px; font-weight: 700; margin: 10px 0;">' . $result['percentage'] . '%</div>
                <div style="color: #606070;">' . $result['score'] . ' из ' . $result['max_score'] . ' баллов</div>
            </div>
        ';
        
        $body = $this->template($subject, $content, 'Открыть карточку', $candidateUrl);
        return $this->send($hrEmail, $subject, $body);
    }
}

function emailNotifier() {
    static $instance = null;
    if ($instance === null) {
        $instance = new EmailNotifier();
    }
    return $instance;
}
