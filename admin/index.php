<?php
require_once __DIR__ . '/../auth.php';
require_admin();

$challenges = db()->query('SELECT * FROM challenges ORDER BY sort_order ASC, id ASC')->fetchAll();

$pageTitle = 'Admin';
include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/admin_subnav.php'; ?>

<div class="admin-section-header">
  <h1>Challenges</h1>
  <a class="button" href="<?= h(SITE_URL) ?>/admin/challenge_form.php">+ Add challenge</a>
</div>

<div class="list">
<?php foreach ($challenges as $c): ?>
  <div class="list-row">
    <div>
      <div class="list-title">
        <?= h($c['title']) ?>
        <span class="muted">/<?= h($c['slug']) ?></span>
        <?php if (!$c['published']): ?><span class="badge">Draft</span><?php endif; ?>
      </div>
      <div class="muted small"><?= h($c['difficulty']) ?> · <?= (int) $c['points'] ?> pts · <?= h($c['challenge_type']) ?></div>
    </div>
    <div class="list-actions">
      <a href="<?= h(SITE_URL) ?>/admin/challenge_form.php?id=<?= (int) $c['id'] ?>">Edit</a>
      <form method="post" action="<?= h(SITE_URL) ?>/admin/challenge_delete.php" onsubmit="return confirm('Delete this challenge? This also removes everyone\'s progress on it.');">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
        <button type="submit" class="button-link danger">Delete</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$challenges): ?><p class="muted">No challenges yet.</p><?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
