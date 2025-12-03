<?php
require_once __DIR__ . '/../includes/functions.php';

$token = $_GET['token'] ?? '';
$candidate = getCandidateByToken($token);

if (!$candidate) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Доступ запрещён - HR Agency</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body>
        <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem;">
            <div class="card" style="max-width: 400px; text-align: center;">
                <div class="card-body" style="padding: 3rem;">
                    <div style="font-size: 4rem; margin-bottom: 1rem;">🔒</div>
                    <h2 style="margin-bottom: 1rem;">Доступ запрещён</h2>
                    <p class="text-muted">Неверная или устаревшая ссылка. Обратитесь к HR-менеджеру для получения новой ссылки.</p>
                    <a href="../index.php" class="btn btn-primary mt-3">На главную</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Получаем активный тест
$test = db()->fetch("SELECT * FROM tests WHERE is_active = 1 LIMIT 1");
$testEnabled = getSetting('test_enabled', '1') === '1';

// Проверяем, может ли кандидат проходить тест
$canTakeTest = false;
$testMessage = '';

if ($test && $testEnabled) {
    if ($candidate['test_passed']) {
        $testMessage = 'Вы успешно прошли тестирование!';
    } elseif ($candidate['test_attempts_used'] >= $test['max_attempts']) {
        $testMessage = 'Вы исчерпали количество попыток. Обратитесь к HR.';
    } else {
        $canTakeTest = true;
        $attemptsLeft = $test['max_attempts'] - $candidate['test_attempts_used'];
        $testMessage = "Осталось попыток: $attemptsLeft";
    }
}

// Получаем материалы "О компании" (доступны после прохождения теста)
$aboutMaterials = [];
if ($candidate['test_passed']) {
    $aboutMaterials = db()->fetchAll("SELECT * FROM about_materials WHERE is_active = 1 ORDER BY order_num");
}

// Получаем результаты тестов
$testResults = db()->fetchAll(
    "SELECT tr.*, t.title as test_title 
     FROM test_results tr 
     LEFT JOIN tests t ON tr.test_id = t.id 
     WHERE tr.candidate_id = ? 
     ORDER BY tr.started_at DESC",
    [$candidate['id']]
);

// Получаем документы, доступные кандидату
$candidateDocuments = db()->fetchAll(
    "SELECT d.*, c.name as category_name 
     FROM hr_documents d 
     LEFT JOIN hr_doc_categories c ON d.category_id = c.id 
     WHERE d.candidate_id = ? AND d.is_visible_to_candidate = 1 
     ORDER BY d.created_at DESC",
    [$candidate['id']]
);

