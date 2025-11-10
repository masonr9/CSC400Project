<?php
session_start(); // Start or resume the session to access $_SESSION (logged-in user)
include "connect.php"; // this is where $database comes from

if (!isset($_SESSION['user_id'])) {  // If no user is logged in
  header("Location: login.php");  // it will redirect to login
  exit(); // stop further script execution
}
$userId = (int) $_SESSION['user_id'];  // Normalize current user's id to an integer

define('FINE_RATE_PER_DAY', 0.50); // $0.50/day 

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }  // Small helper to escape output for HTML

// Flash message
$flash = $_SESSION['flash_msg'] ?? ''; // Get flash message if set
$flashColor = $_SESSION['flash_color'] ?? 'green'; // Get flash color, default green
unset($_SESSION['flash_msg'], $_SESSION['flash_color']); // Clear them so they won’t persist

// Get list of loan_ids already paid as fines for this user, We need these to hide already-paid items
$paidLoanIds = [];  // Map of loan_id which is true for quick lookup
$stmt = mysqli_prepare( // Prepare statement to fetch paid fines tied to loans
  $database,
  "SELECT loan_id FROM fines WHERE user_id = ? AND paid = TRUE AND loan_id IS NOT NULL"
);
mysqli_stmt_bind_param($stmt, "i", $userId); // Bind current user id
mysqli_stmt_execute($stmt); // Run query
$resPaid = mysqli_stmt_get_result($stmt); // Get result cursor
if ($resPaid) {  // If query succeeded
  while ($r = mysqli_fetch_assoc($resPaid)) { // Iterate each row
    $paidLoanIds[(int)$r['loan_id']] = true; // Mark that loan as already paid
  }
}
mysqli_stmt_close($stmt); // Close statement to free resources

// Pull user's loans (we'll compute returned-late only), We only allow paying after the book is returned
$stmt = mysqli_prepare( // Prepare to fetch loans with book info
  $database,
  "SELECT l.loan_id, l.book_id, l.borrow_date, l.due_date, l.return_date, l.status,
          b.title, b.author
     FROM loans l
     JOIN books b ON b.book_id = l.book_id
    WHERE l.user_id = ?
    ORDER BY l.due_date ASC, l.loan_id ASC"
);
mysqli_stmt_bind_param($stmt, "i", $userId); // Bind user id
mysqli_stmt_execute($stmt); // Execute query
$res = mysqli_stmt_get_result($stmt); // Get results

$computed = []; // Computed fines keyed by loan_id (payable only)
$totalComputed = 0.0; // Sum of all payable computed fines

if ($res) { // If we got rows
  while ($row = mysqli_fetch_assoc($res)) { // Iterate each loan row
    $loanId  = (int)$row['loan_id']; // Normalize loan id
    $dueStr  = $row['due_date'] ?? null; // String due date
    $retStr  = $row['return_date'] ?? null;  // String return date

    // Only consider fines for loans that are already returned
    if (empty($dueStr) || empty($retStr)) { // If due or return date missing
      continue; // Skip this loan (not payable)
    }

    try { // Try parsing dates
      $due = new DateTime($dueStr); // Due date as DateTime
      $ret = new DateTime($retStr); // Return date as DateTime
    } catch (Throwable $e) { // If invalid date format
      continue; // Skip this record
    }

    // Overdue if returned after due date, Determine lateness
    if ($ret > $due) { // If returned late
      $daysOverdue = (int)$due->diff($ret)->format('%a'); // Compute days late (absolute)
      $amount = $daysOverdue * FINE_RATE_PER_DAY; // Fine = days late * rate

      // Skip if already paid for this loan, Hide items already settled
      if (!isset($paidLoanIds[$loanId]) && $daysOverdue > 0 && $amount > 0) { // If unpaid and positive amount
        $computed[$loanId] = [ // Store payable row
          'loan_id'      => $loanId,
          'title'        => (string)($row['title'] ?? 'Unknown'),
          'author'       => (string)($row['author'] ?? ''),
          'due_date'     => $dueStr,
          'return_date'  => $retStr,
          'days_overdue' => $daysOverdue,
          'amount'       => $amount,
        ];
        $totalComputed += $amount; // Add to total payable
      }
    }
  }
}
mysqli_stmt_close($stmt); // Close loans statement


