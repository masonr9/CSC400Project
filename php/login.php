<?php
  session_start();
  include "connect.php"; // this is where $database comes from

  $loginMessage = ""; // this is the message that will be shown to the user if it is successful or has errors
  $msgColor = "red"; // default message color

  // handle form submission only when this page receives a POST with the submit button
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) { // Check request method and presence of submit button
    $email = trim($_POST['email'] ?? ''); // store email from post and trim any whitespace
    $password = $_POST['password'] ?? ''; // store password from post

    if ($email === '' || $password === '') { // validate required fields
      $loginMessage = "Email and password are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // validate email format
      $loginMessage = "Invalid email address.";
    } else {
      // This will look up the user by their email, it prepares a parameterized query to prevent SQL injection
      // the select statement fetches one user by email
      $stmt = mysqli_prepare($database, "SELECT user_id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
      mysqli_stmt_bind_param($stmt, "s", $email); // binds a parameter with the string $email
      mysqli_stmt_execute($stmt); // execute the prepared statement
      $result = mysqli_stmt_get_result($stmt); // get the result set for fetching rows

      // if a user row was found, it will fetch it as an associative array
      if ($row = mysqli_fetch_assoc($result)) {
        if ($password === $row['password']) { // compare passwords
          session_regenerate_id(true); // prevent session fixation by regenerating the session ID
          // store user credentials in the session
          $_SESSION['user_id'] = $row['user_id'];
          $_SESSION['name'] = $row['name'];
          $_SESSION['email'] = $row['email'];
          $_SESSION['role'] = $row['role'];

          if ($row['role'] === 'Librarian') {
            header("Location: librarian.php"); // redirect user to librarian dashboard if their role is librarian
          } elseif ($row['role'] === 'Admin') {
            header("Location: admin.php"); // redirect user to admin dashboard if their role is admin
          } else {
            header("Location: member.php"); // Member default
          }
          exit(); // stops executing output after redirect to user dashboard
        } else {
          $loginMessage = "Invalid email or password."; // if no user is found with that email or password
        }
      } else {
        $loginMessage = "Invalid email or password.";
      }
      mysqli_stmt_close($stmt);
    }
  }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
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
  <form class="form-box" id="loginForm" method="post" action="login.php">
    <h2>Login to Your Account</h2>
    <label for="loginEmail">Email:</label>
    <input type="email" id="loginEmail" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>">
    <label for="loginPassword">Password:</label>
    <input type="password" id="loginPassword" name="password" required>
    <button type="submit" name="submit">Login</button>
  </form>
  <!-- convert the php variables msgColor and loginMessage to html entities -->
  <p id="loginMessage" style="text-align:center;color:<?= htmlspecialchars($msgColor) ?>;">
    <?= htmlspecialchars($loginMessage) ?>
  </p>
</main>
</body>
</html>