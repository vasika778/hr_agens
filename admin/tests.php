<?php
$pageTitle = 'Управление тестами';
require_once 'includes/header.php';

$success = '';
$error = '';

// Обработка POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_test':
            $title = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $timeLimit = (int)($_POST['time_limit'] ?? 0);
            $maxAttempts = (int)($_POST['max_attempts'] ?? 1);
            $passingScore = (int)($_POST['passing_score'] ?? 70);
            
            if (empty($title)) {
                $error = 'Укажите название теста';
            } else {
                db()->query(
                    "INSERT INTO tests (title, description, time_limit, max_attempts, passing_score) VALUES (?, ?, ?, ?, ?)",
                    [$title, $description, $timeLimit, $maxAttempts, $passingScore]
                );
                $success = 'Тест успешно создан';
            }
            break;
            
        case 'update_test':
            $testId = (int)($_POST['test_id'] ?? 0);
            $title = sanitize($_POST['title'] ?? '');
            $description = sanitize($_POST['description'] ?? '');
            $timeLimit = (int)($_POST['time_limit'] ?? 0);
            $maxAttempts = (int)($_POST['max_attempts'] ?? 1);
            $passingScore = (int)($_POST['passing_score'] ?? 70);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            if ($testId && $title) {
                db()->query(
                    "UPDATE tests SET title = ?, description = ?, time_limit = ?, max_attempts = ?, passing_score = ?, is_active = ? WHERE id = ?",
                    [$title, $description, $timeLimit, $maxAttempts, $passingScore, $isActive, $testId]
                );
                $success = 'Тест успешно обновлён';
            }
            break;
            
        case 'delete_test':
            $testId = (int)($_POST['test_id'] ?? 0);
            if ($testId) {
                db()->query("DELETE FROM tests WHERE id = ?", [$testId]);
                $success = 'Тест удалён';
            }
            break;
            
        case 'toggle_test':
            $testId = (int)($_POST['test_id'] ?? 0);
            if ($testId) {
                db()->query("UPDATE tests SET is_active = NOT is_active WHERE id = ?", [$testId]);
                $success = 'Статус теста изменён';
            }
            break;
    }
}

// Получаем все тесты
$tests = db()->fetchAll(
    "SELECT t.*, 
            (SELECT COUNT(*) FROM questions WHERE test_id = t.id) as questions_count,
            (SELECT COUNT(*) FROM test_results WHERE test_id = t.id) as attempts_count
     FROM tests t 
     ORDER BY t.created_at DESC"
);
?>

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

<div class="d-flex justify-between align-center mb-3">
    <div></div>
    <button class="btn btn-primary" data-modal="createTestModal">
        ➕ Создать тест
    </button>
</div>

<!-- Tests List -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($tests)): ?>
            <div class="empty-state">
                <div class="empty-icon">📝</div>
                <div class="empty-title">Тесты не созданы</div>
                <div class="empty-text">Создайте первый тест для кандидатов</div>
            </div>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Название</th>
                        <th>Вопросов</th>
                        <th>Попыток</th>
                        <th>Проходной балл</th>
                        <th>Лимит времени</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tests as $test): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 500;"><?= sanitize($test['title']) ?></div>
                                <?php if ($test['description']): ?>
                                    <div class="text-muted" style="font-size: 0.8125rem;">
                                        <?= mb_substr(sanitize($test['description']), 0, 50) ?>...
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="questions.php?test_id=<?= $test['id'] ?>">
                                    <?= $test['questions_count'] ?> вопросов
                                </a>
                            </td>
                            <td>
                                <?= $test['max_attempts'] ?> 
                                <span class="text-muted">(пройдено: <?= $test['attempts_count'] ?>)</span>
                            </td>
                            <td><?= $test['passing_score'] ?>%</td>
                            <td>
                                <?php if ($test['time_limit'] > 0): ?>
                                    <?= $test['time_limit'] ?> мин.
                                <?php else: ?>
                                    <span class="text-muted">Без лимита</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($test['is_active']): ?>
                                    <span class="badge badge-approved">Активен</span>
                                <?php else: ?>
                                    <span class="badge badge-rejected">Отключён</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="questions.php?test_id=<?= $test['id'] ?>" class="btn btn-sm btn-secondary" title="Вопросы">
                                        ❓
                                    </a>
                                    <button class="btn btn-sm btn-secondary" 
                                            onclick="editTest(<?= htmlspecialchars(json_encode($test)) ?>)"
                                            title="Редактировать">
                                        ✏️
                                    </button>
                                    <form action="" method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="toggle_test">
                                        <input type="hidden" name="test_id" value="<?= $test['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-secondary" title="Переключить статус">
                                            <?= $test['is_active'] ? '🔴' : '🟢' ?>
                                        </button>
                                    </form>
                                    <form action="" method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Удалить тест? Все вопросы и результаты будут удалены!');">
                                        <input type="hidden" name="action" value="delete_test">
                                        <input type="hidden" name="test_id" value="<?= $test['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Удалить">
                                            🗑️
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Create Test Modal -->
<div class="modal-overlay" id="createTestModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Создать тест</h3>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="action" value="create_test">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Название теста</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Описание</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Лимит времени (мин.)</label>
                        <input type="number" name="time_limit" class="form-control" value="0" min="0">
                        <div class="form-text">0 = без лимита</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Макс. попыток</label>
                        <input type="number" name="max_attempts" class="form-control" value="2" min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Проходной балл (%)</label>
                    <input type="number" name="passing_score" class="form-control" value="70" min="0" max="100">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Отмена</button>
                <button type="submit" class="btn btn-primary">Создать</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Test Modal -->
<div class="modal-overlay" id="editTestModal">
    <div class="modal">
        <div class="modal-header">
            <h3 class="modal-title">Редактировать тест</h3>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="action" value="update_test">
            <input type="hidden" name="test_id" id="edit_test_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label required">Название теста</label>
                    <input type="text" name="title" id="edit_title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Описание</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Лимит времени (мин.)</label>
                        <input type="number" name="time_limit" id="edit_time_limit" class="form-control" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Макс. попыток</label>
                        <input type="number" name="max_attempts" id="edit_max_attempts" class="form-control" min="1">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Проходной балл (%)</label>
                    <input type="number" name="passing_score" id="edit_passing_score" class="form-control" min="0" max="100">
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="checkbox" name="is_active" id="edit_is_active" value="1">
                        <span>Тест активен</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Отмена</button>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
function editTest(test) {
    document.getElementById('edit_test_id').value = test.id;
    document.getElementById('edit_title').value = test.title;
    document.getElementById('edit_description').value = test.description || '';
    document.getElementById('edit_time_limit').value = test.time_limit;
    document.getElementById('edit_max_attempts').value = test.max_attempts;
    document.getElementById('edit_passing_score').value = test.passing_score;
    document.getElementById('edit_is_active').checked = test.is_active == 1;
    
    openModal(document.getElementById('editTestModal'));
}
</script>

<?php require_once 'includes/footer.php'; ?>
