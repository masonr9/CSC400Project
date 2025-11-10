<?php
session_start();
include "connect.php"; // include database connection, creates $database which links mysqli

// This indicates you must be logged in
if (!isset($_SESSION['user_id'])) { // if there is no logged-in user in the session
  header("Location: login.php"); // you will be redirected to the login page.
  // terminates the script after redirect
  exit();
}

$userId = (int) $_SESSION['user_id']; // cast user_id from session to int for safety purposes
$flash = ""; // message string to show either success or error feedback
$flashColor = "green"; // default message color

// This is where it fetches the current user
// create a prepared statement to fetch the user row
$stmt = mysqli_prepare($database, "SELECT user_id, name, email, password, role FROM users WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $userId); // bind the user id as an integer
mysqli_stmt_execute($stmt); // execute the statement
$result = mysqli_stmt_get_result($stmt); // this will get the result set
$user = mysqli_fetch_assoc($result); // fetch the single row as an associative array
mysqli_stmt_close($stmt); // close the prepared statement

if (!$user) {
  // if no user is found, it forces you to logout
  $_SESSION = []; // it will clear all session data
  session_destroy(); 
  header("Location: login.php"); // redirect to login.php
  exit(); // stop executing
}

// This is where handling the profile updates for name / email takes place
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) { // if the form was submitted
  $name = trim($_POST['name'] ?? ''); // store name from POST and trim whitespace
  $email = trim($_POST['email'] ?? ''); // store email from POST and trim whitespace

  if ($name === '' || $email === '') { // validating required fields
    $flash = "Name and email are required.";
    $flashColor = "red";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // validating the email structure
    $flash = "Invalid email address.";
    $flashColor = "red";
  } else {
    // here we are checking for any duplicate email for other users
    // preparing a query to find any other users with this email
    $stmt = mysqli_prepare($database, "SELECT user_id FROM users WHERE email = ? AND user_id <> ?");
    mysqli_stmt_bind_param($stmt, "si", $email, $userId); // bind email and current user id
    mysqli_stmt_execute($stmt); // execute the statement
    mysqli_stmt_store_result($stmt); // buffer the result to use num_rows next

    if (mysqli_stmt_num_rows($stmt) > 0) { // if any row exists, then the email is taken
      $flash = "That email is already in use.";
      $flashColor = "red";
      mysqli_stmt_close($stmt);
    } else {
      mysqli_stmt_close($stmt);

      // prepare an update for the name and email.
      $stmt = mysqli_prepare($database, "UPDATE users SET name = ?, email = ? WHERE user_id = ?");
      mysqli_stmt_bind_param($stmt, "ssi", $name, $email, $userId); // bind name, email and id
      if (mysqli_stmt_execute($stmt)) { // execute the update
        $flash = "Profile updated successfully.";
        $flashColor = "green";
        $user['name'] = $name; // keep in memory user array in sync for this request
        $user['email'] = $email;
        // this will keep the session in sync
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
      } else {
        $flash = "Server error. Please try again.";
        $flashColor = "red";
      }
      mysqli_stmt_close($stmt);
    }
  }
}

