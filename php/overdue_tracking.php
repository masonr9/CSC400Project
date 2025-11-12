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
define('SMTP_PASS', 'need this in order to send email'); // Gmail app password
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

      // this writes an entry to logs table about reminder
      $who  = $_SESSION['name'] ?? ('User#' . (int)($_SESSION['user_id'] ?? 0));
      $what = $sent
        ? "Sent overdue reminder to {$toName} ({$toEmail}) for \"{$title}\" (loan #{$loanId}, due {$due})."
        : "Attempted overdue reminder to {$toName} ({$toEmail}) for \"{$title}\" (loan #{$loanId}, due {$due}) - send failed.";
      if ($logStmt = mysqli_prepare($database, "INSERT INTO logs (`user`, `action`) VALUES (?, ?)")) {
        mysqli_stmt_bind_param($logStmt, "ss", $who, $what);
        mysqli_stmt_execute($logStmt);
        mysqli_stmt_close($logStmt);
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
  <link rel="stylesheet" href="styles.css">
  <title>Overdue Tracking</title>
  <style>
    /* Shell & hero */
    .shell { 
      max-width: 1100px; 
      margin: 1.5rem auto 3rem; 
      padding: 0 1rem; 
    }
    .hero {
      background: radial-gradient(circle at top, #e5f0ff 0%, #ffffff 42%, #ffffff 100%);
      border: 1px solid #e5e7eb; border-radius: 14px; padding: 1.5rem 1.25rem;
      display: flex; align-items: center; justify-content: space-between; gap: 1rem;
      margin-bottom: 1rem;
    }
    .hero-title { margin: 0; font-size: 1.5rem; color: #111827; }
    .hero-sub   { margin: .15rem 0 0; color: #6b7280; }

    /* Flash */
    .flash { margin: 1rem 0; padding: .7rem .9rem; border-radius: 10px; font-weight: 600; }
    .flash.green { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .flash.red   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

    /* Card container */
    .card {
      background: #fff;
      border: 1px solid #eef2f7;
      border-radius: 12px;
      box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
      padding: 1.2rem 1.1rem;
    }

    /* Table */
    .table-wrap { overflow-x: auto; }
    table.pretty { width: 100%; border-collapse: collapse; font-size: .95rem; }
    table.pretty thead th {
      text-align: left; padding: .65rem .6rem; color: #6b7280; font-weight: 700;
      border-bottom: 1px solid #e5e7eb; white-space: nowrap;
    }
    table.pretty tbody td {
      padding: .65rem .6rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle;
    }
    table.pretty tbody tr:hover { background: #fcfcfd; }

    /* Pills / badges */
    .pill {
      display: inline-block; font-size: .75rem; font-weight: 700; letter-spacing: .02em;
      padding: .2rem .6rem; border-radius: 9999px; border: 1px solid #fde68a; color: #92400e; background: #fffbeb;
    }

    /* Buttons */
    .btn {
      display: inline-flex; align-items: center; gap: .35rem;
      border: 1px solid #e5e7eb; background: #fff; color: #1f2937;
      padding: .45rem .75rem; border-radius: 8px; cursor: pointer; text-decoration: none;
      font-weight: 600; font-size: .88rem;
    }
    .btn:hover { background: #f9fafb; }
    .btn-link { border: 0; background: transparent; color: #dc2626; font-weight: 700; cursor: pointer; }
    .btn-link:hover { text-decoration: underline; }

    /* Muted */
    .muted { color: #6b7280; }

    /* Responsive table: stack rows on small screens */
    @media (max-width: 720px) {
      .hero { flex-direction: column; align-items: flex-start; }
      .toolbar { flex-direction: column; align-items: stretch; }
      table.pretty thead { display: none; }
      table.pretty, table.pretty tbody, table.pretty tr, table.pretty td { display: block; width: 100%; }
      table.pretty tr { border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: .65rem; background: #fff; }
      table.pretty td { border-bottom: 0; padding: .55rem .75rem; }
      table.pretty td::before {
        content: attr(data-label);
        display: block; font-size: .75rem; color: #6b7280; margin-bottom: .2rem; text-transform: uppercase;
      }
    }
  </style>
</head>
<body>

<?php include 'librarian_nav.php' ?>

<main class="shell">
  <div class="hero">
    <div>
      <h1 class="hero-title">Overdue Tracking</h1>
      <p class="hero-sub">View active loans that passed their due date and send reminders.</p>
    </div>
    <span class="pill"><?= count($rows) ?> overdue</span>
  </div>
  <?php if ($flash): ?>
    <div class="flash <?= h($flashColor) ?>"><?= h($flash) ?></div>
  <?php endif; ?>

  <?php if (empty($rows)): ?>
    <div class="card">
      <p class="muted">No overdue loans at the moment.</p>
    </div>
  <?php else: ?>
    <section class="card">
      <div class="table-wrap">
        <table class="pretty" id="overdueTable">
          <thead>
            <tr>
              <th>Member</th>
              <th>Book</th>
              <th>Due Date</th>
              <th>Reminder</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $o): ?>
              <tr>
                <td data-label="Member">
                  <?= h($o['member_name']) ?>
                  <span class="muted"> (<?= h($o['member_email']) ?>)</span>
                </td>
                <td data-label="Book"><?= h($o['book_title']) ?></td>
                <td data-label="Due Date"><?= h($o['due_date']) ?></td>
                <td data-label="Reminder">
                  <form method="post" action="overdue_tracking.php" style="display:inline;" onsubmit="return confirm('Send reminder?');">
                    <input type="hidden" name="remind_id" value="<?= (int)$o['loan_id'] ?>">
                    <button type="submit" class="btn-link">Send Reminder</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
</main>

</body>
</html>