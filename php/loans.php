<?php
session_start(); // start or resume session so we can read or write to $_SESSION 
include "connect.php"; // this is where $database comes from

// Require login
if (!isset($_SESSION['user_id'])) { // if the user is not logged in
  header("Location: login.php"); // then redirect them to the login page
  exit(); // stop executing the script after redirect
}

$userId = (int) $_SESSION['user_id']; // current user's id from session

// flash helpers
$flash = $_SESSION['flash_msg'] ?? ''; // one-time message text from a prior action if there is any
$flashColor = $_SESSION['flash_color'] ?? 'green'; // one-time message color, the default is green
unset($_SESSION['flash_msg'], $_SESSION['flash_color']); // clear flash so it only shows once

// small helper function to safely escape output and avoid XSS
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }

// ===== Handle Return action =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_id'])) { // if the form posted with a return action
  $loanId = (int) $_POST['return_id']; // loan id to return, cast it to integer
  if ($loanId > 0) { // validate the id
    // Load the active loan and ensure it belongs to the current user
    $stmt = mysqli_prepare( // prepare a query to verify the loan exists and is active
      $database,
      "SELECT loan_id, book_id
         FROM loans
        WHERE loan_id = ? AND user_id = ? AND status = 'Active'
        LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "ii", $loanId, $userId); // bind loan id and user id as integers
    mysqli_stmt_execute($stmt); // execute the verification query
    $res = mysqli_stmt_get_result($stmt); // get the result set
    $loanRow = mysqli_fetch_assoc($res); // fetch the single row if it exists
    mysqli_stmt_close($stmt); // close statement

    if (!$loanRow) { // if loan not found or not active
      $_SESSION['flash_msg'] = "Unable to return this loan (not found or not active)."; // set error flash
      $_SESSION['flash_color'] = "red"; // mark flash color as red
      header("Location: loans.php"); // and redirect back to page
      exit(); // stop execution 
    }

    $bookId = (int)$loanRow['book_id']; // extract book id for availability update

    // Transaction: mark loan returned (+ optionally mark book available)
    mysqli_begin_transaction($database); // begin a database transaction for updates
    try {
      // Update loan status + return_date
      $stmt = mysqli_prepare( // prepare update to set return date and status
        $database,
        "UPDATE loans
            SET status = 'Returned', return_date = CURDATE()
          WHERE loan_id = ? AND user_id = ? AND status = 'Active'"
      );
      mysqli_stmt_bind_param($stmt, "ii", $loanId, $userId); // binds the loan id and user id
      mysqli_stmt_execute($stmt); // execute the update
      $affected = mysqli_stmt_affected_rows($stmt); // check how many rows changed
      mysqli_stmt_close($stmt); // close statement

      if ($affected <= 0) { // if nothing changed, treat as failure
        throw new Exception("Loan was not updated.");
      }

      // Optional: mark the book available again now that it's returned
      $stmt = mysqli_prepare($database, "UPDATE books SET available = TRUE WHERE book_id = ?"); // prepare availability update
      mysqli_stmt_bind_param($stmt, "i", $bookId); // bind the book id
      mysqli_stmt_execute($stmt); // execute the update
      mysqli_stmt_close($stmt); // close statement

      mysqli_commit($database); // commmit both updates as a single unit
      $_SESSION['flash_msg'] = "Book returned successfully."; // success message for the user
      $_SESSION['flash_color'] = "green";
    } catch (Throwable $e) { // in the case of any error
      mysqli_rollback($database); // rollback the transaction
      $_SESSION['flash_msg'] = "Could not return the book. Please try again."; // and show a generic error
      $_SESSION['flash_color'] = "red";
    }
  } else { // if loan id was invalid
    $_SESSION['flash_msg'] = "Invalid loan selection."; // set error flash
    $_SESSION['flash_color'] = "red";
  }

  header("Location: loans.php"); // redirect to refresh the table / user interface
  exit(); // end the request
}

