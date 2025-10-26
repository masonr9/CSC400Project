<?php
session_start();
require_once "connect.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
  header("Location: login.php");
  exit();
}

// Simple example: toggle maintenance mode file
$config_file = "maintenance_status.txt";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $status = $_POST['maintenance'];
  file_put_contents($config_file, $status);
  $message = "Maintenance mode set to: $status";
}

$current_status = file_exists($config_file) ? file_get_contents($config_file) : "off";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>System Configuration</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
<header>
  <h1>System Configuration</h1>
  <nav>
    <ul>
      <li><a href="admin_dashboard.php">Dashboard</a></li>
      <li><a href="logout.php" class="logout-btn">Logout</a></li>
    </ul>
  </nav>
</header>

<main>
  <?php if (isset($message)) echo "<p style='color:green;'>$message</p>"; ?>
  <form method="POST">
    <label>Maintenance Mode:</label>
    <select name="maintenance">
      <option value="on" <?php if ($current_status=='on') echo 'selected'; ?>>ON</option>
      <option value="off" <?php if ($current_status=='off') echo 'selected'; ?>>OFF</option>
    </select>
    <button type="submit">Save</button>
  </form>
</main>
</body>
</html>
