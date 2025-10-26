<?php
session_start();
require_once "connect.php";

// Security: only admins
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
  header("Location: login.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<header>
  <h1>Library Management System - Admin</h1>
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
  <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user']); ?> (Admin)</h2>
  <section>
    <ul class="task-list">
      <li><a href="role_management.php">Manage User Roles</a></li>
      <li><a href="system_config.php">Configure System</a></li>
      <li><a href="logs.php">View Activity Logs</a></li>
    </ul>
  </section>
</main>

</body>
</html>
