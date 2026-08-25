<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../challenge_types.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/index.php');
}
require_csrf();

$data = [
    'slug' => trim((string) ($_POST['slug'] ?? '')),
    'title' => trim((string) ($_POST['title'] ?? '')),
    'category' => trim((string) ($_POST['category'] ?? '')),
    'description' => (string) ($_POST['description'] ?? ''),
    'difficulty' => in_array($_POST['difficulty'] ?? '', ['EASY', 'MEDIUM', 'HARD'], true) ? $_POST['difficulty'] : 'EASY',
    'points' => max(1, (int) ($_POST['points'] ?? 100)),
    'challenge_type' => array_key_exists($_POST['challenge_type'] ?? '', CHALLENGE_TYPE_LABELS) ? $_POST['challenge_type'] : 'html_source',
    'terminal_url' => trim((string) ($_POST['terminal_url'] ?? '')) ?: null,
    'hint' => trim((string) ($_POST['hint'] ?? '')) ?: null,
    'hint_cost' => max(0, (int) ($_POST['hint_cost'] ?? 0)),
    'sort_order' => (int) ($_POST['sort_order'] ?? 0),
    'published' => isset($_POST['published']) ? 1 : 0,
];

if ($data['slug'] === '' || $data['title'] === '') {
    flash_set('error', 'Title and slug are required.');
    redirect('/admin/challenge_form.php' . (!empty($_POST['id']) ? '?id=' . (int) $_POST['id'] : ''));
}

if (!empty($_POST['id'])) {
    $id = (int) $_POST['id'];
    $stmt = db()->prepare(
        'UPDATE challenges SET slug=?, title=?, category=?, description=?, difficulty=?, points=?, challenge_type=?, terminal_url=?, hint=?, hint_cost=?, sort_order=?, published=? WHERE id=?'
    );
    $stmt->execute([
        $data['slug'], $data['title'], $data['category'], $data['description'], $data['difficulty'],
        $data['points'], $data['challenge_type'], $data['terminal_url'], $data['hint'], $data['hint_cost'],
        $data['sort_order'], $data['published'], $id,
    ]);
} else {
    $stmt = db()->prepare(
        'INSERT INTO challenges (slug, title, category, description, difficulty, points, challenge_type, terminal_url, hint, hint_cost, sort_order, published) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute([
        $data['slug'], $data['title'], $data['category'], $data['description'], $data['difficulty'],
        $data['points'], $data['challenge_type'], $data['terminal_url'], $data['hint'], $data['hint_cost'],
        $data['sort_order'], $data['published'],
    ]);
}

redirect('/admin/index.php');
