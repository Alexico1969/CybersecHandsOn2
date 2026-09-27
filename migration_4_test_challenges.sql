-- Run this once against your existing database (phpMyAdmin -> SQL tab) to
-- add the test-challenges feature: admin-only variants of real challenges
-- that can be assigned to individual students. Requires migration_3 (the
-- `solution` column) to have already been run.
--
-- Everything here is additive and isolated:
--   * `config` is a new nullable column on the existing `challenges` table
--     (NULL = unchanged current behavior for every existing challenge).
--   * The five test_* tables are brand new and never referenced by the
--     live challenges/progress/user_flags/flag_attempts tables.
--   * test_progress.points_awarded is never added to users.points -- it's
--     only ever read by the admin-only scores page.
-- Safe to run once. Re-running will fail on the ALTER TABLE / CREATE TABLE
-- statements if it already succeeded once (harmless -- just skip to the
-- ones that haven't run yet).

ALTER TABLE challenges ADD COLUMN config TEXT NULL AFTER solution;

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
