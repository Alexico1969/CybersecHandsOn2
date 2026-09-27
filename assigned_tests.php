<?php
require_once __DIR__ . '/auth.php';
$user = require_login();

$stmt = db()->prepare(
    'SELECT tc.*, tca.assigned_at
     FROM test_challenge_assignments tca
     JOIN test_challenges tc ON tc.id = tca.test_challenge_id
     WHERE tca.user_id = ? AND tc.published = 1
     ORDER BY tca.assigned_at DESC'
);
$stmt->execute([$user['id']]);
$assigned = $stmt->fetchAll();

$progressStmt = db()->prepare('SELECT * FROM test_progress WHERE user_id = ?');
$progressStmt->execute([$user['id']]);
$progressByTestChallenge = [];
foreach ($progressStmt->fetchAll() as $p) {
    $progressByTestChallenge[$p['test_challenge_id']] = $p;
}

$pageTitle = 'Assigned Tests';
include __DIR__ . '/includes/header.php';
?>
<h1>Assigned Tests</h1>
<p class="muted">Variant challenges your instructor assigned specifically to you. They're separate from your regular challenges and points.</p>

<div class="card-list">
<?php foreach ($assigned as $c): ?>
  <?php
    $p = $progressByTestChallenge[$c['id']] ?? null;
    $status = $p['status'] ?? 'not_started';
    $statusLabel = ['not_started' => 'Not started', 'started' => 'In progress', 'finished' => 'Completed'][$status];
  ?>
  <a class="card" href="<?= h(SITE_URL) ?>/test_challenge.php?slug=<?= urlencode($c['slug']) ?>">
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
<?php if (!$assigned): ?>
  <p class="muted">No test-challenges have been assigned to you yet.</p>
<?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
