<?php
require_once __DIR__ . '/vendor/autoload.php';

session_start();

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['access_token']);
    header('Location: /dorykeepswimming.online/');
    exit();
}

$client = new Google\Client();
$client->setAuthConfig('credentials.json');
$client->addScope(Google\Service\Drive::DRIVE_FILE);
$client->setRedirectUri('http://localhost/dorykeepswimming.online/oauth2callback.php');

if (isset($_SESSION['access_token'])) {
    $client->setAccessToken($_SESSION['access_token']);

    // Refresh token if expired
    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            $_SESSION['access_token'] = $client->getAccessToken();
        } else {
            unset($_SESSION['access_token']);
            header('Location: /dorykeepswimming.online/');
            exit();
        }
    }

    $driveService = new Google\Service\Drive($client);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Upload to Google Drive</title>
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
                max-width: 1200px;
                margin: 0 auto;
                padding: 2rem;
            }
            
            header {
                background-color: var(--primary-color);
                padding: 1.5rem 2rem;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .logo {
                font-size: 1.5rem;
                font-weight: 500;
                color: white;
                text-decoration: none;
            }
            
            .logout-btn {
                color: var(--text-light);
                text-decoration: none;
                padding: 0.5rem 1rem;
                border-radius: 4px;
                transition: background-color 0.3s;
            }
            
            .logout-btn:hover {
                background-color: var(--primary-dark);
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
            
            .form-group {
                margin-bottom: 1.5rem;
            }
            
            label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 500;
                color: var(--text-light);
            }
            
            input[type="text"],
            input[type="file"] {
                width: 100%;
                padding: 0.75rem;
                border: 1px solid var(--border-color);
                border-radius: 4px;
                background-color: rgba(255, 255, 255, 0.05);
                color: var(--text-light);
                font-size: 1rem;
                transition: border-color 0.3s;
            }
            
            input[type="text"]:focus,
            input[type="file"]:focus {
                outline: none;
                border-color: var(--secondary-color);
            }
            
            .btn {
                background-color: var(--secondary-color);
                color: white;
                border: none;
                padding: 0.75rem 1.5rem;
                font-size: 1rem;
                border-radius: 4px;
                cursor: pointer;
                transition: background-color 0.3s, transform 0.2s;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .btn:hover {
                background-color: #1565c0;
                transform: translateY(-2px);
            }
            
            .btn:active {
                transform: translateY(0);
            }
            
            .file-list {
                margin-top: 1rem;
                background-color: rgba(0, 0, 0, 0.1);
                border-radius: 4px;
                padding: 1rem;
                border: 1px dashed var(--border-color);
            }
            
            .file-item {
                padding: 0.5rem;
                background-color: rgba(255, 255, 255, 0.05);
                border-radius: 4px;
                margin-bottom: 0.5rem;
                display: flex;
                align-items: center;
            }
            
            .file-item:before {
                content: "📄";
                margin-right: 0.5rem;
            }
            
            .drive-files {
                list-style: none;
            }
            
            .drive-files li {
                padding: 0.75rem;
                border-bottom: 1px solid var(--border-color);
                display: flex;
                align-items: center;
            }
            
            .drive-files li:before {
                content: "📁";
                margin-right: 0.75rem;
            }
            
            .drive-files li a {
                color: var(--text-light);
                text-decoration: none;
                transition: color 0.3s;
                flex-grow: 1;
            }
            
            .drive-files li a:hover {
                color: var(--secondary-color);
            }
            
            .file-type {
                color: var(--text-muted);
                font-size: 0.85rem;
                margin-left: 1rem;
            }
            
            .empty-state {
                color: var(--text-muted);
                text-align: center;
                padding: 2rem;
                font-style: italic;
            }
            
            .error-message {
                color: var(--error-color);
                background-color: rgba(244, 67, 54, 0.1);
                padding: 1rem;
                border-radius: 4px;
                margin-bottom: 1rem;
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
            <a href="/dorykeepswimming.online/" class="logo">ATMS Drive Uploader</a>
            <a href="?logout" class="logout-btn">Logout</a>
        </header>
        
        <div class="container">
            <div class="card">
                <h1>Upload Files to Google Drive</h1>
                
                <form action="upload.php" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="folder_name">Folder Name</label>
                        <input type="text" name="folder_name" id="folder_name" required placeholder="Enter a name for your folder">
                    </div>
                    <div class="form-group">
                        <label for="files">Select Files</label>
                        <input type="file" name="files[]" id="files" multiple required>
                        <div class="file-list" id="fileList"></div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn">Upload to Drive</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2>Your Google Drive Files</h2>
                <?php
                // Fetch files from Google Drive
                try {
                    $files = $driveService->files->listFiles([
                        'fields' => 'files(id, name, mimeType, webViewLink)',
                        'orderBy' => 'createdTime desc',
                        'pageSize' => 10
                    ])->getFiles();

                    if (!empty($files)) {
                        echo "<ul class='drive-files'>";
                        foreach ($files as $file) {
                            echo "<li>";
                            echo "<a href='{$file->getWebViewLink()}' target='_blank'>{$file->getName()}</a>";
                            echo "<span class='file-type'>{$file->getMimeType()}</span>";
                            echo "</li>";
                        }
                        echo "</ul>";
                    } else {
                        echo "<div class='empty-state'>No files found in your Drive.</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='error-message'>Error fetching files: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                ?>
            </div>
        </div>

        <script>
            // Display selected file names
            document.getElementById('files').addEventListener('change', function() {
                const fileList = document.getElementById('fileList');
                fileList.innerHTML = '';

                if (this.files.length === 0) {
                    fileList.innerHTML = '<div class="empty-state">No files selected</div>';
                    return;
                }

                for (let i = 0; i < this.files.length; i++) {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item';
                    fileItem.textContent = this.files[i].name;
                    fileList.appendChild(fileItem);
                }
            });
        </script>
    </body>
    </html>
    <?php
} else {
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
}
?>