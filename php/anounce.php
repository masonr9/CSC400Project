<?php
session_start();
require_once "connect.php"; // provides $database (mysqli link)

// small function helper to safely escape output
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

// fetch announcements (most recent first)
$sql = "SELECT id, title, message, created_at FROM announcements ORDER BY id DESC";
$result = mysqli_query($database, $sql);

// if the query failed, capture an error for display
$loadError = null;
if ($result === false) {
  $loadError = "Could not load announcements.";
}

// collect rows so we can reuse the result safely
$announcements = [];
if ($result) {
  while ($row = mysqli_fetch_assoc($result)) {
    $announcements[] = $row;
  }
  mysqli_free_result($result);
}
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
      <li><a href="anounce.php">Announcements</a></li>
      <?php if (isset($_SESSION['role'])): ?>
        <?php if ($_SESSION['role'] === 'Admin'): ?>
          <li><a href="admin.php">Admin</a></li>
        <?php elseif ($_SESSION['role'] === 'Librarian'): ?>
          <li><a href="librarian.php">Librarian</a></li>
        <?php else: ?>
          <li><a href="member.php">Member</a></li>
        <?php endif; ?>
        <li><a href="logout.php" class="logout-btn">Logout</a></li>
      <?php else: ?>
        <li><a href="login.php">Login</a></li>
        <li><a href="signup.php">Sign Up</a></li>
        <li><a href="contact.php">Contact Us</a></li>
      <?php endif; ?>
    </ul>
  </nav>
</header>

<main class="container">
  <h2>📢 Library Announcements</h2>

  <?php if ($loadError): ?>
    <p style="color:red;"><?= h($loadError) ?></p>
  <?php elseif (empty($announcements)): ?>
    <p class="muted">No announcements at the moment.</p>
  <?php else: ?>
    <?php foreach ($announcements as $row): ?>
      <div class="announcement-box">
        <h3><?= h($row['title']) ?></h3>
        <p><?= nl2br(h($row['message'])) ?></p>
        <small>Posted on: <?= h($row['created_at']) ?></small>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</main>

</body>
</html>

