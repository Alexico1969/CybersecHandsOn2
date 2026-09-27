<?php
require_once __DIR__ . '/auth.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/assigned_tests.php');
}
require_csrf();

$slug = $_POST['slug'] ?? '';
$submitted = (string) ($_POST['flag'] ?? '');

$stmt = db()->prepare('SELECT * FROM test_challenges WHERE slug = ? AND published = 1');
$stmt->execute([$slug]);
$challenge = $stmt->fetch();
if (!$challenge) {
    redirect('/assigned_tests.php');
}

$flagStmt = db()->prepare('SELECT * FROM test_user_flags WHERE user_id = ? AND test_challenge_id = ?');
$flagStmt->execute([$user['id'], $challenge['id']]);
$userFlag = $flagStmt->fetch();

$progressStmt = db()->prepare('SELECT * FROM test_progress WHERE user_id = ? AND test_challenge_id = ?');
$progressStmt->execute([$user['id'], $challenge['id']]);
$progress = $progressStmt->fetch();

if (!$userFlag || !$progress) {
    // Nothing to check against — they haven't started this test-challenge yet.
    redirect('/test_challenge.php?slug=' . urlencode($slug));
}

$isCorrect = $submitted !== '' && verify_flag($submitted, $userFlag['flag_value']);

db()->prepare('INSERT INTO test_flag_attempts (user_id, test_challenge_id, submitted_value, was_correct) VALUES (?, ?, ?, ?)')
    ->execute([$user['id'], $challenge['id'], $submitted, $isCorrect ? 1 : 0]);

db()->prepare('UPDATE test_progress SET attempts = attempts + 1 WHERE id = ?')->execute([$progress['id']]);

// Note: this intentionally never touches `users.points` or the real
// `progress` table — test-challenge results are tracked only in
// `test_progress`, visible solely to admins, and never affect a
// student's real score.
if ($isCorrect && $progress['status'] !== 'finished') {
    $pointsAwarded = max(0, (int) $challenge['points'] - ($progress['hint_used'] ? (int) $challenge['hint_cost'] : 0));

    db()->prepare('UPDATE test_progress SET status = ?, finished_at = NOW(), points_awarded = ? WHERE id = ?')
        ->execute(['finished', $pointsAwarded, $progress['id']]);

    flash_set('success', "Correct! (test only — not added to your challenge score)");
} elseif ($isCorrect) {
    flash_set('success', 'Already solved — nice work. (test only)');
} else {
    flash_set('error', "That's not the right flag — keep digging.");
}

redirect('/test_challenge.php?slug=' . urlencode($slug));
