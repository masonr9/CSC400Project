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
define('SMTP_PASS', 'need this in order to send email'); // gmail app password
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
  <style>
    /* Layout shell */
    .shell {
      max-width: 1100px;
      margin: 1.75rem auto 3rem;
      padding: 0 1rem;
    }

    /* Page title */
    .page-title-bar {
      margin-bottom: 1rem;
    }
    .page-title {
      margin: 0;
      font-size: 1.6rem;
      line-height: 1.25;
      color: #111827;
    }
    .page-subtitle {
      margin: .35rem 0 0;
      color: #6b7280;
      font-size: .95rem;
    }

    /* Flash alerts */
    .alert {
      border-radius: .6rem;
      padding: .75rem 1rem;
      margin: 1rem 0;
      font-weight: 500;
      border: 1px solid transparent;
    }
    .alert-success {
      background: #ecfdf5;
      color: #065f46;
      border-color: #a7f3d0;
    }
    .alert-danger {
      background: #fef2f2;
      color: #991b1b;
      border-color: #fecaca;
    }

    /* Cards */
    .card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: .9rem;
      box-shadow: 0 6px 18px rgba(17, 24, 39, 0.06);
    }
    .card + .card { margin-top: 1rem; }
    .mt-lg { margin-top: 1.25rem; }

    .card-header {
      padding: .9rem 1.1rem .75rem;
      border-bottom: 1px solid #eef2f7;
    }
    .card-title {
      margin: 0;
      font-size: 1.05rem;
      color: #111827;
    }
    .section-header { display: flex; align-items: center; gap: .6rem; }
    .section-title { margin: 0; }

    .card-body {
      padding: 1rem 1.1rem 1.15rem;
    }

    /* Badge */
    .badge {
      display: inline-block;
      font-weight: 600;
      font-size: .75rem;
      padding: .2rem .55rem;
      border-radius: 9999px;
    }
    .badge.soft {
      color: #1f2937;
      background: #f3f4f6;
      border: 1px solid #e5e7eb;
    }

    /* Forms */
    .form-grid {
      display: grid;
      gap: .85rem;
    }
    .form-row { display: grid; gap: .4rem; width: 95%; }
    .form-row label { color: #374151; }

    .form-grid input[type="text"],
    .form-grid textarea,
    .inline-fields input[type="text"],
    .inline-fields textarea {
      width: 100%;
      padding: .6rem .7rem;
      border: 1px solid #e5e7eb;
      border-radius: .55rem;
      background: #fff;
      color: #111827;
      font-size: .95rem;
      transition: border-color .15s ease, box-shadow .15s ease;
    }
    .form-grid textarea,
    .inline-fields textarea { min-height: 120px; resize: vertical; }

    .form-grid input:focus,
    .form-grid textarea:focus,
    .inline-fields input:focus,
    .inline-fields textarea:focus {
      outline: none;
      border-color: #c7d2fe;
      box-shadow: 0 0 0 4px rgba(59,130,246,0.12);
    }

    .form-actions {
      margin-top: .35rem;
      display: flex;
      gap: .6rem;
    }

    /* Buttons */
    .btn {
      border: 1px solid transparent;
      border-radius: .6rem;
      padding: .55rem 1rem;
      cursor: pointer;
      font-weight: 600;
      font-size: .92rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      transition: transform .04s ease, background-color .15s ease, border-color .15s ease, color .15s ease;
      user-select: none;
    }
    .btn:active { transform: translateY(1px); }

    .btn.primary {
      background: #2563eb;
      color: #fff;
      box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25);
    }
    .btn.primary:hover { background: #1d4ed8; }

    .btn.ghost {
      background: #fff;
      color: #1f2937;
      border-color: #e5e7eb;
    }
    .btn.ghost:hover { background: #f9fafb; }

    .btn-link {
      background: transparent;
      border: none;
      padding: 0;
      color: #2563eb;
      font-weight: 600;
      text-decoration: none;
      cursor: pointer;
    }
    .btn-link:hover { text-decoration: underline; }
    .btn-link.danger { color: #dc2626; }

    /* Table wrapper */
    .table-wrap {
      width: 100%;
      overflow-x: auto;
    }

    /* Table */
    .table {
      width: 95%;
      border-collapse: collapse;
      font-size: .95rem;
    }
    .table thead th {
      text-align: left;
      background: #f9fafb;
      color: #374151;
      font-weight: 700;
      border-bottom: 1px solid #e5e7eb;
      padding: .7rem .65rem;
      white-space: nowrap;
    }
    .table tbody td {
      border-bottom: 1px solid #f1f5f9;
      padding: .65rem;
      vertical-align: top;
      color: #111827;
    }
    .table tbody tr:hover { background: #fcfdff; }

    /* Utility widths for columns */
    .th-16 { width: 16%; }
    .th-18 { width: 18%; }
    .th-24 { width: 24%; }

    /* Inline edit row */
    .inline-form { display: inline-block; margin-right: .5rem; vertical-align: top; }
    .inline-fields {
      display: grid;
      grid-template-columns: minmax(160px, 220px) minmax(220px, 420px);
      gap: .5rem;
      margin-bottom: .5rem;
    }
    .inline-actions { display: inline-flex; gap: .4rem; }

    /* Mono timestamp */
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }

    /* Muted text */
    .muted { color: #6b7280; }

    /* Responsive */
    @media (max-width: 768px) {
      .inline-fields {
        grid-template-columns: 1fr;
      }
      .page-title { font-size: 1.35rem; }
    }
  </style>
</head>
<body>

<?php include 'admin_nav.php'; ?>

<main class="shell">
  <!-- Page header -->
  <div class="page-title-bar">
    <h2 class="page-title">📢 Manage Announcements</h2>
    <p class="page-subtitle">Create, update, and notify members about important library news.</p>
  </div>

  <!-- Flash -->
  <?php if ($flash): ?>
    <div class="alert <?= $flashColor === 'red' ? 'alert-danger' : 'alert-success' ?>">
      <?= h($flash) ?>
    </div>
  <?php endif; ?>

  <!-- Add new announcement -->
  <section class="card mt-lg">
    <div class="card-header section-header">
      <h3 class="card-title section-title">Add Announcement</h3>
    </div>
    <div class="card-body">
      <form method="POST" class="form-grid">
        <div class="form-row">
          <label for="title"><strong>Title</strong></label>
          <input type="text" id="title" name="title" required>
        </div>

        <div class="form-row">
          <label for="message"><strong>Message</strong></label>
          <textarea id="message" name="message" rows="5" required></textarea>
        </div>

        <div class="form-actions">
          <button type="submit" name="add" class="btn primary">Add Announcement</button>
        </div>
      </form>
    </div>
  </section>

  <!-- Existing announcements -->
  <section class="card mt-lg">
    <div class="card-header section-header">
      <h3 class="card-title section-title">Existing Announcements</h3>
      <span class="badge soft"><?= (int)count($rows) ?> total</span>
    </div>

    <?php if (empty($rows)): ?>
      <div class="card-body">
        <p class="muted">No announcements yet.</p>
      </div>
    <?php else: ?>
      <div class="card-body table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th class="th-18">Title</th>
              <th>Message</th>
              <th class="th-16">Date</th>
              <th class="th-24">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><strong><?= h($row['title']) ?></strong></td>
                <td><?= nl2br(h($row['message'])) ?></td>
                <td><span class="mono"><?= h($row['created_at'] ?? '') ?></span></td>
                <td>
                  <!-- Inline edit form -->
                  <form method="POST" class="inline-form">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <div class="inline-fields">
                      <input type="text" name="title" value="<?= h($row['title']) ?>" required>
                    </div>
                    <div class="inline-fields">
                      <textarea name="message" rows="3" required><?= h($row['message']) ?></textarea>
                    </div>
                    <div class="inline-actions">
                      <button type="submit" name="edit" class="btn ghost">Save</button>
                    </div>
                  </form>

                  <!-- Delete -->
                  <form method="POST" class="inline-form" onsubmit="return confirm('Remove this announcement?');">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <button type="submit" name="delete" class="btn-link danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>

</body>
</html>