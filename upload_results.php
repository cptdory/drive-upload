<?php
require_once __DIR__ . '/vendor/autoload.php';

session_start();

// Check if user is logged in
if (!isset($_SESSION['access_token'])) {
    header('Location: https://dorykeepswimming.online/');
    exit();
}

// Check if there are upload results to display
if (!isset($_SESSION['upload_results'])) {
    header('Location: index.php');
    exit();
}

$client = new Google\Client();
$client->setAuthConfig('credentials.json');
$client->addScope(Google\Service\Drive::DRIVE_FILE);
$client->setAccessToken($_SESSION['access_token']);

// Refresh token if expired
if ($client->isAccessTokenExpired()) {
    if ($client->getRefreshToken()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        $_SESSION['access_token'] = $client->getAccessToken();
    } else {
        header('Location: https://dorykeepswimming.online/');
        exit();
    }
}

// Get the upload results from session
$uploadResults = $_SESSION['upload_results'];
unset($_SESSION['upload_results']); // Clear the results after displaying

// Separate successful and failed uploads
$successfulUploads = array_filter($uploadResults, function($item) {
    return $item['status'] === 'success';
});

$failedUploads = array_filter($uploadResults, function($item) {
    return $item['status'] === 'error';
});

// Get the folder link (first successful item is the folder)
$folderLink = '';
foreach ($uploadResults as $item) {
    if ($item['type'] === 'folder' && $item['status'] === 'success') {
        $folderLink = $item['link'];
        break;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Results</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body>
    <header>
        <a href="/drive-upload/" class="logo">ATMS Drive Uploader</a>
        <a href="?logout" class="logout-btn">Logout</a>
    </header>

    <div class="container">
        <div class="card">
            <h1>Upload Results</h1>

            <?php if ($folderLink): ?>
                <div class="form-group">
                    <p>Your files have been uploaded to:</p>
                    <a href="<?php echo htmlspecialchars($folderLink); ?>" target="_blank" class="btn" style="display: inline-block; margin-top: 10px;">
                        Open Folder in Google Drive
                    </a>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <h3>Summary</h3>
                <p>
                    <?php echo count($successfulUploads); ?> files uploaded successfully
                    <?php if (count($failedUploads)) echo ', ' . count($failedUploads) . ' failed'; ?>
                </p>
            </div>

            <?php if (!empty($successfulUploads)): ?>
                <div class="form-group">
                    <h3>Successfully Uploaded Files</h3>
                    <ul class="drive-files">
                        <?php foreach ($successfulUploads as $item): ?>
                            <?php if ($item['type'] === 'file'): ?>
                                <li>
                                    <a href="<?php echo htmlspecialchars($item['link']); ?>" target="_blank">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($failedUploads)): ?>
                <div class="form-group">
                    <h3>Failed Uploads</h3>
                    <ul class="file-list">
                        <?php foreach ($failedUploads as $item): ?>
                            <li class="file-item" style="color: var(--error-color);">
                                <?php echo htmlspecialchars($item['name']); ?>
                                <span style="font-size: 0.8em; display: block; margin-top: 5px;">
                                    <?php echo htmlspecialchars($item['message'] ?? 'Unknown error'); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <a href="index.php" class="btn">Upload More Files</a>
            </div>
        </div>
    </div>
</body>
</html>