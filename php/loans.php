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

    // mark loan returned + optionally mark book available
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
      }      $stmt = mysqli_prepare(
        $database,
        "INSERT INTO loans (user_id, book_id, borrow_date, due_date, return_date, status)
         SELECT user_id, book_id, borrow_date, due_date, CURDATE(), 'Returned'
           FROM loans
          WHERE loan_id = ?
          LIMIT 1"
      );
      mysqli_stmt_bind_param($stmt, "i", $loanId);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);

      // mark the book available again now that it's returned
      $stmt = mysqli_prepare($database, "UPDATE books SET available = TRUE WHERE book_id = ?"); // prepare availability update
      mysqli_stmt_bind_param($stmt, "i", $bookId); // bind the book id
      mysqli_stmt_execute($stmt); // execute the update
      mysqli_stmt_close($stmt); // close statement
      
      $title = '';
      $stmt = mysqli_prepare($database, "SELECT title FROM books WHERE book_id = ? LIMIT 1");
      mysqli_stmt_bind_param($stmt, "i", $bookId);
      mysqli_stmt_execute($stmt);
      $rs = mysqli_stmt_get_result($stmt);
      if ($rowT = mysqli_fetch_assoc($rs)) {
        $title = $rowT['title'] ?? '';
      }
      mysqli_stmt_close($stmt);

      // Build "who" and "what" for the log
      $who  = $_SESSION['name'] ?? ('User#' . (int)$userId);
      $what = $title !== ''
        ? "Returned book #{$bookId} (\"{$title}\") on loan #{$loanId}"
        : "Returned book #{$bookId} on loan #{$loanId}";

      // Insert the log row
      $stmt = mysqli_prepare($database, "INSERT INTO logs (`user`, `action`) VALUES (?, ?)");
      mysqli_stmt_bind_param($stmt, "ss", $who, $what);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);

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
  <style>
    body { 
      background: #f3f4f6; 
    }
    .loans-shell {
      max-width: 1150px;
      margin: 2.1rem auto 2.3rem;
      padding: 0 1rem;
    }
    .loans-hero {
      background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 60%);
      border-radius: 16px;
      padding: 1.2rem 1.35rem 1rem;
      color: #fff;
      box-shadow: 0 20px 35px rgba(30,64,175,.25);
      margin-bottom: 1.5rem;
    }
    .loans-hero h2 {
      margin: 0 0 .35rem;
    }
    .loans-hero p {
      margin: 0;
      color: rgba(255,255,255,.85);
      font-size: .9rem;
    }
    .mini-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(185px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .mini-card {
      background: #fff;
      border-radius: 14px;
      padding: .85rem .85rem .7rem;
      border: 1px solid rgba(148,163,184,.25);
      box-shadow: 0 10px 20px rgba(15,23,42,.02);
    }
    .mini-card h3 {
      margin: 0 0 .4rem;
      font-size: .85rem;
    }
    .mini-card p {
      margin: 0 0 .4rem;
      color: #6b7280;
      font-size: .78rem;
    }
    .mini-card a {
      font-size: .75rem;
      font-weight: 600;
      color: #1d4ed8;
      text-decoration: none;
    }
    .flash-msg {
      padding: .6rem .85rem;
      border-radius: 10px;
      margin-bottom: 1.1rem;
      font-size: .83rem;
    }
    .table-shell {
      background: #fff;
      border-radius: 14px;
      border: 1px solid rgba(148,163,184,.15);
      overflow: hidden;
      box-shadow: 0 10px 32px rgba(15,23,42,.03);
    }
    table.loans-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 750px;
    }
    .loans-table thead {
      background: #f8fafc;
    }
    .loans-table th,
    .loans-table td {
      padding: .6rem .75rem;
      text-align: left;
      border-bottom: 1px solid #e2e8f0;
      font-size: .82rem;
    }
    .loans-table th {
      font-weight: 600;
      color: #475569;
    }
    .badge-status {
      display: inline-block;
      padding: .3rem .65rem;
      border-radius: 9999px;
      font-size: .7rem;
      font-weight: 600;
    }
    .badge-status.Active {
      background: #dbeafe;
      color: #1d4ed8;
    }
    .badge-status.Returned {
      background: #dcfce7;
      color: #166534;
    }
    .muted {
      color: #94a3b8;
      font-size: .78rem;
    }
    .btn-link {
      background: none;
      border: none;
      color: #1d4ed8;
      text-decoration: underline;
      cursor: pointer;
      font-size: .78rem;
    }
    .no-data {
      background: #fff;
      border-radius: 14px;
      border: 1px solid rgba(148,163,184,.1);
      padding: 1.2rem;
      text-align: center;
      color: #6b7280;
      box-shadow: 0 10px 20px rgba(15,23,42,.02);
    }
    @media (max-width: 850px) {
      .table-shell { overflow-x: auto; }
    }
  </style>
