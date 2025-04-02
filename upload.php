<?php
require_once __DIR__ . '/vendor/autoload.php';

// Start session without writing to prevent early lock
session_start(['read_and_close' => true]);

if (!isset($_SESSION['access_token'])) {
    header('Location: https://dorykeepswimming.online/');
    exit();
}

// Function to send progress updates
function sendProgress($progress, $message = '', $currentFile = '') {
    session_start();
    $_SESSION['upload_progress'] = [
        'progress' => $progress,
        'message' => $message,
        'current_file' => $currentFile
    ];
    session_write_close();
}

// Initialize progress
sendProgress(5, 'Starting upload process...', '');

$client = new Google\Client();
$client->setAuthConfig('credentials.json');
$client->addScope(Google\Service\Drive::DRIVE_FILE);
$client->setAccessToken($_SESSION['access_token']);

if ($client->isAccessTokenExpired()) {
    if ($client->getRefreshToken()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
    } else {
        header('Location: https://dorykeepswimming.online/');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['folder_name']) && !empty($_FILES['files']['name'][0])) {
    // Immediately send response to prevent timeout
    ignore_user_abort(true);
    header("Connection: close");
    header("Content-Length: 0");
    ob_end_flush();
    flush();
    
    // Process upload in background
    register_shutdown_function(function() use ($client) {
        $driveService = new Google\Service\Drive($client);
        $uploadResults = [];
        $totalFiles = count($_FILES['files']['name']);
        $filesProcessed = 0;

        try {
            // 1. Create the folder
            sendProgress(10, 'Creating folder...', $_POST['folder_name']);
            
            $folderMetadata = new Google\Service\Drive\DriveFile([
                'name' => $_POST['folder_name'],
                'mimeType' => 'application/vnd.google-apps.folder'
            ]);

            $folder = $driveService->files->create($folderMetadata, [
                'fields' => 'id, name, webViewLink'
            ]);

            $folderId = $folder->getId();
            $uploadResults[] = [
                'name' => $folder->getName(),
                'type' => 'folder',
                'status' => 'success',
                'link' => $folder->getWebViewLink()
            ];

            sendProgress(20, 'Folder created successfully', $_POST['folder_name']);

            // 2. Upload files to the folder
            foreach ($_FILES['files']['tmp_name'] as $key => $tmpName) {
                $fileName = $_FILES['files']['name'][$key];
                $filesProcessed++;
                
                $progress = 20 + (($filesProcessed / $totalFiles) * 70);
                sendProgress($progress, 'Uploading file...', $fileName);

                if ($_FILES['files']['error'][$key] !== UPLOAD_ERR_OK) {
                    $uploadResults[] = [
                        'name' => $fileName,
                        'type' => 'file',
                        'status' => 'error',
                        'message' => 'Upload failed with error code ' . $_FILES['files']['error'][$key]
                    ];
                    continue;
                }

                try {
                    $fileMetadata = new Google\Service\Drive\DriveFile([
                        'name' => $fileName,
                        'parents' => [$folderId]
                    ]);

                    $content = file_get_contents($tmpName);

                    $uploadedFile = $driveService->files->create($fileMetadata, [
                        'data' => $content,
                        'mimeType' => $_FILES['files']['type'][$key],
                        'uploadType' => 'multipart',
                        'fields' => 'id, name, webViewLink'
                    ]);

                    $uploadResults[] = [
                        'name' => $uploadedFile->getName(),
                        'type' => 'file',
                        'status' => 'success',
                        'link' => $uploadedFile->getWebViewLink()
                    ];

                    sendProgress($progress, 'File uploaded successfully', $fileName);
                } catch (Exception $e) {
                    $uploadResults[] = [
                        'name' => $fileName,
                        'type' => 'file',
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                    sendProgress($progress, 'Error uploading file: ' . $e->getMessage(), $fileName);
                }
            }

            sendProgress(100, 'Upload complete!', '');
            
            // Store results in session for the results page
            session_start();
            $_SESSION['upload_results'] = $uploadResults;
            session_write_close();
            
        } catch (Exception $e) {
            sendProgress(0, 'Upload failed: ' . $e->getMessage(), '');
            error_log("Upload error: " . $e->getMessage());
        }
    });
    
    exit();
} else {
    header('Location: https://dorykeepswimming.online/');
}
?>