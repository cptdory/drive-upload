<?php
// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

session_start();

if (isset($_SESSION['upload_progress'])) {
    echo json_encode($_SESSION['upload_progress']);
} else {
    echo json_encode([
        'progress' => 0, 
        'message' => 'Waiting for upload to start...',
        'current_file' => ''
    ]);
}

session_write_close();
?>