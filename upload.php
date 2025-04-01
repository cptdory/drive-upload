<?php
require_once __DIR__ . '/vendor/autoload.php';

session_start();

// Enhanced JSON response function
function jsonResponse($success, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'success' => $success,
        'timestamp' => time()
    ], $data));
    exit();
}

// Check if it's an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Authentication check
if (!isset($_SESSION['access_token'])) {
    $message = 'Session expired. Please refresh the page.';
    if ($isAjax) {
        jsonResponse(false, ['message' => $message], 401);
    } else {
        $_SESSION['redirect_message'] = $message;
        header('Location: https://dorykeepswimming.online/');
        exit();
    }
}

// Initialize Google Client
try {
    $client = new Google\Client();
    $client->setAuthConfig('credentials.json');
    $client->addScope(Google\Service\Drive::DRIVE_FILE);
    $client->setAccessToken($_SESSION['access_token']);

    // Token refresh handling
    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            $_SESSION['access_token'] = $client->getAccessToken();
        } else {
            $message = 'Authentication failed. Please login again.';
            if ($isAjax) {
                jsonResponse(false, ['message' => $message], 401);
            } else {
                $_SESSION['redirect_message'] = $message;
                header('Location: https://dorykeepswimming.online/');
                exit();
            }
        }
    }
} catch (Exception $e) {
    jsonResponse(false, ['message' => 'Client initialization failed: ' . $e->getMessage()], 500);
}

// Handle success redirect
if (isset($_GET['success'])) {
    // Display the success page
    include 'success-page.php';
    exit();
}

// Main upload processing
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate input
    if (empty($_POST['folder_name'])) {
        jsonResponse(false, ['message' => 'Folder name is required'], 400);
    }

    $driveService = new Google\Service\Drive($client);
    $uploadResults = [];
    
    try {
        // 1. Create the folder
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
        
        // For AJAX requests, return early after folder creation
        if ($isAjax) {
            jsonResponse(true, [
                'folder_id' => $folderId,
                'folder_name' => $folder->getName(),
                'folder_link' => $folder->getWebViewLink(),
                'message' => 'Folder created successfully. Ready for file uploads.'
            ]);
        }

        // 2. Process file uploads if present
        if (!empty($_FILES['files']['name'][0])) {
            $uploadedFiles = [];
            
            foreach ($_FILES['files']['tmp_name'] as $key => $tmpName) {
                $fileName = $_FILES['files']['name'][$key];
                $fileError = $_FILES['files']['error'][$key];
                
                if ($fileError !== UPLOAD_ERR_OK) {
                    $uploadResults[] = [
                        'name' => $fileName,
                        'type' => 'file',
                        'status' => 'error',
                        'message' => $this->getUploadErrorMessage($fileError)
                    ];
                    continue;
                }
                
                try {
                    // Validate file size (example: 50MB limit)
                    if ($_FILES['files']['size'][$key] > 50 * 1024 * 1024) {
                        throw new Exception('File size exceeds 50MB limit');
                    }
                    
                    $fileMetadata = new Google\Service\Drive\DriveFile([
                        'name' => $fileName,
                        'parents' => [$folderId]
                    ]);
                    
                    $content = file_get_contents($tmpName);
                    if ($content === false) {
                        throw new Exception('Could not read file contents');
                    }
                    
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
                        'link' => $uploadedFile->getWebViewLink(),
                        'size' => $_FILES['files']['size'][$key]
                    ];
                    
                    $uploadedFiles[] = $uploadedFile->getName();
                } catch (Exception $e) {
                    $uploadResults[] = [
                        'name' => $fileName,
                        'type' => 'file',
                        'status' => 'error',
                        'message' => $e->getMessage()
                    ];
                }
            }
        }
        
        // For non-AJAX requests, show results page
        if (!$isAjax) {
            $_SESSION['upload_results'] = $uploadResults;
            header('Location: upload-results.php');
            exit();
        }
        
    } catch (Exception $e) {
        $errorMessage = 'Error during upload process: ' . $e->getMessage();
        error_log($errorMessage);
        
        if ($isAjax) {
            jsonResponse(false, ['message' => $errorMessage], 500);
        } else {
            $_SESSION['error_message'] = $errorMessage;
            header('Location: error-page.php');
            exit();
        }
    }
} else {
    // Invalid request method
    $message = 'Invalid request method';
    if ($isAjax) {
        jsonResponse(false, ['message' => $message], 405);
    } else {
        $_SESSION['error_message'] = $message;
        header('Location: https://dorykeepswimming.online/');
        exit();
    }
}

// Helper function to translate upload error codes
function getUploadErrorMessage($code) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    
    return $errors[$code] ?? 'Unknown upload error';
}
?>