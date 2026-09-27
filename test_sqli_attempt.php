<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/challenge_types.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/assigned_tests.php');
}
require_csrf();

$slug = $_POST['slug'] ?? '';
$username = (string) ($_POST['username'] ?? '');
$password = (string) ($_POST['password'] ?? '');

$stmt = db()->prepare('SELECT * FROM test_challenges WHERE slug = ? AND published = 1 AND challenge_type = ?');
$stmt->execute([$slug, 'sql_injection']);
$challenge = $stmt->fetch();
if (!$challenge) {
    redirect('/assigned_tests.php');
}

$flagCheck = db()->prepare('SELECT id FROM test_user_flags WHERE user_id = ? AND test_challenge_id = ?');
$flagCheck->execute([$user['id'], $challenge['id']]);
if (!$flagCheck->fetch()) {
    redirect('/test_challenge.php?slug=' . urlencode($slug));
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
        $_SESSION[challenge_session_key('sqli_unlocked', $challenge['id'], 'test')] = true;
    } else {
        $_SESSION[challenge_session_key('sqli_error', $challenge['id'], 'test')] = 'Invalid credentials.';
    }
} catch (Throwable $e) {
    $_SESSION[challenge_session_key('sqli_error', $challenge['id'], 'test')] = 'Query error: ' . $e->getMessage();
}

redirect('/test_challenge.php?slug=' . urlencode($slug));
