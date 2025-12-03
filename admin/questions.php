<?php
$pageTitle = 'Вопросы теста';
require_once 'includes/header.php';

$testId = (int)($_GET['test_id'] ?? 0);
$success = '';
$error = '';

// Получаем тест
$test = null;
if ($testId) {
    $test = db()->fetch("SELECT * FROM tests WHERE id = ?", [$testId]);
}

// Обработка POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $testId) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_question':
            $questionText = sanitize($_POST['question_text'] ?? '');
            $questionType = $_POST['question_type'] ?? 'single';
            $points = (int)($_POST['points'] ?? 1);
            $answers = $_POST['answers'] ?? [];
            $correctAnswers = $_POST['correct'] ?? [];
            
            if (empty($questionText)) {
                $error = 'Введите текст вопроса';
            } elseif (empty($answers) || count(array_filter($answers)) < 2) {
                $error = 'Добавьте минимум 2 варианта ответа';
            } elseif (empty($correctAnswers)) {
                $error = 'Выберите правильный ответ';
            } else {
                // Получаем следующий порядковый номер
                $maxOrder = db()->fetch("SELECT MAX(order_num) as max_order FROM questions WHERE test_id = ?", [$testId]);
                $orderNum = ($maxOrder['max_order'] ?? 0) + 1;
                
                // Создаём вопрос
                db()->query(
                    "INSERT INTO questions (test_id, question_text, question_type, points, order_num) VALUES (?, ?, ?, ?, ?)",
                    [$testId, $questionText, $questionType, $points, $orderNum]
                );
                $questionId = db()->lastInsertId();
                
                // Добавляем ответы
                foreach ($answers as $idx => $answerText) {
                    if (trim($answerText)) {
                        $isCorrect = in_array($idx, $correctAnswers) ? 1 : 0;
                        db()->query(
                            "INSERT INTO answers (question_id, answer_text, is_correct, order_num) VALUES (?, ?, ?, ?)",
                            [$questionId, trim($answerText), $isCorrect, $idx]
                        );
                    }
                }
                
                $success = 'Вопрос добавлен';
            }
            break;
            
        case 'delete_question':
            $questionId = (int)($_POST['question_id'] ?? 0);
            if ($questionId) {
                db()->query("DELETE FROM questions WHERE id = ? AND test_id = ?", [$questionId, $testId]);
                $success = 'Вопрос удалён';
            }
            break;
            
        case 'toggle_question':
            $questionId = (int)($_POST['question_id'] ?? 0);
            if ($questionId) {
                db()->query("UPDATE questions SET is_active = NOT is_active WHERE id = ? AND test_id = ?", [$questionId, $testId]);
                $success = 'Статус вопроса изменён';
            }
            break;
    }
}

// Получаем все тесты для выбора
$tests = db()->fetchAll("SELECT * FROM tests ORDER BY title");

// Получаем вопросы теста
$questions = [];
if ($testId) {
    $questions = db()->fetchAll(
        "SELECT q.*, 
                (SELECT GROUP_CONCAT(CONCAT(a.id, ':', a.answer_text, ':', a.is_correct) SEPARATOR '||') 
                 FROM answers a WHERE a.question_id = q.id ORDER BY a.order_num) as answers_data
         FROM questions q 
         WHERE q.test_id = ? 
         ORDER BY q.order_num",
        [$testId]
    );
}
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

