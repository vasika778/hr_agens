<?php
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$docId = (int)($_GET['id'] ?? 0);
$currentAdmin = getCurrentAdmin();

if (!$docId) {
    header('Location: documents.php');
    exit;
}

// Получаем документ
$doc = db()->fetch(
    "SELECT * FROM hr_documents WHERE id = ? AND admin_id = ?",
    [$docId, $currentAdmin['id']]
);

if (!$doc) {
    header('Location: documents.php');
    exit;
}

// Путь к файлу
$filePath = UPLOAD_PATH . 'hr_docs/' . $currentAdmin['id'] . '/' . $doc['file_name'];

if (!file_exists($filePath)) {
    die('Файл не найден');
}

// Увеличиваем счётчик скачиваний
db()->query("UPDATE hr_documents SET download_count = download_count + 1 WHERE id = ?", [$docId]);

// Определяем MIME тип
$mimeTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'txt' => 'text/plain',
    'rtf' => 'application/rtf',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed'
];

$mimeType = $mimeTypes[$doc['file_type']] ?? 'application/octet-stream';

// Отправляем файл
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $doc['original_name'] . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit;
