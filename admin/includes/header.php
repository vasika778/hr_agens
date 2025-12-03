<?php
require_once __DIR__ . '/../../includes/functions.php';
requireAdmin();

$currentAdmin = getCurrentAdmin();
$stats = getCandidateStats();

// Определяем текущую страницу
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Админ-панель' ?> - HR Agency</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💼</text></svg>">
    <script>
        // Загрузка темы до рендеринга
        (function() {
            var theme = localStorage.getItem('hr-theme');
            if (theme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="logo">
                    <span class="logo-icon">💼</span>
                    <span>HR Agency</span>
                </a>
            </div>

            <nav class="sidebar-nav">
                <div class="sidebar-section">
                    <div class="sidebar-section-title">Главное меню</div>
                    <a href="index.php" class="sidebar-link <?= $currentPage === 'index' ? 'active' : '' ?>">
                        <span>📊</span>
                        Панель управления
                    </a>
                    <a href="candidates.php" class="sidebar-link <?= $currentPage === 'candidates' ? 'active' : '' ?>">
                        <span>👥</span>
                        Кандидаты
                        <?php if ($stats['new'] > 0): ?>
                            <span class="badge badge-new"><?= $stats['new'] ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="employees.php" class="sidebar-link <?= $currentPage === 'employees' ? 'active' : '' ?>">
                        <span>💼</span>
                        Сотрудники
                    </a>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Тестирование</div>
                    <a href="tests.php" class="sidebar-link <?= $currentPage === 'tests' ? 'active' : '' ?>">
                        <span>📝</span>
                        Управление тестами
                    </a>
                    <a href="questions.php" class="sidebar-link <?= $currentPage === 'questions' ? 'active' : '' ?>">
                        <span>❓</span>
                        Вопросы
                    </a>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Контент</div>
                    <a href="documents.php" class="sidebar-link <?= $currentPage === 'documents' ? 'active' : '' ?>">
                        <span>📂</span>
                        Документы
                    </a>
                    <a href="about.php" class="sidebar-link <?= $currentPage === 'about' ? 'active' : '' ?>">
                        <span>ℹ️</span>
                        О компании
                    </a>
                    <?php if (hasPermission('positions', 'edit')): ?>
                    <a href="positions.php" class="sidebar-link <?= $currentPage === 'positions' ? 'active' : '' ?>">
                        <span>📋</span>
                        Должности
                    </a>
                    <?php endif; ?>
                    <?php if (hasPermission('groups', 'edit')): ?>
                    <a href="groups.php" class="sidebar-link <?= $currentPage === 'groups' ? 'active' : '' ?>">
                        <span>🏷️</span>
                        Группы
                    </a>
                    <?php endif; ?>
                </div>

                <div class="sidebar-section">
                    <div class="sidebar-section-title">Настройки</div>
                    <?php if (hasPermission('settings', 'view')): ?>
                    <a href="settings.php" class="sidebar-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
                        <span>⚙️</span>
                        Настройки
                    </a>
                    <?php endif; ?>
                    <?php if (hasRole('admin')): ?>
                    <a href="users.php" class="sidebar-link <?= $currentPage === 'users' ? 'active' : '' ?>">
                        <span>👤</span>
                        Пользователи
                    </a>
                    <a href="test-email.php" class="sidebar-link <?= $currentPage === 'test-email' ? 'active' : '' ?>">
                        <span>📧</span>
                        Тест Email
                    </a>
                    <?php endif; ?>
                </div>
            </nav>

            <div class="sidebar-footer">
                <div class="d-flex align-center gap-2" style="margin-bottom: 0.75rem;">
                    <div style="width: 36px; height: 36px; background: <?= getRoleColor($currentAdmin['role']) ?>; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-weight: 600; color: white;">
                        <?= mb_substr($currentAdmin['full_name'], 0, 1) ?>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 500; font-size: 0.875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?= sanitize($currentAdmin['full_name']) ?>
                        </div>
                        <div style="font-size: 0.75rem; color: <?= getRoleColor($currentAdmin['role']) ?>;">
                            <?= getRoleName($currentAdmin['role']) ?>
                        </div>
                    </div>
                </div>
                <a href="logout.php" class="btn btn-secondary btn-sm btn-block">
                    Выйти
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="admin-content">
            <header class="admin-header">
                <button class="btn btn-icon btn-secondary mobile-menu-toggle" style="display: none;">
                    ☰
                </button>
                <h1 style="font-size: 1.25rem; font-weight: 600;"><?= $pageTitle ?? 'Панель управления' ?></h1>
                <div class="d-flex align-center gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" id="themeBtn" title="Переключить тему">
                        <span id="themeIcon">🌙</span>
                    </button>
                    <a href="../index.php" target="_blank" class="btn btn-secondary btn-sm">
                        🌐 Открыть сайт
                    </a>
                </div>
            </header>

            <main class="admin-main">

<script>
// Переключатель темы - вставляем сразу после кнопки
document.getElementById('themeBtn').addEventListener('click', function() {
    var html = document.documentElement;
    var current = html.getAttribute('data-theme');
    var next = (current === 'light') ? 'dark' : 'light';
    
    html.setAttribute('data-theme', next);
    localStorage.setItem('hr-theme', next);
    document.getElementById('themeIcon').textContent = (next === 'light') ? '☀️' : '🌙';
});

// Установка иконки при загрузке
(function() {
    var theme = localStorage.getItem('hr-theme') || 'dark';
    document.getElementById('themeIcon').textContent = (theme === 'light') ? '☀️' : '🌙';
})();
</script>
