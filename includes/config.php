<?php
// Конфигурация базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'j1000260_hr');
define('DB_USER', '047065156_hr');
define('DB_PASS', '121325kvs');

// Конфигурация приложения
define('SITE_URL', 'https://hr.kvskg.ru/');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Допустимые расширения файлов
define('ALLOWED_RESUME_EXTENSIONS', ['pdf', 'doc', 'docx']);
define('ALLOWED_ABOUT_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm']);
define('ALLOWED_HR_DOC_EXTENSIONS', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'rtf', 'odt', 'ods', 'ppt', 'pptx', 'zip', 'rar']);

// Статусы кандидатов
define('CANDIDATE_STATUSES', [
    'new' => 'Новая заявка',
    'reviewing' => 'На рассмотрении',
    'interview' => 'Приглашён на собеседование',
    'testing' => 'Тестирование',
    'approved' => 'Одобрен',
    'rejected' => 'Отклонён',
    'hired' => 'Принят на работу'
]);

// Группы кандидатов
define('DEFAULT_GROUPS', [
    1 => 'Кандидаты',
    2 => 'Постоянные сотрудники'
]);

// Роли пользователей
define('USER_ROLES', [
    'admin' => [
        'name' => 'Администратор',
        'description' => 'Полный доступ ко всем функциям системы',
        'color' => '#ef4444'
    ],
    'hr_manager' => [
        'name' => 'HR-менеджер',
        'description' => 'Управление кандидатами, тестами, документами',
        'color' => '#6366f1'
    ],
    'recruiter' => [
        'name' => 'Рекрутер',
        'description' => 'Только просмотр и работа с кандидатами',
        'color' => '#10b981'
    ]
]);

// Права доступа по ролям
define('ROLE_PERMISSIONS', [
    'admin' => [
        'candidates' => ['view', 'create', 'edit', 'delete', 'change_status', 'change_group'],
        'employees' => ['view', 'edit'],
        'tests' => ['view', 'create', 'edit', 'delete'],
        'questions' => ['view', 'create', 'edit', 'delete'],
        'documents' => ['view', 'upload', 'edit', 'delete'],
        'about' => ['view', 'create', 'edit', 'delete'],
        'positions' => ['view', 'create', 'edit', 'delete'],
        'groups' => ['view', 'create', 'edit', 'delete'],
        'settings' => ['view', 'edit'],
        'users' => ['view', 'create', 'edit', 'delete']
    ],
    'hr_manager' => [
        'candidates' => ['view', 'create', 'edit', 'delete', 'change_status', 'change_group'],
        'employees' => ['view', 'edit'],
        'tests' => ['view', 'create', 'edit', 'delete'],
        'questions' => ['view', 'create', 'edit', 'delete'],
        'documents' => ['view', 'upload', 'edit', 'delete'],
        'about' => ['view', 'create', 'edit', 'delete'],
        'positions' => ['view', 'create', 'edit', 'delete'],
        'groups' => ['view', 'create', 'edit', 'delete'],
        'settings' => ['view'],
        'users' => []
    ],
    'recruiter' => [
        'candidates' => ['view', 'create', 'change_status'],
        'employees' => ['view'],
        'tests' => ['view'],
        'questions' => ['view'],
        'documents' => ['view'],
        'about' => ['view'],
        'positions' => ['view'],
        'groups' => ['view'],
        'settings' => [],
        'users' => []
    ]
]);

// Настройки сессии
session_start();

// Часовой пояс
date_default_timezone_set('Europe/Moscow');

// Отображение ошибок (отключить на продакшене)
error_reporting(E_ALL);
ini_set('display_errors', 1);
