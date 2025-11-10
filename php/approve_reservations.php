<?php
session_start(); // so we can start or resume the session
include "connect.php"; // // this is where $database comes from

// require login
if (!isset($_SESSION['user_id'])) { // if the user is not logged in
  header("Location: login.php"); // then redirect them to the login page
  exit(); // stop executing after redirect
}
// librarian and admin roles are only able to access
$role = $_SESSION['role'] ?? 'Member'; // read the logged-in user's role, use member as default if their role is missing
if (!in_array($role, ['Librarian','Admin'], true)) { // only librarian or admin can access this page
  http_response_code(403); // send HTTP 403 Forbidden
  echo "Forbidden: Librarian/Admin access only.";  // simple message for unauthorized access
  exit(); // stop executing
}

define('DEFAULT_LOAN_DAYS', 14); // sets as 14 days

$flash = $_SESSION['flash_msg'] ?? ''; // one-time feedback message from a prior request
$flashColor = $_SESSION['flash_color'] ?? 'green';  // color for the feedback message
unset($_SESSION['flash_msg'], $_SESSION['flash_color']);

// helper function to safely escape output for HTML and prevent XSS
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

// Simple helper to write to the logs table
function log_action(mysqli $db, string $what): void {
  $who  = $_SESSION['name'] ?? ('User#' . (int)($_SESSION['user_id'] ?? 0));
  $stmt = mysqli_prepare($db, "INSERT INTO logs (`user`, `action`) VALUES (?, ?)");
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $who, $what);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  }
}

// -------------------- Handle Approve action (POST) --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) { // if a POST came in with approve_id set
  $resId = (int) $_POST['approve_id']; // normalize reservation id to integer
  if ($resId > 0) { // make sure its a valid id

    // load details only if pending so we can include them in the log
    $stmt = mysqli_prepare(
      $database,
      "SELECT r.reservation_id, u.user_id, u.name AS member_name, b.book_id, b.title
         FROM reservations r
         JOIN users u ON u.user_id = r.user_id
         JOIN books b ON b.book_id = r.book_id
        WHERE r.reservation_id = ? AND r.status = 'Pending'
        LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "i", $resId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res) ?: null;
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare( // prepare an update statement to set status as Approved but only if its marked as pending
      $database,
      "UPDATE reservations
       SET status = 'Approved'
       WHERE reservation_id = ? AND status = 'Pending'"
    );
    mysqli_stmt_bind_param($stmt, "i", $resId); // bind the reservation id as an integer
    mysqli_stmt_execute($stmt); // execute the update
    $affected = mysqli_stmt_affected_rows($stmt); // how many rows were changed
    mysqli_stmt_close($stmt); // close the statement

    if ($affected > 0) { // if the update actually happened

      // log success include member / book if we fetched them
      if ($row) {
        $logText = sprintf(
          'Approved reservation #%d for %s - book #%d "%s"',
          (int)$row['reservation_id'],
          $row['member_name'] ?? ('User#' . (int)$row['user_id']),
          (int)$row['book_id'],
          $row['title'] ?? 'Unknown Title'
        );
      } else {
        $logText = "Approved reservation #{$resId}";
      }
      log_action($database, $logText);

      $_SESSION['flash_msg'] = "Reservation approved."; // set success flash
      $_SESSION['flash_color'] = "green";
    } else {
      $_SESSION['flash_msg'] = "Unable to approve reservation (already approved/fulfilled or not found)."; // error flash
      $_SESSION['flash_color'] = "red";
    }
  } else {
    $_SESSION['flash_msg'] = "Invalid reservation id."; // bad input flash
    $_SESSION['flash_color'] = "red";
  }
  header("Location: approve_reservations.php"); // redirect to avoid form re-submission
  exit(); // stop processing
}

