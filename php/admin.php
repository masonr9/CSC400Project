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
  <style>
    body {
      background: #f1f5f9;
    }
    main.container {
      max-width: 1100px;
      margin: 1.75rem auto 3rem;
      padding: 0 1rem;
    }
    .page-heading {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1rem;
    }
    .page-heading h2 {
      margin: 0;
      font-size: 1.5rem;
      color: #0f172a;
    }
    .subtitle {
      color: #94a3b8;
      font-size: .8rem;
    }
    .welcome-card {
      background: #fff;
      border-radius: 12px;
      padding: 1.25rem 1.25rem 1.1rem;
      box-shadow: 0 10px 30px rgba(15,23,42,.03);
      border: 1px solid rgba(148,163,184,.25);
      margin-bottom: 1.25rem;
    }
    .welcome-card p {
      margin-top: .25rem;
      color: #64748b;
    }
    .quick-links {
      display: flex;
      gap: .6rem;
      flex-wrap: wrap;
      margin-top: .8rem;
    }
    .quick-links a {
      background: #eff6ff;
      color: #1d4ed8;
      border-radius: 9999px;
      padding: .3rem .75rem;
      font-size: .75rem;
      text-decoration: none;
      font-weight: 600;
    }
    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
      gap: 1rem;
    }
    .dash-card {
      background: #fff;
      border-radius: 12px;
      padding: 1rem 1rem .9rem;
      box-shadow: 0 10px 30px rgba(15,23,42,.03);
      border: 1px solid rgba(148,163,184,.19);
    }
    .dash-card h3 {
      margin-top: 0;
      margin-bottom: .4rem;
      font-size: 1rem;
      color: #0f172a;
    }
    .dash-card p {
      margin: 0 0 .6rem;
      font-size: .82rem;
      color: #64748b;
    }
    .dash-card a {
      font-size: .78rem;
      font-weight: 600;
      color: #2563eb;
      text-decoration: none;
    }
    .dash-card a:hover {
      text-decoration: underline;
    }
    @media (max-width: 640px) {
      .page-heading {
        flex-direction: column;
        align-items: flex-start;
      }
      .quick-links {
        flex-direction: column;
        align-items: flex-start;
      }
    }
  </style>
</head>
<body>

<?php include 'admin_nav.php'; ?>

<main class="container">
  <div class="page-heading">
    <div>
      <h2>Welcome, <?= h($displayName) ?> 👋</h2>
      <p class="subtitle">Admin dashboard · manage system and users</p>
    </div>
  </div>

  <section class="welcome-card">
    <h3 style="margin-top:0;">Admin Shortcuts</h3>
    <p>Select one of the quick actions below to manage the library system.</p>
    <div class="quick-links">
      <a href="role_management.php">Manage Roles</a>
      <a href="system_config.php">System Config</a>
      <a href="logs.php">View Logs</a>
      <a href="announcements.php">Announcements</a>
      <a href="monitoring.php">Monitoring</a>
    </div>
  </section>

  <section class="dashboard-grid">
    <article class="dash-card">
      <h3>Role Management</h3>
      <p>Assign Admin, Librarian, or Member roles to users.</p>
      <a href="role_management.php">Go to role management -></a>
    </article>
    <article class="dash-card">
      <h3>System Configuration</h3>
      <p>Toggle maintenance and adjust system-level settings.</p>
      <a href="system_config.php">Open system config -></a>
    </article>
    <article class="dash-card">
      <h3>Activity Logs</h3>
      <p>Review system and user actions for auditing.</p>
      <a href="logs.php">View audit logs -></a>
    </article>
    <article class="dash-card">
      <h3>Announcements</h3>
      <p>Post updates that members can see on the site.</p>
      <a href="announcements.php">Manage announcements -></a>
    </article>
    <article class="dash-card">
      <h3>System Monitoring</h3>
      <p>Check counts of users, books, and recent log entries.</p>
      <a href="monitoring.php">Open monitoring -></a>
    </article>
  </section>
</main>
</body>
</html>
