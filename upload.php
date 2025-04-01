

<?php
require_once __DIR__ . '/vendor/autoload.php';

session_start();

if (!isset($_SESSION['access_token'])) {
    header('Location: https://dorykeepswimming.online/');
    exit();
}

// Function to send progress updates
function sendProgress($progress, $message = '', $currentFile = '') {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['upload_progress'] = [
            'progress' => $progress,
            'message' => $message,
            'current_file' => $currentFile
        ];
        session_write_close();
    }
}

// Initialize progress
sendProgress(0, 'Preparing upload...', '');

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
    $driveService = new Google\Service\Drive($client);
    $uploadResults = [];
    $totalFiles = count($_FILES['files']['name']);
    $filesProcessed = 0;

    try {
        // 1. Create the folder
        sendProgress(5, 'Creating folder...', $_POST['folder_name']);
        
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

        sendProgress(10, 'Folder created', $_POST['folder_name']);

        // 2. Upload files to the folder
        foreach ($_FILES['files']['tmp_name'] as $key => $tmpName) {
            $fileName = $_FILES['files']['name'][$key];
            $filesProcessed++;
            
            // Calculate progress (10-90% for files, 10% was for folder creation)
            $baseProgress = 10;
            $progressRange = 90;
            $progress = $baseProgress + (($filesProcessed / $totalFiles) * $progressRange);
            
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

                sendProgress($progress, 'File uploaded', $fileName);
            } catch (Exception $e) {
                $uploadResults[] = [
                    'name' => $fileName,
                    'type' => 'file',
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                sendProgress($progress, 'Error uploading file', $fileName);
            }
        }

        sendProgress(100, 'Upload complete', '');
    } catch (Exception $e) {
        sendProgress(0, 'Error: ' . $e->getMessage(), '');
        die("Error: " . $e->getMessage());
    }

    // Store results in session for the results page
    $_SESSION['upload_results'] = $uploadResults;
    
    // Output JavaScript to redirect to results page
    ?>
    <script>
        window.location.href = 'upload_results.php';
    </script>
    <?php
    exit();
} else {
    header('Location: https://dorykeepswimming.online/');
}
?>