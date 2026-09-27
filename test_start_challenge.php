<?php
require_once __DIR__ . '/auth.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/assigned_tests.php');
}
require_csrf();

$slug = $_POST['slug'] ?? '';
$stmt = db()->prepare('SELECT * FROM test_challenges WHERE slug = ? AND published = 1');
$stmt->execute([$slug]);
$challenge = $stmt->fetch();
if (!$challenge) {
    redirect('/assigned_tests.php');
}

if ($user['role'] !== 'admin') {
    $assignStmt = db()->prepare('SELECT id FROM test_challenge_assignments WHERE user_id = ? AND test_challenge_id = ?');
    $assignStmt->execute([$user['id'], $challenge['id']]);
    if (!$assignStmt->fetch()) {
        redirect('/assigned_tests.php');
    }
}

db()->beginTransaction();

$progressStmt = db()->prepare('SELECT * FROM test_progress WHERE user_id = ? AND test_challenge_id = ?');
$progressStmt->execute([$user['id'], $challenge['id']]);
if (!$progressStmt->fetch()) {
    db()->prepare('INSERT INTO test_progress (user_id, test_challenge_id, status) VALUES (?, ?, ?)')
        ->execute([$user['id'], $challenge['id'], 'started']);
}

$flagStmt = db()->prepare('SELECT * FROM test_user_flags WHERE user_id = ? AND test_challenge_id = ?');
$flagStmt->execute([$user['id'], $challenge['id']]);
if (!$flagStmt->fetch()) {
    $flagValue = 'flag{' . slugify($user['name']) . '_' . bin2hex(random_bytes(3)) . '}';
    db()->prepare('INSERT INTO test_user_flags (user_id, test_challenge_id, flag_value) VALUES (?, ?, ?)')
        ->execute([$user['id'], $challenge['id'], $flagValue]);
}

db()->commit();

redirect('/test_challenge.php?slug=' . urlencode($slug));
