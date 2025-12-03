<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$candidateId = (int)($_GET['id'] ?? 0);

if (!$candidateId) {
    header('Location: candidates.php');
    exit;
}

// Получаем данные кандидата
$candidate = db()->fetch("SELECT * FROM candidates WHERE id = ?", [$candidateId]);

if (!$candidate) {
    header('Location: candidates.php');
    exit;
}

// Удаляем файл резюме
if ($candidate['resume_file']) {
    deleteFile($candidate['resume_file'], 'resumes');
}

// Удаляем кандидата (каскадно удалятся и связанные записи)
db()->query("DELETE FROM candidates WHERE id = ?", [$candidateId]);

// Редирект с сообщением
$_SESSION['flash_message'] = 'Кандидат успешно удалён';
$_SESSION['flash_type'] = 'success';

header('Location: candidates.php');
exit;
