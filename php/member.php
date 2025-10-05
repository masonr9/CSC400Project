<?php
session_start();
include "connect.php";

// This indicates you must be logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  // terminates the script
  exit();
}

$userId = (int) $_SESSION['user_id'];
$flash = "";
$flashColor = "green";

// This is where the fetches the current user
$stmt = mysqli_prepare($database, "SELECT user_id, name, email, password, role FROM users WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$user) {
  // if no user is found, it forces you to logout
  $_SESSION = [];
  session_destory();
  header("Location: login.php");
  exit();
}

// This is where handling the profile updates for name / email takes place
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');

  if ($name === '' || $email === '') {
    $flash = "Name and email are required.";
    $flashColor = "red";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $flash = "Invalid email address.";
    $flashColor = "red";
  } else {
    // here we are checking for any duplicate email for other users
    $stmt = mysqli_prepare($database, "SELECT user_id FROM users WHERE email = ? AND user_id <> ?");
    mysqli_stmt_bind_param($stmt, "si", $email, $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) > 0) {
      $flash = "That email is already in use.";
      $flashColor = "red";
      mysqli_stmt_close($stmt);
    } else {
      mysqli_stmt_close($stmt);

      $stmt = mysqli_prepare($database, "UPDATE users SET name = ?, email = ? WHERE user_id = ?");
      mysqli_stmt_bind_param($stmt, "ssi", $name, $email, $userId);
      if (mysqli_stmt_execute($stmt)) {
        $flash = "Profile updated successfully.";
        $flashColor = "green";
        $user['name'] = $name;
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

// Here is where we handle password changes.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
  $current = $_POST['current_password'] ?? '';
  $new = $_POST['new_password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';

  if ($current === '' || $new === '' || $confirm === '') {
    $flash = "All password fields are required.";
    $flashColor = "red";
  } elseif ($new !== $confirm) {
    $flash = "New password and confirmation do not match.";
    $flashColor = "red";
  } elseif (strlen($new) < 8) {
    $flash = "New password must be at least 8 characters.";
    $flashColor = "red";
  } else {
    if ($current !== $user['password']) {
      $flash = "Current password is incorrect.";
      $flashColor = "red";
    } else {
      $stmt = mysqli_prepare($database, "UPDATE users SET password = ? WHERE user_id = ?");
      mysqli_stmt_bind_param($stmt, "si", $new, $userId);
      if (mysqli_stmt_execute($stmt)) {
        $flash = "Password changed successfully.";
        $flashColor = "green";
        $user['password'] = $new;
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
</head>
<body>

<header>
  <h1>Library Management System</h1>
  <nav>
    <ul>
      <li><a href="index.php">Home</a></li>
      <li><a href="catalog.php">Catalog</a></li>
      <li><a href="login.php">Login</a></li>
      <li><a href="signup.php">Sign Up</a></li>
      <li><a href="contact.php">Contact Us</a></li>
    </ul>
  </nav>
</header>

<main>
  <section>
    <h2>Member Dashboard</h2>
    <ul>
      <li>Search Books</li>
      <li>My Loans</li>
      <li>My Reservations</li>
      <li>Fines</li>
    </ul>
  </section>
    <h2>My Account</h2>

    <?php if (!empty($flash)): ?>
      <p style="color: <?= htmlspecialchars($flashColor) ?>;"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>

    <form method="post" class="form-box">
      <h3>Profile - Change Your Name / Email</h3>
      <label for="name">Full Name</label>
      <input type="text" id="name" name="name" required value="<?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES) ?>">

      <label for="email">Email</label>
      <input type="email" id="email" name="email" required value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES) ?>">

      <button type="submit" name="update_profile" value="1">Save Changes</button>
    </form>

    <form method="post" class="form-box">
      <h3>Change Password</h3>
      
      <label for="current_password">Current Password</label>
      <input type="password" id="current_password" name="current_password" required value="<?= htmlspecialchars($user['password'] ?? '', ENT_QUOTES) ?>">

      <label for="new_password">New Password</label>
      <input type="password" id="new_password" name="new_password" minlength="8" required>

      <label for="confirm_password">Confirm New Password</label>
      <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>

      <button type="submit" name="change_password" value="1">Update Password</button>
    </form>
</main>
</body>
</html>