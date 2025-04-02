<?php
session_start();

if (!isset($_SESSION['access_token'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit();
}

if (isset($_SESSION['upload_progress'])) {
    header('Content-Type: application/json');
    echo json_encode($_SESSION['upload_progress']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['progress' => 0, 'message' => 'Upload not started']);
}
?>