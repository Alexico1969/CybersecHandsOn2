<?php
require_once __DIR__ . '/auth.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/dashboard.php');
}
require_csrf();

$slug = $_POST['slug'] ?? '';
$stmt = db()->prepare('SELECT * FROM challenges WHERE slug = ? AND published = 1');
$stmt->execute([$slug]);
$challenge = $stmt->fetch();
if (!$challenge) {
    redirect('/dashboard.php');
}

db()->beginTransaction();

$progressStmt = db()->prepare('SELECT * FROM progress WHERE user_id = ? AND challenge_id = ?');
$progressStmt->execute([$user['id'], $challenge['id']]);
if (!$progressStmt->fetch()) {
    db()->prepare('INSERT INTO progress (user_id, challenge_id, status) VALUES (?, ?, ?)')
        ->execute([$user['id'], $challenge['id'], 'started']);
}

$flagStmt = db()->prepare('SELECT * FROM user_flags WHERE user_id = ? AND challenge_id = ?');
$flagStmt->execute([$user['id'], $challenge['id']]);
if (!$flagStmt->fetch()) {
    $flagValue = 'flag{' . slugify($user['name']) . '_' . bin2hex(random_bytes(3)) . '}';
    db()->prepare('INSERT INTO user_flags (user_id, challenge_id, flag_value) VALUES (?, ?, ?)')
        ->execute([$user['id'], $challenge['id'], $flagValue]);
}

db()->commit();

redirect('/challenge.php?slug=' . urlencode($slug));
