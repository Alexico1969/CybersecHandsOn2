<?php

function h(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void {
    header('Location: ' . SITE_URL . $path);
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function require_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid or expired form submission. Go back and try again.');
    }
}

function normalize_flag(string $flag): string {
    return trim($flag);
}

function verify_flag(string $submitted, string $expected): bool {
    return hash_equals(normalize_flag($expected), normalize_flag($submitted));
}

function slugify(string $text): string {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-') ?: 'user';
}

function flash_set(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_take(): array {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}
