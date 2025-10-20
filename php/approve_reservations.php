<?php //
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

// -------------------- Handle Approve action (POST) --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) { // if a POST came in with approve_id set
  $resId = (int) $_POST['approve_id']; // normalize reservation id to integer
  if ($resId > 0) { // make sure its a valid id
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

// here is fulfill action is handled
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
    "SELECT r.reservation_id, r.user_id, r.book_id, r.status, b.available
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
  <h2>Approve Reservations</h2>

  <?php if ($flash): ?> <!-- if there is a flash message -->
    <p style="color: <?= h($flashColor) ?>;"><?= h($flash) ?></p> <!-- show the message in its color-->
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
        <?php foreach ($reservations as $r): ?> <!-- loop through each reservation row -->
          <tr>
            <td><?= h($r['member_name']) ?></td>
            <td><?= h($r['book_title']) ?></td>
            <td><?= h($r['reservation_date'] ?? '') ?></td>
            <td><?= h($r['status']) ?></td>
            <td>
              <?php if ($r['status'] === 'Pending'): ?>
                <form method="post" action="approve_reservations.php" class="btn-inline" style="display:inline;">
                  <input type="hidden" name="approve_id" value="<?= (int)$r['reservation_id'] ?>"> <!-- Hidden id field -->
                  <button type="submit">Approve</button>
                </form>
              <?php elseif ($r['status'] === 'Approved'): ?> <!-- if approved, it will show fulfill button -->
                <form method="post" action="approve_reservations.php" class="btn-inline" style="display:inline;">
                  <input type="hidden" name="fulfill_id" value="<?= (int)$r['reservation_id'] ?>"> <!-- hidden id field -->
                  <button type="submit">Fulfill to Create Loan</button>
                </form>
              <?php else: ?> <!-- if fulfilled, no actions -->
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
