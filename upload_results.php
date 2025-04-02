<?php
session_start();

if (!isset($_SESSION['upload_results'])) {
    header('Location: index.php');
    exit();
}

$uploadResults = $_SESSION['upload_results'];
unset($_SESSION['upload_results']);
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
            
            <div class="upload-summary">
                <?php
                $successCount = 0;
                $errorCount = 0;
                
                foreach ($uploadResults as $result) {
                    if ($result['status'] === 'success') $successCount++;
                    else $errorCount++;
                }
                
                echo "<p>Total items: " . count($uploadResults) . "</p>";
                echo "<p class='success'>Successful uploads: $successCount</p>";
                echo "<p class='error'>Failed uploads: $errorCount</p>";
                ?>
            </div>
            
            <div class="results-list">
                <h2>Upload Details</h2>
                <ul>
                    <?php foreach ($uploadResults as $result): ?>
                    <li class="<?= $result['status'] ?>">
                        <span class="item-type"><?= $result['type'] === 'folder' ? '📁' : '📄' ?></span>
                        <span class="item-name"><?= htmlspecialchars($result['name']) ?></span>
                        <span class="item-status"><?= $result['status'] ?></span>
                        <?php if ($result['status'] === 'success' && isset($result['link'])): ?>
                            <a href="<?= $result['link'] ?>" target="_blank" class="view-link">View</a>
                        <?php endif; ?>
                        <?php if (isset($result['message'])): ?>
                            <span class="error-message"><?= htmlspecialchars($result['message']) ?></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="form-group">
                <a href="index.php" class="btn">Upload More Files</a>
            </div>
        </div>
    </div>
</body>
</html>