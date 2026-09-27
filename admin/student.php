<?php
require_once __DIR__ . '/../auth.php';
require_admin();

$stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$student = $stmt->fetch();
if (!$student) {
    flash_set('error', 'That student no longer exists.');
    redirect('/admin/students.php');
}
$id = (int) $student['id'];

// Every published challenge, plus any unpublished one they already have
// progress on, so nothing they did disappears from this page.
$stmt = db()->prepare(
    'SELECT c.title, c.slug, c.difficulty, c.points, c.published,
            p.status, p.started_at, p.finished_at, p.attempts, p.hint_used, p.points_awarded
     FROM challenges c
     LEFT JOIN progress p ON p.challenge_id = c.id AND p.user_id = ?
     WHERE c.published = 1 OR p.id IS NOT NULL
     ORDER BY c.sort_order ASC, c.id ASC'
);
$stmt->execute([$id]);
$challenges = $stmt->fetchAll();

// Currently assigned test-challenges, plus revoked ones that still have
// progress (test_unassign.php keeps progress in case of reassignment).
$stmt = db()->prepare(
    'SELECT tc.title, tc.points, tca.assigned_at,
            tp.status, tp.finished_at, tp.attempts, tp.hint_used, tp.points_awarded
     FROM test_challenges tc
     LEFT JOIN test_challenge_assignments tca ON tca.test_challenge_id = tc.id AND tca.user_id = ?
     LEFT JOIN test_progress tp ON tp.test_challenge_id = tc.id AND tp.user_id = ?
     WHERE tca.id IS NOT NULL OR tp.id IS NOT NULL
     ORDER BY tca.assigned_at DESC, tc.sort_order ASC'
);
$stmt->execute([$id, $id]);
$tests = $stmt->fetchAll();

$stmt = db()->prepare(
    "(SELECT fa.submitted_value, fa.was_correct, fa.created_at, c.title, 'challenge' AS kind
      FROM flag_attempts fa JOIN challenges c ON c.id = fa.challenge_id
      WHERE fa.user_id = ?)
     UNION ALL
     (SELECT tfa.submitted_value, tfa.was_correct, tfa.created_at, tc.title, 'test' AS kind
      FROM test_flag_attempts tfa JOIN test_challenges tc ON tc.id = tfa.test_challenge_id
      WHERE tfa.user_id = ?)
     ORDER BY created_at DESC
     LIMIT 25"
);
$stmt->execute([$id, $id]);
$attempts = $stmt->fetchAll();

$rank = null;
if ($student['role'] === 'student') {
    $stmt = db()->prepare("SELECT COUNT(*) + 1 FROM users WHERE role = 'student' AND points > ?");
    $stmt->execute([$student['points']]);
    $rank = (int) $stmt->fetchColumn();
    $studentCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
}

$publishedCount = count(array_filter($challenges, fn($c) => $c['published']));
$completedCount = count(array_filter($challenges, fn($c) => $c['status'] === 'finished'));
$inProgressCount = count(array_filter($challenges, fn($c) => $c['status'] === 'started'));
$totalAttempts = array_sum(array_map(fn($c) => (int) $c['attempts'], $challenges));
$hintsUsed = count(array_filter($challenges, fn($c) => $c['hint_used']));

const STATUS_LABELS = ['started' => 'In progress', 'finished' => 'Completed'];

function status_cell(?string $status): string {
    $key = $status ?? 'not_started';
    return '<span class="status status-' . h($key) . '">' . h(STATUS_LABELS[$status] ?? 'Not started') . '</span>';
}

function fmt_datetime(?string $value): string {
    return $value ? h(date('j M Y, H:i', strtotime($value))) : '<span class="muted">—</span>';
}

function yes_no_unknown(?string $value): string {
    if ($value === null) {
        return '<span class="muted">Unknown</span>';
    }
    return $value ? 'Yes' : 'No';
}

