<?php
// this ensures the session is started exactly once
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Simple HTML escaper
if (!function_exists('h')) {
  function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }
}

// detect login status and see account link by role
$isLoggedIn = isset($_SESSION['user_id']);
$role = $_SESSION['role'] ?? null;

$accountHref = 'signup.php'; // the default when logged out
$accountText = 'Sign Up';

$authHref = 'login.php'; // default when logged out
$authText = 'Login';

if ($isLoggedIn) {
  switch ($role) {
    case 'Admin':
      $accountHref = 'admin.php';
      break;
    case 'Librarian':
      $accountHref = 'librarian.php';
      break;
    default:
    // fallback to member
      $accountHref = 'member.php';
      break;
  }
  $accountText = 'My Account';
  $authHref = 'logout.php';
  $authText = 'Logout';
}
?>
<head>
  <link rel="stylesheet" href="styles.css">
</head>

<header class="topbar">
  <div class="topbar-inner">
    <div class="brand">
      <h1>Library Management System</h1>
    </div>
    <nav>
      <ul class="nav-links">
        <li><a href="index.php">Home</a></li>
        <li><a href="anounce.php">Announcements</a></li>
        <li><a href="catalog.php">Catalog</a></li>
        <li><a href="<?= h($authHref) ?>"><?= h($authText) ?></a></li>
        <li><a href="<?= h($accountHref) ?>"><?= h($accountText) ?></a></li>
        <li><a href="contact.php">Contact Us</a></li>
      </ul>
    </nav>
  </div>
</header>