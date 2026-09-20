<?php
require_once __DIR__ . '/auth.php';

if (current_user()) {
    redirect('/dashboard.php');
}

$pageTitle = 'Sign in';
include __DIR__ . '/includes/header.php';
?>
<div class="hero">
  <h1>HackLab</h1>
  <p>Hands-on cybersecurity challenges — every flag is unique to you, so it's your work that counts.</p>
  <a class="button google-button" href="<?= h(SITE_URL) ?>/login.php">
    <img src="<?= h(SITE_URL) ?>/assets/google.svg" alt="" width="18" height="18">
    Sign in with Google
  </a>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
