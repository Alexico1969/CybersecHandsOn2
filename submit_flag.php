<?php
require_once __DIR__ . '/auth.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/dashboard.php');
}
require_csrf();

$slug = $_POST['slug'] ?? '';
$submitted = (string) ($_POST['flag'] ?? '');

$stmt = db()->prepare('SELECT * FROM challenges WHERE slug = ? AND published = 1');
$stmt->execute([$slug]);
$challenge = $stmt->fetch();
if (!$challenge) {
    redirect('/dashboard.php');
}

$flagStmt = db()->prepare('SELECT * FROM user_flags WHERE user_id = ? AND challenge_id = ?');
$flagStmt->execute([$user['id'], $challenge['id']]);
$userFlag = $flagStmt->fetch();

$progressStmt = db()->prepare('SELECT * FROM progress WHERE user_id = ? AND challenge_id = ?');
$progressStmt->execute([$user['id'], $challenge['id']]);
$progress = $progressStmt->fetch();

if (!$userFlag || !$progress) {
    // Nothing to check against — they haven't started this challenge yet.
    redirect('/challenge.php?slug=' . urlencode($slug));
}

$isCorrect = $submitted !== '' && verify_flag($submitted, $userFlag['flag_value']);

db()->prepare('INSERT INTO flag_attempts (user_id, challenge_id, submitted_value, was_correct) VALUES (?, ?, ?, ?)')
    ->execute([$user['id'], $challenge['id'], $submitted, $isCorrect ? 1 : 0]);

db()->prepare('UPDATE progress SET attempts = attempts + 1 WHERE id = ?')->execute([$progress['id']]);

if ($isCorrect && $progress['status'] !== 'finished') {
    $pointsAwarded = max(0, (int) $challenge['points'] - ($progress['hint_used'] ? (int) $challenge['hint_cost'] : 0));

    db()->beginTransaction();
    db()->prepare('UPDATE progress SET status = ?, finished_at = NOW(), points_awarded = ? WHERE id = ?')
        ->execute(['finished', $pointsAwarded, $progress['id']]);
    db()->prepare('UPDATE users SET points = points + ? WHERE id = ?')
        ->execute([$pointsAwarded, $user['id']]);
    db()->commit();

    flash_set('success', "Correct! +{$pointsAwarded} points.");
} elseif ($isCorrect) {
    flash_set('success', 'Already solved — nice work.');
} else {
    flash_set('error', "That's not the right flag — keep digging.");
}

redirect('/challenge.php?slug=' . urlencode($slug));
