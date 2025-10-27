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
</head>
<body>

<?php include 'nav.php'; ?>

<main>
  <section>
    <h2>My Reservations</h2>
    <ul>
      <li><a href="member.php">Member Dashboard</a></li>
      <li><a href="catalog.php">Search Books</a></li>
      <li><a href="loans.php">My Loans</a></li>
      <li><a href="fines.php">Fines</a></li>
    </ul>

    <?php if ($flash): ?> <!-- If there is a flash message to show -->
      <p style="color: <?= h($flashColor) ?>;"><?= h($flash) ?></p> <!-- Display flash message in its selected color -->
    <?php endif; ?>

    <?php if (empty($reservations)): ?> <!-- if the current user has no reservations -->
      <p class="muted">You don't have any reservations yet.<p>
    <?php else: ?>
      <table class="res-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Book Title</th>
            <th>Author</th>
            <th>Reservation Date</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($reservations as $r): ?> <!-- this loops through each reservation row -->
          <tr>
            <td><?= htmlspecialchars((string)$r['reservation_id']) ?></td>
            <td><?= htmlspecialchars($r['title'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['author'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['reservation_date'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['status'] ?? '') ?></td>
            <td>
              <?php if (($r['status'] ?? '') === 'Pending'): ?> <!-- this shows cancel button only for pending reservations -->
                  <form method="post" action="cancel_reservation.php" onsubmit="return confirm('Cancel this reservation?');" style="display:inline;"> <!-- POST to cancel the handler; confirm() asks user -->
                    <input type="hidden" name="reservation_id" value="<?= (int)$r['reservation_id'] ?>"> <!-- this is a hidden field with reservation id -->
                    <button type="submit" class="btn-link">Cancel</button>
                  </form>
                <?php else: ?> <!-- if not pending so either approved or fulfilled -->
                  <span class="muted">-</span> <!-- Placeholder dash to indicate no action -->
                <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?> <!-- Ends the loop -->
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</main>
</body>
</html>