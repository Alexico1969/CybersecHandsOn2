<?php
require_once __DIR__ . '/auth.php';
$user = require_login();

$challenges = db()->query(
    'SELECT * FROM challenges WHERE published = 1 ORDER BY sort_order ASC, id ASC'
)->fetchAll();

$progressStmt = db()->prepare('SELECT * FROM progress WHERE user_id = ?');
$progressStmt->execute([$user['id']]);
$progressByChallenge = [];
foreach ($progressStmt->fetchAll() as $p) {
    $progressByChallenge[$p['challenge_id']] = $p;
}

$finishedCount = count(array_filter($progressByChallenge, fn($p) => $p['status'] === 'finished'));

$pageTitle = 'Challenges';
include __DIR__ . '/includes/header.php';
?>
<h1>Challenges</h1>
<p class="muted"><?= $finishedCount ?> / <?= count($challenges) ?> completed · <?= (int) $user['points'] ?> points</p>

<div class="card-list">
<?php foreach ($challenges as $c): ?>
  <?php
    $p = $progressByChallenge[$c['id']] ?? null;
    $status = $p['status'] ?? 'not_started';
    $statusLabel = ['not_started' => 'Not started', 'started' => 'In progress', 'finished' => 'Completed'][$status];
  ?>
  <a class="card" href="<?= h(SITE_URL) ?>/challenge.php?slug=<?= urlencode($c['slug']) ?>">
    <div class="card-main">
      <div class="card-title">
        <span><?= h($c['title']) ?></span>
        <span class="badge badge-<?= h(strtolower($c['difficulty'])) ?>"><?= h($c['difficulty']) ?></span>
      </div>
      <p class="muted card-desc"><?= h($c['category']) ?></p>
    </div>
    <div class="card-side">
      <div><?= (int) $c['points'] ?> pts</div>
      <div class="status status-<?= h($status) ?>"><?= h($statusLabel) ?></div>
    </div>
  </a>
<?php endforeach; ?>
<?php if (!$challenges): ?>
  <p class="muted">No challenges published yet — check back soon.</p>
<?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
