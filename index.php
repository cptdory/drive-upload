<?php
require_once __DIR__ . '/vendor/autoload.php';

session_start();

// Set upload size limits
ini_set('upload_max_filesize', '1536M');
ini_set('post_max_size', '1536M');
ini_set('max_execution_time', 300); // 5 minutes
ini_set('max_input_time', 300); // 5 minutes

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['access_token']);
    header('Location:https://dorykeepswimming.online//');
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
            header('Location:https://dorykeepswimming.online/');
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

                <form id="uploadForm" method="post" enctype="multipart/form-data" action="upload.php">
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
                            </div>
                            <div class="progress-text" id="overallText">Preparing upload...</div>
                        </div>
                        <div id="fileProgressContainer"></div>
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

            // Handle form submission and progress updates
            document.getElementById('uploadForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const form = e.target;
                const formData = new FormData(form);
                const progressContainer = document.getElementById('progressContainer');
                const overallProgress = document.getElementById('overallProgress');
                const overallText = document.getElementById('overallText');
                const fileProgressContainer = document.getElementById('fileProgressContainer');
                const uploadBtn = document.getElementById('uploadBtn');
                
                // Show progress container
                progressContainer.style.display = 'block';
                uploadBtn.disabled = true;
                uploadBtn.textContent = 'Uploading...';
                
                // Clear previous file progress indicators
                fileProgressContainer.innerHTML = '';
                
                // Create progress elements for each file
                const files = document.getElementById('files').files;
                for (let i = 0; i < files.length; i++) {
                    const fileProgress = document.createElement('div');
                    fileProgress.className = 'file-progress';
                    
                    const fileName = document.createElement('div');
                    fileName.className = 'file-progress-name';
                    fileName.textContent = files[i].name;
                    
                    const progressBar = document.createElement('div');
                    progressBar.className = 'progress-bar';
                    
                    const progress = document.createElement('div');
                    progress.className = 'progress';
                    progress.id = `fileProgress${i}`;
                    
                    progressBar.appendChild(progress);
                    fileProgress.appendChild(fileName);
                    fileProgress.appendChild(progressBar);
                    fileProgressContainer.appendChild(fileProgress);
                }
                
                // Submit form via AJAX to track progress
                const xhr = new XMLHttpRequest();
                
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = Math.round((e.loaded / e.total) * 100);
                        overallProgress.style.width = percent + '%';
                        overallText.textContent = `Uploading... ${percent}%`;
                    }
                });
                
                xhr.addEventListener('load', function() {
                    if (xhr.status === 200) {
                        // Redirect to results page as handled by upload.php
                        window.location.href = 'upload_results.php';
                    } else {
                        overallText.textContent = 'Upload failed';
                        uploadBtn.disabled = false;
                        uploadBtn.textContent = 'Upload to Drive';
                    }
                });
                
                xhr.addEventListener('error', function() {
                    overallText.textContent = 'Upload failed';
                    uploadBtn.disabled = false;
                    uploadBtn.textContent = 'Upload to Drive';
                });
                
                xhr.open('POST', form.action, true);
                xhr.send(formData);
                
                // Check server-side progress periodically
                const checkProgress = setInterval(function() {
                    fetch('check_progress.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.progress) {
                                overallProgress.style.width = data.progress + '%';
                                overallText.textContent = data.message || `Uploading... ${data.progress}%`;
                                
                                if (data.current_file) {
                                    // Update current file being processed
                                    overallText.textContent = `Uploading ${data.current_file}... ${data.progress}%`;
                                }
                                
                                if (data.progress === 100) {
                                    clearInterval(checkProgress);
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error checking progress:', error);
                        });
                }, 1000);
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