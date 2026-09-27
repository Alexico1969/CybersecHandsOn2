<?php
require_once __DIR__ . '/../auth.php';
require_admin();

$students = db()->query(
    "SELECT u.*, COUNT(CASE WHEN p.status = 'finished' THEN 1 END) AS solved_count
     FROM users u
     LEFT JOIN progress p ON p.user_id = u.id
     WHERE u.role = 'student'
     GROUP BY u.id
     ORDER BY u.points DESC"
)->fetchAll();

$pageTitle = 'Students';
include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/admin_subnav.php'; ?>

<h1>Students</h1>
<div class="list">
<?php foreach ($students as $s): ?>
  <div class="list-row">
    <div>
      <div class="list-title"><a href="<?= h(SITE_URL) ?>/admin/student.php?id=<?= (int) $s['id'] ?>"><?= h($s['name']) ?></a></div>
      <div class="muted small"><?= h($s['email']) ?></div>
    </div>
    <div class="muted"><?= (int) $s['solved_count'] ?> solved · <?= (int) $s['points'] ?> pts</div>
  </div>
<?php endforeach; ?>
<?php if (!$students): ?><p class="muted">No students yet.</p><?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
