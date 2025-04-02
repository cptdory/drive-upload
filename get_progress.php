<?php
session_start();

header('Content-Type: application/json');

if (isset($_SESSION['upload_progress'])) {
    echo json_encode($_SESSION['upload_progress']);
} else {
    echo json_encode(['progress' => 0, 'message' => 'Starting upload...']);
}
?>