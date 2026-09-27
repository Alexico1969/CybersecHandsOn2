<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../challenge_types.php';
require_admin();

$challenge = null;
if (!empty($_GET['id'])) {
    $stmt = db()->prepare('SELECT * FROM test_challenges WHERE id = ?');
    $stmt->execute([(int) $_GET['id']]);
    $challenge = $stmt->fetch();
    if (!$challenge) {
        redirect('/admin/test_challenges.php');
    }
}

$types = CHALLENGE_TYPE_LABELS;
$difficulties = ['EASY', 'MEDIUM', 'HARD'];
$realChallenges = db()->query('SELECT slug, title FROM challenges ORDER BY sort_order ASC, id ASC')->fetchAll();

$pageTitle = $challenge ? 'Edit test-challenge' : 'Add test-challenge';
include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/admin_subnav.php'; ?>

<h1><?= $challenge ? 'Edit test-challenge' : 'Add test-challenge' ?></h1>
<p class="muted">Test-challenges are variants of real challenges, assigned to individual students. They never affect a student's real points.</p>

<form method="post" action="<?= h(SITE_URL) ?>/admin/test_challenge_save.php" class="admin-form">
  <?= csrf_field() ?>
  <?php if ($challenge): ?><input type="hidden" name="id" value="<?= (int) $challenge['id'] ?>"><?php endif; ?>

  <label>Title
    <input type="text" name="title" required value="<?= h($challenge['title'] ?? '') ?>">
  </label>

  <label>Slug <span class="muted">(short, unique, used in the URL)</span>
    <input type="text" name="slug" required value="<?= h($challenge['slug'] ?? '') ?>">
  </label>

  <label>Category <span class="muted">(free text, e.g. "SQL injection")</span>
    <input type="text" name="category" required value="<?= h($challenge['category'] ?? '') ?>">
  </label>

  <label>Description
    <textarea name="description" rows="5" required><?= h($challenge['description'] ?? '') ?></textarea>
  </label>

  <div class="form-row">
    <label>Difficulty
      <select name="difficulty">
        <?php foreach ($difficulties as $d): ?>
          <option value="<?= $d ?>" <?= ($challenge['difficulty'] ?? 'EASY') === $d ? 'selected' : '' ?>><?= $d ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Points
      <input type="number" name="points" min="1" required value="<?= (int) ($challenge['points'] ?? 100) ?>">
    </label>
  </div>

  <label>Challenge type <span class="muted">(which built-in mechanic renders it)</span>
    <select name="challenge_type">
      <?php foreach ($types as $value => $label): ?>
        <option value="<?= h($value) ?>" <?= ($challenge['challenge_type'] ?? '') === $value ? 'selected' : '' ?>><?= h($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>Based on <span class="muted">(which real challenge this variant is for — just a note, not enforced)</span>
    <select name="based_on_slug">
      <option value="">— none —</option>
      <?php foreach ($realChallenges as $rc): ?>
        <option value="<?= h($rc['slug']) ?>" <?= ($challenge['based_on_slug'] ?? '') === $rc['slug'] ? 'selected' : '' ?>><?= h($rc['title']) ?> (<?= h($rc['slug']) ?>)</option>
      <?php endforeach; ?>
    </select>
  </label>

  <label>Terminal URL <span class="muted">(optional — for challenges needing an external Linux/Python sandbox)</span>
    <input type="url" name="terminal_url" value="<?= h($challenge['terminal_url'] ?? '') ?>">
  </label>

  <div class="form-row">
    <label>Hint text <span class="muted">(optional — rewrite this so it doesn't just repeat the real challenge's hint)</span>
      <input type="text" name="hint" value="<?= h($challenge['hint'] ?? '') ?>">
    </label>
    <label>Hint cost (points)
      <input type="number" name="hint_cost" min="0" value="<?= (int) ($challenge['hint_cost'] ?? 0) ?>">
    </label>
  </div>

  <label>Solution <span class="muted">(admin-only answer key — never shown to students)</span>
    <textarea name="solution" rows="4"><?= h($challenge['solution'] ?? '') ?></textarea>
  </label>

  <label>Config <span class="muted">(optional JSON — parameterizes this variant's secret so it differs from the real challenge. Leave blank to use the classic defaults.)</span>
    <textarea name="config" rows="3" placeholder='e.g. {"shift": 7}'><?= h($challenge['config'] ?? '') ?></textarea>
  </label>
  <p class="muted small" style="margin-top:-.75rem">
    Keys read by each type — cookie: <code>cookie_name</code>, <code>locked_value</code>, <code>unlock_value</code>
    &nbsp;·&nbsp; caesar: <code>shift</code> (number)
    &nbsp;·&nbsp; vigenere: <code>keyword</code>
    &nbsp;·&nbsp; hidden_param: <code>param_name</code>, <code>param_value</code>
    &nbsp;·&nbsp; custom_header: <code>header_name</code>, <code>header_value</code>
    &nbsp;·&nbsp; file_upload: <code>allowed_extensions</code> (JSON array, e.g. <code>[".png",".gif"]</code>).
    html_source, base64, hex and sql_injection don't read any config yet.
  </p>

  <div class="form-row">
    <label>Order
      <input type="number" name="sort_order" value="<?= (int) ($challenge['sort_order'] ?? 0) ?>">
    </label>
    <label class="checkbox-label">
      <input type="checkbox" name="published" <?= ($challenge['published'] ?? 1) ? 'checked' : '' ?>>
      Published
    </label>
  </div>

  <button type="submit" class="button"><?= $challenge ? 'Save changes' : 'Create test-challenge' ?></button>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
