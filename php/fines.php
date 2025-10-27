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
</head>
<body>

<?php include 'nav.php'; ?>

<main class="container">
  <section>   
    <ul>
      <li><a href="member.php">Member Dashboard</a></li>
      <li><a href="catalog.php">Search Books</a></li>
      <li><a href="reservations.php">My Reservations</a></li>
      <li><a href="loans.php">Loans</a></li>
    </ul>
    <h2>Payable Fines (Returned-Late Only)
      <span class="badge">$<?= number_format($totalComputed, 2) ?></span>
    </h2>

    <?php if ($flash): ?>  <!-- If there is a flash message -->
      <p style="color: <?= h($flashColor) ?>;"><?= h($flash) ?></p> <!-- Show flash with color -->
    <?php endif; ?> <!-- End flash -->

    <?php if (empty($computed)): ?>  <!-- If nothing to pay -->
      <p class="muted">No payable fines. Fines become payable only after the book is returned late.</p>
    <?php else: ?>  <!-- Else show table and actions -->
      <form method="post" style="margin:.5rem 0;">
        <button type="submit" name="pay_all" class="btn-link"
                onclick="return confirm('Pay all returned-loan fines now?');">  <!-- Confirm bulk pay -->
          Pay All (<?= '$'.number_format($totalComputed, 2) ?>) 
        </button>
      </form>

      <table class="fines"> 
        <thead>
          <tr>
            <th># Loan</th>
            <th>Book Title</th> 
            <th>Author</th>
            <th>Due Date</th>
            <th>Returned</th>
            <th>Days Late</th>
            <th>Fine (<?= '$'.number_format(FINE_RATE_PER_DAY, 2) ?>/day)</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($computed as $c): ?><!-- Loop each payable fine -->
          <tr>
            <td><?= h($c['loan_id']) ?></td> <!-- Show loan id -->
            <td><?= h($c['title']) ?></td> 
            <td><?= h($c['author']) ?></td> 
            <td><?= h($c['due_date']) ?></td>
            <td><?= h($c['return_date']) ?></td>
            <td><?= h($c['days_overdue']) ?></td>
            <td>$<?= number_format($c['amount'], 2) ?></td> 
            <td class="actions">
              <form method="post" style="display:inline;" onsubmit="return confirm('Pay this fine now?');"> <!-- Single pay form -->
                <input type="hidden" name="pay_single_loan" value="<?= (int)$c['loan_id'] ?>"> <!-- Hidden loan id -->
                <button type="submit" class="btn-link">Pay</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>  <!-- End loop -->
        </tbody>
      </table>

      <p class="totals">Total payable: $<?= number_format($totalComputed, 2) ?></p> <!-- Total payable sum -->
    <?php endif; ?> <!-- End payable block -->
  </section>

  <section style="margin-top:2rem;">   <!-- History section -->
    <h3>Recorded Fines (History)</h3>
    <?php if (empty($recorded)): ?>  <!-- If none recorded -->
      <p class="muted">No recorded fines found.</p> <!-- Inform user -->
    <?php else: ?> <!-- Else show history table -->
      <table class="fines"> <!-- History table -->
        <thead>
          <tr>
            <th># Fine</th> 
            <th># Loan</th>
            <th>Amount</th>
            <th>Paid</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($recorded as $f): ?> <!-- Loop recorded fines -->
          <tr>
            <td><?= h($f['fine_id']) ?></td>  <!-- Show fine id -->
            <td><?= h($f['loan_id'] ?? '—') ?></td> <!-- Show related loan id or dash -->
            <td>$<?= number_format((float)$f['amount'], 2) ?></td> <!-- Show amount -->
            <td><?= ((int)$f['paid'] === 1) ? 'Yes' : 'No' ?></td> <!-- Show paid status -->
          </tr>
        <?php endforeach; ?> <!-- End loop -->
        </tbody>
      </table>
      <?php if ($totalRecordedUnpaid > 0): ?> <!-- If unpaid recorded fines exist -->
        <p class="totals">Total recorded unpaid: $<?= number_format($totalRecordedUnpaid, 2) ?></p> <!-- Show unpaid total -->
      <?php endif; ?>  <!-- End unpaid total -->
    <?php endif; ?> <!-- End recorded block -->
    <p class="muted" style="margin-top:.5rem;"> <!-- Explanatory note -->
      You can only pay a fine after the book is returned. Paid fines are tied to the specific loan and will no longer appear in the payable list.
    </p>
  </section>
</main>
</body>
</html>