</head>
<body>

<?php include 'nav.php'; ?>

<main class="loans-shell">
  <div class="loans-hero">
    <h2>My Loans</h2>
    <p>See what you've borrowed, when it's due, and return items on time.</p>
  </div>

  <section class="mini-grid">
    <div class="mini-card">
      <h3>Member Dashboard</h3>
      <p>Your overall account & shortcuts.</p>
      <a href="member.php">Open -></a>
    </div>
    <div class="mini-card">
      <h3>Search Books</h3>
      <p>Find new items to reserve.</p>
      <a href="catalog.php">Browse -></a>
    </div>
    <div class="mini-card">
      <h3>Reservations</h3>
      <p>Pending and approved holds.</p>
      <a href="reservations.php">View -></a>
    </div>
    <div class="mini-card">
      <h3>Fines</h3>
      <p>Pay late fees if any.</p>
      <a href="fines.php">Pay -></a>
    </div>
  </section>

  <?php if ($flash): ?>
    <p class="flash-msg" style="background: <?= h($flashColor)==='red' ? '#fee2e2' : '#dcfce7' ?>; color: <?= h($flashColor)==='red' ? '#b91c1c' : '#166534' ?>;"> <!-- render flash text with chosen color -->
      <?= h($flash) ?>
    </p>
  <?php endif; ?>

  <?php if (empty($loans)): ?>
    <div class="no-data">
      You don't have any loans yet.
    </div>
  <?php else: ?>
    <div class="table-shell">
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
        <?php foreach ($loans as $L): ?> <!-- ierate over each loan row -->
          <?php
            $status = $L['status'] ?? ''; // read status string
            // compute days left / overdue (only for not-yet-returned loans with a due date)
            $hasReturn = !empty($L['return_date']); // whether the book has been returned
            $daysLabel = '-';
            if (!$hasReturn && !empty($L['due_date'])) { // only compute if the book is still out and due date exists
              try {
                $due = new DateTime($L['due_date']); // parse due date
                $diffDays = (int)$today->diff($due)->format('%r%a'); // get signed days difference, negative means its overdue
                if ($diffDays >= 0) { // if there's still time left
                  $daysLabel = $diffDays . ' day' . ($diffDays == 1 ? '' : 's') . ' left'; // this is set up to be singular/plural friendly
                } else { // if its overdue
                  $daysLabel = abs($diffDays) . ' day' . (abs($diffDays) == 1 ? '' : 's') . ' overdue';
                }
              } catch (Exception $e) {}
            }
          ?>
          <tr>
            <td><?= h($L['loan_id']) ?></td>
            <td><?= h($L['title'] ?? '') ?></td>
            <td><?= h($L['author'] ?? '') ?></td>
            <td><?= h($L['borrow_date'] ?? '') ?></td>
            <td><?= h($L['due_date'] ?? '') ?></td>
            <td><?= h($L['return_date'] ?? '') ?></td>
            <td><span class="badge-status <?= h($status) ?>"><?= h($status) ?></span></td>
            <td><?= h($daysLabel) ?></td>
            <td>
              <?php if ($status === 'Active'): ?> <!-- only allow return action for active loans -->
                <form method="post" action="loans.php" style="display:inline;"> <!-- posts back to same page -->
                  <input type="hidden" name="return_id" value="<?= (int)$L['loan_id'] ?>"> <!-- hidden input with loan id -->
                  <button type="submit" class="btn-link">Return</button>
                </form>
              <?php else: ?>
                <span class="muted">-</span> <!-- no action for non-active loans -->
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>

</body>
</html>
