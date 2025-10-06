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
    if ($current !== $user['password']) { // compare the current password with stored password
      $flash = "Current password is incorrect.";
      $flashColor = "red";
    } else {
      // prepare update to write the new password
      $stmt = mysqli_prepare($database, "UPDATE users SET password = ? WHERE user_id = ?");
      mysqli_stmt_bind_param($stmt, "si", $new, $userId); // bind the new password and user id
      if (mysqli_stmt_execute($stmt)) { // execute the update
        $flash = "Password changed successfully.";
        $flashColor = "green";
        $user['password'] = $new; // updates the in-memory user record for this request
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

    <?php if (!empty($flash)): ?> <!-- show feedback message when $flash is set -->
      <p style="color: <?= htmlspecialchars($flashColor) ?>;"><?= htmlspecialchars($flash) ?></p>
    <?php endif; ?>

    <form method="post" class="form-box">
      <h3>Profile - Change Your Name / Email</h3>
      <label for="name">Full Name</label>
      <!-- pre-fill with current name and escape any quotes -->
      <input type="text" id="name" name="name" required value="<?= htmlspecialchars($user['name'] ?? '', ENT_QUOTES) ?>">

      <label for="email">Email</label>
      <!-- pre-fill with current email and escape any quotes -->
      <input type="email" id="email" name="email" required value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES) ?>">

      <button type="submit" name="update_profile">Save Changes</button>
    </form>

    <form method="post" class="form-box">
      <h3>Change Password</h3>
      
      <label for="current_password">Current Password</label>
      <!-- pre-fill with current password -->
      <input type="password" id="current_password" name="current_password" required value="<?= htmlspecialchars($user['password'] ?? '', ENT_QUOTES) ?>">

      <label for="new_password">New Password</label>
      <input type="password" id="new_password" name="new_password" minlength="8" required>

      <label for="confirm_password">Confirm New Password</label>
      <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>

      <button type="submit" name="change_password">Update Password</button>
    </form>
</main>
</body>
</html>