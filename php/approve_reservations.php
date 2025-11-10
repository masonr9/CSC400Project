<?php
session_start(); 
include "connect.php"; 

if (!isset($_SESSION['user_id'])) { 
  header("Location: login.php");
  exit();
}

$role = $_SESSION['role'] ?? 'Member'; 
if (!in_array($role, ['Librarian','Admin'], true)) { 
  http_response_code(403);
  echo "Forbidden: Librarian/Admin access only.";  
  exit();
}

define('DEFAULT_LOAN_DAYS', 14);

$flash = $_SESSION['flash_msg'] ?? ''; 
$flashColor = $_SESSION['flash_color'] ?? 'green';
unset($_SESSION['flash_msg'], $_SESSION['flash_color']);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

function log_action(mysqli $db, string $what): void {
  $who  = $_SESSION['name'] ?? ('User#' . (int)($_SESSION['user_id'] ?? 0));
  $stmt = mysqli_prepare($db, "INSERT INTO logs (`user`, `action`) VALUES (?, ?)");
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $who, $what);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) { 
  $resId = (int) $_POST['approve_id']; 
  if ($resId > 0) { 
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

    $stmt = mysqli_prepare($database,
      "UPDATE reservations SET status = 'Approved' WHERE reservation_id = ? AND status = 'Pending'"
    );
    mysqli_stmt_bind_param($stmt, "i", $resId);
    mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($affected > 0) { 
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

      $_SESSION['flash_msg'] = "Reservation approved.";
      $_SESSION['flash_color'] = "green";
    } else {
      $_SESSION['flash_msg'] = "Unable to approve reservation (already approved/fulfilled or not found).";
      $_SESSION['flash_color'] = "red";
    }
  } else {
    $_SESSION['flash_msg'] = "Invalid reservation id.";
    $_SESSION['flash_color'] = "red";
  }
  header("Location: approve_reservations.php");
  exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fulfill_id'])) { 
  $resId = (int) $_POST['fulfill_id']; 
  if ($resId <= 0) { 
    $_SESSION['flash_msg'] = "Invalid reservation id."; 
    $_SESSION['flash_color'] = "red";
    header("Location: approve_reservations.php");
    exit();
  }

  $stmt = mysqli_prepare(
    $database,
    "SELECT r.reservation_id, r.user_id, r.book_id, r.status, b.available, b.title
     FROM reservations r
     JOIN books b ON b.book_id = r.book_id
     WHERE r.reservation_id = ?
     LIMIT 1"
  );
  mysqli_stmt_bind_param($stmt, "i", $resId);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $row = mysqli_fetch_assoc($res);
  mysqli_stmt_close($stmt);

  if (!$row) {
    $_SESSION['flash_msg'] = "Reservation not found.";
    $_SESSION['flash_color'] = "red";
    header("Location: approve_reservations.php");
    exit();
  }

  if ($row['status'] !== 'Approved') {
    $_SESSION['flash_msg'] = "Only approved reservations can be fulfilled.";
    $_SESSION['flash_color'] = "red";
    header("Location: approve_reservations.php");
    exit();
  }

  $userId = (int)$row['user_id'];
  $bookId = (int)$row['book_id'];
  $bookTitle = $row['title'] ?? 'Unknown Title';

  $stmt = mysqli_prepare(
    $database,
    "SELECT loan_id FROM loans WHERE book_id = ? AND status = 'Active' LIMIT 1"
  );
  mysqli_stmt_bind_param($stmt, "i", $bookId);
  mysqli_stmt_execute($stmt);
  mysqli_stmt_store_result($stmt);
  $activeLoanExists = mysqli_stmt_num_rows($stmt) > 0;
  mysqli_stmt_close($stmt);

  if ($activeLoanExists) {
    $_SESSION['flash_msg'] = "This book already has an active loan. Cannot fulfill reservation.";
    $_SESSION['flash_color'] = "red";
    header("Location: approve_reservations.php");
    exit();
  }

  mysqli_begin_transaction($database);
  try {
    $stmt = mysqli_prepare(
      $database,
      "INSERT INTO loans (user_id, book_id, borrow_date, due_date, status)
       VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), 'Active')"
    );
    $days = DEFAULT_LOAN_DAYS;
    mysqli_stmt_bind_param($stmt, "iii", $userId, $bookId, $days);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare(
      $database,
      "UPDATE reservations SET status = 'Fulfilled' WHERE reservation_id = ? AND status = 'Approved'"
    );
    mysqli_stmt_bind_param($stmt, "i", $resId);
    mysqli_stmt_execute($stmt);
    $resAffected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($resAffected <= 0) { 
      throw new Exception("Reservation status could not be updated to Fulfilled.");
    }

    $stmt = mysqli_prepare($database, "UPDATE books SET available = FALSE WHERE book_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $bookId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($database);

    $logText = sprintf(
      'Fulfilled reservation #%d and created loan for user #%d - book #%d "%s" (due in %d days)',
      $resId, $userId, $bookId, $bookTitle, $days
    );
    log_action($database, $logText);
    $_SESSION['flash_msg'] = "Reservation fulfilled and loan created. Due in {$days} days.";
    $_SESSION['flash_color'] = "green";

  } catch (Throwable $e) {
    mysqli_rollback($database);
    $_SESSION['flash_msg'] = "Could not fulfill reservation: " . $e->getMessage();
    $_SESSION['flash_color'] = "red";
  }

  header("Location: approve_reservations.php");
  exit();
}

$stmt = mysqli_prepare(
  $database,
  "SELECT r.reservation_id, r.reservation_date, r.status, u.name AS member_name, b.title AS book_title
   FROM reservations r
   JOIN users u ON u.user_id = r.user_id
   JOIN books b ON b.book_id = r.book_id
   ORDER BY FIELD(r.status,'Pending','Approved','Fulfilled'),
            r.reservation_date DESC, r.reservation_id DESC"
);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$reservations = [];
if ($result) {
  while ($row = mysqli_fetch_assoc($result)) {
    $reservations[] = $row;
  }
}
mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approve Reservations</title>
<link rel="stylesheet" href="styles.css">

<style>
  /* main shell */
  .home-shell {
    max-width: 1100px;
    margin: 1.75rem auto 3rem;
    padding: 0 1rem;
  }

  /* hero and quick cards styles (as given by user) */
  /* ... full CSS block from your message ... */
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

