<?php
session_start();
require_once "connect.php"; // assumes $database = mysqli connection

// --- Access Control ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
  header("Location: login.php");
  exit();
}

// --- Helper function for safe HTML output ---
function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES);
}

// --- Get Display Name ---
$displayName = $_SESSION['name'] ?? '';
if ($displayName === '' && isset($_SESSION['user_id'])) {
  $uid = (int)$_SESSION['user_id'];
  $stmt = mysqli_prepare($database, "SELECT name FROM users WHERE user_id = ? LIMIT 1");
  mysqli_stmt_bind_param($stmt, "i", $uid);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  if ($row = mysqli_fetch_assoc($res)) {
    $displayName = $row['name'];
    $_SESSION['name'] = $displayName;
  }
  mysqli_stmt_close($stmt);
}
if ($displayName === '') $displayName = 'Admin';

// --- Quick Stats Queries ---
$totalUsers = 0;
$totalBooks = 0;
$borrowedBooks = 0;
$overdueBooks = 0;

// Total Users
$result = mysqli_query($database, "SELECT COUNT(*) AS total FROM users");
if ($row = mysqli_fetch_assoc($result)) $totalUsers = $row['total'];

// Total Books
$result = mysqli_query($database, "SELECT COUNT(*) AS total FROM books");
if ($row = mysqli_fetch_assoc($result)) $totalBooks = $row['total'];

// Borrowed Books
$result = mysqli_query($database, "SELECT COUNT(*) AS total FROM borrowed_books");
if ($row = mysqli_fetch_assoc($result)) $borrowedBooks = $row['total'];

// Overdue Books
$result = mysqli_query($database, "SELECT COUNT(*) AS total FROM borrowed_books WHERE due_date < NOW()");
if ($row = mysqli_fetch_assoc($result)) $overdueBooks = $row['total'];

// --- Recent Activity (last 5 logs) ---
$logs = [];
$result = mysqli_query($database, "SELECT user, action, time FROM user_logs ORDER BY time DESC LIMIT 5");
while ($row = mysqli_fetch_assoc($result)) {
  $logs[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .dashboard-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem;
    }
    .stat-card {
      background: #3498db;
      color: white;
      text-align: center;
      padding: 1.2rem;
      border-radius: 12px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .stat-card h3 {
      font-size: 2rem;
      margin: 0;
    }
    .activity-feed {
      list-style: none;
      padding: 0;
    }
    .activity-feed li {
      border-bottom: 1px solid #ddd;
      padding: 8px 0;
    }
    .logout-btn {
      background: #e74c3c;
      color: white;
      border: none;
      padding: 8px 12px;
      border-radius: 6px;
      cursor: pointer;
    }
  </style>
</head>
<body>

<header>
  <h1><?= h($_SESSION['library_name'] ?? 'Library Management System') ?></h1>
  <nav>
    <ul>
      <li><a href="admin_dashboard.php">Admin Menu</a></li>
      <li><a href="role_management.php">Roles</a></li>
      <li><a href="system_config.php">Settings</a></li>
      <li><a href="logs.php">Logs</a></li>
      <li><a href="logout.php" class="logout-btn">Logout</a></li>
    </ul>
  </nav>
</header>

<main>
  <h2>📊 Admin Dashboard</h2>
  <p>Welcome, <?= h($displayName) ?> (Admin)</p>

  <!-- Quick Stats Section -->
  <section class="dashboard-cards">
    <div class="stat-card">
      <h3><?= h($totalUsers) ?></h3>
      <p>Total Users</p>
    </div>
    <div class="stat-card">
      <h3><?= h($totalBooks) ?></h3>
      <p>Total Books</p>
    </div>
    <div class="stat-card">
      <h3><?= h($borrowedBooks) ?></h3>
      <p>Borrowed Books</p>
    </div>
    <div class="stat-card">
      <h3><?= h($overdueBooks) ?></h3>
      <p>Overdue Books</p>
    </div>
  </section>

  <!-- Recent Activity Feed -->
  <section class="form-box">
    <h3>🕓 Recent Activity</h3>
    <ul class="activity-feed">
      <?php if (empty($logs)): ?>
        <li>No recent activity yet.</li>
      <?php else: ?>
        <?php foreach ($logs as $log): ?>
          <li><?= h($log['time']) ?> — <?= h($log['user']) ?>: <?= h($log['action']) ?></li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>
  </section>

  <!-- System Clock -->
  <section class="form-box">
    <h3>⏰ System Time</h3>
    <p id="clock" style="font-size: 1.5em; font-weight: bold;"></p>
  </section>
</main>

<script>
  // --- Live Clock ---
  function updateClock() {
    let now = new Date();
    document.getElementById("clock").innerText = now.toLocaleString();
  }
  setInterval(updateClock, 1000);
  updateClock();
</script>

</body>
</html>
