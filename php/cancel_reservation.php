<?php
session_start();
include "connect.php";

// block access if not logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$userId = (int) $_SESSION['user_id'];
$resId  = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;

$message = "";
$color = "red";

if ($resId <= 0) {
  $message = "Invalid reservation.";
} else {
  // fetch reservation (must belong to user and be Pending)
  $stmt = mysqli_prepare(
    $database,
    "SELECT r.reservation_id, r.book_id, b.title
       FROM reservations r
       JOIN books b ON b.book_id = r.book_id
      WHERE r.reservation_id = ? AND r.user_id = ? AND r.status = 'Pending'
      LIMIT 1"
  );
  mysqli_stmt_bind_param($stmt, "ii", $resId, $userId);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $row = mysqli_fetch_assoc($res);
  mysqli_stmt_close($stmt);

  if ($row) {
    $bookId = (int)$row['book_id'];
    $bookTitle = $row['title'] ?? '';

    // delete pending reservation
    $stmt = mysqli_prepare(
      $database,
      "DELETE FROM reservations
       WHERE reservation_id = ? AND user_id = ? AND status = 'Pending'"
    );
    mysqli_stmt_bind_param($stmt, "ii", $resId, $userId);
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) > 0) {
      // log cancellation
      $who = $_SESSION['name'] ?? ('User#' . $userId);
      $what = "Cancelled reservation #{$resId} for book #{$bookId}" . ($bookTitle !== '' ? " (\"{$bookTitle}\")" : "");
      $logStmt = mysqli_prepare($database, "INSERT INTO logs (`user`, `action`) VALUES (?, ?)");
      mysqli_stmt_bind_param($logStmt, "ss", $who, $what);
      mysqli_stmt_execute($logStmt);
      mysqli_stmt_close($logStmt);

      $message = "Reservation for <strong>" . htmlspecialchars($bookTitle) . "</strong> has been cancelled successfully.";
      $color = "green";
    } else {
      $message = "Unable to cancel this reservation.";
    }
    mysqli_stmt_close($stmt);
  } else {
    $message = "Reservation not found or already processed.";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Cancel Reservation</title>
  <link rel="stylesheet" href="styles.css">
  <style>
  /* main shell */
  .home-shell {
    max-width: 1100px;
    margin: 1.75rem auto 3rem;
    padding: 0 1rem;
  }

  /* hero */
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

  <div class="home-shell">
    <div class="message-box">
      <p style="color: <?= $color === 'green' ? '#059669' : '#dc2626' ?>;"><?= $message ?></p>
      <a href="reservations.php" class="btn primary">Back to Reservations</a>
    </div>
  </div>

</body>
</html>

