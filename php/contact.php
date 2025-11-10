<?php
session_start();

// load PHPMailer, adjust path if yours is different
require __DIR__ . '/vendor/autoload.php'; // here we load composer's autoloader so PHPMailer is available
use PHPMailer\PHPMailer\PHPMailer; // imports the PHPMailer class into the current namespace
use PHPMailer\PHPMailer\Exception; // import PHPMailer's Exception class for try/catch

// SMTP settings (same pattern you've been using elsewhere)
define('SMTP_USE', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587); // standard port for sending mail, it works with gmail and outlook
define('SMTP_USER', 'ryanmason1127@gmail.com');
define('SMTP_PASS', 'need this in order to send / receive email'); // gmail app password
define('SMTP_FROM', 'ryanmason1127@gmail.com');
define('SMTP_FROM_NAME', 'Library Management System');
define('CONTACT_TO', 'ryanmason1127@gmail.com'); // where we receive contact emails

// simple flash-ish vars
$contactMsg = '';
$contactColor = 'green';

if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
  // collecting inputs and validating them
  $name    = trim($_POST['name'] ?? '');
  $email   = trim($_POST['email'] ?? '');
  $message = trim($_POST['message'] ?? '');

  if ($name === '' || $email === '' || $message === '') {
    $contactMsg = "All fields are required.";
    $contactColor = "red";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // FILTER_VALIDATE_EMAIL is used to validate the email address.
    // If the email is not valid, it will update $contactMsg.
    $contactMsg = "Please enter a valid email address.";
    $contactColor = "red";
  } else {
    $sent = false;
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

        // set the from address and the sender name
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress(CONTACT_TO, 'Library Admin');

        // reply-to is the person who filled out the form
        $mail->addReplyTo($email, $name);

        // email content
        $mail->isHTML(true);
        $mail->Subject = "New contact form message from {$name}";
        $mail->Body    =
          "<p><strong>Name:</strong> " . htmlspecialchars($name, ENT_QUOTES) . "</p>" .
          "<p><strong>Email:</strong> " . htmlspecialchars($email, ENT_QUOTES) . "</p>" .
          "<p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message, ENT_QUOTES)) . "</p>";
        $mail->AltBody =
          "Name: {$name}\n" .
          "Email: {$email}\n\n" .
          "Message:\n{$message}";

        $mail->send(); // attempt to send the email via the configured SMTP server
        $sent = true; // mark as sent so the site can show a success message
      } catch (Throwable $e) { // catch any exception such as connection, authentication or send issues.
        $sent = false;
      }
    }

    if ($sent) {
      $contactMsg = "Thank you! Your message has been sent.";
      $contactColor = "green";
      // clear fields on success
      $_POST = [];
    } else {
      $contactMsg = "Sorry, we couldn't send your message right now.";
      $contactColor = "red";
    }
  }
}

// helper function to safely output html
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Us</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    body {
      background: #f3f4f6;
    }
    .contact-shell {
      max-width: 1050px;
      margin: 2.5rem auto;
      padding: 0 1rem;
      display: grid;
      gap: 1.5rem;
      grid-template-columns: 1.05fr .7fr;
    }
    @media (max-width: 880px) {
      .contact-shell {
        grid-template-columns: 1fr;
      }
    }
    .contact-form-box {
      background: #fff;
      border-radius: 16px;
      padding: 1.6rem 1.5rem 1.4rem;
      box-shadow: 0 20px 35px rgba(15,23,42,.05);
      border: 1px solid rgba(148,163,184,.14);
    }
    .contact-form-box h2 {
      margin-top: 0;
      margin-bottom: .75rem;
    }
    .contact-form-box p.sub {
      margin-top: 0;
      margin-bottom: 1.25rem;
      color: #6b7280;
      font-size: .9rem;
    }
    .contact-form-box label {
      display: block;
      margin-top: .5rem;
      font-weight: 600;
      font-size: .9rem;
    }
    .contact-form-box input,
    .contact-form-box textarea {
      width: 100%;
      margin-top: .3rem;
      border: 1px solid #d1d5db;
      border-radius: 10px;
      padding: .55rem .6rem;
      font-size: .9rem;
      background: #fff;
    }
    .contact-form-box input:focus,
    .contact-form-box textarea:focus {
      outline: none;
      border-color: #2563eb;
      box-shadow: 0 0 0 3px rgba(37,99,235,.13);
    }
    .contact-form-box button {
      margin-top: .9rem;
      background: #2563eb;
      border: none;
      color: #fff;
      padding: .6rem 1.3rem;
      border-radius: 9999px;
      font-weight: 600;
      cursor: pointer;
    }
    .contact-form-box button:hover {
      background: #1d4ed8;
    }
    .contact-msg-inline {
      margin-top: .75rem;
      font-size: .85rem;
    }
    .contact-side {
      background: linear-gradient(160deg, #0f172a, #172554);
      border-radius: 16px;
      padding: 1.5rem 1.4rem 1.25rem;
      color: #fff;
      box-shadow: 0 18px 30px rgba(15,23,42,.12);
    }
    .contact-side h3 {
      margin-top: 0;
      margin-bottom: .5rem;
    }
    .contact-side p {
      margin-top: 0;
      color: rgba(255,255,255,.67);
      font-size: .85rem;
      line-height: 1.45;
    }
    .contact-side .info-line {
      margin-top: .65rem;
      font-size: .85rem;
    }
    .contact-side .info-line strong {
      display: inline-block;
      width: 4.9rem;
      color: #fff;
    }
  </style>
</head>
<body>

<?php include 'nav.php'; ?>

<main class="contact-shell">
  <form method="POST" class="contact-form-box">
    <h2>Contact Us</h2>
    <p class="sub">Have a question about borrowing, your account, or the catalog? Send us a message.</p>

    <label>Name</label>
    <input type="text" name="name" placeholder="Your full name" required value="<?= h($_POST['name'] ?? '') ?>">

    <label>Email</label>
    <input type="email" name="email" placeholder="you@example.com" required value="<?= h($_POST['email'] ?? '') ?>">

    <label>Message</label>
    <textarea rows="4" name="message" placeholder="Tell us how we can help..." required><?= h($_POST['message'] ?? '') ?></textarea>

    <button type="submit">Send Message</button>

    <?php if ($contactMsg !== ''): ?>
      <p class="contact-msg-inline" style="color: <?= h($contactColor) ?>;">
        <?= h($contactMsg) ?>
      </p>
    <?php endif; ?>
  </form>

  <aside class="contact-side">
    <h3>Library Support</h3>
    <p>We usually reply within one business day.</p>
    <div class="info-line"><strong>Email:</strong> support@library.com</div>
    <div class="info-line"><strong>Phone:</strong> (555) 123-4567</div>
    <div class="info-line"><strong>Hours:</strong> Mon-Fri, 9am-5pm</div>
    <p style="margin-top:1.1rem;">You can also visit the library desk during opening hours for in-person help.</p>
  </aside>
</main>
</body>
</html>
