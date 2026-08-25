<?php
require_once __DIR__ . '/auth.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/dashboard.php');
}
require_csrf();

$slug = $_POST['slug'] ?? '';
$stmt = db()->prepare('SELECT * FROM challenges WHERE slug = ? AND published = 1 AND challenge_type = ?');
$stmt->execute([$slug, 'file_upload']);
$challenge = $stmt->fetch();
if (!$challenge) {
    redirect('/dashboard.php');
}

$flagCheck = db()->prepare('SELECT id FROM user_flags WHERE user_id = ? AND challenge_id = ?');
$flagCheck->execute([$user['id'], $challenge['id']]);
if (!$flagCheck->fetch()) {
    redirect('/challenge.php?slug=' . urlencode($slug));
}

$filename = $_FILES['upload']['name'] ?? '';

// Intentionally naive — this is the bug: it checks whether an allowed
// extension appears ANYWHERE in the filename, not that the filename ends
// with it, so "payload.php.jpg" slips through. The uploaded file itself is
// never saved or executed — only its name is inspected — so there's no real
// code-execution risk regardless of what gets uploaded.
$allowed = ['.jpg', '.jpeg', '.png', '.gif'];
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
    $_SESSION['upload_unlocked_' . $challenge['id']] = true;
} elseif ($looksLikeImage) {
    $_SESSION['upload_error_' . $challenge['id']] = 'Upload accepted as a normal image — try tricking the filter instead.';
} else {
    $_SESSION['upload_error_' . $challenge['id']] = 'Rejected: only image files are allowed.';
}

redirect('/challenge.php?slug=' . urlencode($slug));