function or_unknown(?string $value): string {
    return ($value !== null && $value !== '') ? h($value) : '<span class="muted">Unknown</span>';
}

$pageTitle = $student['name'];
include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/admin_subnav.php'; ?>

<p class="small back-link"><a href="<?= h(SITE_URL) ?>/admin/students.php">← All students</a></p>

<section class="profile-card">
  <?php if ($student['avatar_url']): ?>
    <img class="avatar" src="<?= h($student['avatar_url']) ?>" alt="" referrerpolicy="no-referrer">
  <?php else: ?>
    <div class="avatar avatar-initial"><?= h(mb_strtoupper(mb_substr($student['name'], 0, 1))) ?></div>
  <?php endif; ?>
  <div class="profile-main">
    <h1><?= h($student['name']) ?></h1>
    <div class="profile-email"><a href="mailto:<?= h($student['email']) ?>"><?= h($student['email']) ?></a></div>
    <div class="chip-row">
      <span class="chip<?= $student['role'] === 'admin' ? ' chip-accent' : '' ?>"><?= $student['role'] === 'admin' ? 'Admin' : 'Student' ?></span>
      <?php if ($student['email_verified'] !== null): ?>
        <span class="chip"><?= $student['email_verified'] ? '✓ Verified email' : 'Unverified email' ?></span>
      <?php endif; ?>
      <?php if ($student['hosted_domain']): ?><span class="chip"><?= h($student['hosted_domain']) ?></span><?php endif; ?>
      <span class="chip">Joined <?= h(date('j M Y', strtotime($student['created_at']))) ?></span>
    </div>
  </div>
</section>

<div class="stat-grid">
  <div class="stat stat-primary">
    <div class="stat-label">Points</div>
    <div class="stat-value"><?= (int) $student['points'] ?></div>
    <?php if ($rank !== null): ?><div class="stat-sub">Rank #<?= $rank ?> of <?= $studentCount ?></div><?php endif; ?>
  </div>
  <div class="stat">
    <div class="stat-label">Completed</div>
    <div class="stat-value"><?= $completedCount ?><span class="stat-of">/<?= $publishedCount ?></span></div>
    <div class="progress"><div class="progress-bar" style="width: <?= $publishedCount ? round($completedCount / $publishedCount * 100) : 0 ?>%"></div></div>
  </div>
  <div class="stat">
    <div class="stat-label">In progress</div>
    <div class="stat-value"><?= $inProgressCount ?></div>
  </div>
  <div class="stat">
    <div class="stat-label">Flag attempts</div>
    <div class="stat-value"><?= $totalAttempts ?></div>
    <div class="stat-sub"><?= $hintsUsed ?> hint<?= $hintsUsed === 1 ? '' : 's' ?> used</div>
  </div>
</div>

<h2 class="section-title">Profile</h2>
<section class="panel">
  <dl class="field-grid">
    <div class="field"><dt>Given name</dt><dd><?= or_unknown($student['given_name']) ?></dd></div>
    <div class="field"><dt>Family name</dt><dd><?= or_unknown($student['family_name']) ?></dd></div>
    <div class="field"><dt>Email verified by Google</dt><dd><?= yes_no_unknown($student['email_verified']) ?></dd></div>
    <div class="field"><dt>Workspace domain</dt><dd><?= $student['hosted_domain'] ? h($student['hosted_domain']) : '<span class="muted">None</span>' ?></dd></div>
    <div class="field"><dt>Language</dt><dd><?= or_unknown($student['locale']) ?></dd></div>
    <div class="field"><dt>Google account ID</dt><dd><code><?= h($student['google_id']) ?></code></dd></div>
    <div class="field"><dt>First sign-in</dt><dd><?= fmt_datetime($student['created_at']) ?></dd></div>
    <div class="field"><dt>Last sign-in</dt><dd><?= fmt_datetime($student['last_login_at']) ?></dd></div>
    <div class="field"><dt>Sign-ins</dt><dd><?= (int) $student['login_count'] ?: '<span class="muted">Unknown</span>' ?></dd></div>
  </dl>
  <?php if ($student['login_count'] == 0): ?>
    <p class="muted small panel-note">Fields showing "Unknown" fill in the next time this student signs in.</p>
  <?php endif; ?>
