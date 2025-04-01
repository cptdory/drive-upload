<?php
require_once __DIR__ . '/vendor/autoload.php';

session_start();

if (!isset($_SESSION['access_token'])) {
    header('Location: /drive-upload/');
    exit();
}

$client = new Google\Client();
$client->setAuthConfig('credentials.json');
$client->addScope(Google\Service\Drive::DRIVE_FILE);
$client->setAccessToken($_SESSION['access_token']);

if ($client->isAccessTokenExpired()) {
    if ($client->getRefreshToken()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
    } else {
        header('Location: /drive-upload/');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['folder_name']) && !empty($_FILES['files']['name'][0])) {
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
        
        // 2. Upload files to the folder
        foreach ($_FILES['files']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['files']['error'][$key] !== UPLOAD_ERR_OK) {
                $uploadResults[] = [
                    'name' => $_FILES['files']['name'][$key],
                    'type' => 'file',
                    'status' => 'error',
                    'message' => 'Upload failed with error code ' . $_FILES['files']['error'][$key]
                ];
                continue;
            }
            
            try {
                $fileMetadata = new Google\Service\Drive\DriveFile([
                    'name' => $_FILES['files']['name'][$key],
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
            } catch (Exception $e) {
                $uploadResults[] = [
                    'name' => $_FILES['files']['name'][$key],
                    'type' => 'file',
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }
        
    } catch (Exception $e) {
        die("Error creating folder: " . $e->getMessage());
    }
    
    // Display results page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Upload Results - Drive Upload</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
        <style>
            :root {
                --primary-color: #1a237e;
                --primary-light: #534bae;
                --primary-dark: #000051;
                --secondary-color: #1976d2;
                --text-light: #f5f5f5;
                --text-muted: #b0bec5;
                --background-dark: #0a192f;
                --card-bg: #16213e;
                --border-color: #2a3a5e;
                --success-color: #4caf50;
                --error-color: #f44336;
            }
            
            * {
                box-sizing: border-box;
                margin: 0;
                padding: 0;
            }
            
            body {
                font-family: 'Roboto', sans-serif;
                background-color: var(--background-dark);
                color: var(--text-light);
                line-height: 1.6;
                padding: 0;
                margin: 0;
            }
            
            .container {
                max-width: 800px;
                margin: 0 auto;
                padding: 2rem;
            }
            
            header {
                background-color: var(--primary-color);
                padding: 1.5rem 2rem;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                margin-bottom: 2rem;
            }
            
            .logo {
                font-size: 1.5rem;
                font-weight: 500;
                color: white;
                text-decoration: none;
            }
            
            .card {
                background-color: var(--card-bg);
                border-radius: 8px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                padding: 2rem;
                margin-bottom: 2rem;
            }
            
            h1, h2, h3 {
                color: var(--text-light);
                margin-bottom: 1.5rem;
            }
            
            h1 {
                font-size: 2rem;
                font-weight: 500;
                border-bottom: 2px solid var(--secondary-color);
                padding-bottom: 0.5rem;
                display: inline-block;
            }
            
            .result-item {
                padding: 1.25rem;
                border-radius: 6px;
                margin-bottom: 1rem;
                display: flex;
                align-items: center;
                background-color: rgba(255, 255, 255, 0.05);
                border-left: 4px solid transparent;
            }
            
            .result-item.success {
                border-left-color: var(--success-color);
            }
            
            .result-item.error {
                border-left-color: var(--error-color);
            }
            
            .result-icon {
                font-size: 1.5rem;
                margin-right: 1rem;
            }
            
            .success .result-icon {
                color: var(--success-color);
            }
            
            .error .result-icon {
                color: var(--error-color);
            }
            
            .result-content {
                flex-grow: 1;
            }
            
            .result-name {
                font-weight: 500;
                margin-bottom: 0.25rem;
            }
            
            .result-type {
                font-size: 0.85rem;
                color: var(--text-muted);
                margin-bottom: 0.25rem;
            }
            
            .result-status {
                font-size: 0.9rem;
            }
            
            .success .result-status {
                color: var(--success-color);
            }
            
            .error .result-status {
                color: var(--error-color);
            }
            
            .result-link {
                display: inline-block;
                margin-top: 0.5rem;
                color: var(--secondary-color);
                text-decoration: none;
                transition: color 0.3s;
            }
            
            .result-link:hover {
                text-decoration: underline;
            }
            
            .back-link {
                display: inline-block;
                margin-top: 1.5rem;
                color: var(--secondary-color);
                text-decoration: none;
                font-weight: 500;
                transition: color 0.3s;
            }
            
            .back-link:hover {
                text-decoration: underline;
            }
            
            .summary {
                margin-bottom: 2rem;
                padding: 1rem;
                background-color: rgba(0, 0, 0, 0.2);
                border-radius: 6px;
                text-align: center;
            }
            
            .summary-success {
                color: var(--success-color);
                font-weight: 500;
            }
            
            .summary-error {
                color: var(--error-color);
                font-weight: 500;
            }
            
            @media (max-width: 768px) {
                .container {
                    padding: 1rem;
                }
                
                header {
                    padding: 1rem;
                }
                
                .card {
                    padding: 1.5rem;
                }
            }
        </style>
    </head>
    <body>
        <header>
            <a href="/drive-upload/" class="logo">ATMS Drive Uploader</a>
        </header>
        
        <div class="container">
            <div class="card">
                <h1>Upload Results</h1>
                
                <?php
                // Calculate success/error counts
                $successCount = 0;
                $errorCount = 0;
                
                foreach ($uploadResults as $result) {
                    if ($result['status'] === 'success') {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                }
                ?>
                
                <div class="summary">
                    <p>Upload completed with 
                        <span class="summary-success"><?php echo $successCount; ?> successful</span> and 
                        <span class="summary-error"><?php echo $errorCount; ?> failed</span> items.
                    </p>
                </div>
                
                <?php foreach ($uploadResults as $result): ?>
                    <div class="result-item <?php echo $result['status']; ?>">
                        <div class="result-icon">
                            <?php echo $result['status'] === 'success' ? '✓' : '✗'; ?>
                        </div>
                        <div class="result-content">
                            <div class="result-name"><?php echo htmlspecialchars($result['name']); ?></div>
                            <div class="result-type"><?php echo $result['type']; ?></div>
                            <div class="result-status">
                                <?php if ($result['status'] === 'success'): ?>
                                    Uploaded successfully
                                <?php else: ?>
                                    Error: <?php echo htmlspecialchars($result['message']); ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($result['status'] === 'success'): ?>
                                <a href="<?php echo htmlspecialchars($result['link']); ?>" class="result-link" target="_blank">View in Drive</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <a href="/drive-upload/" class="back-link">← Upload more files</a>
            </div>
        </div>
    </body>
    </html>
    <?php
} else {
    header('Location: /drive-upload/');
}
?>