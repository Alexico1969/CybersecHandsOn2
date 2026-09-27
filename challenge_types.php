<?php
/**
 * Each "challenge_type" is a small, self-contained vulnerability mechanic.
 * prepare_challenge_type() runs early (before any HTML output) for anything
 * that needs to touch cookies/headers; render_challenge_type() returns the
 * HTML shown on the challenge page.
 *
 * Some mechanics take a per-row `config` (JSON, decoded via
 * decode_challenge_config()) so the same mechanic can use a different secret
 * for different rows -- e.g. a test-challenge variant with a different
 * Vigenère keyword than the real challenge it's based on. A row with no
 * config (every real challenge today) falls back to the original hardcoded
 * defaults below, so existing behavior is unchanged.
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

/**
 * Decodes a challenge/test_challenge row's `config` column (JSON text) into
 * an array. Returns [] for null/empty/invalid config -- callers then fall
 * back to their own hardcoded defaults, so a row with no config behaves
 * exactly as before this field existed.
 */
function decode_challenge_config(array $challenge): array {
    $raw = $challenge['config'] ?? null;
    if (!$raw) {
        return [];
    }
    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Reads one key out of a decoded config array, falling back to $default
 * when the key is missing OR is an empty string -- an admin form field left
 * blank means "use the default", not "the secret is an empty string".
 */
function config_get(array $config, string $key, $default) {
    if (!isset($config[$key]) || $config[$key] === '') {
        return $default;
    }
    return $config[$key];
}

/** Converts a header name like "X-Debug-Mode" to its $_SERVER key. */
function header_config_key(string $headerName): string {
    return 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));
}

/**
 * Builds the $_SESSION key used for challenge types (sql_injection,
 * file_upload) that track unlock state server-side rather than checking a
 * request directly. $track is 'real' for the live `challenges` table or
 * 'test' for `test_challenges' -- since both tables have their own
 * independent auto-increment id space, a real challenge and a test-challenge
 * can share the same numeric id, so the track prefix keeps their session
 * state from colliding. The action endpoints that WRITE these keys
 * (sqli_attempt.php / test_sqli_attempt.php, upload_check.php /
 * test_upload_check.php) must build the exact same key via this function.
 */
function challenge_session_key(string $prefix, $challengeId, string $track = 'real'): string {
    return ($track === 'test' ? 'test_' : '') . $prefix . '_' . $challengeId;
}

function prepare_challenge_type(string $type, string $flagValue, array $challenge): void {
    if ($type === 'cookie') {
        $config = decode_challenge_config($challenge);
        $cookieName = (string) config_get($config, 'cookie_name', 'hacklab_role');
        $lockedValue = (string) config_get($config, 'locked_value', 'guest');
        if (!isset($_COOKIE[$cookieName])) {
            setcookie($cookieName, $lockedValue, ['path' => '/', 'httponly' => false, 'samesite' => 'Lax']);
            $_COOKIE[$cookieName] = $lockedValue;
        }
    }
}

