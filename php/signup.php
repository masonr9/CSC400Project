<?php
  require __DIR__ . '/vendor/autoload.php'; // here we load composer's autoloader so PHPMailer is available
  use PHPMailer\PHPMailer\PHPMailer; // imports the PHPMailer class into the current namespace
  use PHPMailer\PHPMailer\Exception; // import PHPMailer's Exception class for try/catch
  define('SMTP_USE', true);
  define('SMTP_HOST', 'smtp.gmail.com');
  define('SMTP_PORT', 587); // standard port for sending mail, it works with gmail, outlook
  define('SMTP_USER', 'ryanmason1127@gmail.com');
  define('SMTP_PASS', 'removed for security purposes'); // Gmail app password
  define('SMTP_FROM', 'ryanmason1127@gmail.com');
  define('SMTP_FROM_NAME', 'Library Management System');
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
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // ensures role is valid
        $allowedRoles = ['Member','Librarian','Admin'];
        if (!in_array($role, $allowedRoles, true)) {
          $role = 'Member';
        }

        // preparing to insert the new account into the database
        $stmt = mysqli_prepare($database, "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $hashed, $role);

        if (mysqli_stmt_execute($stmt)) {
          $sent = false;

          // Subject & body
          $subject = 'Welcome to the Library!';
          $htmlBody =
            "<p>Hi " . htmlspecialchars($name) . ",</p>
            <p>Welcome to the Library Management System. Your account has been created successfully.</p>";
          $textBody =
            "Hi {$name},\n\n".
            "Welcome to the Library Management System. Your account has been created successfully.\n";

          if (SMTP_USE) { // this will only attempt to send mail if SMTP is enabled
            try {
              $mail = new PHPMailer(true); // creates a new PHPMailer instance; true is the value since it enables exceptions on errors
              $mail->isSMTP(); // this tells PHPMailer to  use SMTP
              $mail->Host       = SMTP_HOST;
              $mail->SMTPAuth   = true; // enable SMTP authentication
              $mail->Username   = SMTP_USER;
              $mail->Password   = SMTP_PASS;
              $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // use STARTTLS encryption
              $mail->Port       = SMTP_PORT; // port 587 is for STARTTLS

              $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME); // sets the from address and the sender name
              $mail->addAddress($email, $name); // adds a recipient with the email and name
              $mail->isHTML(true);  // send the message as HTML so it allows tags and formatting
              $mail->Subject = $subject;
              $mail->Body    = $htmlBody; // sets the html version of the message body
              $mail->AltBody = $textBody; // sets the plain-text fallback in the case of a client that doesn't render html

              $mail->send(); // attempt to send the email via the configured SMTP server
              $sent = true; // mark as sent so the site can show a success message
            } catch (\Throwable $e) { // catch any exception such as connection, authentication or send issues.
              // fall back, you can log $e->getMessage() for debugging
              $sent = false;
            }
          }

          $signupMessage = "Registration successful! A welcome email has been sent to your address.";
          if (!$sent) {
            $signupMessage = "Registration successful but we couldn't send the welcome email right now.";
          }
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

