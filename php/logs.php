<?php
session_start();
require_once "connect.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
  header("Location: login.php");
  exit();
}

$result = $conn->query("SELECT * FROM logs ORDER BY id DESC");
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Activity Logs</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
<header>
  <h1>User Activity Logs</h1>
  <nav>
    <ul>
      <li><a href="admin_dashboard.php">Dashboard</a></li>
      <li><a href="logout.php" class="logout-btn">Logout</a></li>
    </ul>
  </nav>
</header>

<main>
  <table border="1" width="100%">
    <tr><th>User</th><th>Action</th><th>Timestamp</th></tr>
    <?php while ($row = $result->fetch_assoc()) { ?>
    <tr>
      <td><?php echo htmlspecialchars($row['user']); ?></td>
      <td><?php echo htmlspecialchars($row['action']); ?></td>
      <td><?php echo $row['timestamp']; ?></td>
    </tr>
    <?php } ?>
  </table>
</main>
</body>
</html>
