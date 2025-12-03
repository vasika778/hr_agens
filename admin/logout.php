<?php
require_once __DIR__ . '/../includes/functions.php';

// Уничтожаем сессию
session_destroy();

// Редирект на страницу входа
header('Location: login.php');
exit;
