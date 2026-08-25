<?php
/**
 * Each "challenge_type" is a small, self-contained vulnerability mechanic.
 * prepare_challenge_type() runs early (before any HTML output) for anything
 * that needs to touch cookies/headers; render_challenge_type() returns the
 * HTML shown on the challenge page.
 */

const CHALLENGE_TYPE_LABELS = [
    'html_source' => 'HTML source inspection',
    'cookie' => 'Cookie tampering',
    'base64' => 'Base64 decoding',
    'hex' => 'Hex decoding',
    'caesar' => 'Caesar cipher (ROT13)',
    'vigenere' => 'Vigenère cipher',
    'hidden_param' => 'Hidden URL parameter',
    'custom_header' => 'Custom HTTP header',
    'sql_injection' => 'SQL injection (login bypass)',
    'file_upload' => 'File upload filter bypass',
];

function prepare_challenge_type(string $type, string $flagValue): void {
    if ($type === 'cookie') {
        if (!isset($_COOKIE['hacklab_role'])) {
            setcookie('hacklab_role', 'guest', ['path' => '/', 'httponly' => false, 'samesite' => 'Lax']);
            $_COOKIE['hacklab_role'] = 'guest';
        }
    }
}

function render_challenge_type(string $type, string $flagValue, array $challenge): string {
    switch ($type) {
        case 'html_source':
            return render_html_source_challenge($flagValue);
        case 'cookie':
            return render_cookie_challenge($flagValue);
        case 'base64':
            return render_base64_challenge($flagValue);
        case 'hex':
            return render_hex_challenge($flagValue);
        case 'caesar':
            return render_caesar_challenge($flagValue);
        case 'vigenere':
            return render_vigenere_challenge($flagValue);
        case 'hidden_param':
            return render_hidden_param_challenge($flagValue);
        case 'custom_header':
            return render_custom_header_challenge($flagValue);
        case 'sql_injection':
            return render_sql_injection_challenge($flagValue, $challenge);
        case 'file_upload':
            return render_file_upload_challenge($flagValue, $challenge);
        default:
            return '<p class="muted">This challenge type isn\'t configured yet.</p>';
    }
}

function render_html_source_challenge(string $flagValue): string {
    // The flag only exists in an HTML comment — never in visible text.
    return <<<HTML
<div class="challenge-box">
  <p>Status: <strong>System nominal.</strong> No visible anomalies.</p>
  <!-- {$flagValue} -->
</div>
HTML;
}

function render_cookie_challenge(string $flagValue): string {
    $role = $_COOKIE['hacklab_role'] ?? 'guest';
    $isAdmin = $role === 'admin';

    if ($isAdmin) {
        return '<div class="challenge-box challenge-success">'
            . '<p>Welcome back, <strong>admin</strong>.</p>'
            . '<p>Flag: <code>' . h($flagValue) . '</code></p>'
            . '</div>';
    }

    return '<div class="challenge-box">'
        . '<p>You are logged in as: <strong>' . h($role) . '</strong></p>'
        . '<p class="muted">Only admins can see this page\'s real content.</p>'
        . '</div>';
}

function render_base64_challenge(string $flagValue): string {
    $encoded = base64_encode('The flag is: ' . $flagValue);
    return '<div class="challenge-box">'
        . '<p>Incoming transmission (encoding unknown):</p>'
        . '<pre class="encoded">' . h($encoded) . '</pre>'
        . '</div>';
}

function render_hex_challenge(string $flagValue): string {
    $encoded = bin2hex('The flag is: ' . $flagValue);
    return '<div class="challenge-box">'
        . '<p>A forensic memory dump turned up this fragment:</p>'
        . '<pre class="encoded">' . h($encoded) . '</pre>'
        . '<p class="muted">Every two characters is one byte.</p>'
        . '</div>';
}

function caesar_shift(string $text, int $shift): string {
    return preg_replace_callback('/[a-zA-Z]/', function ($m) use ($shift) {
        $c = $m[0];
        $base = ctype_upper($c) ? 65 : 97;
        return chr((ord($c) - $base + $shift + 26) % 26 + $base);
    }, $text);
}

function render_caesar_challenge(string $flagValue): string {
    $shift = 13; // ROT13 — classic and easy to recognize
    $encoded = caesar_shift('The flag is: ' . $flagValue, $shift);
    return '<div class="challenge-box">'
        . '<p>An old note, scrambled by a simple substitution:</p>'
        . '<pre class="encoded">' . h($encoded) . '</pre>'
        . '<p class="muted">Every letter has been shifted the same fixed amount through the alphabet.</p>'
        . '</div>';
}

