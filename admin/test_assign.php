<?php
require_once __DIR__ . '/../auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/test_challenges.php');
}
require_csrf();

$userId = (int) ($_POST['user_id'] ?? 0);
$testChallengeId = (int) ($_POST['test_challenge_id'] ?? 0);
$admin = current_user();

if (!$userId || !$testChallengeId) {
    flash_set('error', 'Pick both a student and a test-challenge to assign.');
    redirect('/admin/test_challenges.php');
}

$userCheck = db()->prepare('SELECT id FROM users WHERE id = ?');
$userCheck->execute([$userId]);
$challengeCheck = db()->prepare('SELECT id FROM test_challenges WHERE id = ?');
$challengeCheck->execute([$testChallengeId]);

if (!$userCheck->fetch() || !$challengeCheck->fetch()) {
    flash_set('error', 'That student or test-challenge no longer exists.');
    redirect('/admin/test_challenges.php');
}

// Idempotent: the unique key on (user_id, test_challenge_id) means
// re-assigning something already assigned is a harmless no-op.
db()->prepare('INSERT IGNORE INTO test_challenge_assignments (user_id, test_challenge_id, assigned_by) VALUES (?, ?, ?)')
    ->execute([$userId, $testChallengeId, $admin['id']]);

flash_set('success', 'Assigned.');
redirect('/admin/test_challenges.php');
