<?php
require_once __DIR__ . '/../auth.php';
require_admin();

$results = db()->query(
    "SELECT tp.*, u.name AS student_name, u.email AS student_email,
            tc.title AS challenge_title, tc.slug AS challenge_slug, tc.points AS challenge_points
     FROM test_progress tp
     JOIN users u ON u.id = tp.user_id
     JOIN test_challenges tc ON tc.id = tp.test_challenge_id
     ORDER BY (tp.status = 'finished') DESC, tp.finished_at DESC, u.name ASC"
)->fetchAll();

$finishedCount = count(array_filter($results, fn($r) => $r['status'] === 'finished'));

$pageTitle = 'Test scores';
include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/admin_subnav.php'; ?>

<h1>Test scores</h1>
<p class="muted">
  Results from test-challenge attempts — admin-only, and completely separate from the real leaderboard.
  <?= $finishedCount ?> completed attempt<?= $finishedCount === 1 ? '' : 's' ?> so far.
</p>

<div class="list">
<?php foreach ($results as $r): ?>
  <div class="list-row">
    <div>
      <div class="list-title"><?= h($r['student_name']) ?> <span class="muted">→</span> <?= h($r['challenge_title']) ?></div>
      <div class="muted small">
        <?= h($r['student_email']) ?>
        · <span class="status status-<?= h($r['status']) ?>"><?= h(['not_started' => 'Not started', 'started' => 'In progress', 'finished' => 'Completed'][$r['status']]) ?></span>
        · <?= (int) $r['attempts'] ?> attempt<?= (int) $r['attempts'] === 1 ? '' : 's' ?>
        <?php if ($r['hint_used']): ?>· hint used<?php endif; ?>
        <?php if ($r['status'] === 'finished'): ?>· <?= (int) $r['points_awarded'] ?>/<?= (int) $r['challenge_points'] ?> pts · finished <?= h($r['finished_at']) ?><?php endif; ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$results): ?><p class="muted">No test-challenge attempts yet.</p><?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
