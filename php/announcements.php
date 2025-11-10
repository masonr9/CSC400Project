<?php
session_start(); // start or resume the session so we can read / write to $_SESSION
require_once "connect.php"; // provides $database (mysqli link)

require __DIR__ . '/vendor/autoload.php'; // here we load composer's autoloader so PHPMailer is available
use PHPMailer\PHPMailer\PHPMailer; // imports the PHPMailer class into the current namespace
use PHPMailer\PHPMailer\Exception; // import PHPMailer's Exception class for try/catch

define('SMTP_USE', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587); // standard port for sending mail, it works with gmail, outlook
define('SMTP_USER', 'ryanmason1127@gmail.com');
define('SMTP_PASS', 'nrox rlcp xvan mwmm'); // gmail app password
define('SMTP_FROM', 'ryanmason1127@gmail.com');
define('SMTP_FROM_NAME', 'Library Management System');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') { // ensure role exists and is Admin
  header("Location: login.php"); // redirect non-admins to login
  exit(); // stop executing after redirect
}

// small helper function to escape any HTML output
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

// flash helpers 
$flash = $_SESSION['flash_msg'] ?? ''; // read message from session
$flashColor = $_SESSION['flash_color'] ?? 'green'; // read color
unset($_SESSION['flash_msg'], $_SESSION['flash_color']); // clear flash so it shows only once

// Handle Add
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add'])) { // if post request and add button used
  $title   = trim($_POST['title'] ?? ''); // get title and trim any whitespace
  $message = trim($_POST['message'] ?? '');

  if ($title === '' || $message === '') { // validate required fields
    $_SESSION['flash_msg'] = "Title and Message are required."; // set error flash message
    $_SESSION['flash_color'] = "red"; 
    header("Location: announcements.php"); // redirect back to page
    exit();
  }

  // insert announcement
  $stmt = mysqli_prepare($database, "INSERT INTO announcements (title, message) VALUES (?, ?)"); // prepare insert
  mysqli_stmt_bind_param($stmt, "ss", $title, $message); // bind title and message as strings
  $ok = mysqli_stmt_execute($stmt); // execute insert, returns true/false
  mysqli_stmt_close($stmt); // close statement

    if ($ok) {
    // fetch all users to email
    $resUsers = mysqli_query($database, "SELECT name, email FROM users WHERE email <> ''");
    $sentAll  = true;

    if ($resUsers) {
      // build mail subject/body once
      $subject = "New Library Announcement: " . $title;
      $htmlBody = "<p><strong>" . h($title) . "</strong></p>"
                . "<p>" . nl2br(h($message)) . "</p>"
                . "<p style='margin-top:1rem;'>- Library Management System</p>";
      $textBody = $title . "\n\n" . $message . "\n\n" . "- Library Management System";

      while ($u = mysqli_fetch_assoc($resUsers)) {
        $toName  = $u['name'] ?: 'Library Member';
        $toEmail = $u['email'];

        if (!SMTP_USE) {
          // if SMTP is disabled, just skip sending but keep insertion
          continue;
        }

        try { // try to send email
          $mail = new PHPMailer(true); // create new PHPMailer instance
          $mail->isSMTP(); // use SMTP transport
          $mail->Host       = SMTP_HOST; // SMTP server host
          $mail->SMTPAuth   = true; // enable SMTP authentication
          $mail->Username   = SMTP_USER; // SMTP username
          $mail->Password   = SMTP_PASS; // SMTP password (app password)
          $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Use TLS encryption
          $mail->Port       = SMTP_PORT; // port is 587

          $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME); // set the from header and name
          $mail->addAddress($toEmail, $toName); // add recipient address and name
          $mail->isHTML(true); // send as HTML email
          $mail->Subject = $subject; // assign subject
          $mail->Body    = $htmlBody; // assign HTML body
          $mail->AltBody = $textBody; // assign plain text alternative

          $mail->send(); // attempt to send the message
        } catch (\Throwable $e) { // on any error during send
          $sentAll = false; // mark as failed
        }
      }
    }

    if ($sentAll) {
      $_SESSION['flash_msg']   = "Announcement added and emailed to all users.";
      $_SESSION['flash_color'] = "green";
    } else {
      $_SESSION['flash_msg']   = "Announcement added. Some emails could not be sent.";
      $_SESSION['flash_color'] = "red";
    }
  } else {
    $_SESSION['flash_msg']   = "Failed to add announcement.";
    $_SESSION['flash_color'] = "red";
  }

  header("Location: announcements.php"); // redirect after POST
  exit(); // stop executing
}