// Pay one returned-loan fine, Single fine payment action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_single_loan'])) { // If POSTing a single-loan payment
  $loanId = (int)$_POST['pay_single_loan']; // Normalize incoming loan id

  if (!isset($computed[$loanId])) {  // If that loan isn’t currently payable
    $_SESSION['flash_msg'] = "This fine cannot be paid (not returned, not overdue, already paid, or invalid)."; // Inform user
    $_SESSION['flash_color'] = "red";  // Error color
    header("Location: fines.php"); // Redirect back
    exit(); // Stop processing
  }

  $amount = (float)$computed[$loanId]['amount']; // Amount to pay for this loan

  $stmt = mysqli_prepare( // Prepare insert into fines table
    $database,
    "INSERT INTO fines (user_id, loan_id, amount, paid) VALUES (?, ?, ?, TRUE)"
  );
  mysqli_stmt_bind_param($stmt, "iid", $userId, $loanId, $amount); // Bind user, loan, amount

  if (mysqli_stmt_execute($stmt)) {  // Try to record payment
    // insert into logs, record the single fine payment
    $who   = $_SESSION['name'] ?? ('User#' . $userId);
    $title = $computed[$loanId]['title'] ?? '';
    $amtStr = '$' . number_format($amount, 2);
    $what  = $title !== ''
      ? "Paid fine {$amtStr} for loan #{$loanId} (\"{$title}\")"
      : "Paid fine {$amtStr} for loan #{$loanId}";

    $stmtLog = mysqli_prepare($database, "INSERT INTO logs (`user`, `action`) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmtLog, "ss", $who, $what);
    mysqli_stmt_execute($stmtLog);
    mysqli_stmt_close($stmtLog);

    $_SESSION['flash_msg'] = "Fine for loan #{$loanId} ($" . number_format($amount, 2) . ") paid."; // Success message
    $_SESSION['flash_color'] = "green"; // Success color
  } else {
    $_SESSION['flash_msg'] = "Could not record payment. Please try again."; // Failure message
    $_SESSION['flash_color'] = "red"; // Error color
  }
  mysqli_stmt_close($stmt); // Close insert statement

  header("Location: fines.php"); // Redirect to refresh list (paid item disappears)
  exit(); // Stop
}

// Pay all returned-loan fines (one row per loan so each is tracked)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_all'])) { // If user clicked “Pay All”
  if (empty($computed)) {  // Nothing to pay
    $_SESSION['flash_msg'] = "No payable fines (returned-late) to pay."; // Inform user
    $_SESSION['flash_color'] = "red"; // Error color
    header("Location: fines.php");  // Redirect back
    exit(); // Stop
  }

  mysqli_begin_transaction($database); // Start transaction
  try { // Try to insert payments for each payable loan
    foreach ($computed as $loanId => $c) { // Loop each payable item
      $amount = (float)$c['amount']; // Amount for this loan
      $stmt = mysqli_prepare( // Prepare insert
        $database,
        "INSERT INTO fines (user_id, loan_id, amount, paid) VALUES (?, ?, ?, TRUE)"
      );
      mysqli_stmt_bind_param($stmt, "iid", $userId, $loanId, $amount); // Bind values
      if (!mysqli_stmt_execute($stmt)) { // Execute and check
        throw new Exception("Insert failed for loan {$loanId}"); // Throw to trigger rollback on any failure
      }
      // insert into logs, record each fine payment in bulk
      $who   = $_SESSION['name'] ?? ('User#' . $userId);
      $title = (string)($c['title'] ?? '');
      $amtStr = '$' . number_format($amount, 2);
      $what  = $title !== ''
        ? "Paid fine {$amtStr} for loan #{$loanId} (\"{$title}\")"
        : "Paid fine {$amtStr} for loan #{$loanId}";

      $stmtLog = mysqli_prepare($database, "INSERT INTO logs (`user`, `action`) VALUES (?, ?)");
      mysqli_stmt_bind_param($stmtLog, "ss", $who, $what);
      mysqli_stmt_execute($stmtLog);
      mysqli_stmt_close($stmtLog);
      mysqli_stmt_close($stmt); // Close statement
    }
    mysqli_commit($database); // Commit all inserts
    $_SESSION['flash_msg'] = "All returned-loan fines paid."; // Success flash
    $_SESSION['flash_color'] = "green"; // Success color
  } catch (Throwable $e) { // On any exception
    mysqli_rollback($database); // Rollback the transaction
    $_SESSION['flash_msg'] = "Could not pay all fines: " . $e->getMessage(); // Error message
    $_SESSION['flash_color'] = "red"; // Error color
  }

  header("Location: fines.php"); // Redirect back to refresh UI
  exit(); // Stop
}

