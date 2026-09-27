-- Run this once against your MySQL database (e.g. via phpMyAdmin in the
-- WebReus panel) before uploading the site. This is the complete schema for a
-- fresh install -- it already includes everything from migration_2/3/4, so
-- do NOT run those migrations afterwards. (They are only for upgrading a
-- database created from an older version of this file.)

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  google_id VARCHAR(64) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  avatar_url VARCHAR(500) NULL,
  role ENUM('student','admin') NOT NULL DEFAULT 'student',
  points INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS challenges (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  category VARCHAR(100) NOT NULL,
  difficulty ENUM('EASY','MEDIUM','HARD') NOT NULL DEFAULT 'EASY',
  points INT NOT NULL DEFAULT 100,
  -- Which built-in challenge mechanic renders this one. See challenge_types.php.
  challenge_type ENUM(
    'html_source','cookie','base64','hex','caesar','vigenere',
    'hidden_param','custom_header','sql_injection','file_upload'
  ) NOT NULL,
  hint TEXT NULL,
  hint_cost INT NOT NULL DEFAULT 0,
  -- Admin-only written answer key. Never shown to students.
  solution TEXT NULL,
  -- Optional per-row JSON that overrides a mechanic's built-in secret (cookie
  -- name, cipher shift/keyword, ...). NULL = the defaults in challenge_types.php.
  config TEXT NULL,
  -- Optional: for future challenges that need a real terminal/sandbox
  -- (Linux command line, Python scripting) hosted somewhere else.
  terminal_url VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  published TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One unique, generated flag per (student, challenge) — created the first