// Настройки компании
$companyName = getSetting('company_name', 'HR Agency');
$companyEmail = getSetting('company_email', 'hr@company.ru');
$companyPhone = getSetting('company_phone', '+7 (999) 123-45-67');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет - <?= sanitize($companyName) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💼</text></svg>">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-inner">
                <a href="index.php?token=<?= $token ?>" class="logo">
                    <span class="logo-icon">💼</span>
                    <span><?= sanitize($companyName) ?></span>
                </a>
                <nav class="nav">
                    <span class="nav-link">
                        👤 <?= sanitize($candidate['name']) ?>
                    </span>
                </nav>
            </div>
        </div>
    </header>

    <main class="container" style="padding-top: 2rem; padding-bottom: 3rem;">
        <!-- Welcome Card -->
        <div class="card mb-3 animate-fade-in">
            <div class="candidate-header">
                <div class="candidate-avatar">
                    <?= mb_substr($candidate['name'], 0, 1) ?>
                </div>
                <div class="candidate-info">
                    <h2>Добро пожаловать, <?= sanitize(explode(' ', $candidate['name'])[0]) ?>!</h2>
                    <p>Ваша заявка на должность: <?= sanitize($candidate['position_name'] ?? 'Не указана') ?></p>
                </div>
                <div style="margin-left: auto;">
                    <span class="badge badge-<?= $candidate['status'] ?>">
                        <span class="status-dot"></span>
                        <?= getStatusName($candidate['status']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <a href="#" class="tab-link active" data-tab="tab-main">Главная</a>
            <?php if ($test && $testEnabled): ?>
                <a href="#" class="tab-link" data-tab="tab-test">Тестирование</a>
            <?php endif; ?>
            <?php if (!empty($candidateDocuments)): ?>
                <a href="#" class="tab-link" data-tab="tab-docs">Мои документы</a>
            <?php endif; ?>
            <?php if ($candidate['test_passed']): ?>
                <a href="#" class="tab-link" data-tab="tab-about">О компании</a>
            <?php endif; ?>
            <a href="#" class="tab-link" data-tab="tab-contact">Связаться с HR</a>
        </div>

        <!-- Main Tab -->
        <div class="tab-content active" id="tab-main">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                <!-- Status Card -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <span>📋</span>
                            Статус вашей заявки
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="info-item mb-2">
                            <span class="info-label">Текущий статус</span>
                            <span class="info-value">
                                <span class="badge badge-<?= $candidate['status'] ?>">
                                    <span class="status-dot"></span>
                                    <?= getStatusName($candidate['status']) ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-item mb-2">
                            <span class="info-label">Дата подачи</span>
                            <span class="info-value"><?= formatDate($candidate['created_at']) ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Группа</span>
                            <span class="info-value"><?= sanitize($candidate['group_name']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Test Status Card -->
                <?php if ($test && $testEnabled): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <span>📝</span>
                                Тестирование
                            </h3>
                        </div>
                        <div class="card-body">
                            <?php if ($candidate['test_passed']): ?>
                                <div class="alert alert-success" style="margin: 0;">
                                    <span>✅</span>
                                    <div>
                                        <strong>Тест успешно пройден!</strong><br>
                                        <span>Ваш результат: <?= $candidate['test_score'] ?> баллов</span>
                                    </div>
                                </div>
                            <?php elseif ($canTakeTest): ?>
                                <div class="alert alert-info" style="margin: 0;">
                                    <span>ℹ️</span>
                                    <div>
                                        <strong>Доступно тестирование</strong><br>
                                        <span><?= $testMessage ?></span>
                                    </div>
                                </div>
                                <a href="test.php?token=<?= $token ?>" class="btn btn-primary btn-block mt-2">
                                    Начать тест
                                </a>
                            <?php else: ?>
                                <div class="alert alert-warning" style="margin: 0;">
                                    <span>⚠️</span>
                                    <div>
                                        <strong>Тестирование недоступно</strong><br>
                                        <span><?= $testMessage ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Test Results History -->
            <?php if (!empty($testResults)): ?>
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">
                            <span>📊</span>
                            История тестирования
                        </h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Тест</th>
                                    <th>Результат</th>
                                    <th>Статус</th>
                                    <th>Дата</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($testResults as $result): ?>
                                    <tr>
                                        <td><?= sanitize($result['test_title']) ?></td>
                                        <td>
                                            <strong><?= $result['score'] ?></strong> / <?= $result['max_score'] ?>
                                            (<?= $result['percentage'] ?>%)
                                        </td>
                                        <td>
                                            <?php if ($result['passed']): ?>
                                                <span class="badge badge-approved">Пройден</span>
                                            <?php else: ?>
                                                <span class="badge badge-rejected">Не пройден</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted"><?= formatDate($result['completed_at'] ?? $result['started_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Test Tab -->
        <?php if ($test && $testEnabled): ?>
            <div class="tab-content" id="tab-test">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <span>📝</span>
                            <?= sanitize($test['title']) ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if ($candidate['test_passed']): ?>
                            <div class="text-center" style="padding: 2rem;">
                                <div style="font-size: 4rem; margin-bottom: 1rem;">🎉</div>
                                <h2>Поздравляем!</h2>
                                <p class="text-muted">Вы успешно прошли тестирование.</p>
                                <p>Ваш результат: <strong><?= $candidate['test_score'] ?></strong> баллов</p>
                            </div>
                        <?php elseif ($canTakeTest): ?>
                            <div class="text-center" style="padding: 2rem;">
                                <?php if ($test['description']): ?>
                                    <p class="text-muted mb-3"><?= nl2br(sanitize($test['description'])) ?></p>
                                <?php endif; ?>
                                
                                <div class="info-grid mb-3" style="max-width: 400px; margin: 0 auto;">
                                    <?php 
                                    $questionsCount = db()->fetch("SELECT COUNT(*) as count FROM questions WHERE test_id = ? AND is_active = 1", [$test['id']]);
                                    ?>
                                    <div class="info-item">
                                        <span class="info-label">Вопросов</span>
                                        <span class="info-value"><?= $questionsCount['count'] ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Проходной балл</span>
                                        <span class="info-value"><?= $test['passing_score'] ?>%</span>
                                    </div>
                                    <?php if ($test['time_limit'] > 0): ?>
                                        <div class="info-item">
                                            <span class="info-label">Время</span>
                                            <span class="info-value"><?= $test['time_limit'] ?> мин.</span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="info-item">
                                        <span class="info-label">Осталось попыток</span>
                                        <span class="info-value"><?= $test['max_attempts'] - $candidate['test_attempts_used'] ?></span>
                                    </div>
                                </div>
                                
                                <a href="test.php?token=<?= $token ?>" class="btn btn-primary btn-lg">
                                    Начать тестирование
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center" style="padding: 2rem;">
                                <div style="font-size: 4rem; margin-bottom: 1rem;">😔</div>
                                <h2>Тестирование недоступно</h2>
                                <p class="text-muted"><?= $testMessage ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Documents Tab -->
        <?php if (!empty($candidateDocuments)): ?>
            <div class="tab-content" id="tab-docs">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <span>📂</span>
                            Ваши документы
                        </h3>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Документ</th>
                                    <th>Категория</th>
                                    <th>Дата</th>
                                    <th>Действие</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($candidateDocuments as $doc): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 500;"><?= sanitize($doc['title']) ?></div>
                                            <div class="text-muted" style="font-size: 0.75rem;">
                                                <?= strtoupper($doc['file_type']) ?>
                                                <?php if ($doc['description']): ?>
                                                    • <?= sanitize(mb_substr($doc['description'], 0, 50)) ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?= $doc['category_name'] ? sanitize($doc['category_name']) : '—' ?>
                                        </td>
                                        <td class="text-muted" style="font-size: 0.875rem;">
                                            <?= formatDate($doc['created_at'], 'd.m.Y') ?>
                                        </td>
                                        <td>
                                            <a href="download.php?token=<?= $token ?>&id=<?= $doc['id'] ?>" class="btn btn-sm btn-primary">
                                                ⬇️ Скачать
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- About Company Tab -->
        <?php if ($candidate['test_passed']): ?>
            <div class="tab-content" id="tab-about">
                <?php if (empty($aboutMaterials)): ?>
                    <div class="card">
                        <div class="empty-state">
                            <div class="empty-icon">ℹ️</div>
                            <div class="empty-title">Материалы не добавлены</div>
                            <div class="empty-text">Информация о компании скоро появится</div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($aboutMaterials as $material): ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                <h3 class="card-title">
                                    <?php
                                    $icons = [
                                        'text' => '📝',
                                        'image' => '🖼️',
                                        'video' => '🎬',
                                        'pdf' => '📄',
                                        'youtube' => '▶️'
                                    ];
                                    echo $icons[$material['type']] ?? '📋';
                                    ?>
                                    <?= sanitize($material['title']) ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php if ($material['content']): ?>
                                    <p><?= nl2br(sanitize($material['content'])) ?></p>
                                <?php endif; ?>
                                
                                <?php if ($material['type'] === 'image' && $material['file_path']): ?>
                                    <img src="../uploads/about/<?= $material['file_path'] ?>" 
                                         alt="<?= sanitize($material['title']) ?>"
                                         style="max-width: 100%; border-radius: var(--radius-md);">
                                <?php elseif ($material['type'] === 'video' && $material['file_path']): ?>
                                    <video controls style="max-width: 100%; border-radius: var(--radius-md);">
                                        <source src="../uploads/about/<?= $material['file_path'] ?>" type="video/mp4">
                                    </video>
                                <?php elseif ($material['type'] === 'pdf' && $material['file_path']): ?>
                                    <a href="../uploads/about/<?= $material['file_path'] ?>" 
                                       target="_blank" 
                                       class="btn btn-secondary">
                                        📄 Открыть PDF документ
                                    </a>
                                <?php elseif ($material['type'] === 'youtube' && $material['youtube_url']): ?>
                                    <?php
                                    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $material['youtube_url'], $matches);
                                    $videoId = $matches[1] ?? '';
                                    if ($videoId):
                                    ?>
                                        <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: var(--radius-md);">
                                            <iframe src="https://www.youtube.com/embed/<?= $videoId ?>" 
                                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;"
                                                    allowfullscreen></iframe>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Contact Tab -->
        <div class="tab-content" id="tab-contact">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <span>📞</span>
                        Связаться с HR
                    </h3>
                </div>
                <div class="card-body">
                    <p>Если у вас есть вопросы, свяжитесь с нами:</p>
                    
                    <div class="info-grid mt-3">
                        <div class="info-item">
                            <span class="info-label">Email</span>
                            <span class="info-value">
                                <a href="mailto:<?= sanitize($companyEmail) ?>"><?= sanitize($companyEmail) ?></a>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Телефон</span>
                            <span class="info-value">
                                <a href="tel:<?= preg_replace('/[^0-9+]/', '', $companyPhone) ?>"><?= sanitize($companyPhone) ?></a>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <a href="mailto:<?= sanitize($companyEmail) ?>" class="btn btn-primary">
                            ✉️ Написать письмо
                        </a>
                        <a href="tel:<?= preg_replace('/[^0-9+]/', '', $companyPhone) ?>" class="btn btn-secondary">
                            📞 Позвонить
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer style="text-align: center; padding: 2rem; color: var(--text-muted); font-size: 0.875rem;">
        <p>&copy; <?= date('Y') ?> <?= sanitize($companyName) ?>. Все права защищены.</p>
    </footer>

    <script src="../assets/js/main.js"></script>
</body>
</html>
