<!DOCTYPE html> 
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Librarian Dashboard</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php include 'nav.php'; ?>

<main>
  <section>
    <h2>Librarian Dashboard</h2>
    <p id="welcomeUser"></p>
    <h3>Available Tasks</h3>
    <ul class="task-list">
      <li><a href="manage_books.php">Manage Books</a></li>
      <li><a href="approve_reservations.php">Approve Reservations</a></li>
      <li><a href="overdue_tracking.php">Overdue Tracking</a></li>
      <li><a href="member_management.php">Member Management</a></li>
    </ul>
  </section>
</main>

</body>
</html>