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
        if (password_verify($password, $row['password'])) { // compare passwords
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
  <style>
  .login-wrapper {
    max-width: 420px;
    margin: 2.5rem auto 0;
  }

  .login-card {
    background: #fff;
    padding: 1.75rem 1.5rem 1.5rem;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
    border: 1px solid #e5e7eb;
  }

  .login-card h2 {
    margin-top: 0;
    margin-bottom: .75rem;
    font-size: 1.4rem;
    text-align: center;
  }

  .login-subtitle {
    text-align: center;
    margin-bottom: 1.5rem;
    color: #6b7280;
    font-size: .9rem;
  }

  .login-card label {
    display: block;
    margin-bottom: .35rem;
    font-weight: 600;
    color: #374151;
  }

  .login-card input[type="email"],
  .login-card input[type="password"] {
    width: 100%;
    padding: .5rem .6rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    margin-bottom: 1rem;
    font-size: .95rem;
  }

  .login-card input:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,.12);
  }

  .login-card button {
    width: 100%;
    background: #2563eb;
    color: #fff;
    border: none;
    padding: .55rem 0;
    border-radius: 6px;
    font-size: .95rem;
    cursor: pointer;
    font-weight: 600;
    transition: background .15s ease-in-out;
  }

  .login-card button:hover {
    background: #1d4ed8;
  }

  .login-footer {
    text-align: center;
    margin-top: 1rem;
    font-size: .85rem;
    color: #6b7280;
  }

  .login-footer a {
    color: #2563eb;
    text-decoration: none;
    font-weight: 500;
  }

  .login-footer a:hover {
    text-decoration: underline;
  }

  .login-message {
    text-align: center;
    margin-top: 1rem;
    font-size: .9rem;
  }
  </style>
</head>
<body>

<?php
include "nav.php"
?>

<main>
  <div class="login-wrapper">
    <form class="login-card" id="loginForm" method="post" action="login.php">
      <h2>Sign in</h2>
      <p class="login-subtitle">Access your library dashboard</p>
      <label for="loginEmail">Email:</label>
      <input type="email" id="loginEmail" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>">
      <label for="loginPassword">Password:</label>
      <input type="password" id="loginPassword" name="password" required>
      <button type="submit" name="submit">Login</button>
      <p class="login-footer">Don't have an account? | <a href="signup.php">Create one</a>
    </form>
  <!-- convert the php variables msgColor and loginMessage to html entities -->
  <p id="loginMessage" class="login-message" style="text-align:center;color:<?= htmlspecialchars($msgColor) ?>;">
    <?= htmlspecialchars($loginMessage) ?>
  </p>
</main>
</body>
</html>