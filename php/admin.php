<?php
session_start(); // start or resume the session to access $_SESSION data 
require_once "connect.php"; // this is where $database comes from

// only admins with a valid login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') { // if no role set or role isn't admin
  header("Location: login.php"); // redirect the user to the login page
  exit(); // stop executing
}

// helper function to HTML-escape any output safely
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

// use the name from session, if missing, fetch from DB once
$displayName = $_SESSION['name'] ?? ''; // try to get the user's name from the session
if ($displayName === '') { // if not present in session
  $uid = (int)$_SESSION['user_id']; // read user id from session and cast to int for safety
  $stmt = mysqli_prepare($database, "SELECT name FROM users WHERE user_id = ? LIMIT 1"); // prepare query to fetch user name
  mysqli_stmt_bind_param($stmt, "i", $uid); // bind the user id as an integer parameter
  mysqli_stmt_execute($stmt); // execute the prepared statement
  $res = mysqli_stmt_get_result($stmt); // get the result set for the executed query
  if ($row = mysqli_fetch_assoc($res)) { // fetch the row as an associative array if found
    $displayName = $row['name'] ?? ''; // pull the name column or default to empty string
    // Keep session in sync so we don't need to re-query next time
    if ($displayName !== '') { // if we successfully obtained a non-empty name
      $_SESSION['name'] = $displayName; // store it back into the session for future requests
    }
  }
  mysqli_stmt_close($stmt); // close the statement
}

// final fallback just in case
if ($displayName === '') { // if the name is still empty after all attempts
  $displayName = 'Admin'; // use a safe default display name
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
</main>

</body>
</html>
