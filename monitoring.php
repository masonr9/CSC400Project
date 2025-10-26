<?php
session_start();
require_once "connect.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
  header("Location: login.php");
  exit();
}

// Get system stats
$totalUsers = $conn->query("SELECT COUNT(*) AS count FROM users")->fetch_assoc()['count'];
$totalBooks = $conn->query("SELECT COUNT(*) AS count FROM books")->fetch_assoc()['count'];
$totalLogs = $conn->query("SELECT COUNT(*) AS count FROM logs")->fetch_assoc()['count'];

// Get recent 10 logs
$recentLogs = $conn->query("SELECT * FROM logs ORDER BY id DESC LIMIT 10");

// Maintenance status
$config_file = "maintenance_status.txt";
$current_status = file_exists($config_file) ? trim(file_get_contents($config_file)) : "off";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>System Monitoring</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<header>
  <h1>System Monitoring</h1>
  <nav>
    <ul>
      <li><a href="admin_dashboard.php">Dashboard</a></li>
      <li><a href="role_management.php">Role Management</a></li>
      <li><a href="system_config.php">System Config</a></li>
      <li><a href="logs.php">Activity Logs</a></li>
      <li><a href="logout.php" class="logout-btn">Logout</a></li>
    </ul>
  </nav>
</header>

<main>
  <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?> (Admin)</h2>

  <section class="form-box">
    <h3>📊 System Overview</h3>
    <p><strong>Maintenance Mode:</strong> <?php echo strtoupper($current_status); ?></p>
    <p><strong>Total Registered Users:</strong> <?php echo $totalUsers; ?></p>
    <p><strong>Total Books in Catalog:</strong> <?php echo $totalBooks; ?></p>
    <p><strong>Total Logged Actions:</strong> <?php echo $totalLogs; ?></p>
  </section>

  <section class="form-box" style="margin-top:20px;">
    <h3>🕒 Recent Activity Logs</h3>
    <table border="1" width="100%">
      <tr><th>User</th><th>Action</th><th>Timestamp</th></tr>
      <?php while ($row = $recentLogs->fetch_assoc()) { ?>
      <tr>
        <td><?php echo htmlspecialchars($row['user']); ?></td>
        <td><?php echo htmlspecialchars($row['action']); ?></td>
        <td><?php echo $row['timestamp']; ?></td>
      </tr>
      <?php } ?>
    </table>
  </section>
</main>

</body>
</html>