// handle edit
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['edit'])) { // if post and edit button used
  $id      = (int)($_POST['id'] ?? 0); // get id as integer
  $title   = trim($_POST['title'] ?? ''); // get new title
  $message = trim($_POST['message'] ?? ''); // get new message

  if ($id <= 0 || $title === '' || $message === '') { // validate id and fields
    $_SESSION['flash_msg'] = "Invalid data for update."; // error flash message
    $_SESSION['flash_color'] = "red";
    header("Location: announcements.php"); // redirect back to page
    exit(); // stop executing
  }

  $stmt = mysqli_prepare($database, "UPDATE announcements SET title = ?, message = ? WHERE id = ?"); // prepare update
  mysqli_stmt_bind_param($stmt, "ssi", $title, $message, $id); // bind title, message, id
  $ok = mysqli_stmt_execute($stmt); // execute update
  mysqli_stmt_close($stmt); // close statement

  $_SESSION['flash_msg']   = $ok ? "Announcement updated." : "Failed to update announcement."; // set result flash message
  $_SESSION['flash_color'] = $ok ? "green" : "red"; // color by result
  header("Location: announcements.php"); // redirect back
  exit(); // stop executing
}

// handle delete
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete'])) { // if post and delete button used
  $id = (int)($_POST['id'] ?? 0); // get id as int
  if ($id <= 0) { // validate id
    $_SESSION['flash_msg'] = "Invalid announcement id."; // error flash message
    $_SESSION['flash_color'] = "red";
    header("Location: announcements.php"); // redirect back
    exit(); // stop executing
  }

  $stmt = mysqli_prepare($database, "DELETE FROM announcements WHERE id = ?"); // prepare delete
  mysqli_stmt_bind_param($stmt, "i", $id); // bind id as int
  $ok = mysqli_stmt_execute($stmt); // execute delete
  mysqli_stmt_close($stmt); // close statement

  $_SESSION['flash_msg']   = $ok ? "Announcement deleted." : "Failed to delete announcement."; // set result flash
  $_SESSION['flash_color'] = $ok ? "green" : "red"; // color by result
  header("Location: announcements.php"); // redirect back
  exit(); // stop executing
}

// fetch announcementss
$stmt = mysqli_prepare($database, "SELECT id, title, message, created_at FROM announcements ORDER BY id DESC"); // prepare select all with newest first
mysqli_stmt_execute($stmt); // execute query
$res = mysqli_stmt_get_result($stmt); // get result set cursor

$rows = []; // initalize array for rows
if ($res) { // if query succeeded
  while ($r = mysqli_fetch_assoc($res)) { // iterate each result row
    $rows[] = $r; // append row to list
  }
}
mysqli_stmt_close($stmt); // close select statement
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Announcements</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<header>
  <h1>Library Management System</h1>
  <nav>
    <ul style="display:flex;justify-content:center;">
      <li><a href="admin.php">Dashboard</a></li>
      <li><a href="system_config.php">System Config</a></li>
      <li><a href="logs.php">Logs</a></li>
      <li><a href="role_management.php">Role Management</a></li>
      <li><a href="monitoring.php">System Monitoring</a></li>
      <li><a href="logout.php" class="logout-btn">Logout</a></li>
    </ul>
  </nav>
</header>

<main class="container">
  <h2>📢 Manage Announcements</h2>

  <?php if ($flash): ?>
    <p style="color: <?= h($flashColor) ?>;"><?= h($flash) ?></p>
  <?php endif; ?>

  <!-- Add new announcement -->
  <section>
  <form method="POST" class="form-box">
    <label>Title:</label>
    <input type="text" name="title" required>
    <label>Message:</label>
    <div>
    <textarea name="message" required></textarea>
    <button type="submit" name="add">Add Announcement</button>
  </form>
  </section>

<section style="margin-top:20px">
  <h3>Existing Announcements</h3>
  <?php if (empty($rows)): ?>
    <p class="muted">No announcements yet.</p>
  <?php else: ?>
    <table class="list">
      <thead>
        <tr>
          <th style="width:18%;">Title</th>
          <th>Message</th>
          <th style="width:16%;">Date</th>
          <th style="width:30%;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= h($row['title']) ?></td>
            <td><?= nl2br(h($row['message'])) ?></td>
            <td><?= h($row['created_at'] ?? '') ?></td>
            <td>
              <!-- Inline edit form -->
              <form method="POST" class="form-inline" style="display:inline-block; margin-right:.5rem;">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <input type="text" name="title" value="<?= h($row['title']) ?>" required>
                <textarea name="message" required><?= h($row['message']) ?></textarea>
                <button type="submit" name="edit">Save</button>
              </form>

              <!-- Delete form, POST with confirm -->
              <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this announcement?');">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button type="submit" name="delete" class="btn-link">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </section>
  <?php endif; ?>
</main>

</body>
</html>