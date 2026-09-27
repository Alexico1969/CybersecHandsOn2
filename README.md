# HackLab (plain PHP + MySQL)

A self-contained CTF-style site for `alexicoo.nl`, built for plain shared
hosting — no Docker, no build step, upload the files as-is via FileZilla.
Students sign in with Google, work through browser-based challenges, and earn
points. Each student gets their own unique flag per challenge (e.g.
`flag{alex_7f3a91}` vs `flag{maria_c42d18}`), generated and stored server-side,
so copying a classmate's answer doesn't work even though the underlying
vulnerability is identical.

The 10 starter challenges are entirely self-contained (no external terminal
needed): HTML source inspection, cookie tampering, Base64/hex decoding,
Caesar/Vigenère ciphers, hidden URL parameters, custom HTTP headers, SQL
injection, and a file-upload filter bypass. Challenges that genuinely need a
real Linux/Python environment can set an optional external "terminal URL"
from the admin panel — the site just links out to it.

On top of that, admins can assign **test-challenges** — variants of the real
challenges with different secrets — to individual students, e.g. after a
solution has leaked around the classroom. See [Test-challenges](#test-challenges).

## What tracks what

- **users** — one row per student/admin, created on first Google sign-in.
- **challenges** — title, description, difficulty, points, which built-in
  mechanic renders it (`challenge_type`), optional hint, admin-only
  `solution`, optional `config` (JSON overriding the mechanic's secret),
  optional external terminal URL.
- **user_flags** — the unique flag generated for each (student, challenge)
  pair the first time they hit "Start challenge."
- **progress** — started/finished status, timestamps, attempts count, points
  actually awarded (hint cost subtracted if used).
- **flag_attempts** — full log of every submission, right or wrong.
- **test_challenges**, **test_challenge_assignments**, **test_user_flags**,
  **test_progress**, **test_flag_attempts** — the same idea for
  test-challenges, kept completely separate from the tables above.

## One-time setup

### 1. Create the database

In your WebReus panel: **Databases → Databases** → create a new database and
a database user with access to it. Note the database name, username,
password, and host (usually `localhost`).

Open **phpMyAdmin** (or whatever DB tool WebReus provides) for that database
and run [`schema.sql`](schema.sql). It creates every table (including the
test-challenge tables) and seeds the 10 starter challenges with their answer
keys. Optionally, also run [`seed_test_challenges.sql`](seed_test_challenges.sql)
to load 10 ready-made test-challenge variants, one per starter challenge.

Don't run the `migration_*.sql` files on a fresh install — `schema.sql`
already includes them. They're only for upgrading an older database (see
[Upgrading an existing install](#upgrading-an-existing-install)).

### 2. Google OAuth client

1. [Google Cloud Console](https://console.cloud.google.com/) → pick/create a
   project → **APIs & Services → Google Auth Platform**.
2. **Branding**: app name, support email.
3. **Audience**: User type **External**, then publish to production (basic
   sign-in scopes don't need Google's verification review).
4. **Clients → Create client → Web application**. Authorized redirect URI is
   `SITE_URL` + `/oauth_callback.php` — for the `/ctf` subfolder used below:
   ```
   https://alexicoo.nl/ctf/oauth_callback.php
   ```
   It must match exactly, or Google will reject the sign-in with a
   `redirect_uri_mismatch` error.
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
   showing the raw code. **Delete it from the server once confirmed.**
2. **`.htaccess` is actually being honored**: after uploading `app_secrets.php`
   and `.htaccess`, visit `app_secrets.php`'s URL directly — it must return
   "403 Forbidden", not a blank page and not the raw source. If it doesn't
   403, something is stopping `.htaccess` from taking effect (commonly
   `AllowOverride None` set at the server level) and this needs fixing
   before the site is safe to use with real credentials.

### 5. Upload

Via FileZilla, upload everything in this folder — including `app_secrets.php`
and `.htaccess` — into `web/ctf/` (or wherever `SITE_URL` points). You don't
need `config.sample.php`, the `.sql` files, or this `README.md` on the
server — `.htaccess` blocks direct access to those anyway, but there's no
reason to ship them.

Make sure the database step is done **before** the new files go live: the
nav bar on every page checks for assigned test-challenges, so if the
`test_*` tables don't exist yet, every page errors out.

Visit `https://alexicoo.nl/ctf/`, sign in with the Google account listed in
`ADMIN_EMAILS`, and you'll see **Admin** in the nav.

## Upgrading an existing install

If your database was created from an older `schema.sql`, run whichever of
these you haven't run yet, **in order**, in phpMyAdmin. Each `ALTER TABLE`
only succeeds once; if one errors because the column already exists, that
migration has already been applied.

1. [`migration_2_more_challenge_types.sql`](migration_2_more_challenge_types.sql)
   — extends `challenge_type` to all 10 mechanics and adds the 7 extra
   starter challenges.
2. [`migration_3_add_solution_field.sql`](migration_3_add_solution_field.sql)
   — adds the admin-only `solution` column, backfills answer keys for the
   10 starter challenges, and corrects the `avatar-upload` hint.
   Required: saving a challenge in the admin panel fails without it.
3. [`migration_4_test_challenges.sql`](migration_4_test_challenges.sql)
   — adds the `config` column and the five `test_*` tables. Required: every
   page fails without it (see step 5 above).
4. [`seed_test_challenges.sql`](seed_test_challenges.sql) — optional, the
   10 example test-challenge variants.

Then upload the updated files. Don't re-run the full `schema.sql` on an
existing database — it'll fail on duplicate slugs.

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

The **Solution** field is an admin-only answer key. It's never shown to
students.

To add a genuinely new *mechanic* beyond these (a downloadable-file forensics
challenge, a different crypto scheme, ...), a new `case` needs to be added
to `render_challenge_type()` in `challenge_types.php` — that's a code
change, not something the admin form alone can do, since each mechanic is
real PHP logic.

For challenges that need actual command-line/Python access, set the
**Terminal URL** field to point at whatever external environment you're
using for those — the challenge page will show an "Open terminal ↗" button
alongside its normal flag mechanic.

## Test-challenges

Test-challenges are variants of the real challenges, meant for when a
technique has leaked around the class and you want to check that a specific
student can really do it. They use the same mechanics, but a JSON `config`
can change the secret. Examples: a different cookie name or value, Caesar
shift, Vigenère keyword, URL parameter, header name or value, or allowed
upload extensions. With no config, a mechanic uses the same defaults as the
real challenge.

| `challenge_type`  | `config` keys                                   |
|-------------------|-------------------------------------------------|
| `cookie`          | `cookie_name`, `locked_value`, `unlock_value`   |
| `caesar`          | `shift`                                         |
| `vigenere`        | `keyword`                                       |
| `hidden_param`    | `param_name`, `param_value`                     |
| `custom_header`   | `header_name`, `header_value`                   |
| `file_upload`     | `allowed_extensions` (array, e.g. `[".png"]`)   |

`html_source`, `base64`, `hex`, and `sql_injection` have nothing to configure
yet, so their variants differ only in wording, not in the technique needed.

**Admin side** (**Admin** → sub-nav):
- **Test-challenges** — create/edit/delete variants, assign one to a
  student, and see or remove current assignments.
- **Test scores** — results for assigned test-challenges.

**Student side:** an **Assigned Tests** link in the nav shows the student's
assigned variants, with a red **!** badge while any are unfinished. Test
flags, hints, and attempts are tracked in the `test_*` tables only. Test
points are shown on **Test scores** and are **never** added to a student's
regular points total.

Files involved: `assigned_tests.php`, `test_challenge.php`,
`test_start_challenge.php`, `test_submit_flag.php`, `test_request_hint.php`,
`test_sqli_attempt.php`, `test_upload_check.php` (site root), and
`admin/test_*.php`.

## Security notes

- Every query against the real MySQL database goes through PDO prepared
  statements — no string-built SQL. The only deliberately injectable query
  is the SQL-injection challenge, which runs against a throwaway in-memory
  SQLite database.
- All admin/student form submissions are CSRF-protected via a per-session
  token.
- Flags are compared with `hash_equals()` (constant-time) to avoid timing
  side-channels.
- `app_secrets.php` (real secrets) is excluded from git via `.gitignore`,
  and `.htaccess` blocks direct HTTP access to it outright (see step 4
  above) — verify that block actually 403s before going live. `.htaccess`
  also blocks direct access to `.sql`/`.md` files and `config.sample.php`
  so the schema, answer keys, and setup docs aren't publicly browsable.
- Don't leave the PHP test file from step 4 on the server.
