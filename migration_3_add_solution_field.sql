-- Run this once against your existing database (phpMyAdmin -> SQL tab) to
-- add an admin-only 'solution' field to each challenge (a written answer key,
-- never shown to students) and backfill it for the 10 existing challenges.
-- Safe to run even if some of this already exists, except the ALTER TABLE --
-- only run that once.

ALTER TABLE challenges ADD COLUMN solution TEXT NULL AFTER hint_cost;

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

-- The avatar-upload challenge's existing hint asked "What would
-- "payload.php.jpg" look like to that check?" -- but that filename
-- actually ends in .jpg (a valid extension), so it's accepted as a normal
-- image, not a bypass. Correcting it to payload.jpg.php, which genuinely
-- demonstrates the bug (contains .jpg, but ends in .php).
UPDATE challenges SET hint = 'The filter checks whether an allowed extension appears in the filename, not that the filename ends with it. What would "payload.jpg.php" look like to that check?' WHERE slug = 'avatar-upload';
