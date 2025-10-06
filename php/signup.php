<?php
  include "connect.php"; // This is where $database comes from

  $signupMessage = "";
  $msgColor = "green";

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // collecting inputs and validating them
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'Member'; // member is the default role

    if ($name === '' || $email === '' || $password === '') {
      $signupMessage = "All fields are required.";
      $msgColor = "red";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      // FILTER_VALIDATE_EMAIL is used to validate the email address.
      // If the email is not valid, it will update the signupMessage variable.
      $signupMessage = "Invalid email address.";
      $msgColor = "red";
      // if the length of password is less than 8, it will update the signupMessage variable.
    } elseif (strlen($password) < 8) {
      $signupMessage = "Your password must be at least 8 characters.";
      $msgColor = "red";
    } else {
      // Check for duplicate email, prepare the select statement to check
      $stmt = mysqli_prepare($database, "SELECT user_id FROM users where email = ?");
      // bind the previous variable to the email variable, the "s" means it is a string which means it tells 
      // mysqli what data type each placeholder in the select statement should be.
      mysqli_stmt_bind_param($stmt, "s", $email);
      mysqli_stmt_execute($stmt);
      // store the result set in the variable
      mysqli_stmt_store_result($stmt);

      // the method represents the number of rows that were consistent with the select statement above.
      if (mysqli_stmt_num_rows($stmt) > 0) {
        $signupMessage = "Email already registered!";
        $msgColor = "red";
        mysqli_stmt_close($stmt);
      } else {
        mysqli_stmt_close($stmt);

        // Hash password
        // $hashed = password_hash($password, PASSWORD_BCRYPT);

        // ensures role is valid
        $allowedRoles = ['Member','Librarian','Admin'];
        if (!in_array($role, $allowedRoles, true)) {
          $role = 'Member';
        }

        // preparing to insert the new account into the database
        $stmt = mysqli_prepare($database, "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $password, $role);

        if (mysqli_stmt_execute($stmt)) {
          $signupMessage = "Registration successful! You can now login.";
          $msgColor = "green";
          // clear POST values so the form is reset on refresh
          $_POST = [];
        } else {
          $signupMessage = "Server error. Please try again.";
          $msgColor = "red";
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
  <title>Sign Up</title>
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
  <form class="form-box" id="signupForm" name="Registration" method="post" action="signup.php" novalidate>
    <h2>Create Your Library Account</h2>
    
    <label>Full Name:</label>
    <!-- ENT_QUOTES is a flag for the method htmlspecialchars() that tells it to escape both single and double quotes. -->
    <input type="text" id="fullName" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES) ?>">
    
    <label>Email:</label>
    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>">
    
    <label>Password:</label>
    <input type="password" id="password" name="password" required>
    
    <label>Role:</label>
    <select id="role" name="role" required>
      <?php
      // function that echoes an option for each role when selecting a role during the signup process.
        $currentRole = $_POST['role'] ?? 'Member';
        foreach (['Member','Admin','Librarian'] as $r) {
          $sel = ($currentRole === $r) ? 'selected' : '';
          echo "<option value=\"$r\" $sel>$r</option>";
        }
      ?>
    </select>
    
    <button type="submit" name="submit">Register</button>
  </form>
      <!-- convert the php variables msgColor and signupMessage to html entities -->
  <p id="signupMessage" style="text-align:center;color:<?= htmlspecialchars($msgColor) ?>;">
    <?= htmlspecialchars($signupMessage) ?>
  </p>
</main>
</body>
</html>

