<?php
require_once __DIR__ . '/app_secrets.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
        if ($user === null) {
            // Session points at a user that no longer exists.
            session_destroy();
        }
    }
    return $user;
}

function require_login(): array {
    $user = current_user();
    if (!$user) {
        redirect('/index.php');
    }
    return $user;
}

function require_admin(): array {
    $user = require_login();
    if ($user['role'] !== 'admin') {
        redirect('/dashboard.php');
    }
    return $user;
}

function google_auth_url(): string {
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => SITE_URL . '/oauth_callback.php',
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => csrf_token(),
        'prompt' => 'select_account',
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/**
 * Exchanges an OAuth code for the Google profile, server-side over HTTPS —
 * the response is trusted directly rather than re-verified, since it never
 * passes through the browser.
 */
function google_fetch_profile(string $code): array {
    $tokenResponse = http_post_json('https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => SITE_URL . '/oauth_callback.php',
        'grant_type' => 'authorization_code',
    ]);

    if (empty($tokenResponse['access_token'])) {
        throw new RuntimeException('Google did not return an access token.');
    }

    $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $tokenResponse['access_token']],
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        throw new RuntimeException('Failed to reach Google userinfo endpoint: ' . curl_error($ch));
    }
    curl_close($ch);

    $profile = json_decode($body, true);
    if (empty($profile['sub']) || empty($profile['email'])) {
        throw new RuntimeException('Google userinfo response was missing required fields.');
    }
    return $profile;
}

function http_post_json(string $url, array $fields): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        throw new RuntimeException('Request to ' . $url . ' failed: ' . curl_error($ch));
    }
    curl_close($ch);
    $decoded = json_decode($body, true);
    return is_array($decoded) ? $decoded : [];
}

function admin_emails(): array {
    return array_filter(array_map('trim', explode(',', strtolower(ADMIN_EMAILS))));
}

function upsert_user_from_google(array $profile): array {
    $email = strtolower($profile['email']);
    $role = in_array($email, admin_emails(), true) ? 'admin' : 'student';

    $stmt = db()->prepare('SELECT * FROM users WHERE google_id = ?');
    $stmt->execute([$profile['sub']]);
    $existing = $stmt->fetch();

    if ($existing) {
        $newRole = $existing['role'] === 'admin' ? 'admin' : $role;
        $update = db()->prepare('UPDATE users SET name = ?, avatar_url = ?, email = ?, role = ? WHERE id = ?');
        $update->execute([$profile['name'] ?? $existing['name'], $profile['picture'] ?? null, $email, $newRole, $existing['id']]);
        $existing['name'] = $profile['name'] ?? $existing['name'];
        $existing['role'] = $newRole;
        return $existing;
    }

    $insert = db()->prepare('INSERT INTO users (google_id, email, name, avatar_url, role) VALUES (?, ?, ?, ?, ?)');
    $insert->execute([$profile['sub'], $email, $profile['name'] ?? $email, $profile['picture'] ?? null, $role]);

    $id = (int) db()->lastInsertId();
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}
