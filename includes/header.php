<?php
// Expects $pageTitle to optionally be set before including this file.
$user = current_user();

// True when this student has a published test-challenge assigned to them
// that they have not yet finished -- drives the "!" badge on the nav link.
$hasPendingTest = false;
if ($user) {
    $pendingStmt = db()->prepare(
        "SELECT EXISTS (
            SELECT 1 FROM test_challenge_assignments tca
            JOIN test_challenges tc ON tc.id = tca.test_challenge_id AND tc.published = 1
            LEFT JOIN test_progress tp ON tp.user_id = tca.user_id
                AND tp.test_challenge_id = tca.test_challenge_id
                AND tp.status = 'finished'
            WHERE tca.user_id = ? AND tp.id IS NULL
        )"
    );
    $pendingStmt->execute([$user['id']]);
    $hasPendingTest = (bool) $pendingStmt->fetchColumn();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h(isset($pageTitle) ? $pageTitle . ' — HackLab' : 'HackLab') ?></title>
<link rel="stylesheet" href="<?= h(SITE_URL) ?>/assets/style.css">
</head>
<body>
<?php if ($user): ?>
<header class="nav">
  <div class="nav-inner">
    <div class="nav-left">
      <a class="brand" href="<?= h(SITE_URL) ?>/dashboard.php">HackLab</a>
      <a href="<?= h(SITE_URL) ?>/dashboard.php">Challenges</a>
      <a href="<?= h(SITE_URL) ?>/assigned_tests.php" class="nav-link-with-badge">
        Assigned Tests
        <?php if ($hasPendingTest): ?><span class="nav-badge" title="You have an assigned test-challenge">!</span><?php endif; ?>
      </a>
      <?php if ($user['role'] === 'admin'): ?>
        <a href="<?= h(SITE_URL) ?>/admin/index.php">Admin</a>
      <?php endif; ?>
    </div>
    <div class="nav-right">
      <span class="points"><?= h($user['name']) ?> · <strong><?= (int) $user['points'] ?> pts</strong></span>
      <a href="<?= h(SITE_URL) ?>/logout.php">Sign out</a>
    </div>
  </div>
</header>
<?php endif; ?>
<main class="container">
<?php foreach (flash_take() as $flash): ?>
  <div class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
<?php endforeach; ?>
