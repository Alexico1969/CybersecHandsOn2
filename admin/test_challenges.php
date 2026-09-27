<?php
require_once __DIR__ . '/../auth.php';
require_admin();

$testChallenges = db()->query('SELECT * FROM test_challenges ORDER BY sort_order ASC, id ASC')->fetchAll();

$students = db()->query("SELECT id, name, email FROM users WHERE role = 'student' ORDER BY name ASC")->fetchAll();

$assignments = db()->query(
    "SELECT tca.id, tca.assigned_at, u.name AS student_name, u.email AS student_email,
            tc.title AS challenge_title, tc.slug AS challenge_slug,
            tp.status AS progress_status
     FROM test_challenge_assignments tca
     JOIN users u ON u.id = tca.user_id
     JOIN test_challenges tc ON tc.id = tca.test_challenge_id
     LEFT JOIN test_progress tp ON tp.user_id = tca.user_id AND tp.test_challenge_id = tca.test_challenge_id
     ORDER BY tca.assigned_at DESC"
)->fetchAll();

$pageTitle = 'Test-challenges';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-subnav">
  <a href="<?= h(SITE_URL) ?>/admin/index.php">Challenges</a>
  <a href="<?= h(SITE_URL) ?>/admin/test_challenges.php">Test-challenges</a>
  <a href="<?= h(SITE_URL) ?>/admin/test_scores.php">Test scores</a>
</div>

<div class="admin-section-header">
  <h1>Test-challenges</h1>
  <a class="button" href="<?= h(SITE_URL) ?>/admin/test_challenge_form.php">+ Add test-challenge</a>
</div>
<p class="muted">Variants of real challenges you can assign to individual students — e.g. after a technique leaks classroom-wide, give the flagged student a differently-keyed version instead of the original.</p>

<div class="list">
<?php foreach ($testChallenges as $c): ?>
  <div class="list-row">
    <div>
      <div class="list-title">
        <?= h($c['title']) ?>
        <span class="muted">/<?= h($c['slug']) ?></span>
        <?php if (!$c['published']): ?><span class="badge">Draft</span><?php endif; ?>
      </div>
      <div class="muted small">
        <?= h($c['difficulty']) ?> · <?= (int) $c['points'] ?> pts · <?= h($c['challenge_type']) ?>
        <?php if ($c['based_on_slug']): ?> · based on <?= h($c['based_on_slug']) ?><?php endif; ?>
        <?php if ($c['config']): ?> · <span title="<?= h($c['config']) ?>">custom config</span><?php endif; ?>
      </div>
    </div>
    <div class="list-actions">
      <a href="<?= h(SITE_URL) ?>/admin/test_challenge_form.php?id=<?= (int) $c['id'] ?>">Edit</a>
      <form method="post" action="<?= h(SITE_URL) ?>/admin/test_challenge_delete.php" onsubmit="return confirm('Delete this test-challenge? This also removes every assignment and attempt tied to it.');">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
        <button type="submit" class="button-link danger">Delete</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$testChallenges): ?><p class="muted">No test-challenges yet — add one above.</p><?php endif; ?>
</div>

<h2>Assign to a student</h2>
<form method="post" action="<?= h(SITE_URL) ?>/admin/test_assign.php" class="admin-form">
  <?= csrf_field() ?>
  <div class="form-row">
    <label>Student
      <select name="user_id" required>
        <option value="">— choose —</option>
        <?php foreach ($students as $s): ?>
          <option value="<?= (int) $s['id'] ?>"><?= h($s['name']) ?> (<?= h($s['email']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Test-challenge
      <select name="test_challenge_id" required>
        <option value="">— choose —</option>
        <?php foreach ($testChallenges as $c): ?>
          <option value="<?= (int) $c['id'] ?>"><?= h($c['title']) ?> (/<?= h($c['slug']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>
  <button type="submit" class="button">Assign</button>
</form>

<h2>Current assignments</h2>
<div class="list">
<?php foreach ($assignments as $a): ?>
  <?php $status = $a['progress_status'] ?? 'not_started'; ?>
  <div class="list-row">
    <div>
      <div class="list-title"><?= h($a['student_name']) ?> <span class="muted">→</span> <?= h($a['challenge_title']) ?></div>
      <div class="muted small">
        <?= h($a['student_email']) ?> · assigned <?= h($a['assigned_at']) ?>
        · <span class="status status-<?= h($status) ?>"><?= h(['not_started' => 'Not started', 'started' => 'In progress', 'finished' => 'Completed'][$status]) ?></span>
      </div>
    </div>
    <div class="list-actions">
      <form method="post" action="<?= h(SITE_URL) ?>/admin/test_unassign.php" onsubmit="return confirm('Revoke this assignment? Their test-challenge progress is kept in case you reassign it later.');">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
        <button type="submit" class="button-link danger">Revoke</button>
      </form>
    </div>
  </div>
<?php endforeach; ?>
<?php if (!$assignments): ?><p class="muted">No assignments yet.</p><?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
