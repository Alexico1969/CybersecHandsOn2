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

db()->prepare('UPDATE progress SET hint_used = 1 WHERE user_id = ? AND challenge_id = ? AND status != ?')
    ->execute([$user['id'], $challenge['id'], 'finished']);

redirect('/challenge.php?slug=' . urlencode($slug));
