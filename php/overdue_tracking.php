<?php
session_start();
include "connect.php"; // this is where $database (mysqli) comes from

require __DIR__ . '/vendor/autoload.php'; // here we load composer's autoloader so PHPMailer is available
use PHPMailer\PHPMailer\PHPMailer; // imports the PHPMailer class into the current namespace
use PHPMailer\PHPMailer\Exception; // import PHPMailer's Exception class for try/catch

define('SMTP_USE', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587); // standard port for sending mail, it works with gmail, outlook
define('SMTP_USER', 'ryanmason1127@gmail.com');
define('SMTP_PASS', 'ycoc army iwag fxrw'); // Gmail app password
define('SMTP_FROM', 'ryanmason1127@gmail.com');
define('SMTP_FROM_NAME', 'Library Management System');

// Access control: Librarian/Admin only
if (!isset($_SESSION['user_id'])) { // if no logged-in user in the session
  header("Location: login.php"); // redirect them to the login page
  exit(); // stop executing
}
$role = $_SESSION['role'] ?? 'Member'; // read the user's role from session, default to Member if missing
if (!in_array($role, ['Librarian','Admin'], true)) { // if the role is not Librarian or Admin
  http_response_code(403); // send HTTP 403 Forbidden
  echo "Forbidden: Librarian/Admin access only."; // simple error message
  exit(); // stop executing
}

// Helper function to safely escape text for HTML output
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

// Flash helpers
$flash = $_SESSION['flash_msg'] ?? ''; // pull any flash message from session
$flashColor = $_SESSION['flash_color'] ?? 'green'; // pull flash color, default to green
unset($_SESSION['flash_msg'], $_SESSION['flash_color']); // clear flash so it doesn't keep showing across refreshes

// Handle "Send Reminder" action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remind_id'])) { // only handle when form posts with remind_id
  $loanId = (int)($_POST['remind_id'] ?? 0); // sanitize loan id from POST to an integer

  if ($loanId > 0) { // proceed only if we have a positive loan id
    // Verify this loan is overdue and active, fetch recipient info
    $stmt = mysqli_prepare( // prepare SQL query with parameters
      $database,
      "SELECT l.loan_id, u.name AS member_name, u.email AS member_email,
              b.title AS book_title, l.due_date
         FROM loans l
         JOIN users u ON u.user_id = l.user_id
         JOIN books b ON b.book_id = l.book_id
        WHERE l.loan_id = ? AND l.status = 'Active' AND l.due_date < CURDATE()
        LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "i", $loanId); // bind loan id as an integer to the placeholder
    mysqli_stmt_execute($stmt); // execute query
    $res = mysqli_stmt_get_result($stmt); // retrieve the result set handle
    $row = mysqli_fetch_assoc($res); // fetch single matching row as an associative array
    mysqli_stmt_close($stmt); // close the prepared statement


  if ($row) { // if we found a matching overdue active loan
      $toName  = $row['member_name']; // save member's display name
      $toEmail = $row['member_email']; // save member's email address
      $title   = $row['book_title']; // save book title
      $due     = $row['due_date']; // save due date

      $subject = "Overdue Notice: \"$title\" was due on $due"; // build email subject line
      $htmlBody = "<p>Hi ".htmlspecialchars($toName).",</p>
                   <p>This is a friendly reminder that <strong>".htmlspecialchars($title)."</strong> was due on <strong>".htmlspecialchars($due)."</strong>.</p>
                   <p>Please return it at your earliest convenience.</p>
                   <p>-Library</p>"; // build HTML email body with escaped values
      $textBody = "Hi {$toName},\n\n".
                  "This is a friendly reminder that \"{$title}\" was due on {$due}.\n".
                  "Please return it at your earliest convenience.\n\n".
                  "- Library"; // build plain-text alternative body

      $sent = false; // track whether email sending succeeds
      if (SMTP_USE) { // if SMTP is enabled in configuration
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
          $sent = true; // mark as sent if no exception thrown
        } catch (\Throwable $e) { // on any error during send
          $sent = false; // mark as failed
        }
      }

      $_SESSION['flash_msg'] = $sent // set a flash message depending on result
        ? "Reminder email sent to {$toName}."
        : "Reminder noted for {$toName}, but the email could not be sent right now.";
      $_SESSION['flash_color'] = $sent ? "green" : "red"; // choose either green (success) or red (failure)
    } else { // if no overdue / active loan matched that id
      $_SESSION['flash_msg'] = "Selected loan is not overdue or not found."; // inform the user
      $_SESSION['flash_color'] = "red"; // error color
    }
  } else { // if loan id was invalid
    $_SESSION['flash_msg'] = "Invalid selection."; // inform user
    $_SESSION['flash_color'] = "red"; // error color
  }

  header("Location: overdue_tracking.php"); // redirect to avoid form resubmission
  exit(); // stop executing
  }

// Fetch overdue loans
$stmt = mysqli_prepare( // prepare a SELECT statement
  $database,
  "SELECT l.loan_id,
          u.name  AS member_name,
          u.email AS member_email,
          b.title AS book_title,
          l.due_date
     FROM loans l
     JOIN users u ON u.user_id = l.user_id
     JOIN books b ON b.book_id = l.book_id
    WHERE l.status = 'Active'
      AND l.due_date < CURDATE()
    ORDER BY l.due_date ASC, l.loan_id ASC"
);
mysqli_stmt_execute($stmt); // execute the SELECT statement
$result = mysqli_stmt_get_result($stmt); // get a result set cursor

$rows = []; // initalize array to hold rows
if ($result) { // if the query executed successfully
  while ($r = mysqli_fetch_assoc($result)) { // fetch each row as an associative array
    $rows[] = $r; // append to the rows array
  }
}
mysqli_stmt_close($stmt); // close the prepared statement
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Overdue Tracking</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<header>
  <h1>Library Management System</h1>
  <nav>
    <ul>
      <li><a href="librarian.php">Librarian Dashboard</a></li>
      <li><a class="logout-btn" href="logout.php">Logout</a></li>
    </ul>
  </nav>
</header>

<main>
  <h2>Overdue Tracking</h2>

  <?php if ($flash): ?> <!-- if a flash message exists -->
    <p style="color: <?= h($flashColor) ?>;"><?= h($flash) ?></p> <!-- show it with proper escapeing and color -->
  <?php endif; ?>

  <?php if (empty($rows)): ?> <!-- if there are no overdue loans -->
    <p class="muted">No overdue loans at the moment.</p> <!-- show empty state -->
  <?php else: ?> <!-- otherwise render the tables -->
    <table class="list" id="overdueTable">
      <thead>
        <tr>
          <th>Member</th>
          <th>Book</th>
          <th>Due Date</th>
          <th>Reminder</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $o): ?> <!-- loop through each overdue loan -->
          <tr>
            <td><?= h($o['member_name']) ?> <span class="muted">(<?= h($o['member_email']) ?>)</span></td>
            <td><?= h($o['book_title']) ?></td>
            <td><?= h($o['due_date']) ?></td>
            <td>
              <form method="post" action="overdue_tracking.php" style="display:inline;" onsubmit="return confirm('Send reminder?');">
                <input type="hidden" name="remind_id" value="<?= (int)$o['loan_id'] ?>"> <!-- pass loan id -->
                <button type="submit" class="btn-link">Send Reminder</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?> <!-- end loop -->
      </tbody>
    </table>
  <?php endif; ?>
</main>

</body>
</html>