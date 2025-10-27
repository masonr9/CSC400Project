<?php
session_start();
require_once "connect.php";

// Fetch announcements from database
$result = $conn->query("SELECT * FROM announcements ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Library Announcements</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<header>
  <h1>Library Management System</h1>
  <nav>
    <ul>
      <li><a href="index.php">Home</a></li>
      <li><a href="catalog.php">Catalog</a></li>
      <li><a href="announce.php">Announcements</a></li>
      <?php if (isset($_SESSION['role'])) { ?>
        <li><a href="logout.php" class="logout-btn">Logout</a></li>
      <?php } else { ?>
        <li><a href="login.php">Login</a></li>
        <li><a href="signup.php">Sign Up</a></li>
      <?php } ?>
    </ul>
  </nav>
</header>

<main>
  <h2>📢 Library Announcements</h2>

  <?php if ($result && $result->num_rows > 0) { ?>
    <?php while ($row = $result->fetch_assoc()) { ?>
      <div class="announcement-box">
        <h3><?php echo htmlspecialchars($row['title']); ?></h3>
        <p><?php echo htmlspecialchars($row['message']); ?></p>
        <small>Posted on: <?php echo htmlspecialchars($row['created_at']); ?></small>
      </div>
    <?php } ?>
  <?php } else { ?>
    <p>No announcements at the moment.</p>
  <?php } ?>
</main>

</body>
</html>


