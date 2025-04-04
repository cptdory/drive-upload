<?php
require_once __DIR__ . '/vendor/autoload.php';

session_start();

$client = new Google\Client();
$client->setAuthConfig('credentials.json');
$client->addScope(Google\Service\Drive::DRIVE_FILE);
$client->setRedirectUri('https://dorykeepswimming.online/oauth2callback.php');

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $_SESSION['access_token'] = $token;
    header('Location: ' . filter_var('https://dorykeepswimming.online/', FILTER_SANITIZE_URL));
} else {
    header('Location: ' . filter_var('https://dorykeepswimming.online/', FILTER_SANITIZE_URL));
}
?>