// Here is where we handle password changes
// if the password form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
  $current = $_POST['current_password'] ?? ''; 
  $new = $_POST['new_password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';

  if ($current === '' || $new === '' || $confirm === '') { // require all fields
    $flash = "All password fields are required.";
    $flashColor = "red";
  } elseif ($new !== $confirm) { // make sure the new and confirmation passwords match
    $flash = "New password and confirmation do not match.";
    $flashColor = "red";
  } elseif (strlen($new) < 8) {
    $flash = "New password must be at least 8 characters.";
    $flashColor = "red";
  } else {
    if (!password_verify($current, $user['password'])) { // verify the current password against the stored hash
      $flash = "Current password is incorrect.";
      $flashColor = "red";
    } else {
      // hash the new password
      $newHash = password_hash($new, PASSWORD_DEFAULT);
      // prepare update to write the new password
      $stmt = mysqli_prepare($database, "UPDATE users SET password = ? WHERE user_id = ?");
      mysqli_stmt_bind_param($stmt, "si", $newHash, $userId); // bind the new password and user id
      if (mysqli_stmt_execute($stmt)) { // execute the update
        $flash = "Password changed successfully.";
        $flashColor = "green";
        $user['password'] = $newHash; // updates the in-memory user record for this request
      } else {
        $flash = "Server error. Please try again.";
        $flashColor = "red";
      }
      mysqli_stmt_close($stmt);
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Member Dashboard</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    body {
      background: #f3f4f6;
    }
    .member-shell {
      max-width: 1100px;
      margin: 2.2rem auto;
      padding: 0 1rem 2.2rem;
    }
    .member-header {
      background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 60%);
      border-radius: 16px;
      padding: 1.2rem 1.5rem 1.1rem;
      color: #fff;
      box-shadow: 0 18px 30px rgba(37,99,235,.22);
      margin-bottom: 1.6rem;
    }
    .member-header h2 {
      margin: 0 0 .25rem;
    }
    .member-header p {
      margin: 0;
      color: rgba(255,255,255,.85);
      font-size: .9rem;
    }
    .quick-links {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
      margin-bottom: 1.6rem;
    }
    .quick-card {
      background: #fff;
      border-radius: 14px;
      padding: 1rem .9rem;
      border: 1px solid rgba(148,163,184,.18);
      box-shadow: 0 10px 20px rgba(15,23,42,.02);
    }
    .quick-card h3 {
      margin: 0 0 .35rem;
      font-size: .93rem;
    }
    .quick-card p {
      margin: 0 0 .4rem;
      color: #6b7280;
      font-size: .78rem;
    }
    .quick-card a {
      display: inline-block;
      font-size: .78rem;
      font-weight: 600;
      color: #2563eb;
      text-decoration: none;
    }
    .account-grid {
      display: grid;
      grid-template-columns: 1.05fr .95fr;
      gap: 1.2rem;
    }
    @media (max-width: 900px) {
      .account-grid {
        grid-template-columns: 1fr;
      }
    }
    .panel {
      background: #fff;
      border-radius: 14px;
      padding: 1.05rem 1rem .9rem;
      border: 1px solid rgba(148,163,184,.18);
      box-shadow: 0 10px 20px rgba(15,23,42,.03);
    }
    .panel h3 {
      margin-top: 0;
      margin-bottom: .5rem;
    }
    .panel label {
      display: block;
      margin-top: .55rem;
      font-size: .84rem;
      font-weight: 600;
    }
    .panel input {
      width: 100%;
      margin-top: .3rem;
      border: 1px solid #d1d5db;
      border-radius: 10px;
      padding: .5rem .55rem;
      font-size: .85rem;
    }
    .panel input:focus {
      outline: none;
      border-color: #2563eb;
      box-shadow: 0 0 0 3px rgba(37,99,235,.12);
    }
    .panel button {
      margin-top: .8rem;
      background: #2563eb;
      border: none;
      color: #fff;
      padding: .55rem 1.1rem;
      border-radius: 9999px;
      font-weight: 600;
      cursor: pointer;
    }
    .panel button:hover {
      background: #1d4ed8;
    }
    .flash-msg {
      margin-bottom: 1rem;
      padding: .6rem .8rem;
      border-radius: 10px;
      font-size: .82rem;
    }
  </style>
</head>
<body>

<?php include 'nav.php'; ?>

<main class="member-shell">
  <div class="member-header">
    <h2>Welcome back, <?= h($user['name'] ?? 'Member') ?> 👋</h2>
    <p>Manage your account details, view your library activity, and keep your info up to date.</p>
  </div>

  <?php if (!empty($flash)): ?>
    <p class="flash-msg" style="background: <?= h($flashColor)==='red' ? '#fee2e2' : '#dcfce7' ?>; color: <?= h($flashColor)==='red' ? '#b91c1c' : '#166534' ?>;">
      <?= h($flash) ?>
    </p>
  <?php endif; ?>

  <!-- quick actions -->
  <section class="quick-links">
    <div class="quick-card">
      <h3>Search Books</h3>
      <p>Find titles, authors, and more.</p>
      <a href="catalog.php">Go to catalog -></a>
    </div>
    <div class="quick-card">
      <h3>My Loans</h3>
      <p>Check due dates & returns.</p>
      <a href="loans.php">View loans -></a>
    </div>
    <div class="quick-card">
      <h3>Reservations</h3>
      <p>See reserved items.</p>
      <a href="reservations.php">My reservations -></a>
    </div>
    <div class="quick-card">
      <h3>Fines</h3>
      <p>Pay outstanding fines.</p>
      <a href="fines.php">View fines -></a>
    </div>
  </section>

  <!-- account management -->
  <section class="account-grid">
    <form method="post" class="panel">
      <h3>Profile</h3>
      <p style="margin:0 0 .6rem; color:#6b7280; font-size:.78rem;">Update your name or email address.</p>
      <label for="name">Full Name</label>
      <input type="text" id="name" name="name" required value="<?= h($user['name'] ?? '') ?>">

      <label for="email">Email</label>
      <input type="email" id="email" name="email" required value="<?= h($user['email'] ?? '') ?>">

      <button type="submit" name="update_profile">Save Changes</button>
    </form>

    <form method="post" class="panel">
      <h3>Change Password</h3>
      <p style="margin:0 0 .6rem; color:#6b7280; font-size:.78rem;">Use at least 8 characters.</p>
      
      <label for="current_password">Current Password</label>
      <input type="password" id="current_password" name="current_password" required>

      <label for="new_password">New Password</label>
      <input type="password" id="new_password" name="new_password" minlength="8" required>

      <label for="confirm_password">Confirm New Password</label>
      <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>

      <button type="submit" name="change_password">Update Password</button>
    </form>
  </section>
</main>
</body>
</html>