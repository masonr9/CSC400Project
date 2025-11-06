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

<?php
include "nav.php"
?>

<main class="announce-shell">
  <header class="annouce-header">
    <p class="annouce-pill">📢 Updates</p>
    <h2>Library Announcements</h2>
    <p class="announce-sub">
      Stay up to date on library hours, events, and important notices.
    </p>
  </header>

  <?php if ($loadError): ?>
    <p style="color:red;"><?= h($loadError) ?></p>
  <?php elseif (empty($announcements)): ?>
    <div class="empty-card">
      <p class="muted">No announcements at the moment.</p>
    </div>
  <?php else: ?>
    <section class="annouce-list">
      <?php foreach ($announcements as $row): ?>
        <article class="annouce-card">
          <div class="announcement-box">
            <h3><?= h($row['title']) ?></h3>
            <span class="announce-date">
              <?= h(date('M j, Y g:i a', strtotime($row['created_at']))) ?>
            </span>
          </div>
          <p class="announce-body"><?= nl2br(h($row['message'])) ?></p>
        </article>
     <?php endforeach; ?>
    </section>
  <?php endif; ?>
</main>

</body>
</html>