function vigenere_encode(string $text, string $key): string {
    $key = strtoupper(preg_replace('/[^A-Za-z]/', '', $key));
    if ($key === '') {
        return $text;
    }
    $ki = 0;
    return preg_replace_callback('/[a-zA-Z]/', function ($m) use ($key, &$ki) {
        $c = $m[0];
        $base = ctype_upper($c) ? 65 : 97;
        $shift = ord($key[$ki % strlen($key)]) - 65;
        $ki++;
        return chr((ord($c) - $base + $shift) % 26 + $base);
    }, $text);
}

function render_vigenere_challenge(string $flagValue): string {
    $key = 'HACK';
    $encoded = vigenere_encode('The flag is: ' . $flagValue, $key);
    return '<div class="challenge-box">'
        . '<p>Intercepted transmission, encoded with a repeating keyword cipher:</p>'
        . '<pre class="encoded">' . h($encoded) . '</pre>'
        . '<p class="muted">The keyword is 4 letters long and directly relevant to this site.</p>'
        . '</div>';
}

function render_hidden_param_challenge(string $flagValue): string {
    $unlocked = ($_GET['debug'] ?? '') === 'true';

    if ($unlocked) {
        return '<div class="challenge-box challenge-success">'
            . '<p>Debug mode enabled.</p>'
            . '<p>Flag: <code>' . h($flagValue) . '</code></p>'
            . '</div>';
    }

    return '<div class="challenge-box">'
        . '<p>This staging page has a debug mode, but it\'s off right now.</p>'
        . '<p class="muted">Some apps read extra settings straight from the URL\'s query string.</p>'
        . '</div>';
}

function render_custom_header_challenge(string $flagValue): string {
    $header = strtolower($_SERVER['HTTP_X_DEBUG_MODE'] ?? '');
    $unlocked = $header === 'true';

    if ($unlocked) {
        return '<div class="challenge-box challenge-success">'
            . '<p>Custom header accepted.</p>'
            . '<p>Flag: <code>' . h($flagValue) . '</code></p>'
            . '</div>';
    }

    return '<div class="challenge-box">'
        . '<p>This endpoint expects an internal tooling header that your browser doesn\'t send by default.</p>'
        . '<p class="muted">A browser extension or a command-line HTTP client can add custom request headers — try <code>X-Debug-Mode</code>.</p>'
        . '</div>';
}

function render_sql_injection_challenge(string $flagValue, array $challenge): string {
    $sessionKey = 'sqli_unlocked_' . $challenge['id'];

    if (!empty($_SESSION[$sessionKey])) {
        return '<div class="challenge-box challenge-success">'
            . '<p>Access granted.</p>'
            . '<p>Flag: <code>' . h($flagValue) . '</code></p>'
            . '</div>';
    }

    $error = $_SESSION['sqli_error_' . $challenge['id']] ?? null;
    unset($_SESSION['sqli_error_' . $challenge['id']]);

    $html = '<div class="challenge-box">'
        . '<p>Internal portal login:</p>'
        . '<form method="post" action="' . h(SITE_URL) . '/sqli_attempt.php" class="admin-form">'
        . csrf_field()
        . '<input type="hidden" name="slug" value="' . h($challenge['slug']) . '">'
        . '<label>Username<input type="text" name="username" autocomplete="off"></label>'
        . '<label>Password<input type="text" name="password" autocomplete="off"></label>'
        . '<button type="submit" class="button">Log in</button>'
        . '</form>';

    if ($error) {
        $html .= '<p class="muted" style="margin-top:.75rem">' . h($error) . '</p>';
    }

    $html .= '</div>';
    return $html;
}

function render_file_upload_challenge(string $flagValue, array $challenge): string {
    $sessionKey = 'upload_unlocked_' . $challenge['id'];

    if (!empty($_SESSION[$sessionKey])) {
        return '<div class="challenge-box challenge-success">'
            . '<p>File accepted by the "image" filter.</p>'
            . '<p>Flag: <code>' . h($flagValue) . '</code></p>'
            . '</div>';
    }

    $error = $_SESSION['upload_error_' . $challenge['id']] ?? null;
    unset($_SESSION['upload_error_' . $challenge['id']]);

    $html = '<div class="challenge-box">'
        . '<p>Avatar upload — images only:</p>'
        . '<form method="post" action="' . h(SITE_URL) . '/upload_check.php" enctype="multipart/form-data">'
        . csrf_field()
        . '<input type="hidden" name="slug" value="' . h($challenge['slug']) . '">'
        . '<input type="file" name="upload" required>'
        . '<button type="submit" class="button" style="margin-top:.75rem">Upload</button>'
        . '</form>';

    if ($error) {
        $html .= '<p class="muted" style="margin-top:.75rem">' . h($error) . '</p>';
    }

    $html .= '</div>';
    return $html;
}
