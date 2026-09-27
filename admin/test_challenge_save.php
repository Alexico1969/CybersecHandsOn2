<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../challenge_types.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/test_challenges.php');
}
require_csrf();

$backToForm = '/admin/test_challenge_form.php' . (!empty($_POST['id']) ? '?id=' . (int) $_POST['id'] : '');

$rawConfig = trim((string) ($_POST['config'] ?? ''));
$config = null;
if ($rawConfig !== '') {
    $decoded = json_decode($rawConfig, true);
    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
        flash_set('error', 'Config must be valid JSON (e.g. {"shift": 7}), or left blank.');
        redirect($backToForm);
    }
    $config = $rawConfig;
}

$data = [
    'slug' => trim((string) ($_POST['slug'] ?? '')),
    'title' => trim((string) ($_POST['title'] ?? '')),
    'category' => trim((string) ($_POST['category'] ?? '')),
    'description' => (string) ($_POST['description'] ?? ''),
    'difficulty' => in_array($_POST['difficulty'] ?? '', ['EASY', 'MEDIUM', 'HARD'], true) ? $_POST['difficulty'] : 'EASY',
    'points' => max(1, (int) ($_POST['points'] ?? 100)),
    'challenge_type' => array_key_exists($_POST['challenge_type'] ?? '', CHALLENGE_TYPE_LABELS) ? $_POST['challenge_type'] : 'html_source',
    'based_on_slug' => trim((string) ($_POST['based_on_slug'] ?? '')) ?: null,
    'terminal_url' => trim((string) ($_POST['terminal_url'] ?? '')) ?: null,
    'hint' => trim((string) ($_POST['hint'] ?? '')) ?: null,
    'solution' => trim((string) ($_POST['solution'] ?? '')) ?: null,
    'config' => $config,
    'hint_cost' => max(0, (int) ($_POST['hint_cost'] ?? 0)),
    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
    'published' => isset($_POST['published']) ? 1 : 0,
];

if ($data['slug'] === '' || $data['title'] === '') {
    flash_set('error', 'Title and slug are required.');
    redirect($backToForm);
}

if (!empty($_POST['id'])) {
    $id = (int) $_POST['id'];
    $stmt = db()->prepare(
        'UPDATE test_challenges SET slug=?, title=?, category=?, description=?, difficulty=?, points=?, challenge_type=?, based_on_slug=?, terminal_url=?, hint=?, hint_cost=?, solution=?, config=?, sort_order=?, published=? WHERE id=?'
    );
    $stmt->execute([
        $data['slug'], $data['title'], $data['category'], $data['description'], $data['difficulty'],
        $data['points'], $data['challenge_type'], $data['based_on_slug'], $data['terminal_url'], $data['hint'],
        $data['hint_cost'], $data['solution'], $data['config'], $data['sort_order'], $data['published'], $id,
    ]);
} else {
    $stmt = db()->prepare(
        'INSERT INTO test_challenges (slug, title, category, description, difficulty, points, challenge_type, based_on_slug, terminal_url, hint, hint_cost, solution, config, sort_order, published) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $data['slug'], $data['title'], $data['category'], $data['description'], $data['difficulty'],
        $data['points'], $data['challenge_type'], $data['based_on_slug'], $data['terminal_url'], $data['hint'],
        $data['hint_cost'], $data['solution'], $data['config'], $data['sort_order'], $data['published'],
    ]);
}

redirect('/admin/test_challenges.php');
