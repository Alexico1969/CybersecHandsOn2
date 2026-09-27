<?php
require_once __DIR__ . '/../auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/test_challenges.php');
}
require_csrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id) {
    // Only removes the assignment -- the student's test_progress/test_user_flags
    // rows (their in-progress attempt, if any) are left alone on purpose, so
    // re-assigning the same test-challenge later resumes where they left off.
    db()->prepare('DELETE FROM test_challenge_assignments WHERE id = ?')->execute([$id]);
}

redirect('/admin/test_challenges.php');
