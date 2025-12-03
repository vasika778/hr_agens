<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email.php';

$token = $_GET['token'] ?? '';
$candidate = getCandidateByToken($token);

if (!$candidate) {
    header('Location: index.php');
    exit;
}

// Получаем активный тест
$test = db()->fetch("SELECT * FROM tests WHERE is_active = 1 LIMIT 1");
$testEnabled = getSetting('test_enabled', '1') === '1';

// Проверка доступности теста
if (!$test || !$testEnabled || $candidate['test_passed'] || $candidate['test_attempts_used'] >= $test['max_attempts']) {
    header('Location: index.php?token=' . $token);
    exit;
}

// Получаем вопросы
$questions = db()->fetchAll(
    "SELECT q.* FROM questions q WHERE q.test_id = ? AND q.is_active = 1 ORDER BY q.order_num",
    [$test['id']]
);

// Получаем ответы для каждого вопроса
foreach ($questions as &$question) {
    $question['answers'] = db()->fetchAll(
        "SELECT id, answer_text FROM answers WHERE question_id = ? ORDER BY order_num",
        [$question['id']]
    );
}
unset($question);

$companyName = getSetting('company_name', 'HR Agency');

// Обработка отправки теста
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $answers = $_POST['answers'] ?? [];
    
    // Подсчёт результатов
    $totalScore = 0;
    $maxScore = 0;
    $answersJson = [];
    
    foreach ($questions as $question) {
        $maxScore += $question['points'];
        $selectedAnswer = $answers[$question['id']] ?? null;
        
        if ($selectedAnswer) {
            // Проверяем правильность ответа
            $correctAnswer = db()->fetch(
                "SELECT id FROM answers WHERE question_id = ? AND is_correct = 1",
                [$question['id']]
            );
            
            if ($correctAnswer && (int)$selectedAnswer === (int)$correctAnswer['id']) {
                $totalScore += $question['points'];
            }
            
            $answersJson[$question['id']] = $selectedAnswer;
        }
    }
    
    $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;
    $passed = $percentage >= $test['passing_score'];
    
    // Сохраняем результат
    db()->query(
        "INSERT INTO test_results (candidate_id, test_id, score, max_score, percentage, passed, answers_json, completed_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
        [$candidate['id'], $test['id'], $totalScore, $maxScore, $percentage, $passed ? 1 : 0, json_encode($answersJson)]
    );
    
    // Обновляем данные кандидата
    $newAttempts = $candidate['test_attempts_used'] + 1;
    
    // Данные результата для email
    $result = [
        'score' => $totalScore,
        'max_score' => $maxScore,
        'percentage' => $percentage,
        'passed' => $passed
    ];
    
    if ($passed) {
        db()->query(
            "UPDATE candidates SET test_attempts_used = ?, test_passed = 1, test_score = ? WHERE id = ?",
            [$newAttempts, $totalScore, $candidate['id']]
        );
        
        // Отправляем уведомление об успешном прохождении
        emailNotifier()->notifyTestPassed($candidate, $totalScore, $percentage);
        emailNotifier()->notifyTestCompleted($candidate, $result);
        
    } else {
        db()->query(
            "UPDATE candidates SET test_attempts_used = ? WHERE id = ?",
            [$newAttempts, $candidate['id']]
        );
        
        // Проверяем, остались ли попытки
        if ($newAttempts >= $test['max_attempts']) {
            // Попытки закончились
            emailNotifier()->notifyTestFailed($candidate);
        }
        
        // Уведомляем HR о результате
        emailNotifier()->notifyTestCompleted($candidate, $result);
    }
    
    // Показываем результат
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Результат теста - <?= sanitize($companyName) ?></title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body>
        <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 2rem;">
            <div class="card animate-fade-in" style="max-width: 500px; text-align: center;">
                <div class="card-body" style="padding: 3rem;">
                    <?php if ($passed): ?>
                        <div style="font-size: 5rem; margin-bottom: 1rem;">🎉</div>
                        <h2 style="color: var(--success); margin-bottom: 1rem;">Поздравляем!</h2>
                        <p>Вы успешно прошли тестирование!</p>
                    <?php else: ?>
                        <div style="font-size: 5rem; margin-bottom: 1rem;">😔</div>
                        <h2 style="color: var(--danger); margin-bottom: 1rem;">К сожалению...</h2>
                        <p>Вы не набрали достаточно баллов.</p>
                    <?php endif; ?>
                    
                    <div class="info-grid mt-3 mb-3" style="text-align: left;">
                        <div class="info-item">
                            <span class="info-label">Ваш результат</span>
                            <span class="info-value" style="font-size: 1.5rem; font-weight: 700;">
                                <?= $totalScore ?> / <?= $maxScore ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Процент</span>
                            <span class="info-value" style="font-size: 1.5rem; font-weight: 700; color: <?= $passed ? 'var(--success)' : 'var(--danger)' ?>;">
                                <?= $percentage ?>%
                            </span>
                        </div>
                    </div>
                    
                    <div class="progress-bar mb-3" style="height: 12px;">
                        <div class="progress-fill" style="width: <?= $percentage ?>%; background: <?= $passed ? 'var(--success)' : 'var(--danger)' ?>;"></div>
                    </div>
                    
                    <p class="text-muted" style="font-size: 0.875rem;">
                        Проходной балл: <?= $test['passing_score'] ?>%
                    </p>
                    
                    <a href="index.php?token=<?= $token ?>" class="btn btn-primary btn-lg mt-3">
                        Вернуться в личный кабинет
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тестирование - <?= sanitize($companyName) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
                <div class="nav">
                    <span class="text-muted">
                        <?= sanitize($test['title']) ?>
                    </span>
                </div>
            </div>
        </div>
    </header>

    <main class="container" style="padding-top: 2rem; padding-bottom: 3rem;">
        <div class="test-container">
            <form action="" method="POST" id="testForm">
                <?php foreach ($questions as $idx => $question): ?>
                    <div class="question-card animate-fade-in" style="animation-delay: <?= $idx * 0.1 ?>s;">
                        <div class="question-number">Вопрос <?= $idx + 1 ?> из <?= count($questions) ?></div>
                        <div class="question-text"><?= sanitize($question['question_text']) ?></div>
                        <div class="answer-options">
                            <?php foreach ($question['answers'] as $answer): ?>
                                <label class="answer-option">
                                    <input type="radio" 
                                           name="answers[<?= $question['id'] ?>]" 
                                           value="<?= $answer['id'] ?>"
                                           required>
                                    <span class="answer-radio"></span>
                                    <span class="answer-text"><?= sanitize($answer['answer_text']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-muted mt-2" style="font-size: 0.8125rem;">
                            За этот вопрос: <?= $question['points'] ?> балл(ов)
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-between align-center">
                            <a href="index.php?token=<?= $token ?>" class="btn btn-secondary">
                                ← Отменить
                            </a>
                            <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('Вы уверены, что хотите завершить тест?');">
                                Завершить тест ✓
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
    <script>
        // Добавляем выделение выбранного ответа
        document.querySelectorAll('.answer-option').forEach(option => {
            option.addEventListener('click', () => {
                const parent = option.closest('.answer-options');
                parent.querySelectorAll('.answer-option').forEach(o => o.classList.remove('selected'));
                option.classList.add('selected');
            });
        });
    </script>
</body>
</html>
