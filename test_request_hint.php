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

db()->prepare('UPDATE test_progress SET hint_used = 1 WHERE user_id = ? AND test_challenge_id = ? AND status != ?')
    ->execute([$user['id'], $challenge['id'], 'finished']);

redirect('/test_challenge.php?slug=' . urlencode($slug));
