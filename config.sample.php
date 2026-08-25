<?php
// Copy this file to app_secrets.php, fill in the values below, and upload
// app_secrets.php alongside the rest of the site's PHP files. .htaccess
// blocks any direct HTTP request to it (see the "app_secrets.php" block in
// .htaccess) — verify that actually returns 403 before relying on it.
// app_secrets.php holds real secrets, so it's listed in .gitignore and
// should never be committed.

// --- Site ---
// Exactly where this site lives, no trailing slash. If you upload it into a
// subfolder (e.g. https://alexicoo.nl/ctf), include that path here too.
define('SITE_URL', 'https://alexicoo.nl');

// --- Database ---
// From your WebReus hosting panel -> Databases.
define('DB_HOST', 'localhost');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');

// --- Google OAuth ---
// From Google Cloud Console -> APIs & Services -> Google Auth Platform -> Clients.
// Authorized redirect URI must be exactly: SITE_URL . '/oauth_callback.php'
define('GOOGLE_CLIENT_ID', '');
define('GOOGLE_CLIENT_SECRET', '');

// --- Admins ---
// Comma-separated Google account emails to promote to admin on first sign-in.
define('ADMIN_EMAILS', 'you@gmail.com');
