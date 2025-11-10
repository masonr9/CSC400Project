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

$result = mysqli_query($database, "SELECT COUNT(*) AS total FROM users");
if ($row = mysqli_fetch_assoc($result)) $totalUsers = $row['total'];

$result = mysqli_query($database, "SELECT COUNT(*) AS total FROM books");
if ($row = mysqli_fetch_assoc($result)) $totalBooks = $row['total'];

$result = mysqli_query($database, "SELECT COUNT(*) AS total FROM borrowed_books");
if ($row = mysqli_fetch_assoc($result)) $borrowedBooks = $row['total'];

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
    /* === Same CSS as manage_books.php === */

    /* main shell */
    .home-shell {
      max-width: 1100px;
      margin: 1.75rem auto 3rem;
      padding: 0 1rem;
    }

    /* hero */
    .hero {
      background: radial-gradient(circle at top, #dbeafe 0%, #ffffff 40%, #ffffff 100%);
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
      background: #e0ecff;
      color: #1d4ed8;
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
      background: #2563eb;
      color: #fff;
      box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
    }

    .btn.primary:hover {
      background: #1d4ed8;
    }

    .btn.ghost {
      background: #fff;
      color: #1f2937;
      border: 1px solid #e5e7eb;
    }

    .hero-illustration {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .hero-card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: .75rem;
      padding: 1rem 1.1rem;
      box-shadow: 0 6px 16px rgba(15, 23, 42, 0.03);
    }

    .hero-card-title {
      font-size: .75rem;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #6b7280;
      margin-bottom: .25rem;
    }

    .hero-card-value {
      font-weight: 600;
      color: #111827;
    }

    .hero-card-value.small {
      font-size: .8rem;
    }

    /* quick grid */
    .quick-grid {
      margin-top: 2.2rem;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
      gap: 1rem;
    }

    .quick-card {
      background: #fff;
      border: 1px solid #edf2f7;
      border-radius: .75rem;
      padding: 1.15rem 1.1rem 1.05rem;
      box-shadow: 0 4px 14px rgba(15,23,42,.02);
    }

    .quick-card h3 {
      margin-top: 0;
      margin-bottom: .35rem;
      font-size: 1rem;
      color: #111827;
    }

    .quick-card p {
      margin-top: 0;
      margin-bottom: .5rem;
      color: #6b7280;
      font-size: .9rem;
    }

    .quick-card a {
      font-weight: 600;
      color: #2563eb;
      text-decoration: none;
      font-size: .85rem;
    }

    .quick-card a:hover {
      text-decoration: underline;
    }

    /* small screens */
    @media (max-width: 768px) {
      .hero {
        grid-template-columns: 1fr;
      }
      .topbar-inner {
        flex-direction: column;
        align-items: flex-start;
      }
      .nav-links {
        flex-wrap: wrap;
      }
    }

    /* Admin dashboard custom styles */
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

  <section class="form-box">
    <h3>⏰ System Time</h3>
    <p id="clock" style="font-size: 1.5em; font-weight: bold;"></p>
  </section>
</main>

<script>
  function updateClock() {
    let now = new Date();
    document.getElementById("clock").innerText = now.toLocaleString();
  }
  setInterval(updateClock, 1000);
  updateClock();
</script>

</body>
</html>

