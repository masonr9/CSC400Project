<?php
session_start();
require_once "connect.php";

// only admins with a valid login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
  header("Location: login.php");
  exit();
}

// safe HTML escape helper
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

// fetch display name
$displayName = $_SESSION['name'] ?? '';
if ($displayName === '') {
  $uid = (int)$_SESSION['user_id'];
  $stmt = mysqli_prepare($database, "SELECT name FROM users WHERE user_id = ? LIMIT 1");
  mysqli_stmt_bind_param($stmt, "i", $uid);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  if ($row = mysqli_fetch_assoc($res)) {
    $displayName = $row['name'] ?? '';
    if ($displayName !== '') {
      $_SESSION['name'] = $displayName;
    }
  }
  mysqli_stmt_close($stmt);
}
if ($displayName === '') {
  $displayName = 'Admin';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .dashboard-shell {
      max-width: 1100px;
      margin: 1.75rem auto 3rem;
      padding: 0 1rem;
    }

    /* hero */
    .hero {
      background: radial-gradient(circle at top, #fee2e2 0%, #ffffff 40%, #ffffff 100%);
      border: 1px solid #e5e7eb;
      border-radius: 1rem;
      padding: 2.5rem 2rem;
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      gap: 1.5rem;
      align-items: center;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    }

    .hero-body h2 {
      font-size: 2.1rem;
      margin: 0.4rem 0 0.6rem;
      color: #111827;
    }

    .hero-text {
      margin: 0;
      color: #4b5563;
      max-width: 480px;
    }

    .hero-pill {
      display: inline-block;
      background: #fee2e2;
      color: #b91c1c;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: .01em;
    }

    .hero-actions {
      margin-top: 1.2rem;
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .btn {
      border: none;
      border-radius: .6rem;
      padding: .55rem 1rem;
      cursor: pointer;
      font-weight: 600;
      font-size: .9rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: .35rem;
    }

    .btn.primary {
      background: #dc2626;
      color: #fff;
      box-shadow: 0 8px 20px rgba(220, 38, 38, 0.3);
    }

    .btn.primary:hover {
      background: #b91c1c;
    }

    .btn.ghost {
      background: #fff;
      color: #1f2937;
      border: 1px solid #e5e7eb;
    }

    /* task grid */
    .task-grid {
      margin-top: 2.2rem;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
      gap: 1rem;
    }

    .task-card {
      background: #fff;
      border: 1px solid #edf2f7;
      border-radius: .75rem;
      padding: 1.15rem 1.1rem 1.05rem;
      box-shadow: 0 4px 14px rgba(15,23,42,.02);
      transition: transform .2s;
    }

    .task-card:hover {
      transform: translateY(-3px);
    }

    .task-card h3 {
      margin-top: 0;
      margin-bottom: .35rem;
      font-size: 1rem;
      color: #111827;
    }

    .task-card p {
      margin-top: 0;
      margin-bottom: .5rem;
      color: #6b7280;
      font-size: .9rem;
    }

    .task-card a {
      font-weight: 600;
      color: #dc2626;
      text-decoration: none;
      font-size: .85rem;
    }

    .task-card a:hover {
      text-decoration: underline;
    }

    @media (max-width: 768px) {
      .hero {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

<header>
  <h1>Admin Dashboard</h1>
  <nav>
    <ul>
      <li><a href="admin.php">Dashboard</a></li>
      <li><a href="role_management.php">Role Management</a></li>
      <li><a href="system_config.php">System Config</a></li>
      <li><a href="logs.php">Activity Logs</a></li>
      <li><a href="logout.php" class="logout-btn">Logout</a></li>
    </ul>
  </nav>
</header>

<main>
  <h2>Welcome, <?= h($displayName)?> </h2>
  <section>
    <ul class="task-list">
      <li><a href="role_management.php">Manage User Roles</a></li>
      <li><a href="system_config.php">Configure System</a></li>
      <li><a href="logs.php">View Activity Logs</a></li>
      <li><a href="announcements.php">Create Announcements</a></li>
    </ul>
  </section>

  <!-- task grid -->
  <section class="task-grid">
    <article class="task-card">
      <h3>Role Management</h3>
      <p>Assign and manage roles for users.</p>
      <a href="role_management.php">Go to roles →</a>
    </article>
    <article class="task-card">
      <h3>System Configuration</h3>
      <p>Adjust library system settings and preferences.</p>
      <a href="system_config.php">Configure system →</a>
    </article>
    <article class="task-card">
      <h3>Activity Logs</h3>
      <p>Review user and system actions for accountability.</p>
      <a href="logs.php">View logs →</a>
    <

