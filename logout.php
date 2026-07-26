<?php
// logout.php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth_guard.php';
init_session();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;