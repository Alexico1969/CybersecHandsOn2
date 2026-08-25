<?php
require_once __DIR__ . '/auth.php';

$state = $_GET['state'] ?? '';
$code = $_GET['code'] ?? '';

if (!hash_equals($_SESSION['csrf_token'] ?? '', $state)) {
    http_response_code(403);
    die('Login request expired or is invalid. <a href="' . h(SITE_URL) . '/login.php">Try again</a>.');
}

if (isset($_GET['error']) || !$code) {
    redirect('/index.php');
}

try {
    $profile = google_fetch_profile($code);
    $user = upsert_user_from_google($profile);

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];

    redirect('/dashboard.php');
} catch (Throwable $e) {
    http_response_code(502);
    die('Sign-in failed: ' . h($e->getMessage()));
}