// fetch the user's loans with book details
$stmt = mysqli_prepare( // prepare a query to list user's loans with book info
  $database,
  "SELECT l.loan_id,
          l.book_id,
          l.borrow_date,
          l.due_date,
          l.return_date,
          l.status,
          b.title,
          b.author
     FROM loans AS l
     INNER JOIN books AS b ON b.book_id = l.book_id
    WHERE l.user_id = ?
    ORDER BY l.borrow_date DESC, l.loan_id DESC"
);
mysqli_stmt_bind_param($stmt, "i", $userId); // bind the user id
mysqli_stmt_execute($stmt); // execute the query
$result = mysqli_stmt_get_result($stmt); // get a result set

// Collect rows
$loans = []; // array to hold loan rows
if ($result) { // if the query succeeded
  while ($row = mysqli_fetch_assoc($result)) { // fetch each row as an associative array
    $loans[] = $row; // add to the $loans list
  }
}
mysqli_stmt_close($stmt); // close the statement

// today for days-left/overdue calculation
$today = new DateTime('today');  // create a DateTime for today's date
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Loans</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php include 'nav.php'; ?>

<main>
  <section>
    <h2>My Loans</h2>
    <ul>
      <li><a href="member.php">Member Dashboard</a></li>
      <li><a href="catalog.php">Search Books</a></li>
      <li><a href="reservations.php">My Reservations</a></li>
      <li><a href="fines.php">Fines</a></li>
    </ul>

    <?php if ($flash): ?>
      <p style="color: <?= h($flashColor) ?>;"><?= h($flash) ?></p> <!-- Render flash text with chosen color -->
    <?php endif; ?>

    <?php if (empty($loans)): ?>
      <p class="muted">You don’t have any loans yet.</p>
    <?php else: ?>
      <table class="loans-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Book Title</th>
            <th>Author</th>
            <th>Borrowed</th>
            <th>Due</th>
            <th>Returned</th>
            <th>Status</th>
            <th>Days Left / Overdue</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($loans as $L): ?> <!-- Iterate over each loan row -->
            <?php
              $status = $L['status'] ?? ''; // read status string
              $statusClass = 'status-' . preg_replace('/[^A-Za-z]/', '', (string)$status); // build css class from status

              // Compute days left/overdue (only for not-yet-returned loans with a due date)
              $daysLabel = '-';
              $hasReturn = !empty($L['return_date']); // whether the book has been returned
              if (!$hasReturn && !empty($L['due_date'])) { // only compute if the book is still out and due date exists
                try {
                  $due = new DateTime($L['due_date']); // parse due date
                  $diffDays = (int)$today->diff($due)->format('%r%a'); // get signed days difference, negative means its overdue
                  if ($diffDays >= 0) { // if there's still time left
                    $daysLabel = $diffDays . ' day' . ($diffDays == 1 ? '' : 's') . ' left'; // this is set up to be singular/plural friendly
                  } else { // if its overdue
                    $daysLabel = abs($diffDays) . ' day' . (abs($diffDays) == 1 ? '' : 's') . ' overdue';
                  }
                } catch (Exception $e) { /* leave default em dash on parse errors */ }
              }
            ?>
            <tr>
              <td><?= h($L['loan_id']) ?></td>
              <td><?= h($L['title'] ?? '') ?></td>
              <td><?= h($L['author'] ?? '') ?></td>
              <td><?= h($L['borrow_date'] ?? '') ?></td>
              <td><?= h($L['due_date'] ?? '') ?></td>
              <td><?= h($L['return_date'] ?? '') ?></td>
              <td class="<?= h($statusClass) ?>"><?= h($status) ?></td>
              <td><?= h($daysLabel) ?></td>
              <td>
                <?php if ($status === 'Active'): ?> <!-- only allow return action for active loans -->
                  <form method="post" action="loans.php" style="display:inline;"> <!-- posts back to same page -->
                    <input type="hidden" name="return_id" value="<?= (int)$L['loan_id'] ?>"> <!-- Hidden input with loan id -->
                    <button type="submit" class="btn-link">Return</button>
                  </form>
                <?php else: ?>
                  <span class="muted">-</span> <!-- No action for non-active loans -->
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</main>

</body>
</html>
