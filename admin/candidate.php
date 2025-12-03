<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$candidateId = (int)($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if (!$candidateId) {
    header('Location: candidates.php');
    exit;
}

// Получаем данные кандидата
$candidate = db()->fetch(
    "SELECT c.*, p.name as position_name, g.name as group_name, g.color as group_color
     FROM candidates c 
     LEFT JOIN positions p ON c.position_id = p.id 
     LEFT JOIN candidate_groups g ON c.group_id = g.id 
     WHERE c.id = ?",
    [$candidateId]
);

if (!$candidate) {
    header('Location: candidates.php');
    exit;
}

// Получаем историю статусов
$statusHistory = db()->fetchAll(
    "SELECT sh.*, a.full_name as admin_name 
     FROM status_history sh 
     LEFT JOIN admins a ON sh.changed_by = a.id 
     WHERE sh.candidate_id = ? 
     ORDER BY sh.created_at DESC",
    [$candidateId]
);

// Получаем результаты тестирования
$testResults = db()->fetchAll(
    "SELECT tr.*, t.title as test_title 
     FROM test_results tr 
     LEFT JOIN tests t ON tr.test_id = t.id 
     WHERE tr.candidate_id = ? 
     ORDER BY tr.started_at DESC",
    [$candidateId]
);

$positions = getPositions();
$groups = getGroups();
$currentAdmin = getCurrentAdmin();
$error = '';
$success = '';

// Получаем документы, привязанные к кандидату
$candidateDocuments = db()->fetchAll(
    "SELECT d.*, c.name as category_name, c.color as category_color 
     FROM hr_documents d 
     LEFT JOIN hr_doc_categories c ON d.category_id = c.id 
     WHERE d.candidate_id = ? 
     ORDER BY d.created_at DESC",
    [$candidateId]
);

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    switch ($postAction) {
        case 'update_status':
            $newStatus = $_POST['status'] ?? '';
            $comment = sanitize($_POST['comment'] ?? '');
            
            if (array_key_exists($newStatus, CANDIDATE_STATUSES) && $newStatus !== $candidate['status']) {
                db()->query("UPDATE candidates SET status = ? WHERE id = ?", [$newStatus, $candidateId]);
                logStatusChange($candidateId, $candidate['status'], $newStatus, $currentAdmin['id'], $comment);
                
                $success = 'Статус успешно обновлён';
                $candidate['status'] = $newStatus;
                
                // Обновляем историю
                $statusHistory = db()->fetchAll(
                    "SELECT sh.*, a.full_name as admin_name 
                     FROM status_history sh 
                     LEFT JOIN admins a ON sh.changed_by = a.id 
                     WHERE sh.candidate_id = ? 
                     ORDER BY sh.created_at DESC",
                    [$candidateId]
                );
            }
            break;
            
        case 'update_group':
            $newGroupId = (int)($_POST['group_id'] ?? 0);
            
            if ($newGroupId > 0 && $newGroupId !== (int)$candidate['group_id']) {
                db()->query("UPDATE candidates SET group_id = ? WHERE id = ?", [$newGroupId, $candidateId]);
                $success = 'Группа успешно изменена';
                
                // Обновляем данные кандидата
                $candidate = db()->fetch(
                    "SELECT c.*, p.name as position_name, g.name as group_name, g.color as group_color
                     FROM candidates c 
                     LEFT JOIN positions p ON c.position_id = p.id 
                     LEFT JOIN candidate_groups g ON c.group_id = g.id 
                     WHERE c.id = ?",
                    [$candidateId]
                );
            }
            break;
            
        case 'update_info':
            $name = sanitize($_POST['name'] ?? '');
            $phone = sanitize($_POST['phone'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $positionId = (int)($_POST['position_id'] ?? 0);
            $hrComment = sanitize($_POST['hr_comment'] ?? '');
            
            if (empty($name)) {
                $error = 'Укажите имя';
            } elseif (!isValidPhone($phone)) {
                $error = 'Укажите корректный телефон';
            } elseif (!isValidEmail($email)) {
                $error = 'Укажите корректный email';
            } else {
                db()->query(
                    "UPDATE candidates SET name = ?, phone = ?, email = ?, position_id = ?, comment = ? WHERE id = ?",
                    [$name, $phone, $email, $positionId ?: null, $hrComment, $candidateId]
                );
                
                $success = 'Данные успешно обновлены';
                $candidate['name'] = $name;
                $candidate['phone'] = $phone;
                $candidate['email'] = $email;
                $candidate['position_id'] = $positionId;
                $candidate['comment'] = $hrComment;
            }
            break;
            
        case 'reset_test':
            db()->query("UPDATE candidates SET test_attempts_used = 0, test_passed = 0, test_score = 0 WHERE id = ?", [$candidateId]);
            $success = 'Попытки тестирования сброшены';
            $candidate['test_attempts_used'] = 0;
            $candidate['test_passed'] = 0;
            break;
    }
}

// Генерация ссылки на ЛК
$candidateLink = SITE_URL . '/candidate/index.php?token=' . $candidate['access_token'];

$pageTitle = 'Карточка кандидата';
require_once 'includes/header.php';
?>

<style>
.candidate-page {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 1.5rem;
}

@media (max-width: 1024px) {
    .candidate-page {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Back Button -->
<div class="mb-3">
    <a href="candidates.php" class="btn btn-secondary btn-sm">
        ← Назад к списку
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success" data-auto-hide="5000">
        <span>✅</span>
        <span><?= $success ?></span>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <span>⚠️</span>
        <span><?= $error ?></span>
    </div>
<?php endif; ?>

<div class="candidate-page">
    <div>
        <!-- Main Info Card -->
        <div class="candidate-card mb-3">
            <div class="candidate-header">
                <div class="candidate-avatar">
                    <?= mb_substr($candidate['name'], 0, 1) ?>
                </div>
                <div class="candidate-info">
                    <h2><?= sanitize($candidate['name']) ?></h2>
                    <p><?= sanitize($candidate['position_name'] ?? 'Должность не указана') ?></p>
                </div>
                <div style="margin-left: auto;">
                    <span class="badge badge-<?= $candidate['status'] ?>">
                        <span class="status-dot"></span>
                        <?= getStatusName($candidate['status']) ?>
                    </span>
                </div>
            </div>
            <div class="candidate-body">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <span class="info-value">
                            <a href="mailto:<?= sanitize($candidate['email']) ?>">
                                <?= sanitize($candidate['email']) ?>
                            </a>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Телефон</span>
                        <span class="info-value">
                            <a href="tel:<?= preg_replace('/[^0-9+]/', '', $candidate['phone']) ?>">
                                <?= sanitize($candidate['phone']) ?>
                            </a>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Группа</span>
                        <span class="info-value">
                            <span style="display: inline-flex; align-items: center; gap: 0.375rem;">
                                <span style="width: 8px; height: 8px; border-radius: 50%; background: <?= $candidate['group_color'] ?>;"></span>
                                <?= sanitize($candidate['group_name']) ?>
                            </span>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Дата регистрации</span>
                        <span class="info-value"><?= formatDate($candidate['created_at']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Последнее обновление</span>
                        <span class="info-value"><?= formatDate($candidate['updated_at']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Резюме</span>
                        <span class="info-value">
                            <?php if ($candidate['resume_file']): ?>
                                <a href="../uploads/resumes/<?= $candidate['resume_file'] ?>" 
                                   target="_blank" 
                                   class="btn btn-sm btn-secondary">
                                    📄 Скачать резюме
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Не загружено</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <?php if ($candidate['comment']): ?>
                    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                        <div class="info-label" style="margin-bottom: 0.5rem;">Комментарий кандидата</div>
                        <p style="margin: 0; color: var(--text-secondary);">
                            <?= nl2br(sanitize($candidate['comment'])) ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Test Results -->
        <?php if (!empty($testResults)): ?>
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">
                        <span>📝</span>
                        Результаты тестирования
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

        <!-- Документы кандидата -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <span>📂</span>
                    Документы
                </h3>
                <a href="documents.php?candidate=<?= $candidateId ?>" class="btn btn-sm btn-secondary">
                    Все документы
                </a>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (empty($candidateDocuments)): ?>
                    <div class="empty-state" style="padding: 2rem;">
                        <div class="empty-icon" style="font-size: 2rem;">📄</div>
                        <div class="empty-title">Документы не прикреплены</div>
                        <a href="documents.php" class="btn btn-sm btn-primary mt-2">
                            Загрузить документ
                        </a>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Документ</th>
                                <th>Категория</th>
                                <th>Доступ</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidateDocuments as $doc): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 500;"><?= sanitize($doc['title']) ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;">
                                            <?= strtoupper($doc['file_type']) ?> • <?= formatDate($doc['created_at'], 'd.m.Y') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($doc['category_name']): ?>
                                            <span style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.8125rem;">
                                                <span style="width: 8px; height: 8px; border-radius: 50%; background: <?= $doc['category_color'] ?>;"></span>
                                                <?= sanitize($doc['category_name']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($doc['is_visible_to_candidate']): ?>
                                            <span class="badge badge-approved" style="font-size: 0.6875rem;">Виден</span>
                                        <?php else: ?>
                                            <span class="badge badge-rejected" style="font-size: 0.6875rem;">Скрыт</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="download.php?id=<?= $doc['id'] ?>" class="btn btn-sm btn-secondary">
                                            ⬇️ Скачать
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status History -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <span>📜</span>
                    История изменений
                </h3>
            </div>
            <div class="card-body">
                <?php if (empty($statusHistory)): ?>
                    <p class="text-muted text-center">История пуста</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($statusHistory as $history): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="timeline-content">
                                    <div class="timeline-date"><?= formatDate($history['created_at']) ?></div>
                                    <div class="timeline-title">
                                        <?php if ($history['old_status']): ?>
                                            <?= getStatusName($history['old_status']) ?> → 
                                        <?php endif; ?>
                                        <strong><?= getStatusName($history['new_status']) ?></strong>
                                    </div>
                                    <?php if ($history['admin_name']): ?>
                                        <p class="timeline-text">Изменил: <?= sanitize($history['admin_name']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($history['comment']): ?>
                                        <p class="timeline-text"><?= sanitize($history['comment']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar Actions -->
    <div>
        <!-- Change Status -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <span>🔄</span>
                    Изменить статус
                </h3>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <div class="form-group">
                        <select name="status" class="form-control">
                            <?php foreach (CANDIDATE_STATUSES as $key => $name): ?>
                                <option value="<?= $key ?>" <?= $candidate['status'] === $key ? 'selected' : '' ?>>
                                    <?= $name ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <textarea name="comment" class="form-control" placeholder="Комментарий к изменению..." rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        Сохранить статус
                    </button>
                </form>
            </div>
        </div>

        <!-- Change Group -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <span>🏷️</span>
                    Изменить группу
                </h3>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="update_group">
                    <div class="form-group">
                        <select name="group_id" class="form-control">
                            <?php foreach ($groups as $group): ?>
                                <option value="<?= $group['id'] ?>" <?= (int)$candidate['group_id'] === (int)$group['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($group['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        Сохранить группу
                    </button>
                </form>
            </div>
        </div>

        <!-- Personal Cabinet Link -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <span>🔗</span>
                    Ссылка на ЛК
                </h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <input type="text" 
                           class="form-control" 
                           value="<?= $candidateLink ?>" 
                           readonly 
                           onclick="this.select()"
                           style="font-size: 0.8125rem;">
                </div>
                <button type="button" class="btn btn-secondary btn-block" onclick="navigator.clipboard.writeText('<?= $candidateLink ?>'); showNotification('Ссылка скопирована', 'success');">
                    📋 Копировать ссылку
                </button>
            </div>
        </div>

        <!-- Test Management -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <span>📝</span>
                    Тестирование
                </h3>
            </div>
            <div class="card-body">
                <div class="info-item mb-2">
                    <span class="info-label">Попыток использовано</span>
                    <span class="info-value"><?= $candidate['test_attempts_used'] ?></span>
                </div>
                <div class="info-item mb-3">
                    <span class="info-label">Тест пройден</span>
                    <span class="info-value">
                        <?php if ($candidate['test_passed']): ?>
                            <span class="text-success">✓ Да (<?= $candidate['test_score'] ?> баллов)</span>
                        <?php else: ?>
                            <span class="text-muted">Нет</span>
                        <?php endif; ?>
                    </span>
                </div>
                <form action="" method="POST" onsubmit="return confirm('Сбросить попытки тестирования?');">
                    <input type="hidden" name="action" value="reset_test">
                    <button type="submit" class="btn btn-secondary btn-block">
                        🔄 Сбросить попытки
                    </button>
                </form>
            </div>
        </div>

        <!-- Quick Edit -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">
                    <span>✏️</span>
                    Редактировать
                </h3>
            </div>
            <div class="card-body">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="update_info">
                    <div class="form-group">
                        <label class="form-label">Имя</label>
                        <input type="text" name="name" class="form-control" value="<?= sanitize($candidate['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Телефон</label>
                        <input type="text" name="phone" class="form-control" value="<?= sanitize($candidate['phone']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= sanitize($candidate['email']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Должность</label>
                        <select name="position_id" class="form-control">
                            <option value="">Не указана</option>
                            <?php foreach ($positions as $position): ?>
                                <option value="<?= $position['id'] ?>" <?= (int)$candidate['position_id'] === (int)$position['id'] ? 'selected' : '' ?>>
                                    <?= sanitize($position['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Комментарий HR</label>
                        <textarea name="hr_comment" class="form-control" rows="3"><?= sanitize($candidate['comment']) ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        Сохранить изменения
                    </button>
                </form>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="card" style="border-color: var(--danger);">
            <div class="card-header" style="border-color: var(--danger);">
                <h3 class="card-title text-danger">
                    <span>⚠️</span>
                    Опасная зона
                </h3>
            </div>
            <div class="card-body">
                <a href="candidate-delete.php?id=<?= $candidate['id'] ?>" 
                   class="btn btn-danger btn-block"
                   data-confirm="Вы уверены, что хотите удалить этого кандидата? Это действие нельзя отменить.">
                    🗑️ Удалить кандидата
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
