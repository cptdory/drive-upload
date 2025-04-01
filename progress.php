<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['upload_progress'])) {
    echo json_encode([
        'progress' => 0,
        'message' => 'Starting upload...'
    ]);
    exit();
}

echo json_encode($_SESSION['upload_progress']);