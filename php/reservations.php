<?php
session_start(); // start or resume session to access $_SESSION data
include "connect.php"; // this is where $database comes from

if (!isset($_SESSION['user_id'])) { // requires you to login first, if no logged-in user in session currently
  header("Location: login.php"); // redirect you to login page
  exit(); // stops executing the rest of the script after redirecting you
}

$userId = (int) $_SESSION['user_id']; // gets the current user id from session

$flash = $_SESSION['flash_msg'] ?? ''; // read flash message from session if it is set
$flashColor = $_SESSION['flash_color'] ?? 'green'; // read flash color, the default is green if its not set
unset($_SESSION['flash_msg'], $_SESSION['flash_color']); // clear flash values so they show only once

$stmt = mysqli_prepare( // this prepares a SQL statement with parameters to fetch reservations
  $database,
  "SELECT r.reservation_id, r.reservation_date, r.status, b.title, b.author
  FROM reservations AS r
  JOIN books AS b ON b.book_id = r.book_id
  WHERE r.user_id = ?
  ORDER BY r.reservation_date DESC, r.reservation_id DESC"
);
mysqli_stmt_bind_param($stmt, "i", $userId); // binds the current user id as an integer to the SQL placeholder
mysqli_stmt_execute($stmt); // executes the prepared statement
$result = mysqli_stmt_get_result($stmt); // this retrieves the result set from the executed statement

// collect the rows from database
$reservations = []; // this array is meant to hold reservation rows
if ($result) { // if the query returned a result set
  while ($row = mysqli_fetch_assoc($result)) { // fetch each row as an associative array
    $reservations[] = $row; // append row to the $reservations array
  }
}
mysqli_stmt_close($stmt); // closes the prepared statement
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Member Dashboard</title>
  <link rel="stylesheet" href="styles.css"/>
  <style>
    body {
      background: #f3f4f6;
    }
    .res-shell {
      max-width: 1100px;
      margin: 2.2rem auto;
      padding: 0 1rem 2rem;
    }
    .res-header {
      background: linear-gradient(135deg, #0f766e 0%, #115e59 60%);
      border-radius: 16px;
      padding: 1.2rem 1.5rem 1.05rem;
      color: #fff;
      box-shadow: 0 18px 30px rgba(15,118,110,.22);
      margin-bottom: 1.6rem;
    }
    .res-header h2 {
      margin: 0 0 .25rem;
    }
    .res-header p {
      margin: 0;
      color: rgba(255,255,255,.82);
      font-size: .9rem;
    }
    .res-nav-cards {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
      margin-bottom: 1.4rem;
    }
    .res-card {
      background: #fff;
      border-radius: 14px;
      padding: .9rem .85rem .8rem;
      border: 1px solid rgba(148,163,184,.2);
      box-shadow: 0 10px 20px rgba(15,23,42,.02);
    }
    .res-card h3 {
      margin: 0 0 .4rem;
      font-size: .88rem;
    }
    .res-card p {
      margin: 0 0 .4rem;
      color: #6b7280;
      font-size: .78rem;
    }
    .res-card a {
      font-size: .77rem;
      font-weight: 600;
      color: #0f766e;
      text-decoration: none;
    }
    .flash-msg {
      margin-bottom: 1rem;
      padding: .55rem .8rem;
      border-radius: 10px;
      font-size: .82rem;
    }
    .res-table-wrap {
      background: #fff;
      border-radius: 14px;
      border: 1px solid rgba(148,163,184,.2);
      overflow: hidden;
      box-shadow: 0 10px 20px rgba(15,23,42,.03);
    }
    table.res-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 720px;
    }
    .res-table thead {
      background: #f8fafc;
    }
    .res-table th,
    .res-table td {
      padding: .65rem .75rem;
      text-align: left;
      font-size: .83rem;
      border-bottom: 1px solid #e2e8f0;
    }
    .res-table th {
      font-weight: 600;
      color: #475569;
    }
    .status-pill {
      display: inline-block;
      padding: .25rem .65rem;
      border-radius: 9999px;
      font-size: .7rem;
      font-weight: 600;
    }
    .status-pending {
      background: #fef9c3;
      color: #854d0e;
    }
    .status-approved {
      background: #dcfce7;
      color: #166534;
    }
    .status-fulfilled {
      background: #e2e8f0;
      color: #0f172a;
    }
    .no-data {
      background: #fff;
      border-radius: 14px;
      border: 1px solid rgba(148,163,184,.15);
      padding: 1.2rem;
      text-align: center;
      color: #6b7280;
      box-shadow: 0 10px 20px rgba(15,23,42,.02);
    }
    .btn-link {
      background: none;
      border: none;
      color: #0f766e;
      text-decoration: underline;
      font-size: .78rem;
      cursor: pointer;
    }
    @media (max-width: 850px) {
      .res-table-wrap {
        overflow-x: auto;
      }
    }
  </style>
