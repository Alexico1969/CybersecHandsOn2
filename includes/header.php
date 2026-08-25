<?php
// Expects $pageTitle to optionally be set before including this file.
$user = current_user();
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