// Pull recorded fines to show a history, paid/unpaid
$recorded = []; // Collected list of recorded fines
$totalRecordedUnpaid = 0.0; // Sum of unpaid recorded fines
$stmt = mysqli_prepare( // Prepare select from fines table
  $database,
  "SELECT fine_id, loan_id, amount, paid
     FROM fines
    WHERE user_id = ?
    ORDER BY fine_id ASC"
);
mysqli_stmt_bind_param($stmt, "i", $userId); // Bind user id
mysqli_stmt_execute($stmt);  // Execute
$res2 = mysqli_stmt_get_result($stmt);  // Get results
if ($res2) { // If successful
  while ($r = mysqli_fetch_assoc($res2)) {  // Iterate
    $recorded[] = $r; // Add to list
    if ((int)$r['paid'] === 0) { // If unpaid
      $totalRecordedUnpaid += (float)$r['amount']; // Add to unpaid total
    }
  }
}
mysqli_stmt_close($stmt); // Close statement
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Fines</title>
  <link rel="stylesheet" href="styles.css"/> 
  <style>
    body { 
      background: #f3f4f6; 
    }
    .fines-shell {
      max-width: 1150px;
      margin: 2.1rem auto 2.5rem;
      padding: 0 1rem;
    }
    .fines-hero {
      background: linear-gradient(135deg, #b91c1c 0%, #be123c 70%);
      border-radius: 16px;
      padding: 1.2rem 1.35rem 1.25rem;
      color: #fff;
      box-shadow: 0 20px 35px rgba(190,18,60,.25);
      margin-bottom: 1.5rem;
    }
    .fines-hero h2 {
      margin: 0 0 .3rem;
    }
    .fines-hero p {
      margin: 0;
      color: rgba(255,255,255,.85);
      font-size: .9rem;
    }
    .top-links {
      display: flex;
      gap: .6rem;
      flex-wrap: wrap;
      margin-bottom: 1.3rem;
    }
    .top-links a {
      background: #fff;
      color: #1f2937;
      border-radius: 9999px;
      padding: .38rem .75rem;
      font-size: .72rem;
      border: 1px solid rgba(148,163,184,.35);
      text-decoration: none;
      display: inline-flex;
      gap: .35rem;
      align-items: center;
    }
    .flash {
      padding: .7rem .85rem;
      border-radius: 10px;
      margin-bottom: 1rem;
      font-size: .82rem;
    }
    .card {
      background: #fff;
      border-radius: 14px;
      border: 1px solid rgba(148,163,184,.12);
      box-shadow: 0 10px 32px rgba(15,23,42,.03);
      margin-bottom: 1.5rem;
      overflow: hidden;
    }
    .card-header {
      padding: .85rem 1rem .6rem;
      border-bottom: 1px solid #e2e8f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
    }
    .card-header h3 {
      margin: 0;
      font-size: .95rem;
      color: #0f172a;
    }
    .card-body {
      padding: .75rem 1rem 1rem;
    }
    .tag {
      background: rgba(248,250,252,.7);
      border: 1px solid rgba(148,163,184,.25);
      border-radius: 9999px;
      padding: .35rem .7rem;
      font-size: .7rem;
      color: #0f172a;
    }
    table.fines-table {
      width: 100%;
      border-collapse: collapse;
    }
    .fines-table th,
    .fines-table td {
      padding: .5rem .55rem;
      border-bottom: 1px solid #e2e8f0;
      text-align: left;
      font-size: .78rem;
    }
    .fines-table thead {
      background: #f8fafc;
    }
    .btn-link {
      background: none;
      border: none;
      color: #b91c1c;
      text-decoration: underline;
      cursor: pointer;
      font-size: .75rem;
    }
    .bulk-btn {
      background: #b91c1c;
      color: #fff;
      border: none;
      border-radius: 9999px;
      padding: .4rem .75rem;
      font-size: .7rem;
      cursor: pointer;
    }
    .muted {
      color: #94a3b8;
      font-size: .78rem;
    }
    .amount-badge {
      background: #fee2e2;
      color: #b91c1c;
      border-radius: 9999px;
      padding: .25rem .55rem;
      font-size: .7rem;
      display: inline-block;
    }
    .totals {
      margin-top: .6rem;
      font-size: .8rem;
      color: #0f172a;
    }
    @media (max-width: 850px) {
      .card-body { overflow-x: auto; }
    }
  </style>
</head>
<body>

<?php include 'nav.php'; ?>

<main class="fines-shell">
  <div class="fines-hero">
    <h2>My Fines</h2>
    <p>View late-return charges and settle them online.</p>
  </div>

  <div class="top-links">
    <a href="member.php">Member Dashboard</a>
    <a href="catalog.php">Search Books</a>
    <a href="reservations.php">My Reservations</a>
    <a href="loans.php">Loans</a>
  </div>

  <?php if ($flash): ?> <!-- If there is a flash message -->
    <p class="flash" style="background: <?= h($flashColor)==='red' ? '#fee2e2' : '#dcfce7' ?>; color: <?= h($flashColor)==='red' ? '#b91c1c' : '#166534' ?>;">
      <?= h($flash) ?>
    </p> <!-- show flash message with color -->
  <?php endif; ?>

  <!-- Payable fines -->
  <section class="card">
    <div class="card-header">
      <h3>Payable Fines (Returned-Late)</h3>
      <span class="tag">Total payable: $<?= number_format($totalComputed, 2) ?></span>
    </div>
    <div class="card-body">
      <?php if (empty($computed)): ?> <!-- if nothing to pay -->
        <p class="muted">No payable fines. Fines become payable only after the book is returned late.</p>
      <?php else: ?> <!-- else show table and actions -->
        <form method="post" style="margin-bottom:.6rem;">
          <button type="submit" name="pay_all" class="bulk-btn" onclick="return confirm('Pay all fines now?');">
            Pay All ($<?= number_format($totalComputed, 2) ?>)
          </button>
        </form>
        <table class="fines-table">
          <thead>
            <tr>
              <th># Loan</th>
              <th>Book Title</th>
              <th>Author</th>
              <th>Due</th>
              <th>Returned</th>
              <th>Days Late</th>
              <th>Fine (<?= '$'.number_format(FINE_RATE_PER_DAY, 2) ?>/day)</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($computed as $c): ?> <!-- loop each payable fine -->
            <tr>
              <td><?= h($c['loan_id']) ?></td> <!-- show loan id -->
              <td><?= h($c['title']) ?></td>
              <td><?= h($c['author']) ?></td>
              <td><?= h($c['due_date']) ?></td>
              <td><?= h($c['return_date']) ?></td>
              <td><?= h($c['days_overdue']) ?></td>
              <td><span class="amount-badge">$<?= number_format($c['amount'], 2) ?></span></td>
              <td>
                <form method="post" style="display:inline;" onsubmit="return confirm('Pay this fine now?');"> <!-- single pay form -->
                  <input type="hidden" name="pay_single_loan" value="<?= (int)$c['loan_id'] ?>"> <!-- hidden loan id -->
                  <button type="submit" class="btn-link">Pay</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?> <!-- End loop -->
          </tbody>
        </table>
        <p class="totals">Total payable: $<?= number_format($totalComputed, 2) ?></p> <!-- total payable sum -->
      <?php endif; ?> <!-- end payable block -->
    </div>
  </section>

  <!-- History -->
  <section class="card">
    <div class="card-header">
      <h3>Recorded Fines (History)</h3>
      <?php if ($totalRecordedUnpaid > 0): ?> <!-- if unpaid recorded fines exist -->
        <span class="tag" style="background:#fef9c3;border-color:rgba(250,204,21,.35);">Unpaid total: $<?= number_format($totalRecordedUnpaid, 2) ?></span> <!-- show unpaid total -->
      <?php endif; ?> <!-- end unpaid total -->
    </div>
    <div class="card-body">
      <?php if (empty($recorded)): ?> <!-- if none recorded -->
        <p class="muted">No recorded fines found.</p>
      <?php else: ?> <!-- else show history table -->
        <table class="fines-table">
          <thead>
            <tr>
              <th># Fine</th>
              <th># Loan</th>
              <th>Amount</th>
              <th>Paid</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($recorded as $f): ?> <!-- loop recorded fines -->
            <tr>
              <td><?= h($f['fine_id']) ?></td> <!-- show fine id -->
              <td><?= h($f['loan_id'] ?? '-') ?></td> <!-- show related loan id or dash -->
              <td>$<?= number_format((float)$f['amount'], 2) ?></td> <!-- show amount -->
              <td><?= ((int)$f['paid'] === 1) ? 'Yes' : 'No' ?></td> <!-- show paid status -->
            </tr>
          <?php endforeach; ?> <!-- end loop -->
          </tbody>
        </table>
      <?php endif; ?> <!-- end recorded block -->
      <p class="muted" style="margin-top:.6rem;"> <!-- Explanatory note -->
        You can only pay a fine after the book is returned. Paid fines are tied to the specific loan and will no longer appear in the payable list.
      </p>
    </div>
  </section>
</main>
</body>
</html>