-- time a student starts a challenge, then stable for the rest of the course.
CREATE TABLE IF NOT EXISTS user_flags (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  challenge_id INT UNSIGNED NOT NULL,
  flag_value VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_challenge (user_id, challenge_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS progress (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  challenge_id INT UNSIGNED NOT NULL,
  status ENUM('started','finished') NOT NULL DEFAULT 'started',
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  points_awarded INT NOT NULL DEFAULT 0,
  attempts INT NOT NULL DEFAULT 0,
  hint_used TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_user_challenge (user_id, challenge_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Full audit log of every flag submission, right or wrong.
CREATE TABLE IF NOT EXISTS flag_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  challenge_id INT UNSIGNED NOT NULL,
  submitted_value VARCHAR(255) NOT NULL,
  was_correct TINYINT(1) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO challenges (slug, title, description, category, difficulty, points, challenge_type, hint, hint_cost, sort_order, published)
VALUES
('view-source', 'Hidden in Plain Sight', 'This page looks empty, but your browser downloaded more than what''s rendered. Every flag on this site is unique to you, so find yours — don''t copy a classmate''s.\n\nTip: right-click the page and choose "View Page Source" (or press Ctrl+U).', 'HTML source inspection', 'EASY', 100, 'html_source', 'HTML comments look like <!-- this --> and never render on the page, but they''re still in the source.', 20, 1, 1),
('cookie-monster', 'Cookie Monster', 'This page treats you as a guest. Somewhere in your browser is a cookie deciding that — and cookies are just text you can edit yourself.', 'Cookies', 'EASY', 100, 'cookie', 'Open DevTools (F12) → Application/Storage tab → Cookies, and look for one that says "guest". What happens if it said something else?', 20, 2, 1),
('decode-me', 'Decode Me', 'A message arrived, but it looks like gibberish. It''s not encrypted — it''s just encoded. See if you recognize the alphabet it''s using.', 'Encoding', 'EASY', 100, 'base64', 'That alphabet of letters, digits, +, /, and = at the end is a strong hint. Try an online Base64 decoder, or the `base64 -d` command if you have a terminal handy.', 20, 3, 1),
('hex-dump', 'Memory Dump', 'A forensic tool pulled a fragment of memory off a compromised machine. It''s not text — or is it?', 'Forensics', 'EASY', 100, 'hex', 'Every byte of text is shown as two hex digits. Python''s bytes.fromhex(...) or any online hex-to-text converter will do it in one line.', 20, 4, 1),
('caesar-shift', 'The Old Cipher', 'A note found taped under a desk. Whoever wrote it wasn''t trying very hard to hide it — just shifted every letter by the same amount.', 'Cryptography', 'EASY', 100, 'caesar', 'This is ROT13 specifically — shifting by 13 twice gets you back to the original. Any ROT13 tool online will solve it instantly.', 20, 5, 1),
('keyword-cipher', 'Keyword Cipher', 'Another intercepted message, but this one resists a simple shift — the shift amount changes letter by letter, repeating a short keyword.', 'Cryptography', 'MEDIUM', 150, 'vigenere', 'This is a Vigenère cipher. The keyword is 4 letters and describes what this whole site is about.', 30, 6, 1),
('debug-mode', 'Staging Server', 'A staging page mentions a debug mode, currently switched off. Web apps sometimes read extra settings straight out of the URL.', 'HTTP requests', 'EASY', 100, 'hidden_param', 'Try adding a query string to the URL, like ?debug=true.', 20, 7, 1),
('internal-tooling', 'Internal Tooling', 'An internal dashboard checks for a specific request header before showing anything useful. Your regular browsing never sends it.', 'HTTP requests', 'MEDIUM', 150, 'custom_header', 'You''ll need a way to add a custom HTTP header to a request — a browser extension (like ModHeader) or a command-line tool like curl (curl -H "X-Debug-Mode: true" ...) both work.', 30, 8, 1),
('portal-login', 'Internal Portal', 'A login form for an internal portal. It builds its query by gluing your input straight into SQL — classic mistake.', 'SQL injection', 'MEDIUM', 200, 'sql_injection', 'A username like admin''-- (with two dashes and a trailing space) can comment out the rest of the query, including the password check.', 40, 9, 1),
('avatar-upload', 'Avatar Upload', 'A profile picture uploader that only accepts image files — checked by looking at the filename.', 'File upload', 'MEDIUM', 150, 'file_upload', 'The filter checks whether an allowed extension appears in the filename, not that the filename ends with it. What would "payload.jpg.php" look like to that check?', 30, 10, 1);

-- Admin-only answer keys for the 10 starter challenges.
UPDATE challenges SET solution = 'Right-click the page and choose "View Page Source" (or press Ctrl+U). The flag is written directly inside an HTML comment (<!-- ... -->), which never renders on the page but is still present in the downloaded source.' WHERE slug = 'view-source';
UPDATE challenges SET solution = 'Open DevTools (F12) -> Application/Storage tab -> Cookies, find the cookie named hacklab_role (currently "guest"), and edit its value to "admin", then reload. The page trusts the cookie''s value without verifying it server-side, so changing it unlocks the flag.' WHERE slug = 'cookie-monster';
UPDATE challenges SET solution = 'The text is Base64-encoded ("The flag is: <flag>"). Paste it into any Base64 decoder, or run: echo ''<text>'' | base64 -d' WHERE slug = 'decode-me';
UPDATE challenges SET solution = 'The dump is a hex-encoded string (two hex characters per byte) of "The flag is: <flag>". Decode it with Python''s bytes.fromhex(text).decode(), or any online hex-to-text converter.' WHERE slug = 'hex-dump';
UPDATE challenges SET solution = 'This is ROT13 (a Caesar cipher shifted by 13 letters). Running the text through any ROT13 tool (shifting by 13 again) recovers "The flag is: <flag>".' WHERE slug = 'caesar-shift';
UPDATE challenges SET solution = 'This is a Vigenere cipher using the 4-letter keyword HACK. Decrypt with any Vigenere decoder using that key to recover the flag.' WHERE slug = 'keyword-cipher';
UPDATE challenges SET solution = 'The page checks $_GET[''debug''] === ''true''. Add ?debug=true to the challenge URL, e.g. challenge.php?slug=debug-mode&debug=true, to unlock the flag.' WHERE slug = 'debug-mode';
UPDATE challenges SET solution = 'The page checks for a request header X-Debug-Mode: true. Send it with curl (curl -H "X-Debug-Mode: true" <url>) or a browser extension like ModHeader to unlock the flag.' WHERE slug = 'internal-tooling';
UPDATE challenges SET solution = 'The login query concatenates input directly into SQL: SELECT * FROM users WHERE username = ''$username'' AND password = ''$password''. A username of admin''-- (with a trailing space, so the rest of the query becomes a SQL comment) bypasses the password check entirely and unlocks the flag.' WHERE slug = 'portal-login';
UPDATE challenges SET solution = 'The filter only checks whether an allowed extension (.jpg/.jpeg/.png/.gif) appears ANYWHERE in the filename, not that the filename ends with it. A filename like payload.jpg.php bypasses it: it contains an allowed extension (.jpg) but ends in .php, which the filter never checks. No uploaded content is ever saved or executed -- only the filename is inspected.' WHERE slug = 'avatar-upload';

-- ---------------------------------------------------------------------
-- Test-challenges: admin-only variants of real challenges that can be
-- assigned to individual students. Completely separate from the tables
-- above -- test_progress.points_awarded is never added to users.points.
-- Load the 10 example variants with seed_test_challenges.sql (optional).
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS test_challenges (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  category VARCHAR(100) NOT NULL,
  difficulty ENUM('EASY','MEDIUM','HARD') NOT NULL DEFAULT 'EASY',
  points INT NOT NULL DEFAULT 100,
  challenge_type ENUM(
    'html_source','cookie','base64','hex','caesar','vigenere',
    'hidden_param','custom_header','sql_injection','file_upload'
  ) NOT NULL,
  hint TEXT NULL,
  hint_cost INT NOT NULL DEFAULT 0,
  solution TEXT NULL,
  config TEXT NULL,
  based_on_slug VARCHAR(100) NULL,
  terminal_url VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  published TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS test_challenge_assignments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  test_challenge_id INT UNSIGNED NOT NULL,
  assigned_by INT UNSIGNED NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_test_challenge (user_id, test_challenge_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (test_challenge_id) REFERENCES test_challenges(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS test_user_flags (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  test_challenge_id INT UNSIGNED NOT NULL,
  flag_value VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_test_challenge (user_id, test_challenge_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (test_challenge_id) REFERENCES test_challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS test_progress (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  test_challenge_id INT UNSIGNED NOT NULL,
  status ENUM('started','finished') NOT NULL DEFAULT 'started',
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  points_awarded INT NOT NULL DEFAULT 0,
  attempts INT NOT NULL DEFAULT 0,
  hint_used TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_user_test_challenge (user_id, test_challenge_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (test_challenge_id) REFERENCES test_challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS test_flag_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  test_challenge_id INT UNSIGNED NOT NULL,
  submitted_value VARCHAR(255) NOT NULL,
  was_correct TINYINT(1) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (test_challenge_id) REFERENCES test_challenges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
