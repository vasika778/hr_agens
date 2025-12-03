<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/email.php';

$positions = getPositions();
$success = false;
$error = '';


// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $positionId = (int)($_POST['position_id'] ?? 0);
    $comment = sanitize($_POST['comment'] ?? '');
    
    // Валидация
    if (empty($name)) {
        $error = 'Укажите ваше имя';
    } elseif (!isValidPhone($phone)) {
        $error = 'Укажите корректный номер телефона';
    } elseif (!isValidEmail($email)) {
        $error = 'Укажите корректный email';
    } elseif ($positionId <= 0) {
        $error = 'Выберите желаемую должность';
    } elseif (!isset($_FILES['resume']) || $_FILES['resume']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Загрузите ваше резюме';
    } else {
        // Загрузка файла
        $uploadResult = uploadFile($_FILES['resume'], 'resumes', ALLOWED_RESUME_EXTENSIONS);
        
        if (!$uploadResult['success']) {
            $error = $uploadResult['error'];
        } else {
            // Генерация токена доступа
            $accessToken = generateToken();
            
            // Сохранение в БД
            try {
                db()->query(
                    "INSERT INTO candidates (name, phone, email, position_id, resume_file, comment, status, group_id, access_token) 
                     VALUES (?, ?, ?, ?, ?, ?, 'new', 1, ?)",
                    [$name, $phone, $email, $positionId, $uploadResult['filename'], $comment, $accessToken]
                );
                
                $candidateId = db()->lastInsertId();
                
                // Логируем создание
                logStatusChange($candidateId, null, 'new', null, 'Заявка подана через сайт');
                
                // Получаем данные кандидата для email
                $candidate = db()->fetch("SELECT * FROM candidates WHERE id = ?", [$candidateId]);
                
                // Отправляем email уведомления
                emailNotifier()->notifyTestInvitation($candidate);  // Кандидату
                emailNotifier()->notifyNewResume($candidate);       // HR
                
                $success = true;
            } catch (Exception $e) {
                $error = 'Ошибка при сохранении данных. Попробуйте позже.';
                // Удаляем загруженный файл при ошибке
                deleteFile($uploadResult['filename'], 'resumes');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Agency - Подать резюме</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💼</text></svg>">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-inner">
                <a href="index.php" class="logo">
                    <span class="logo-icon">💼</span>
                    <span>HR Agency</span>
                </a>
                <nav class="nav">
                    <a href="index.php" class="nav-link active">Подать резюме</a>
                    <a href="admin/login.php" class="nav-link">Для HR</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main>
        <!-- Hero Section -->
        <section class="hero">
            <div class="container">
                <h1 class="hero-title">Найдите работу мечты</h1>
                <p class="hero-subtitle">Заполните форму ниже, и наши HR-специалисты свяжутся с вами в ближайшее время</p>
            </div>
        </section>

        <!-- Application Form -->
        <section class="container container-sm">
            <?php if ($success): ?>
                <div class="card animate-fade-in">
                    <div class="card-body text-center" style="padding: 3rem;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">✅</div>
                        <h2 style="margin-bottom: 1rem;">Заявка отправлена!</h2>
                        <p class="text-muted" style="margin-bottom: 1.5rem;">
                            Спасибо за интерес к нашей компании. Мы рассмотрим вашу заявку и свяжемся с вами в ближайшее время.
                        </p>
                        <a href="index.php" class="btn btn-primary">Подать ещё одну заявку</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card animate-fade-in">
                    <div class="card-header">
                        <h2 class="card-title">
                            <span>📝</span>
                            Форма подачи резюме
                        </h2>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <span>⚠️</span>
                                <span><?= $error ?></span>
                            </div>
                        <?php endif; ?>

                        <form action="" method="POST" enctype="multipart/form-data" id="applicationForm">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label required" for="name">Ваше имя</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="name" 
                                           name="name" 
                                           placeholder="Иванов Иван Иванович"
                                           value="<?= sanitize($_POST['name'] ?? '') ?>"
                                           required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required" for="phone">Телефон</label>
                                    <input type="tel" 
                                           class="form-control" 
                                           id="phone" 
                                           name="phone" 
                                           placeholder="+996 555 000 000"
                                           value="<?= sanitize($_POST['phone'] ?? '') ?>"
                                           required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label required" for="email">Email</label>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           placeholder="example@mail.ru"
                                           value="<?= sanitize($_POST['email'] ?? '') ?>"
                                           required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required" for="position_id">Желаемая должность</label>
                                    <select class="form-control" id="position_id" name="position_id" required>
                                        <option value="">Выберите должность</option>
                                        <?php foreach ($positions as $position): ?>
                                            <option value="<?= $position['id'] ?>" 
                                                    <?= (($_POST['position_id'] ?? '') == $position['id']) ? 'selected' : '' ?>>
                                                <?= sanitize($position['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label required">Резюме (PDF, DOC, DOCX)</label>
                                <div class="file-input-wrapper">
                                    <input type="file" 
                                           class="file-input" 
                                           id="resume" 
                                           name="resume" 
                                           accept=".pdf,.doc,.docx"
                                           required>
                                    <label class="file-input-label" for="resume">
                                        <span class="file-input-icon">📄</span>
                                        <span class="file-input-text">
                                            <strong>Нажмите для выбора файла</strong><br>
                                            <small class="text-muted">или перетащите файл сюда</small>
                                        </span>
                                    </label>
                                    <div class="file-name" style="display: none;"></div>
                                </div>
                                <div class="form-text">Максимальный размер файла: 10 МБ</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="comment">Комментарий (необязательно)</label>
                                <textarea class="form-control" 
                                          id="comment" 
                                          name="comment" 
                                          placeholder="Расскажите о себе или укажите дополнительную информацию..."
                                          rows="4"><?= sanitize($_POST['comment'] ?? '') ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg btn-block">
                                Отправить заявку
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Footer -->
    <footer style="text-align: center; padding: 2rem; margin-top: 3rem; color: var(--text-muted); font-size: 0.875rem;">
        <p>&copy; <?= date('Y') ?> HR Agency. Все права защищены.</p>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>
