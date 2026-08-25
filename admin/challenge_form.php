<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../challenge_types.php';
require_admin();

$challenge = null;
if (!empty($_GET['id'])) {
    $stmt = db()->prepare('SELECT * FROM challenges WHERE id = ?');
    $stmt->execute([(int) $_GET['id']]);
    $challenge = $stmt->fetch();
    if (!$challenge) {
        redirect('/admin/index.php');
    }
}

$types = CHALLENGE_TYPE_LABELS;
$difficulties = ['EASY', 'MEDIUM', 'HARD'];

$pageTitle = $challenge ? 'Edit challenge' : 'Add challenge';
include __DIR__ . '/../includes/header.php';
?>

<h1><?= $challenge ? 'Edit challenge' : 'Add challenge' ?></h1>

<form method="post" action="<?= h(SITE_URL) ?>/admin/challenge_save.php" class="admin-form">
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

  <label>Terminal URL <span class="muted">(optional — for challenges needing an external Linux/Python sandbox)</span>
    <input type="url" name="terminal_url" value="<?= h($challenge['terminal_url'] ?? '') ?>">
  </label>

  <div class="form-row">
    <label>Hint text <span class="muted">(optional)</span>
      <input type="text" name="hint" value="<?= h($challenge['hint'] ?? '') ?>">
    </label>
    <label>Hint cost (points)
      <input type="number" name="hint_cost" min="0" value="<?= (int) ($challenge['hint_cost'] ?? 0) ?>">
    </label>
  </div>

  <div class="form-row">
    <label>Order
      <input type="number" name="sort_order" value="<?= (int) ($challenge['sort_order'] ?? 0) ?>">
    </label>
    <label class="checkbox-label">
      <input type="checkbox" name="published" <?= ($challenge['published'] ?? 1) ? 'checked' : '' ?>>
      Published
    </label>
  </div>

  <button type="submit" class="button"><?= $challenge ? 'Save changes' : 'Create challenge' ?></button>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
