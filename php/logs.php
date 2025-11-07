<?php
session_start();
require_once "connect.php"; // provides $database (mysqli link)

// --- Security: Admins only ---
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
  header("Location: login.php");
  exit();
}

// Small HTML-escape helper
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }

// Fetch logs from DB (newest first)
$sql = "SELECT `user`, `action`, `timestamp` FROM logs ORDER BY `timestamp` DESC";
$result = mysqli_query($database, $sql);

$logs = [];
if ($result) {
  while ($row = mysqli_fetch_assoc($result)) {
    $logs[] = $row;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Activity Logs</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    table.list { width:100%; border-collapse:collapse; margin-top:1rem; }
    table.list th, table.list td { border:1px solid #ddd; padding:.6rem; }
    table.list th { background:#f5f5f5; text-align:left; }
    .muted { color:#777; }
  </style>
</head>
<body>

<header>
  <h1>User Activity Logs</h1>
  <nav>
    <ul style="display:flex;justify-content:center;">
      <li><a href="admin.php">Dashboard</a></li>
      <li><a href="role_management.php">Role Management</a></li>
      <li><a href="system_config.php">System Config</a></li>
      <li><a href="announcements.php">Create Announcements</a></li>
      <li><a href="monitoring.php">System Monitoring</a></li>
      <li><a href="logout.php" class="logout-btn">Logout</a></li>
    </ul>
  </nav>
</header>

<main>
  <?php if (empty($logs)): ?>
    <p class="muted">No activity has been recorded yet.</p>
  <?php else: ?>
    <table class="list">
      <thead>
        <tr>
          <th style="width:25%;">User</th>
          <th>Action</th>
          <th style="width:20%;">Time</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $row): ?>
          <tr>
            <td><?= h($row['user']) ?></td>
            <td><?= h($row['action']) ?></td>
            <td><?= h($row['timestamp']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>
</body>
</html>
