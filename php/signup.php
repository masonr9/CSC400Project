<?php
  require __DIR__ . '/vendor/autoload.php'; // here we load composer's autoloader so PHPMailer is available
  use PHPMailer\PHPMailer\PHPMailer; // imports the PHPMailer class into the current namespace
  use PHPMailer\PHPMailer\Exception; // import PHPMailer's Exception class for try/catch
  define('SMTP_USE', true);
  define('SMTP_HOST', 'smtp.gmail.com');
  define('SMTP_PORT', 587); // standard port for sending mail, it works with gmail, outlook
  define('SMTP_USER', 'ryanmason1127@gmail.com');
  define('SMTP_PASS', 'need this to send email'); // Gmail app password
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
  <style>
  body {
    min-height: 100vh;
  }
  .auth-shell {
  max-width: 420px;
  margin: 2.25rem auto 3rem;
  }
  .auth-card {
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
    border: 1px solid rgba(15,23,42,0.03);
    padding: 1.5rem 1.5rem 1.25rem;
  }
  .auth-card h2 {
    margin-top: 0;
    margin-bottom: 0.65rem;
    font-size: 1.4rem;
    text-align: center;
    color: #0f172a;
  }
  .auth-subtitle {
    text-align: center;
    margin-bottom: 1.3rem;
    color: #64748b;
    font-size: .9rem;
  }
  .form-field {
    margin-bottom: .75rem;
  }
  .form-field label {
    display: block;
    font-weight: 600;
    font-size: .85rem;
    margin-bottom: .25rem;
    color: #0f172a;
  }
  .form-field input,
  .form-field select,
  .form-field textarea {
    width: 100%;
    padding: .55rem .6rem;
    border-radius: 10px;
    border: 1px solid #d1d5db;
    background: #ffffff;
    transition: border .12s ease-out, box-shadow .12s ease-out;
    font-size: .9rem;
  }
  .form-field input:focus,
  .form-field select:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
  }
  .btn-primary {
    width: 100%;
    background: #2563eb;
    color: #fff;
    border: none;
    border-radius: 9999px;
    padding: .55rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .13s ease-out;
    margin-top: .25rem;
  }
  .btn-primary:hover {
    background: #1d4ed8;
  }
  .feedback {
    text-align: center;
    margin-top: .9rem;
    font-size: .85rem;
  }
  .helper-text {
    text-align: center;
    margin-top: .7rem;
    font-size: .78rem;
    color: #94a3b8;
  }
  .role-hint {
    font-size: .7rem;
    color: #94a3b8;
    margin-top: .15rem;
    display: block;
  }
  @media (max-width: 520px) {
    .auth-shell {
      margin: 1.5rem 1rem 3rem;
    }
    .auth-card {
      border-radius: 14px;
    }
  }
  </style>
</head>
<body>

<?php
include "nav.php"
?>

<main class="auth-shell">
  <div class="auth-card">
    <h2>Create Your Library Account</h2>
    <p class="auth-subtitle">Join the library to reserve books, view loans, and get updates.</p>
    <form id="signupForm" name="Registration" method="post" action="signup.php" novalidate>
      <div class="form-field">
        <label for="fullName">Full Name</label>
        <input
          type="text"
          id="fullName"
          name="name"
          required
          value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES) ?>">
      </div>

      <div class="form-field">
        <label for="email">Email</label>
        <input
          type="email"
          id="email"
          name="email"
          required
          value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) ?>">
      </div>

      <div class="form-field">
        <label for="password">Password <span style="color:#94a3b8;font-weight:400;">(min. 8 characters)</span></label>
        <input
          type="password"
          id="password"
          name="password"
          required>
      </div>

      <div class="form-field">
        <label for="role">Account Type</label>
        <select id="role" name="role" required>
          <?php
            $currentRole = $_POST['role'] ?? 'Member';
            foreach (['Member','Admin','Librarian'] as $r) {
              $sel = ($currentRole === $r) ? 'selected' : '';
              echo "<option value=\"$r\" $sel>$r</option>";
            }
          ?>
        </select>
        <span class="role-hint">Most users should select “Member”.</span>
      </div>

      <button type="submit" name="submit" class="btn-primary">
        Create Account
      </button>
    </form>
    <p class="feedback" style="color:<?= htmlspecialchars($msgColor) ?>;">
      <?= htmlspecialchars($signupMessage) ?>
    </p>
    <p class="helper-text">
      Already have an account? <a href="login.php" style="color:#2563eb;text-decoration:none;">Log in</a>
    </p>
  </div>
</main>
</body>
</html>

