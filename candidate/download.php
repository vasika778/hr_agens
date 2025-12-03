<?php
require_once __DIR__ . '/../includes/functions.php';

$token = $_GET['token'] ?? '';
$docId = (int)($_GET['id'] ?? 0);

$candidate = getCandidateByToken($token);

if (!$candidate || !$docId) {
    die('Доступ запрещён');
}

// Получаем документ (только те, которые доступны кандидату)
$doc = db()->fetch(
    "SELECT d.*, a.id as admin_id 
     FROM hr_documents d 
     JOIN admins a ON d.admin_id = a.id 
     WHERE d.id = ? AND d.candidate_id = ? AND d.is_visible_to_candidate = 1",
    [$docId, $candidate['id']]
);

if (!$doc) {
    die('Документ не найден или недоступен');
}

// Путь к файлу
$filePath = UPLOAD_PATH . 'hr_docs/' . $doc['admin_id'] . '/' . $doc['file_name'];

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
