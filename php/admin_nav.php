<?php 
// make sure the session is started
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// simple HTML escape helper in case it's not already defined
if (!function_exists('h')) {
  function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }
}

// admin-only login check
$isAdmin = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'Admin';

// when librarian is logged in, show Logout
$authHref = $isAdmin ? 'logout.php' : 'login.php';
$authText = $isAdmin ? 'Logout' : 'Login';
?>
<head>
  <link rel="stylesheet" href="styles.css">
  <style>
      .topbar {
      color: #fff;
      padding: 0.6rem 1rem;
      box-shadow: 0 3px 6px rgba(0,0,0,.1);
    }
    .topbar-inner {
      max-width: 1150px;
      margin: 0 auto;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .brand h1 {
      margin: 0 0 .4rem;
      font-size: 1.3rem;
      font-weight: 700;
      text-align: center;
      color: #fff;
    }
    nav {
      width: 100%;
    }
    .nav-links {
      list-style: none;
      display: flex;
      justify-content: center; /* centers horizontally */
      align-items: center;
      flex-wrap: wrap;
      gap: 1.5rem;
      padding: 0;
      margin: 0;
    }
    .nav-links li a {
      color: #fff;
      text-decoration: none;
      font-weight: 500;
      transition: opacity 0.2s;
    }
    .nav-links li a:hover {
      opacity: 0.85;
    }
    /* visually center Home and Logout slightly more by emphasis */
    .nav-links li.centered a {
      font-weight: 600;
    }
  </style>
</head>

<header class="topbar">
  <div class="topbar-inner">
    <div class="brand">
      <h1>Librarian Management System</h1>
    </div>
    <nav>
      <ul class="nav-links">
        <li class="centered"><a href="index.php">Home</a></li>
        <li><a href="role_management.php">Role Management</a></li>
        <li><a href="system_config.php">System Config</a></li>
        <li><a href="logs.php">Activity Logs</a></li>
        <li><a href="announcements.php">Create Announcements</a></li>
        <li><a href="monitoring.php">System Monitoring</a></li>
        <!-- authentication -->
        <li class="centered"><a href="<?= h($authHref) ?>"><?= h($authText) ?></a></li>
      </ul>
    </nav>
  </div>
</header>