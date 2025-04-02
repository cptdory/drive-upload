<?php
require_once __DIR__ . '/vendor/autoload.php';

session_start();

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['access_token']);
    header('Location: https://dorykeepswimming.online/');
    exit();
}

$client = new Google\Client();
$client->setAuthConfig('credentials.json');
$client->addScope(Google\Service\Drive::DRIVE_FILE);
$client->setRedirectUri('https://dorykeepswimming.online/oauth2callback.php');

if (isset($_SESSION['access_token'])) {
    $client->setAccessToken($_SESSION['access_token']);

    // Refresh token if expired
    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            $_SESSION['access_token'] = $client->getAccessToken();
        } else {
            unset($_SESSION['access_token']);
            header('Location: https://dorykeepswimming.online/');
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
        <link href="styles.css" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    </head>

    <body>
        <header>
            <a href="/drive-upload/" class="logo">ATMS Drive Uploader</a>
            <a href="?logout" class="logout-btn">Logout</a>
        </header>

        <div class="container">
            <div class="card">
                <h1>Upload Files to Google Drive</h1>

                <form id="uploadForm" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="folder_name">Folder Name</label>
                        <input type="text" name="folder_name" id="folder_name" required placeholder="Enter a name for your folder">
                    </div>
                    <div class="form-group">
                        <label for="files">Select Files</label>
                        <input type="file" name="files[]" id="files" multiple required>
                        <div class="file-list" id="fileList"></div>
                    </div>

                    <div class="progress-container" id="progressContainer">
                        <div class="overall-progress">
                            <div class="progress-bar">
                                <div class="progress" id="overallProgress"></div>
                                <div class="progress-percentage" id="progressPercentage">0%</div>
                            </div>
                            <div class="progress-text" id="overallText">Preparing upload...</div>
                        </div>
                        <div class="current-file" id="currentFile"></div>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn" id="uploadBtn">Upload to Drive</button>
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

            // Handle form submission with progress tracking
            document.getElementById('uploadForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const form = this;
                const progressContainer = document.getElementById('progressContainer');
                const overallProgress = document.getElementById('overallProgress');
                const overallText = document.getElementById('overallText');
                const currentFile = document.getElementById('currentFile');
                const progressPercentage = document.getElementById('progressPercentage');
                const uploadBtn = document.getElementById('uploadBtn');
                
                // Show progress container
                progressContainer.style.display = 'block';
                uploadBtn.disabled = true;
                uploadBtn.textContent = 'Uploading...';
                
                // Create FormData object
                const formData = new FormData(form);
                
                // Start checking progress
                const progressInterval = setInterval(checkProgress, 500);
                
                // Function to check upload progress
                function checkProgress() {
                    fetch('get_progress.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.progress) {
                                // Update progress bar
                                const progress = Math.round(data.progress);
                                overallProgress.style.width = progress + '%';
                                progressPercentage.textContent = progress + '%';
                                overallText.textContent = data.message || 'Uploading...';
                                
                                // Update current file
                                if (data.current_file) {
                                    currentFile.textContent = 'Current file: ' + data.current_file;
                                }
                                
                                // If upload is complete
                                if (data.progress >= 100) {
                                    clearInterval(progressInterval);
                                    // Submit the form normally to complete the upload
                                    form.submit();
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error checking progress:', error);
                        });
                }
                
                // Submit the form via AJAX to start the upload process
                fetch('upload.php', {
                    method: 'POST',
                    body: formData
                }).catch(error => {
                    console.error('Upload error:', error);
                });
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