</head>
<body>

<?php include 'nav.php'; ?>

<main class="res-shell">
  <div class="res-header">
    <h2>My Reservations</h2>
    <p>View your reserved books and cancel pending ones.</p>
  </div>

  <section class="res-nav-cards">
    <div class="res-card">
      <h3>Member Dashboard</h3>
      <p>Back to your overview.</p>
      <a href="member.php">Go -></a>
    </div>
    <div class="res-card">
      <h3>Search Books</h3>
      <p>Find a new title to reserve.</p>
      <a href="catalog.php">Browse catalog -></a>
    </div>
    <div class="res-card">
      <h3>My Loans</h3>
      <p>Check due dates.</p>
      <a href="loans.php">View loans -></a>
    </div>
    <div class="res-card">
      <h3>Fines</h3>
      <p>Pay late fees.</p>
      <a href="fines.php">View fines -></a>
    </div>
  </section>

  <?php if ($flash): ?> <!-- If there is a flash message to show -->
    <p class="flash-msg" style="background: <?= h($flashColor)==='red' ? '#fee2e2' : '#dcfce7' ?>; color: <?= h($flashColor)==='red' ? '#b91c1c' : '#166534' ?>;"> <!-- Display flash message in its selected color -->
      <?= h($flash) ?>
    </p>
  <?php endif; ?>

  <?php if (empty($reservations)): ?> <!-- if the current user has no reservations -->
    <div class="no-data">
      You don't have any reservations yet.
    </div>
  <?php else: ?>
    <div class="res-table-wrap">
      <table class="res-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Book Title</th>
            <th>Author</th>
            <th>Reservation Date</th>
            <th>Status</th>
            <th style="width:110px;">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($reservations as $r): 
          $status = $r['status'] ?? '';
          $statusClass = 'status-pill ';
          if ($status === 'Pending') {
            $statusClass .= 'status-pending';
          } elseif ($status === 'Approved') {
            $statusClass .= 'status-approved';
          } else {
            $statusClass .= 'status-fulfilled';
          }
        ?>
          <tr>
            <td><?= h((string)$r['reservation_id']) ?></td>
            <td><?= h($r['title'] ?? '') ?></td>
            <td><?= h($r['author'] ?? '') ?></td>
            <td><?= h($r['reservation_date'] ?? '') ?></td>
            <td><span class="<?= $statusClass ?>"><?= h($status) ?></span></td>
            <td>
              <?php if ($status === 'Pending'): ?> <!-- this shows cancel button only for pending reservations -->
                <form method="post" action="cancel_reservation.php" onsubmit="return confirm('Cancel this reservation?');" style="display:inline;"> <!-- POST to cancel the handler; confirm() asks user -->
                  <input type="hidden" name="reservation_id" value="<?= (int)$r['reservation_id'] ?>"> <!-- this is a hidden field with reservation id -->
                  <button type="submit" class="btn-link">Cancel</button>
                </form>
              <?php else: ?> <!-- If not pending so either approved or fulfilled -->
                <span class="muted">-</span> <!-- placeholder dash to indicate no action -->
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?> <!-- ends the loop -->
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</main>
</body>
</html>