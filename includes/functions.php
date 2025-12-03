<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/database.php';


/**
 * Генерация уникального токена
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Очистка входных данных
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Валидация email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Валидация телефона
 */
function isValidPhone($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 15;
}

/**
 * Загрузка файла
 */
function uploadFile($file, $destination, $allowedExtensions) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Файл не загружен'];
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'Файл слишком большой'];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        return ['success' => false, 'error' => 'Недопустимый формат файла'];
    }

    $filename = generateToken(16) . '.' . $extension;
    $filepath = UPLOAD_PATH . $destination . '/' . $filename;

    if (!is_dir(UPLOAD_PATH . $destination)) {
        mkdir(UPLOAD_PATH . $destination, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false, 'error' => 'Ошибка при сохранении файла'];
}

/**
 * Удаление файла
 */
function deleteFile($filename, $destination) {
    $filepath = UPLOAD_PATH . $destination . '/' . $filename;
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

/**
 * Форматирование даты
 */
function formatDate($date, $format = 'd.m.Y H:i') {
    return date($format, strtotime($date));
}

/**
 * Получение статуса на русском
 */
function getStatusName($status) {
    return CANDIDATE_STATUSES[$status] ?? $status;
}

/**
 * Получение класса для статуса
 */
function getStatusClass($status) {
    $classes = [
        'new' => 'status-new',
        'reviewing' => 'status-reviewing',
        'interview' => 'status-interview',
        'testing' => 'status-testing',
        'approved' => 'status-approved',
        'rejected' => 'status-rejected',
        'hired' => 'status-hired'
    ];
    return $classes[$status] ?? 'status-default';
}

/**
 * Проверка авторизации администратора
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

/**
 * Требование авторизации администратора
 */
function requireAdmin() {
    if (!isAdminLoggedIn()) {
        header('Location: ' . SITE_URL . '/admin/login.php');
        exit;
    }
}

/**
 * Получение текущего администратора
 */
function getCurrentAdmin() {
    if (!isAdminLoggedIn()) {
        return null;
    }
    return db()->fetch("SELECT * FROM admins WHERE id = ?", [$_SESSION['admin_id']]);
}

/**
 * Проверка прав доступа
 */
function hasPermission($module, $action) {
    $admin = getCurrentAdmin();
    if (!$admin) {
        return false;
    }
    
    $role = $admin['role'];
    
    if (!isset(ROLE_PERMISSIONS[$role])) {
        return false;
    }
    
    if (!isset(ROLE_PERMISSIONS[$role][$module])) {
        return false;
    }
    
    return in_array($action, ROLE_PERMISSIONS[$role][$module]);
}

/**
 * Требование определённых прав доступа
 */
function requirePermission($module, $action) {
    if (!hasPermission($module, $action)) {
        $_SESSION['error_message'] = 'У вас нет прав для выполнения этого действия';
        header('Location: ' . SITE_URL . '/admin/index.php');
        exit;
    }
}

/**
 * Проверка роли (admin, hr_manager, recruiter)
 */
function hasRole($roles) {
    $admin = getCurrentAdmin();
    if (!$admin) {
        return false;
    }
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    return in_array($admin['role'], $roles);
}

/**
 * Получение названия роли
 */
function getRoleName($role) {
    return USER_ROLES[$role]['name'] ?? $role;
}

/**
 * Получение цвета роли
 */
function getRoleColor($role) {
    return USER_ROLES[$role]['color'] ?? '#6366f1';
}

/**
 * Проверка токена кандидата
 */
function getCandidateByToken($token) {
    if (empty($token)) {
        return null;
    }
    return db()->fetch("SELECT c.*, p.name as position_name, g.name as group_name 
                        FROM candidates c 
                        LEFT JOIN positions p ON c.position_id = p.id 
                        LEFT JOIN candidate_groups g ON c.group_id = g.id 
                        WHERE c.access_token = ?", [$token]);
}

/**
 * Логирование изменения статуса
 */
function logStatusChange($candidateId, $oldStatus, $newStatus, $adminId = null, $comment = '') {
    db()->query(
        "INSERT INTO status_history (candidate_id, old_status, new_status, changed_by, comment) VALUES (?, ?, ?, ?, ?)",
        [$candidateId, $oldStatus, $newStatus, $adminId, $comment]
    );
}

/**
 * Получение настройки
 */
function getSetting($key, $default = null) {
    $result = db()->fetch("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    return $result ? $result['setting_value'] : $default;
}

/**
 * Сохранение настройки
 */
function setSetting($key, $value) {
    db()->query(
        "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?",
        [$key, $value, $value]
    );
}

/**
 * Отправка JSON ответа
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * CSRF токен
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken(32);
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Получение всех должностей
 */
function getPositions() {
    return db()->fetchAll("SELECT * FROM positions WHERE is_active = 1 ORDER BY name");
}

/**
 * Получение всех групп
 */
function getGroups() {
    return db()->fetchAll("SELECT * FROM candidate_groups ORDER BY is_system DESC, name");
}

/**
 * Получение количества кандидатов по статусам
 */
function getCandidateStats() {
    $stats = [];
    foreach (CANDIDATE_STATUSES as $status => $name) {
        $result = db()->fetch("SELECT COUNT(*) as count FROM candidates WHERE status = ?", [$status]);
        $stats[$status] = $result['count'];
    }
    $result = db()->fetch("SELECT COUNT(*) as count FROM candidates");
    $stats['total'] = $result['count'];
    return $stats;
}

/**
 * Пагинация
 */
function paginate($totalItems, $currentPage, $perPage = 20) {
    $totalPages = ceil($totalItems / $perPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'total' => $totalItems,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages
    ];
}