function render_challenge_type(string $type, string $flagValue, array $challenge, string $track = 'real'): string {
    $config = decode_challenge_config($challenge);
    switch ($type) {
        case 'html_source':
            return render_html_source_challenge($flagValue);
        case 'cookie':
            return render_cookie_challenge($flagValue, $config);
        case 'base64':
            return render_base64_challenge($flagValue);
        case 'hex':
            return render_hex_challenge($flagValue);
        case 'caesar':
            return render_caesar_challenge($flagValue, $config);
        case 'vigenere':
            return render_vigenere_challenge($flagValue, $config);
        case 'hidden_param':
            return render_hidden_param_challenge($flagValue, $config);
        case 'custom_header':
            return render_custom_header_challenge($flagValue, $config);
        case 'sql_injection':
            return render_sql_injection_challenge($flagValue, $challenge, $track);
        case 'file_upload':
            return render_file_upload_challenge($flagValue, $challenge, $track);
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

function render_cookie_challenge(string $flagValue, array $config = []): string {
    $cookieName = (string) config_get($config, 'cookie_name', 'hacklab_role');
    $lockedValue = (string) config_get($config, 'locked_value', 'guest');
    $unlockValue = (string) config_get($config, 'unlock_value', 'admin');

    $role = $_COOKIE[$cookieName] ?? $lockedValue;
    $isAdmin = $role === $unlockValue;

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

function render_caesar_challenge(string $flagValue, array $config = []): string {
    $shift = (int) config_get($config, 'shift', 13); // 13 = ROT13, classic and easy to recognize
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

function render_vigenere_challenge(string $flagValue, array $config = []): string {
    $key = (string) config_get($config, 'keyword', 'HACK');
    $encoded = vigenere_encode('The flag is: ' . $flagValue, $key);

    // Preserve the exact original hint for the real (default-keyword)
    // challenge; a configured variant gets a generic length-only hint so it
    // doesn't falsely claim a custom keyword is "relevant to this site".
    $usingDefaultKeyword = !isset($config['keyword']) || $config['keyword'] === '';
    $hintText = $usingDefaultKeyword
        ? 'The keyword is 4 letters long and directly relevant to this site.'
        : 'The keyword is ' . strlen(preg_replace('/[^A-Za-z]/', '', $key)) . ' letters long.';

    return '<div class="challenge-box">'
        . '<p>Intercepted transmission, encoded with a repeating keyword cipher:</p>'
        . '<pre class="encoded">' . h($encoded) . '</pre>'
        . '<p class="muted">' . h($hintText) . '</p>'
        . '</div>';
}

function render_hidden_param_challenge(string $flagValue, array $config = []): string {
    $paramName = (string) config_get($config, 'param_name', 'debug');
    $paramValue = (string) config_get($config, 'param_value', 'true');
    $unlocked = ($_GET[$paramName] ?? '') === $paramValue;

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

function render_custom_header_challenge(string $flagValue, array $config = []): string {
    $headerName = (string) config_get($config, 'header_name', 'X-Debug-Mode');
    $headerValue = (string) config_get($config, 'header_value', 'true');

    $received = strtolower($_SERVER[header_config_key($headerName)] ?? '');
    $unlocked = $received === strtolower($headerValue);

    if ($unlocked) {
        return '<div class="challenge-box challenge-success">'
            . '<p>Custom header accepted.</p>'
            . '<p>Flag: <code>' . h($flagValue) . '</code></p>'
            . '</div>';
    }

    // Preserve the exact original hint (which names the header) for the
    // real challenge; a configured variant doesn't leak its header name in
    // this generic box -- the admin's own `hint` field can reveal it instead.
    $usingDefaultHeader = !isset($config['header_name']) || $config['header_name'] === '';
    $hintText = $usingDefaultHeader
        ? 'A browser extension or a command-line HTTP client can add custom request headers — try <code>X-Debug-Mode</code>.'
        : 'A browser extension or a command-line HTTP client can add custom request headers to a request.';

    return '<div class="challenge-box">'
        . '<p>This endpoint expects an internal tooling header that your browser doesn\'t send by default.</p>'
        . '<p class="muted">' . $hintText . '</p>'
        . '</div>';
}

function render_sql_injection_challenge(string $flagValue, array $challenge, string $track = 'real'): string {
    $sessionKey = challenge_session_key('sqli_unlocked', $challenge['id'], $track);

    if (!empty($_SESSION[$sessionKey])) {
        return '<div class="challenge-box challenge-success">'
            . '<p>Access granted.</p>'
            . '<p>Flag: <code>' . h($flagValue) . '</code></p>'
            . '</div>';
    }

    $errorKey = challenge_session_key('sqli_error', $challenge['id'], $track);
    $error = $_SESSION[$errorKey] ?? null;
    unset($_SESSION[$errorKey]);

    $attemptEndpoint = $track === 'test' ? '/test_sqli_attempt.php' : '/sqli_attempt.php';
    $html = '<div class="challenge-box">'
        . '<p>Internal portal login:</p>'
        . '<form method="post" action="' . h(SITE_URL) . $attemptEndpoint . '" class="admin-form">'
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

function render_file_upload_challenge(string $flagValue, array $challenge, string $track = 'real'): string {
    $sessionKey = challenge_session_key('upload_unlocked', $challenge['id'], $track);

    if (!empty($_SESSION[$sessionKey])) {
        return '<div class="challenge-box challenge-success">'
            . '<p>File accepted by the "image" filter.</p>'
            . '<p>Flag: <code>' . h($flagValue) . '</code></p>'
            . '</div>';
    }

    $errorKey = challenge_session_key('upload_error', $challenge['id'], $track);
    $error = $_SESSION[$errorKey] ?? null;
    unset($_SESSION[$errorKey]);

    $uploadEndpoint = $track === 'test' ? '/test_upload_check.php' : '/upload_check.php';
    $html = '<div class="challenge-box">'
        . '<p>Avatar upload — images only:</p>'
        . '<form method="post" action="' . h(SITE_URL) . $uploadEndpoint . '" enctype="multipart/form-data">'
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
