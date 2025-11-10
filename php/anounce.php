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
  <style>
.announce-shell {
  max-width: 900px;
  margin: 1.75rem auto 3rem;
  padding: 0 1rem;
}

.announce-header {
  text-align: center;
  margin-bottom: 1.8rem;
}

.announce-pill {
  display: inline-block;
  background: #e0ecff;
  color: #1d4ed8;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: .01em;
}

.announce-header h2 {
  margin-top: .6rem;
  margin-bottom: .35rem;
  font-size: 2rem;
  color: #111827;
}

.announce-sub {
  color: #6b7280;
  max-width: 520px;
  margin: 0 auto;
  font-size: .95rem;
}

.announce-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.announce-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: .75rem;
  padding: 1rem 1.15rem 1.05rem;
  box-shadow: 0 4px 14px rgba(15, 23, 42, 0.02);
}

.announce-card-header {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: flex-start;
}

.announce-card h3 {
  margin: 0;
  font-size: 1.05rem;
  color: #111827;
}

.announce-date {
  font-size: .7rem;
  color: #9ca3af;
  white-space: nowrap;
}

.announce-body {
  margin-top: .6rem;
  color: #374151;
  line-height: 1.5;
  white-space: pre-wrap;
}

.empty-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: .75rem;
  padding: 1.5rem;
  text-align: center;
}

@media (max-width: 640px) {
  .announce-card-header {
    flex-direction: column;
    align-items: flex-start;
  }
  .announce-date {
    margin-top: .25rem;
  }
}
  </style>
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

