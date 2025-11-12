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
    .shell { 
      max-width: 1100px; 
      margin: 1.5rem auto 3rem; 
      padding: 0 1rem; 
    }
    .card {
      background: #fff; border: 1px solid #eef2f7; border-radius: 12px; box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05); padding: 1.25rem 1.2rem;
    }

    /* --- Header / Hero --- */
    .hero {
      background: radial-gradient(circle at top, #e5f0ff 0%, #ffffff 42%, #ffffff 100%);
      border: 1px solid #e5e7eb; border-radius: 14px; padding: 1.5rem 1.25rem;
      display: flex; align-items: center; justify-content: space-between; gap: 1rem;
      margin-bottom: 1rem;
    }
    .hero h2 { margin: 0; font-size: 1.5rem; color: #111827; }
    .subtle { color: #6b7280; margin: .2rem 0 0; }

    /* --- Toolbar --- */
    .toolbar { display: flex; gap: .6rem; flex-wrap: wrap; align-items: center; margin: .75rem 0 1rem; }
    .toolbar .search {
      flex: 1 1 240px; min-width: 220px; display: flex; align-items: center;
      border: 1px solid #e5e7eb; border-radius: 10px; padding: .45rem .6rem;
      background: #fff;
    }
    .toolbar .search input { border: 0; outline: none; width: 100%; font-size: .95rem; }
    .chip {
      border: 1px solid #e5e7eb; border-radius: 9999px; padding: .35rem .7rem;
      background: #fff; cursor: pointer;
    }
    .chip.active { background: #111827; color: #fff; border-color: #111827; }

    /* --- Table --- */
    .table-wrap { overflow-x: auto; }
    table.pretty {
      width: 100%; border-collapse: collapse; font-size: .95rem;
    }
    table.pretty thead th {
      text-align: left; padding: .65rem .6rem; color: #6b7280; font-weight: 700;
      border-bottom: 1px solid #e5e7eb; white-space: nowrap;
    }
    table.pretty tbody td {
      padding: .65rem .6rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle;
    }
    tr:hover { background: #fcfcfd; }

    /* --- Status badge --- */
    .badge {
      display: inline-block; font-size: .75rem; font-weight: 700;
      padding: .2rem .6rem; border-radius: 9999px; letter-spacing: .02em;
    }
    .Pending   { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .Approved  { background: #ecfeff; color: #155e75; border: 1px solid #a5f3fc; }
    .Fulfilled { background: #ecfdf5; color: #166534; border: 1px solid #bbf7d0; }

    /* --- Buttons --- */
    .btn {
      display: inline-flex; align-items: center; gap: .35rem;
      border: 1px solid #e5e7eb; background: #fff; color: #1f2937;
      padding: .45rem .75rem; border-radius: 8px; cursor: pointer; text-decoration: none;
      font-weight: 600; font-size: .88rem;
    }
    .btn:hover { background: #f9fafb; }
    .btn.primary { background: #dc2626; color: #fff; border-color: #dc2626; }
    .btn.primary:hover { background: #b91c1c; border-color: #b91c1c; }
    .btn.success { background: #16a34a; color: #fff; border-color: #16a34a; }
    .btn.success:hover { background: #15803d; border-color: #15803d; }

    /* --- Flash --- */
    .flash { padding: .6rem .8rem; border-radius: 10px; margin: .6rem 0 1rem; font-weight: 600; }
    .flash.green { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .flash.red   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

    /* --- Responsive: stack cells --- */
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
        <h2>Approve Reservations</h2>
        <p class="subtle">Review pending requests, approve, or fulfill to create loans.</p>
      </div>
  </div>
  <?php if ($flash): ?>
    <div class="flash <?= h($flashColor) ?>"><?= h($flash) ?></div>
  <?php endif; ?>

  <section class="card">
    <!-- Toolbar -->
    <div class="toolbar">
      <div class="search">
        <input type="text" id="searchBox" placeholder="Search by member or book…">
      </div>
      <button class="chip active" style="font-size: 0.85em;" data-status="All">All</button>
      <button class="chip" style="font-size: 0.85em;" data-status="Pending">Pending</button>
      <button class="chip" style="font-size: 0.85em;" data-status="Approved">Approved</button>
      <button class="chip" style="font-size: 0.85em;" data-status="Fulfilled">Fulfilled</button>
    </div>

    <?php if (empty($reservations)): ?>
      <p class="subtle">There are no reservations.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="pretty" id="reservationTable">
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
            <?php
              $status = (string)($r['status'] ?? '');
              $badgeClass = in_array($status, ['Pending','Approved','Fulfilled'], true) ? $status : '';
            ?>
            <tr
              data-member="<?= h(mb_strtolower($r['member_name'])) ?>"
              data-book="<?= h(mb_strtolower($r['book_title'])) ?>"
              data-status="<?= h($status) ?>"
            >
              <td data-label="Member"><?= h($r['member_name']) ?></td>
              <td data-label="Book"><?= h($r['book_title']) ?></td>
              <td data-label="Reservation Date"><?= h($r['reservation_date'] ?? '') ?></td>
              <td data-label="Status">
                <span class="badge <?= h($badgeClass) ?>"><?= h($status) ?></span>
              </td>
              <td data-label="Action">
                <?php if ($status === 'Pending'): ?>
                  <form method="post" action="approve_reservations.php" style="display:inline;">
                    <input type="hidden" name="approve_id" value="<?= (int)$r['reservation_id'] ?>">
                    <button type="submit" class="btn primary">Approve</button>
                  </form>
                <?php elseif ($status === 'Approved'): ?>
                  <form method="post" action="approve_reservations.php" style="display:inline;">
                    <input type="hidden" name="fulfill_id" value="<?= (int)$r['reservation_id'] ?>">
                    <button type="submit" class="btn success">Fulfill → Loan</button>
                  </form>
                <?php else: ?>
                  <span class="subtle">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>

<script>
  // Simple client-side filtering (status & search)
  const chips = document.querySelectorAll('.chip');
  const rows  = document.querySelectorAll('#reservationTable tbody tr');
  const box   = document.getElementById('searchBox');

  let activeStatus = 'All';

  function applyFilters() {
    const q = (box.value || '').trim().toLowerCase();

    rows.forEach(tr => {
      const member = tr.getAttribute('data-member') || '';
      const book   = tr.getAttribute('data-book') || '';
      const status = tr.getAttribute('data-status') || '';

      const matchStatus = (activeStatus === 'All') || (status === activeStatus);
      const matchSearch = !q || member.includes(q) || book.includes(q);

      tr.style.display = (matchStatus && matchSearch) ? '' : 'none';
    });
  }

  chips.forEach(c => {
    c.addEventListener('click', () => {
      chips.forEach(x => x.classList.remove('active'));
      c.classList.add('active');
      activeStatus = c.getAttribute('data-status');
      applyFilters();
    });
  });

  box.addEventListener('input', applyFilters);
</script>

</body>
</html>
