<?php
require_once __DIR__ . '/../auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/index.php');
}
require_csrf();

$id = (int) ($_POST['id'] ?? 0);
if ($id) {
    db()->prepare('DELETE FROM challenges WHERE id = ?')->execute([$id]);
}

redirect('/admin/index.php');
