-- Run this once against your existing database (phpMyAdmin -> SQL tab)
-- BEFORE uploading the matching auth.php -- the new auth.php writes these
-- columns on every sign-in, so sign-in fails until they exist.
--
-- Stores the rest of what Google's userinfo endpoint returns (given/family
-- name, verified-email flag, locale, Workspace domain) plus sign-in
-- tracking, shown on the admin student page. Existing users get these
-- filled in the next time they sign in; until then they show as unknown.
-- Only run once -- re-running fails on the duplicate columns.

ALTER TABLE users
  ADD COLUMN given_name VARCHAR(255) NULL AFTER name,
  ADD COLUMN family_name VARCHAR(255) NULL AFTER given_name,
  ADD COLUMN email_verified TINYINT(1) NULL AFTER email,
  ADD COLUMN locale VARCHAR(35) NULL AFTER avatar_url,
  ADD COLUMN hosted_domain VARCHAR(255) NULL AFTER locale,
  ADD COLUMN last_login_at DATETIME NULL AFTER created_at,
  ADD COLUMN login_count INT NOT NULL DEFAULT 0 AFTER last_login_at;
