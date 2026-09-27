<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/challenge_types.php';
$user = require_login();

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare('SELECT * FROM test_challenges WHERE slug = ?');
$stmt->execute([$slug]);
$challenge = $stmt->fetch();

$isAdmin = $user['role'] === 'admin';
$isAssigned = false;
if ($challenge) {
    $assignStmt = db()->prepare('SELECT id FROM test_challenge_assignments WHERE user_id = ? AND test_challenge_id = ?');
    $assignStmt->execute([$user['id'], $challenge['id']]);
    $isAssigned = (bool) $assignStmt->fetch();
}

if (!$challenge || (!$challenge['published'] && !$isAdmin) || (!$isAssigned && !$isAdmin)) {
    http_response_code(404);
    $pageTitle = 'Not found';
    include __DIR__ . '/includes/header.php';
    echo '<p>Challenge not found.</p>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$progressStmt = db()->prepare('SELECT * FROM test_progress WHERE user_id = ? AND test_challenge_id = ?');
$progressStmt->execute([$user['id'], $challenge['id']]);
$progress = $progressStmt->fetch();

$userFlag = null;
if ($progress) {
    $flagStmt = db()->prepare('SELECT * FROM test_user_flags WHERE user_id = ? AND test_challenge_id = ?');
    $flagStmt->execute([$user['id'], $challenge['id']]);
    $userFlag = $flagStmt->fetch();

    // Must run before any HTML is sent — some challenge types set cookies here.
    if ($userFlag) {
        prepare_challenge_type($challenge['challenge_type'], $userFlag['flag_value'], $challenge);
    }
}

$finished = $progress && $progress['status'] === 'finished';

$pageTitle = $challenge['title'];
include __DIR__ . '/includes/header.php';
?>

<div class="challenge-header">
  <h1><?= h($challenge['title']) ?> <span class="muted">(test)</span></h1>
  <span class="badge badge-<?= h(strtolower($challenge['difficulty'])) ?>"><?= h($challenge['difficulty']) ?></span>
  <span class="muted"><?= (int) $challenge['points'] ?> pts (test only)</span>
</div>
<p class="muted"><?= h($challenge['category']) ?></p>
<p class="muted">This is a test-challenge assigned to you specifically. It's separate from your regular challenges and points.</p>
<p class="description"><?= nl2br(h($challenge['description'])) ?></p>

<?php if ($finished): ?>
  <div class="flash flash-success">
    You already solved this test-challenge and earned <?= (int) $progress['points_awarded'] ?> points (test only — not added to your challenge score).
  </div>
<?php endif; ?>

<?php if (!$progress): ?>
  <form method="post" action="<?= h(SITE_URL) ?>/test_start_challenge.php">
    <?= csrf_field() ?>
    <input type="hidden" name="slug" value="<?= h($slug) ?>">
    <button type="submit" class="button">Start challenge</button>
  </form>

<?php else: ?>

  <?php if ($challenge['terminal_url']): ?>
    <p><a class="button button-secondary" href="<?= h($challenge['terminal_url']) ?>" target="_blank" rel="noopener">Open terminal ↗</a></p>
  <?php endif; ?>

  <?= render_challenge_type($challenge['challenge_type'], $userFlag['flag_value'], $challenge, 'test') ?>

  <?php if ($challenge['hint']): ?>
    <div class="hint-box">
      <?php if ($progress['hint_used']): ?>
        <p><strong>Hint:</strong> <?= h($challenge['hint']) ?></p>
      <?php elseif (!$finished): ?>
        <form method="post" action="<?= h(SITE_URL) ?>/test_request_hint.php">
          <?= csrf_field() ?>
          <input type="hidden" name="slug" value="<?= h($slug) ?>">
          <button type="submit" class="button button-link">
            Reveal hint (−<?= (int) $challenge['hint_cost'] ?> pts if you solve it)
          </button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (!$finished): ?>
    <form method="post" action="<?= h(SITE_URL) ?>/test_submit_flag.php" class="flag-form">
      <?= csrf_field() ?>
      <input type="hidden" name="slug" value="<?= h($slug) ?>">
      <input type="text" name="flag" placeholder="flag{...}" autocomplete="off" required>
      <button type="submit" class="button">Submit</button>
    </form>
  <?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