<!-- Test Selector -->
<div class="card mb-3">
    <div class="card-body">
        <form action="" method="GET" class="d-flex gap-2 align-center">
            <label style="font-weight: 500;">Выберите тест:</label>
            <select name="test_id" class="filter-select" onchange="this.form.submit()" style="min-width: 300px;">
                <option value="">-- Выберите тест --</option>
                <?php foreach ($tests as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $testId === (int)$t['id'] ? 'selected' : '' ?>>
                        <?= sanitize($t['title']) ?>
                        <?= $t['is_active'] ? '' : '(отключён)' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <a href="tests.php" class="btn btn-secondary">Управление тестами</a>
        </form>
    </div>
</div>

<?php if ($test): ?>
    <div class="d-flex justify-between align-center mb-3">
        <div>
            <h2 style="font-size: 1.25rem; margin-bottom: 0.25rem;"><?= sanitize($test['title']) ?></h2>
            <p class="text-muted" style="margin: 0; font-size: 0.875rem;">
                <?= count($questions) ?> вопросов • Проходной балл: <?= $test['passing_score'] ?>%
            </p>
        </div>
        <button class="btn btn-primary" data-modal="addQuestionModal">
            ➕ Добавить вопрос
        </button>
    </div>

    <!-- Questions List -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <?php if (empty($questions)): ?>
                <div class="empty-state">
                    <div class="empty-icon">❓</div>
                    <div class="empty-title">Вопросы не добавлены</div>
                    <div class="empty-text">Добавьте первый вопрос в тест</div>
                </div>
            <?php else: ?>
                <?php foreach ($questions as $idx => $question): ?>
                    <?php 
                    $answers = [];
                    if ($question['answers_data']) {
                        foreach (explode('||', $question['answers_data']) as $answerData) {
                            $parts = explode(':', $answerData, 3);
                            if (count($parts) === 3) {
                                $answers[] = [
                                    'id' => $parts[0],
                                    'text' => $parts[1],
                                    'is_correct' => $parts[2]
                                ];
                            }
                        }
                    }
                    ?>
                    <div class="question-item" style="padding: 1.25rem; border-bottom: 1px solid var(--border-color); <?= !$question['is_active'] ? 'opacity: 0.5;' : '' ?>">
                        <div class="d-flex justify-between align-center mb-2">
                            <div class="d-flex align-center gap-2">
                                <span class="badge badge-new"><?= $idx + 1 ?></span>
                                <span style="font-weight: 500;">
                                    <?= sanitize($question['question_text']) ?>
                                </span>
                                <?php if (!$question['is_active']): ?>
                                    <span class="badge badge-rejected">Отключён</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-center gap-1">
                                <span class="text-muted" style="font-size: 0.8125rem; margin-right: 0.5rem;">
                                    <?= $question['points'] ?> балл(ов)
                                </span>
                                <form action="" method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_question">
                                    <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-secondary" title="Переключить">
                                        <?= $question['is_active'] ? '🔴' : '🟢' ?>
                                    </button>
                                </form>
                                <form action="" method="POST" style="display: inline;" onsubmit="return confirm('Удалить вопрос?');">
                                    <input type="hidden" name="action" value="delete_question">
                                    <input type="hidden" name="question_id" value="<?= $question['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Удалить">
                                        🗑️
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div style="margin-left: 2.5rem;">
                            <?php foreach ($answers as $answer): ?>
                                <div style="padding: 0.375rem 0; font-size: 0.9375rem; <?= $answer['is_correct'] ? 'color: var(--success); font-weight: 500;' : 'color: var(--text-secondary);' ?>">
                                    <?= $answer['is_correct'] ? '✓' : '○' ?>
                                    <?= sanitize($answer['text']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Question Modal -->
    <div class="modal-overlay" id="addQuestionModal">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">Добавить вопрос</h3>
                <button class="modal-close" data-modal-close>×</button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="action" value="create_question">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label required">Текст вопроса</label>
                        <textarea name="question_text" class="form-control" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Тип вопроса</label>
                            <select name="question_type" class="form-control">
                                <option value="single">Один ответ</option>
                                <option value="multiple">Несколько ответов</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Баллы</label>
                            <input type="number" name="points" class="form-control" value="10" min="1">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required">Варианты ответов</label>
                        <div id="answersContainer">
                            <div class="answer-row d-flex gap-2 mb-2">
                                <input type="radio" name="correct[]" value="0" checked style="margin-top: 0.75rem;">
                                <input type="text" name="answers[]" class="form-control" placeholder="Вариант ответа 1" required>
                            </div>
                            <div class="answer-row d-flex gap-2 mb-2">
                                <input type="radio" name="correct[]" value="1" style="margin-top: 0.75rem;">
                                <input type="text" name="answers[]" class="form-control" placeholder="Вариант ответа 2" required>
                            </div>
                            <div class="answer-row d-flex gap-2 mb-2">
                                <input type="radio" name="correct[]" value="2" style="margin-top: 0.75rem;">
                                <input type="text" name="answers[]" class="form-control" placeholder="Вариант ответа 3">
                            </div>
                            <div class="answer-row d-flex gap-2 mb-2">
                                <input type="radio" name="correct[]" value="3" style="margin-top: 0.75rem;">
                                <input type="text" name="answers[]" class="form-control" placeholder="Вариант ответа 4">
                            </div>
                        </div>
                        <div class="form-text">Отметьте правильный ответ радиокнопкой</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-close>Отмена</button>
                    <button type="submit" class="btn btn-primary">Добавить</button>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-icon">📝</div>
            <div class="empty-title">Выберите тест</div>
            <div class="empty-text">Выберите тест из списка выше для управления вопросами</div>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