</section>

<h2 class="section-title">Challenges</h2>
<div class="table-wrap">
<table class="data-table">
  <thead>
    <tr><th>Challenge</th><th>Status</th><th>Started</th><th>Completed</th><th class="num">Attempts</th><th>Hint</th><th class="num">Points</th></tr>
  </thead>
  <tbody>
  <?php foreach ($challenges as $c): ?>
    <tr>
      <td>
        <?= h($c['title']) ?>
        <?php if (!$c['published']): ?><span class="badge">Draft</span><?php endif; ?>
        <div class="muted small"><?= h($c['difficulty']) ?></div>
      </td>
      <td><?= status_cell($c['status']) ?></td>
      <td class="small"><?= fmt_datetime($c['started_at']) ?></td>
      <td class="small"><?= fmt_datetime($c['finished_at']) ?></td>
      <td class="num"><?= $c['status'] ? (int) $c['attempts'] : '<span class="muted">—</span>' ?></td>
      <td><?= $c['hint_used'] ? 'Used' : '<span class="muted">—</span>' ?></td>
      <td class="num"><?= $c['status'] === 'finished' ? (int) $c['points_awarded'] . '/' . (int) $c['points'] : '<span class="muted">—/' . (int) $c['points'] . '</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php if (!$challenges): ?><p class="muted">No challenges yet.</p><?php endif; ?>

<h2 class="section-title">Test-challenges</h2>
<?php if ($tests): ?>
<p class="muted small">Test points are separate and never count toward the points above.</p>
<div class="table-wrap">
<table class="data-table">
  <thead>
    <tr><th>Test-challenge</th><th>Assigned</th><th>Status</th><th>Completed</th><th class="num">Attempts</th><th>Hint</th><th class="num">Points</th></tr>
  </thead>
  <tbody>
  <?php foreach ($tests as $t): ?>
    <tr>
      <td><?= h($t['title']) ?></td>
      <td class="small"><?= $t['assigned_at'] ? fmt_datetime($t['assigned_at']) : '<span class="muted">Revoked</span>' ?></td>
      <td><?= status_cell($t['status']) ?></td>
      <td class="small"><?= fmt_datetime($t['finished_at']) ?></td>
      <td class="num"><?= $t['status'] ? (int) $t['attempts'] : '<span class="muted">—</span>' ?></td>
      <td><?= $t['hint_used'] ? 'Used' : '<span class="muted">—</span>' ?></td>
      <td class="num"><?= $t['status'] === 'finished' ? (int) $t['points_awarded'] . '/' . (int) $t['points'] : '<span class="muted">—/' . (int) $t['points'] . '</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php else: ?>
<p class="muted">No test-challenges assigned.</p>
<?php endif; ?>

<h2 class="section-title">Recent flag submissions</h2>
<?php if ($attempts): ?>
<p class="muted small">The 25 most recent, from both regular and test-challenges.</p>
<div class="table-wrap">
<table class="data-table">
  <thead>
    <tr><th>When</th><th>Challenge</th><th>Submitted</th><th>Result</th></tr>
  </thead>
  <tbody>
  <?php foreach ($attempts as $a): ?>
    <tr>
      <td class="small"><?= fmt_datetime($a['created_at']) ?></td>
      <td><?= h($a['title']) ?><?php if ($a['kind'] === 'test'): ?> <span class="badge">Test</span><?php endif; ?></td>
      <td><code class="submitted"><?= h($a['submitted_value']) ?></code></td>
      <td><?= $a['was_correct'] ? '<span class="status status-finished">Correct</span>' : '<span class="muted">Wrong</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php else: ?>
<p class="muted">No flags submitted yet.</p>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
