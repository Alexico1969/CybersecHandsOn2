-- Run this once against your MySQL database (e.g. via phpMyAdmin in the
-- WebReus panel) before uploading the site.

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
('avatar-upload', 'Avatar Upload', 'A profile picture uploader that only accepts image files — checked by looking at the filename.', 'File upload', 'MEDIUM', 150, 'file_upload', 'The filter checks whether an allowed extension appears in the filename, not that the filename ends with it. What would "payload.php.jpg" look like to that check?', 30, 10, 1);