// here is where the fulfill action is handled
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fulfill_id'])) { // if a POST came in with fulfill_id set
  $resId = (int) $_POST['fulfill_id']; // normalize reservation id

  if ($resId <= 0) { // validate id
    $_SESSION['flash_msg'] = "Invalid reservation id."; // flash error
    $_SESSION['flash_color'] = "red";
    header("Location: approve_reservations.php"); // back to page
    exit(); // stop processing
  }

  // first, it loads reservation but it must be Approved, and get user_id & book_id
  $stmt = mysqli_prepare( // prepare a SELECT statement to fetch reservation and book availability
    $database,
    "SELECT r.reservation_id, r.user_id, r.book_id, r.status, b.available, b.title
     FROM reservations r
     JOIN books b ON b.book_id = r.book_id
     WHERE r.reservation_id = ?
     LIMIT 1"
  );
  mysqli_stmt_bind_param($stmt, "i", $resId); // bind reservation id
  mysqli_stmt_execute($stmt); // run the query
  $res = mysqli_stmt_get_result($stmt); // get the result set
  $row = mysqli_fetch_assoc($res); // fetch the row if there's any
  mysqli_stmt_close($stmt); // close statement

  if (!$row) { // if the reservation does not exist
    $_SESSION['flash_msg'] = "Reservation not found."; // this flash error message will be displayed
    $_SESSION['flash_color'] = "red";
    header("Location: approve_reservations.php"); // back to page
    exit(); // stop executing
  }

  if ($row['status'] !== 'Approved') { // this says it can only fulfill those that are approved
    $_SESSION['flash_msg'] = "Only approved reservations can be fulfilled."; // flash error message for this case
    $_SESSION['flash_color'] = "red";
    header("Location: approve_reservations.php"); // back to page
    exit(); // stop executing
  }

  $userId = (int)$row['user_id']; // extract user id for the loan insert
  $bookId = (int)$row['book_id']; // extract book id for the loan and availability
  $bookTitle = $row['title'] ?? 'Unknown Title';

  // it ensures there is no active loan for this book already
  $stmt = mysqli_prepare( // prepares query to check if an active loan for this book exists
    $database,
    "SELECT loan_id
     FROM loans
     WHERE book_id = ? AND status = 'Active'
     LIMIT 1"
  );
  mysqli_stmt_bind_param($stmt, "i", $bookId); // binds book id
  mysqli_stmt_execute($stmt); // execute the statement
  mysqli_stmt_store_result($stmt); // buffer the result so we can count rows
  $activeLoanExists = mysqli_stmt_num_rows($stmt) > 0; // this is true if there is already an active loan for this book
  mysqli_stmt_close($stmt); // closes the statement

  if ($activeLoanExists) { // if the book is already on loan, it cannot be fulfilled
    $_SESSION['flash_msg'] = "This book already has an active loan. Cannot fulfill reservation."; // flash error
    $_SESSION['flash_color'] = "red";
    header("Location: approve_reservations.php"); // back to page
    exit(); // stop executing
  }

  mysqli_begin_transaction($database); // begin transaction 

  try {
    // Insert a new loan row
    $stmt = mysqli_prepare( // prepares insert for the new loan
      $database,
      "INSERT INTO loans (user_id, book_id, borrow_date, due_date, status)
       VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), 'Active')"
    );
    $days = DEFAULT_LOAN_DAYS; // due interval in days
    mysqli_stmt_bind_param($stmt, "iii", $userId, $bookId, $days); // bind user id, book id and days
    mysqli_stmt_execute($stmt); // execute the loan insert
    mysqli_stmt_close($stmt); // close the statement

    // update reservation status to Fulfilled
    $stmt = mysqli_prepare( // prepare update to change reservation to fulfilled
      $database,
      "UPDATE reservations
       SET status = 'Fulfilled'
       WHERE reservation_id = ? AND status = 'Approved'"
    );
    mysqli_stmt_bind_param($stmt, "i", $resId); // bind reservation id
    mysqli_stmt_execute($stmt); // execute update
    $resAffected = mysqli_stmt_affected_rows($stmt); // check if it updated
    mysqli_stmt_close($stmt); // close the statement

    if ($resAffected <= 0) { // if nothing changed, 
      throw new Exception("Reservation status could not be updated to Fulfilled."); // throw to trigger rollback
    }

    // Mark the book unavailable now that it's loaned
    $stmt = mysqli_prepare($database, "UPDATE books SET available = FALSE WHERE book_id = ?"); // prepare availability update
    mysqli_stmt_bind_param($stmt, "i", $bookId); // bind book id
    mysqli_stmt_execute($stmt); // execute update
    mysqli_stmt_close($stmt); // close the statement

    mysqli_commit($database); // commit loan insert, reservation update, book update
    // Log fulfillment after a successful commit
    $logText = sprintf(
      'Fulfilled reservation #%d and created loan for user #%d - book #%d "%s" (due in %d days)',
      $resId, $userId, $bookId, $bookTitle, $days
    );
    log_action($database, $logText);
    $_SESSION['flash_msg'] = "Reservation fulfilled and loan created. Due in {$days} days."; // success flash message
    $_SESSION['flash_color'] = "green";

  } catch (Throwable $e) { // if any steps fails
    mysqli_rollback($database); // roll back the transaction to keep database consistent
    $_SESSION['flash_msg'] = "Could not fulfill reservation: " . $e->getMessage(); // show error message
    $_SESSION['flash_color'] = "red";
  }

  header("Location: approve_reservations.php"); // redirect back to list view
  exit(); // stop processing after redirect
}

