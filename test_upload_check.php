<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/challenge_types.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/assigned_tests.php');
}
require_csrf();

$slug = $_POST['slug'] ?? '';
$stmt = db()->prepare('SELECT * FROM test_challenges WHERE slug = ? AND published = 1 AND challenge_type = ?');
$stmt->execute([$slug, 'file_upload']);
$challenge = $stmt->fetch();
if (!$challenge) {
    redirect('/assigned_tests.php');
}

$flagCheck = db()->prepare('SELECT id FROM test_user_flags WHERE user_id = ? AND test_challenge_id = ?');
$flagCheck->execute([$user['id'], $challenge['id']]);
if (!$flagCheck->fetch()) {
    redirect('/test_challenge.php?slug=' . urlencode($slug));
}

$filename = $_FILES['upload']['name'] ?? '';

// Intentionally naive — this is the bug: it checks whether an allowed
// extension appears ANYWHERE in the filename, not that the filename ends
// with it. The uploaded file itself is never saved or executed — only its
// name is inspected — so there's no real code-execution risk regardless of
// what gets uploaded.
$defaultExtensions = ['.jpg', '.jpeg', '.png', '.gif'];
$config = decode_challenge_config($challenge);
$allowed = config_get($config, 'allowed_extensions', $defaultExtensions);
if (!is_array($allowed) || !$allowed) {
    $allowed = $defaultExtensions;
}
$allowed = array_map('strtolower', $allowed);
$looksLikeImage = false;
foreach ($allowed as $ext) {
    if (stripos($filename, $ext) !== false) {
        $looksLikeImage = true;
        break;
    }
}

// Discard the actual uploaded content — PHP already wrote it to a temp file;
// we deliberately never move_uploaded_file() it anywhere web-accessible.
if (!empty($_FILES['upload']['tmp_name']) && is_uploaded_file($_FILES['upload']['tmp_name'])) {
    @unlink($_FILES['upload']['tmp_name']);
}

$endsWithAllowedExt = false;
foreach ($allowed as $ext) {
    if (strtolower(substr($filename, -strlen($ext))) === $ext) {
        $endsWithAllowedExt = true;
        break;
    }
}

if ($looksLikeImage && !$endsWithAllowedExt) {
    // Bypassed: an allowed extension appears in the name, but not at the end.
    $_SESSION[challenge_session_key('upload_unlocked', $challenge['id'], 'test')] = true;
} elseif ($looksLikeImage) {
    $_SESSION[challenge_session_key('upload_error', $challenge['id'], 'test')] = 'Upload accepted as a normal image — try tricking the filter instead.';
} else {
    $_SESSION[challenge_session_key('upload_error', $challenge['id'], 'test')] = 'Rejected: only image files are allowed.';
}

redirect('/test_challenge.php?slug=' . urlencode($slug));
