-- Run this once against your existing database (phpMyAdmin -> SQL tab) to
-- pick up the 7 new challenge types and challenges added after the initial
-- setup. Safe to run even if some of this already exists, except the ALTER
-- TABLE — only run that once.

ALTER TABLE challenges MODIFY COLUMN challenge_type ENUM(
  'html_source','cookie','base64','hex','caesar','vigenere',
  'hidden_param','custom_header','sql_injection','file_upload'
) NOT NULL;

INSERT INTO challenges (slug, title, description, category, difficulty, points, challenge_type, hint, hint_cost, sort_order, published)
VALUES
('hex-dump', 'Memory Dump', 'A forensic tool pulled a fragment of memory off a compromised machine. It''s not text — or is it?', 'Forensics', 'EASY', 100, 'hex', 'Every byte of text is shown as two hex digits. Python''s bytes.fromhex(...) or any online hex-to-text converter will do it in one line.', 20, 4, 1),
('caesar-shift', 'The Old Cipher', 'A note found taped under a desk. Whoever wrote it wasn''t trying very hard to hide it — just shifted every letter by the same amount.', 'Cryptography', 'EASY', 100, 'caesar', 'This is ROT13 specifically — shifting by 13 twice gets you back to the original. Any ROT13 tool online will solve it instantly.', 20, 5, 1),
('keyword-cipher', 'Keyword Cipher', 'Another intercepted message, but this one resists a simple shift — the shift amount changes letter by letter, repeating a short keyword.', 'Cryptography', 'MEDIUM', 150, 'vigenere', 'This is a Vigenère cipher. The keyword is 4 letters and describes what this whole site is about.', 30, 6, 1),
('debug-mode', 'Staging Server', 'A staging page mentions a debug mode, currently switched off. Web apps sometimes read extra settings straight out of the URL.', 'HTTP requests', 'EASY', 100, 'hidden_param', 'Try adding a query string to the URL, like ?debug=true.', 20, 7, 1),
('internal-tooling', 'Internal Tooling', 'An internal dashboard checks for a specific request header before showing anything useful. Your regular browsing never sends it.', 'HTTP requests', 'MEDIUM', 150, 'custom_header', 'You''ll need a way to add a custom HTTP header to a request — a browser extension (like ModHeader) or a command-line tool like curl (curl -H "X-Debug-Mode: true" ...) both work.', 30, 8, 1),
('portal-login', 'Internal Portal', 'A login form for an internal portal. It builds its query by gluing your input straight into SQL — classic mistake.', 'SQL injection', 'MEDIUM', 200, 'sql_injection', 'A username like admin''-- (with two dashes and a trailing space) can comment out the rest of the query, including the password check.', 40, 9, 1),
('avatar-upload', 'Avatar Upload', 'A profile picture uploader that only accepts image files — checked by looking at the filename.', 'File upload', 'MEDIUM', 150, 'file_upload', 'The filter checks whether an allowed extension appears in the filename, not that the filename ends with it. What would "payload.php.jpg" look like to that check?', 30, 10, 1);
