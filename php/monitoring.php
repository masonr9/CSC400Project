<?php
session_start(); // start or resume the current session so we can read $_SESSION
require_once "connect.php"; // include DB connection file that defines $database (mysqli link)

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') { // if the user is not logged in as an Admin
  header("Location: login.php"); // redirect them to the login page
  exit(); // stop executing the script
}

// Small helper function to safely escape HTML output
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

// Display name (prefer session, fall back to DB, final fallback to Admin)
$displayName = $_SESSION['name'] ?? ''; // try to get the display name from the session
if ($displayName === '' && isset($_SESSION['user_id'])) { // if not present, but we have a user_id
  $uid = (int)$_SESSION['user_id']; // normalize user_id to int
  if ($uid > 0) { // only query if user_id is valid
    if ($stmt = mysqli_prepare($database, "SELECT name FROM users WHERE user_id = ? LIMIT 1")) { // prepare statement to fetch name
      mysqli_stmt_bind_param($stmt, "i", $uid); // bind user_id as integer
      mysqli_stmt_execute($stmt); // execute the query
      $res = mysqli_stmt_get_result($stmt); // get the result set
      if ($row = mysqli_fetch_assoc($res)) { // fetch single row if found
        $displayName = $row['name'] ?? ''; // extract the name or empty string
        if ($displayName !== '') { // if we found a non-empty name
          $_SESSION['name'] = $displayName; // cache it in session to avoid re-querying
        }
      }
      mysqli_stmt_close($stmt); // close the prepared statement
    }
  }
}
if ($displayName === '') { // if name is still empty after all attempts
  $displayName = 'Admin'; // use a safe fallback to Admin
}

// System stats
$totalUsers = 0; // initialize total users count
$totalBooks = 0;
$totalLogs  = 0;

if ($qr = mysqli_query($database, "SELECT COUNT(*) AS c FROM users")) { // query count of users
  $totalUsers = (int)(mysqli_fetch_assoc($qr)['c'] ?? 0); // read the count or default to 0
  mysqli_free_result($qr); // free the result resources
}
if ($qr = mysqli_query($database, "SELECT COUNT(*) AS c FROM books")) { // query count of books
  $totalBooks = (int)(mysqli_fetch_assoc($qr)['c'] ?? 0); // read the count or default to 0
  mysqli_free_result($qr); // free the result resources
}
if ($qr = mysqli_query($database, "SELECT COUNT(*) AS c FROM logs")) { // query count of logs
  $totalLogs = (int)(mysqli_fetch_assoc($qr)['c'] ?? 0); // read the count or default to 0
  mysqli_free_result($qr); // free the result resources
}

// Recent 10 logs to not overwhelm site
$recentLogs = [];
if ($stmt = mysqli_prepare($database, "SELECT `user`, `action`, `timestamp` FROM logs ORDER BY id DESC LIMIT 10")) { // prepare statement for recent logs
  mysqli_stmt_execute($stmt); // execute the statement
  $res = mysqli_stmt_get_result($stmt); // get the result set
  if ($res) { // if result set exists
    while ($row = mysqli_fetch_assoc($res)) { // iterate through rows
      $recentLogs[] = $row; // append each log row to recentLogs
    }
  }
  mysqli_stmt_close($stmt);
}

// Maintenance status (no redirect here, just display)
$config_file = __DIR__ . "/maintenance_status.txt"; // define the path to the maintenance flag file
$current_status = file_exists($config_file) ? trim((string)file_get_contents($config_file)) : "off"; // read current status or default 'off'
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
    <ul style="display:flex;justify-content:center;">
      <li><a href="admin.php">Dashboard</a></li>
      <li><a href="role_management.php">Role Management</a></li>
      <li><a href="system_config.php">System Config</a></li>
      <li><a href="logs.php">Activity Logs</a></li>
      <li><a href="announcements.php">Create Announcements</a></li>
      <li><a href="logout.php" class="logout-btn">Logout</a></li>
    </ul>
  </nav>
</header>

<main class="container">
  <h2>Welcome, <?= h($displayName) ?> (Admin)</h2>

  <section class="form-box">
    <h3>📊 System Overview</h3>
    <p><strong>Maintenance Mode:</strong> <span class="badge"><?= h(strtoupper($current_status)) ?></span></p>
    <p><strong>Total Registered Users:</strong> <?= h($totalUsers) ?></p>
    <p><strong>Total Books in Catalog:</strong> <?= h($totalBooks) ?></p>
    <p><strong>Total Logged Actions:</strong> <?= h($totalLogs) ?></p>
  </section>

  <section class="form-box" style="margin-top:20px;">
    <h3>🕒 Recent Activity Logs</h3>
    <?php if (empty($recentLogs)): ?>
      <p class="muted">No recent logs.</p>
    <?php else: ?>
      <table class="list">
        <thead>
          <tr><th>User</th><th>Action</th><th>Timestamp</th></tr>
        </thead>
        <tbody>
        <?php foreach ($recentLogs as $row): ?>
          <tr>
            <td><?= h($row['user'] ?? '') ?></td>
            <td><?= h($row['action'] ?? '') ?></td>
            <td><?= h($row['timestamp'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</main>

</body>
</html>
