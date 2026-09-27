-- Ten test-challenge variants, one per real challenge, to assign to
-- individual students after a technique leaks classroom-wide.
-- Run this in phpMyAdmin (Import tab) AFTER migration_3 and migration_4 have
-- both been applied. Safe to re-run only if you first delete the rows it
-- creates -- slugs are UNIQUE, so running it twice will error on duplicates
-- rather than silently double-inserting.

INSERT INTO test_challenges
  (slug, title, category, description, difficulty, points, challenge_type, hint, hint_cost, solution, config, based_on_slug, sort_order, published)
VALUES

('view-source-variant', 'Hidden in Plain Sight (Variant)', 'HTML source inspection',
 'This page looks empty, but your browser downloaded more than what''s rendered. Every flag on this site is unique to you, so find yours -- don''t copy a classmate''s.\n\nTip: right-click the page and choose "View Page Source" (or press Ctrl+U).',
 'EASY', 100, 'html_source',
 'HTML comments look like <!-- this --> and never render on the page, but they''re still in the source.',
 20,
 'Right-click the page and choose "View Page Source" (or press Ctrl+U). The flag is written directly inside an HTML comment (<!-- ... -->), which never renders on the page but is still present in the downloaded source.',
 NULL, 'view-source', 1, 1),

('cookie-monster-variant', 'Cookie Monster (Variant)', 'Cookies',
 'This page treats you as a visitor. Somewhere in your browser is a cookie deciding that -- and cookies are just text you can edit yourself.',
 'EASY', 100, 'cookie',
 'Open DevTools (F12) -> Application/Storage tab -> Cookies, and look for one related to access level. What happens if it said something else?',
 20,
 'Open DevTools (F12) -> Application/Storage tab -> Cookies, find the cookie named access_level (currently "visitor"), and edit its value to "staff", then reload. The page trusts the cookie''s value without verifying it server-side, so changing it unlocks the flag.',
 '{"cookie_name": "access_level", "locked_value": "visitor", "unlock_value": "staff"}', 'cookie-monster', 2, 1),

('decode-me-variant', 'Decode Me (Variant)', 'Encoding',
 'A different message arrived, but it looks like gibberish too. It''s not encrypted -- it''s just encoded. See if you recognize the alphabet it''s using.',
 'EASY', 100, 'base64',
 'That alphabet of letters, digits, +, /, and = at the end is a strong hint. Try an online Base64 decoder, or the `base64 -d` command if you have a terminal handy.',
 20,
 'The text is Base64-encoded ("The flag is: <flag>"). Paste it into any Base64 decoder, or run: echo ''<text>'' | base64 -d',
 NULL, 'decode-me', 3, 1),

('hex-dump-variant', 'Memory Dump (Variant)', 'Forensics',
 'A different forensic tool pulled a fragment of memory off a compromised machine. It''s not text -- or is it?',
 'EASY', 100, 'hex',
 'Every byte of text is shown as two hex digits. Python''s bytes.fromhex(...) or any online hex-to-text converter will do it in one line.',
 20,
 'The dump is a hex-encoded string (two hex characters per byte) of "The flag is: <flag>". Decode it with Python''s bytes.fromhex(text).decode(), or any online hex-to-text converter.',
 NULL, 'hex-dump', 4, 1),

('caesar-shift-variant', 'The Old Cipher (Variant)', 'Cryptography',
 'Another note found taped under a desk. Whoever wrote it wasn''t trying very hard to hide it -- just shifted every letter by the same amount (not the usual amount, though).',
 'EASY', 100, 'caesar',
 'This is a Caesar cipher, but NOT the usual ROT13 shift -- try a few different shift amounts.',
 20,
 'This is a Caesar cipher shifted by 7 letters (not the classic ROT13/13-letter shift). Running the text through a Caesar-cipher tool with a shift of 7 in the decrypt direction recovers "The flag is: <flag>".',
 '{"shift": 7}', 'caesar-shift', 5, 1),

('keyword-cipher-variant', 'Keyword Cipher (Variant)', 'Cryptography',
 'Another intercepted message, but this one resists a simple shift -- the shift amount changes letter by letter, repeating a short keyword (a different one than usual).',
 'MEDIUM', 150, 'vigenere',
 'This is a Vigenere cipher. The keyword is 4 letters long.',
 30,
 'This is a Vigenere cipher using the 4-letter keyword SAFE. Decrypt with any Vigenere decoder using that key to recover the flag.',
 '{"keyword": "SAFE"}', 'keyword-cipher', 6, 1),

('debug-mode-variant', 'Staging Server (Variant)', 'HTTP requests',
 'A different staging page mentions a preview mode, currently switched off. Web apps sometimes read extra settings straight out of the URL.',
 'EASY', 100, 'hidden_param',
 'Try adding a query string to the URL -- but this one isn''t called "debug".',
 20,
 'The page checks $_GET[''preview''] === ''on''. Add ?preview=on to the challenge URL to unlock the flag.',
 '{"param_name": "preview", "param_value": "on"}', 'debug-mode', 7, 1),

('internal-tooling-variant', 'Internal Tooling (Variant)', 'HTTP requests',
 'Another internal dashboard checks for a specific request header before showing anything useful. Your regular browsing never sends it.',
 'MEDIUM', 150, 'custom_header',
 'You''ll need a way to add a custom HTTP header to a request -- a browser extension (like ModHeader) or a command-line tool like curl both work. This one isn''t X-Debug-Mode though.',
 30,
 'The page checks for a request header X-Preview-Mode: enabled. Send it with curl (curl -H "X-Preview-Mode: enabled" <url>) or a browser extension like ModHeader to unlock the flag.',
 '{"header_name": "X-Preview-Mode", "header_value": "enabled"}', 'internal-tooling', 8, 1),

('portal-login-variant', 'Internal Portal (Variant)', 'SQL injection',
 'A different login form for an internal portal. It builds its query by gluing your input straight into SQL -- same classic mistake as before.',
 'MEDIUM', 200, 'sql_injection',
 'A username like admin''-- (with two dashes and a trailing space) can comment out the rest of the query, including the password check.',
 40,
 'The login query concatenates input directly into SQL: SELECT * FROM users WHERE username = ''$username'' AND password = ''$password''. A username of admin''-- (with a trailing space, so the rest of the query becomes a SQL comment) bypasses the password check entirely and unlocks the flag. Note: this variant currently uses the identical bypass as the real challenge -- SQL injection isn''t parameterizable yet, so this one is a weaker anti-cheat variant than the others until that''s built.',
 NULL, 'portal-login', 9, 1),

('avatar-upload-variant', 'Avatar Upload (Variant)', 'File upload',
 'A different profile picture uploader that only accepts image files -- checked by looking at the filename.',
 'MEDIUM', 150, 'file_upload',
 'The filter checks whether an allowed extension appears in the filename, not that the filename ends with it. This uploader only accepts .png and .gif though -- what would a bypass filename look like here?',
 30,
 'The filter only checks whether an allowed extension (.png/.gif) appears ANYWHERE in the filename, not that the filename ends with it. A filename like payload.png.php bypasses it: it contains an allowed extension (.png) but ends in .php, which the filter never checks. No uploaded content is ever saved or executed -- only the filename is inspected.',
 '{"allowed_extensions": [".png", ".gif"]}', 'avatar-upload', 10, 1);
