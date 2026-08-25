# HackLab (plain PHP + MySQL)

A self-contained CTF-style site for `alexicoo.nl`, built for plain shared
hosting — no Docker, no build step, upload the files as-is via FileZilla.
Students sign in with Google, work through browser-based challenges, and earn
points. Each student gets their own unique flag per challenge (e.g.
`flag{alex_7f3a91}` vs `flag{maria_c42d18}`), generated and stored server-side,
so copying a classmate's answer doesn't work even though the underlying
vulnerability is identical.

The first 3 challenges are entirely self-contained (no external terminal
needed): HTML source inspection, cookie tampering, and Base64 decoding. Later
challenges that genuinely need a real Linux/Python environment can set an
optional external "terminal URL" from the admin panel — the site just links
out to it.

## What tracks what

- **users** — one row per student/admin, created on first Google sign-in.
- **challenges** — title, description, difficulty, points, which built-in
  mechanic renders it (`challenge_type`), optional hint, optional external
  terminal URL.
- **user_flags** — the unique flag generated for each (student, challenge)
  pair the first time they hit "Start challenge."
- **progress** — started/finished status, timestamps, attempts count, points
  actually awarded (hint cost subtracted if used).
- **flag_attempts** — full log of every submission, right or wrong.

## One-time setup

### 1. Create the database

In your WebReus panel: **Databases → Databases** → create a new database and
a database user with access to it. Note the database name, username,
password, and host (usually `localhost`).

Open **phpMyAdmin** (or whatever DB tool WebReus provides) for that database
and run [`schema.sql`](schema.sql) — this creates the tables and seeds the 3
starter challenges.

### 2. Google OAuth client

1. [Google Cloud Console](https://console.cloud.google.com/) → pick/create a
   project → **APIs & Services → Google Auth Platform**.
2. **Branding**: app name, support email.
3. **Audience**: User type **External**, then publish to production (basic
   sign-in scopes don't need Google's verification review).
4. **Clients → Create client → Web application**. Authorized redirect URI:
   ```
   https://alexicoo.nl/oauth_callback.php
   ```
   (adjust the path if you upload into a subfolder instead of the domain
   root — see `SITE_URL` below.)
5. Copy the Client ID and Client Secret.

### 3. HTTPS

Make sure `alexicoo.nl` has an SSL certificate enabled in the WebReus panel
(usually a free Let's Encrypt cert, one click). Google OAuth requires HTTPS
for non-localhost redirect URIs, and the session cookie is set with the
`Secure` flag, so the site won't log anyone in over plain HTTP.

### 4. Configure secrets

Copy `config.sample.php` to `app_secrets.php` and fill in:

```php
define('SITE_URL', 'https://alexicoo.nl/ctf');   // no trailing slash
define('DB_HOST', 'localhost');
define('DB_NAME', '...');
define('DB_USER', '...');
define('DB_PASS', '...');
define('GOOGLE_CLIENT_ID', '...');
define('GOOGLE_CLIENT_SECRET', '...');
define('ADMIN_EMAILS', 'you@gmail.com');     // comma-separated
```

`app_secrets.php` lives alongside the other PHP files in `web/ctf/` — on
some hosts FTP access is jailed to the document root, so there's no "outside
the web folder" to put it. Instead, `.htaccess` explicitly blocks any direct
HTTP request to `app_secrets.php` (`Require all denied`), which Apache
enforces before it even decides whether to execute or serve the file. That
holds regardless of any PHP-handling quirk on the host's end — including
whatever caused an earlier version of this file (then named `config.php`)
to be served as raw text instead of executed, leaking its contents.

Before trusting any of this with real credentials, verify two things on the
live server:

1. **PHP actually executes** where you think it does: upload a throwaway
   file containing only `<?php echo 'PHP is working: ' . PHP_VERSION;` into
   `web/ctf/`, visit its URL, and confirm it prints the version rather than
   showing the raw code. Delete it once confirmed.
2. **`.htaccess` is actually being honored**: after uploading `app_secrets.php`
   and `.htaccess`, visit `app_secrets.php`'s URL directly — it must return
   "403 Forbidden", not a blank page and not the raw source. If it doesn't
   403, something is stopping `.htaccess` from taking effect (commonly
   `AllowOverride None` set at the server level) and this needs fixing
   before the site is safe to use with real credentials.

### 5. Upload

Via FileZilla, upload everything in this folder — including `app_secrets.php`
and `.htaccess` — into `web/ctf/` (or wherever `SITE_URL` points). You don't
need `config.sample.php`,
  `schema.sql`, or this `README.md` on the server — `.htaccess` blocks
  direct access to those anyway, but there's no reason to ship them.

Visit `https://alexicoo.nl/ctf/`, sign in with the Google account listed in
`ADMIN_EMAILS`, and you'll see **Admin** in the nav.

## Adding challenges later

From **Admin → Add challenge**, pick one of the built-in challenge types —
each is a small PHP function in `challenge_types.php` that decides how that
student's unique flag gets surfaced:

- `html_source` — hidden in an HTML comment
- `cookie` — gated behind a cookie value
- `base64` / `hex` — encoded, needs decoding
- `caesar` / `vigenere` — classic ciphers
- `hidden_param` — gated behind a URL query parameter
- `custom_header` — gated behind a custom HTTP request header
- `sql_injection` — a login form vulnerable to classic SQLi, checked against
  a throwaway in-memory SQLite database created fresh per attempt (never
  your real MySQL data, so injection payloads can't reach anything else)
- `file_upload` — a simulated upload filter that only inspects the
  filename, never saves or executes the uploaded file's actual content

To add a genuinely new *mechanic* beyond these (a downloadable-file forensics
challenge, a different crypto scheme, ...), a new `case` needs to be added
to `render_challenge_type()` in `challenge_types.php` — that's a code
change, not something the admin form alone can do, since each mechanic is
real PHP logic. Ask for a new one to be added any time you want to expand
further.

If you're adding this to an already-running install, run
[`migration_2_more_challenge_types.sql`](migration_2_more_challenge_types.sql)
in phpMyAdmin once (it extends the `challenge_type` column and adds the 7
example challenges) — don't re-run the full `schema.sql`, it'll fail on the
duplicate slugs from the original 3. Also make sure `sqli_attempt.php` and
`upload_check.php` are uploaded alongside everything else — they're the
POST handlers the SQL-injection and file-upload challenges submit to.

For challenges that need actual command-line/Python access, set the
**Terminal URL** field to point at whatever external environment you're
using for those — the challenge page will show an "Open terminal ↗" button
alongside its normal flag mechanic.

## Security notes

- Every SQL query goes through PDO prepared statements — no string-built SQL.
- All admin/student form submissions are CSRF-protected via a per-session
  token.
- Flags are compared with `hash_equals()` (constant-time) to avoid timing
  side-channels.
- `app_secrets.php` (real secrets) is excluded from git via `.gitignore`,
  and `.htaccess` blocks direct HTTP access to it outright (see step 4
  above) — verify that block actually 403s before going live. `.htaccess`
  also blocks direct access to `.sql`/`.md` files and `config.sample.php`
  so the schema and setup docs aren't publicly browsable either.