// this will fetch reservations to display
$stmt = mysqli_prepare( // prepare a query to list reservations with member and book names
  $database,
  "SELECT r.reservation_id,
          r.reservation_date,
          r.status,
          u.name  AS member_name,
          b.title AS book_title
   FROM reservations r
   JOIN users u ON u.user_id = r.user_id
   JOIN books b ON b.book_id = r.book_id
   ORDER BY 
     FIELD(r.status,'Pending','Approved','Fulfilled'),  -- pending first, then approved, then fulfilled
     r.reservation_date DESC,
     r.reservation_id DESC"
);
mysqli_stmt_execute($stmt); // execute the SELECT statement
$result = mysqli_stmt_get_result($stmt); // get the result set

$reservations = []; // collect rows here for rendering
if ($result) { // if the query succeeded
  while ($row = mysqli_fetch_assoc($result)) { // fetch each row as an associative array
    $reservations[] = $row; // append to result array
  }
}
mysqli_stmt_close($stmt); // close the statement
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Approve Reservations</title>
  <link rel="stylesheet" href="styles.css">
  <style>
  .home-shell {
    max-width: 1100px;
    margin: 1.75rem auto 3rem;
    padding: 0 1rem;
  }
  .hero {
    background: radial-gradient(circle at top, #dbeafe 0%, #ffffff 40%, #ffffff 100%);
    border: 1px solid #e5e7eb;
    border-radius: 1rem;
    padding: 2.5rem 2rem;
    display: grid;
    grid-template-columns: 1.1fr 0.9fr;
    gap: 1.5rem;
    align-items: center;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
  }

  .hero-body h2 {
    font-size: 2.1rem;
    margin: 0.4rem 0 0.6rem;
    color: #111827;
  }

  .hero-text {
    margin: 0;
    color: #4b5563;
    max-width: 480px;
  }

  .hero-pill {
    display: inline-block;
    background: #e0ecff;
    color: #1d4ed8;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: .01em;
  }

  .hero-actions {
    margin-top: 1.2rem;
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
  }

  .btn {
    border: none;
    border-radius: .6rem;
    padding: .55rem 1rem;
    cursor: pointer;
    font-weight: 600;
    font-size: .9rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: .35rem;
  }

  .btn.primary {
    background: #2563eb;
    color: #fff;
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
  }

  .btn.primary:hover {
    background: #1d4ed8;
  }

  .btn.ghost {
    background: #fff;
    color: #1f2937;
    border: 1px solid #e5e7eb;
  }

  .message-box {
    text-align: center;
    background: #fff;
    padding: 2rem;
    border-radius: 1rem;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
    margin-top: 3rem;
  }

  .message-box p {
    font-size: 1.1rem;
  }

  .message-box .btn {
    margin-top: 1rem;
  }

  /* small screens */
  @media (max-width: 768px) {
    .hero {
      grid-template-columns: 1fr;
    }
  }
  </style>
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

<main class="home-shell">
  <div class="hero">
    <div class="hero-body">
      <span class="hero-pill">Reservation Control</span>
      <h2>Approve & Fulfill Member Reservations</h2>
      <p class="hero-text">Librarians can approve or fulfill pending reservations and generate active loans for users.</p>
    </div>
  </div>

  <h2>Reservation List</h2>
  <?php if ($flash): ?>
    <p style="color: <?= h($flashColor) ?>;"><?= h($flash) ?></p>
  <?php endif; ?>

  <?php if (empty($reservations)): ?> 
    <p class="muted">There are no reservations.</p>
  <?php else: ?>
    <table class="res-table" id="reservationTable">
      <thead>
        <tr>
          <th>Member</th>
          <th>Book</th>
          <th>Reservation Date</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reservations as $r): ?>
          <tr>
            <td><?= h($r['member_name']) ?></td>
            <td><?= h($r['book_title']) ?></td>
            <td><?= h($r['reservation_date'] ?? '') ?></td>
            <td><?= h($r['status']) ?></td>
            <td>
              <?php if ($r['status'] === 'Pending'): ?>
                <form method="post" action="approve_reservations.php" style="display:inline;">
                  <input type="hidden" name="approve_id" value="<?= (int)$r['reservation_id'] ?>">
                  <button type="submit">Approve</button>
                </form>
              <?php elseif ($r['status'] === 'Approved'): ?>
                <form method="post" action="approve_reservations.php" style="display:inline;">
                  <input type="hidden" name="fulfill_id" value="<?= (int)$r['reservation_id'] ?>">
                  <button type="submit">Fulfill to Create Loan</button>
                </form>
              <?php else: ?>
                <span class="muted">-</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</main>

</body>
</html>
