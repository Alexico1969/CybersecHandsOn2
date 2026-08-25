<?php
require_once __DIR__ . '/auth.php';

if (current_user()) {
    redirect('/dashboard.php');
}

header('Location: ' . google_auth_url());
exit;
