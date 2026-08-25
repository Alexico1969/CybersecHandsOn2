<?php
require_once __DIR__ . '/auth.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/dashboard.php');
}
require_csrf();

$slug = $_POST['slug'] ?? '';
$username = (string) ($_POST['username'] ?? '');
$password = (string) ($_POST['password'] ?? '');

$stmt = db()->prepare('SELECT * FROM challenges WHERE slug = ? AND published = 1 AND challenge_type = ?');
$stmt->execute([$slug, 'sql_injection']);
$challenge = $stmt->fetch();
if (!$challenge) {
    redirect('/dashboard.php');
}

$flagCheck = db()->prepare('SELECT id FROM user_flags WHERE user_id = ? AND challenge_id = ?');
$flagCheck->execute([$user['id'], $challenge['id']]);
if (!$flagCheck->fetch()) {
    redirect('/challenge.php?slug=' . urlencode($slug));
}

try {
    // A fresh, throwaway in-memory database per request — the vulnerable
    // query below can only ever touch this dummy table, never real data.
    $sandbox = new PDO('sqlite::memory:');
    $sandbox->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sandbox->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, password TEXT)');
    $dummyPassword = bin2hex(random_bytes(8));
    $sandbox->exec("INSERT INTO users (username, password) VALUES ('admin', '$dummyPassword')");

    // Intentionally vulnerable — this string concatenation is the bug the
    // challenge asks students to find and exploit.
    $query = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
    $row = $sandbox->query($query)->fetch();

    if ($row) {
        $_SESSION['sqli_unlocked_' . $challenge['id']] = true;
    } else {
        $_SESSION['sqli_error_' . $challenge['id']] = 'Invalid credentials.';
    }
} catch (Throwable $e) {
    $_SESSION['sqli_error_' . $challenge['id']] = 'Query error: ' . $e->getMessage();
}

redirect('/challenge.php?slug=' . urlencode($slug));
