<?php
session_start();
require_once "connect.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
  header("Location: login.php");
  exit();
}

// --- Add Announcement ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add'])) {
  $title = $_POST['title'];
  $message = $_POST['message'];
  $stmt = $conn->prepare("INSERT INTO announcements (title, message) VALUES (?, ?)");
  $stmt->bind_param("ss", $title, $message);
  $stmt->execute();
}

// --- Edit Announcement ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit'])) {
  $id = $_POST['id'];
  $title = $_POST['title'];
  $message = $_POST['message'];
  $stmt = $conn->prepare("UPDATE announcements SET title=?, message=? WHERE id=?");
  $stmt->bind_param("ssi", $title, $message, $id);
  $stmt->execute();
}

// --- Delete Announcement ---
if (isset($_GET['delete'])) {
  $id = $_GET['delete'];
  $stmt = $conn->prepare("DELETE FROM announcements WHERE id=?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
}

// --- Fetch Announcements ---
$result = $conn->query("SELECT * FROM announcements ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Announcements</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<header>
  <h1>Library Management System</h1>
  <nav>
    <ul>
      <li><a href="admin_dashboard.php">Dashboard</a></li>
      <li><a href="system_config.php">System Config</a></li>
      <li><a href="logs.php">Logs</a></li>
      <li><a href="announcements.php">Announcements</a></li>
      <li><a href="logout.php" class="logout-btn">Logout</a></li>
    </ul>
  </nav>
</header>

<main>
  <h2>📢 Manage Announcements</h2>

  <form method="POST" class="form-box">
    <label>Title:</label>
    <input type="text" name="title" required>
    <label>Message:</label>
    <textarea name="message" required></textarea>
    <button type="submit" name="add">Add Announcement</button>
  </form>

  <h3>Existing Announcements</h3>
  <table border="1" width="100%">
    <tr><th>Title</th><th>Message</th><th>Date</th><th>Actions</th></tr>
    <?php while($row = $result->fetch_assoc()) { ?>
    <tr>
      <td><?php echo htmlspecialchars($row['title']); ?></td>
      <td><?php echo htmlspecialchars($row['message']); ?></td>
      <td><?php echo $row['created_at']; ?></td>
      <td>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
          <input type="text" name="title" value="<?php echo htmlspecialchars($row['title']); ?>" required>
          <input type="text" name="message" value="<?php echo htmlspecialchars($row['message']); ?>" required>
          <button type="submit" name="edit">Save</button>
        </form>
        <a href="?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to remove this announcement?')">Delete</a>
      </td>
    </tr>
    <?php } ?>
  </table>
</main>

</body>
</html>
