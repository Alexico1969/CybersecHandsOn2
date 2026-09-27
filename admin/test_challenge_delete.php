<?php
require_once __DIR__ . '/../auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/test_challenges.php');
}
require_csrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id) {
    db()->prepare('DELETE FROM test_challenges WHERE id = ?')->execute([$id]);
}

redirect('/admin/test_challenges.php');
