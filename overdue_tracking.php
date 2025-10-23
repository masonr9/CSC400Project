<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Librarian') {
  header("Location: login.php");
  exit();
}
include 'nav.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Overdue Tracking</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<main>
  <h2>Overdue Tracking</h2>

  <table border="1" width="100%" id="overdueTable">
    <thead>
      <tr>
        <th>Member</th>
        <th>Book</th>
        <th>Due Date</th>
        <th>Reminder</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</main>

<script>
  // load demo overdue items from localStorage (this file is a PHP conversion of the original static page)
  let overdue = JSON.parse(localStorage.getItem("overdue")) || [];

  function renderOverdue() {
    let tbody = document.querySelector("#overdueTable tbody");
    tbody.innerHTML = "";
    overdue.forEach((o, index) => {
      let row = `<tr>
        <td>${o.member}</td>
        <td>${o.book}</td>
        <td>${o.due}</td>
        <td><button onclick=\"sendReminder(${index})\">Send Reminder</button></td>
      </tr>`;
      tbody.innerHTML += row;
    });
  }

  function sendReminder(index) {
    alert("Reminder sent to " + overdue[index].member + " for " + overdue[index].book);
  }

  renderOverdue();
</script>

</body>
</html>
