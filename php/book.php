<?php
session_start(); // starts or resumes the session so we can access $_SESSION
include "connect.php"; // this is where $database comes from

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0; // reads book id from query string and cast to int, sets default to 0 if its missing
if ($id <= 0) { // if the id is invalid, it rejects the request
  http_response_code(400); // sends HTTP 400 bad request
  echo "Invalid book id."; // sends error message to the browser
  exit(); // stops executing
}

$stmt = mysqli_prepare( // prepares a query with parameters to safely fetch a single book
  $database,
  "SELECT book_id, title, author, genre, language, isbn, publication_year, summary, available
   FROM books
   WHERE book_id = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "i", $id); // binds the book id as an integer to the placeholder
mysqli_stmt_execute($stmt); // executes the prepared statement
$result = mysqli_stmt_get_result($stmt); // gets the result set from the executed statement
$book = mysqli_fetch_assoc($result); // fetchs one row as a n associative array
mysqli_stmt_close($stmt); // closes the statement

if (!$book) { // if no row was found for this id
  http_response_code(404); // sends HTTP 404 not found
  echo "Book not found."; // this message will be displayed
  exit(); // stop executing
}

function h($s) { // this is a helper function to safely escape output for HTML contexts
  return htmlspecialchars((string)$s, ENT_QUOTES); // converts special chars including quotes to HTML entities
}

$flash = $_SESSION['flash_msg'] ?? ''; // retrieves a flash message from the session if there is any
$flashColor = $_SESSION['flash_color'] ?? 'green'; // retrieve flash color, the default is green
unset($_SESSION['flash_msg'], $_SESSION['flash_color']); // clear flash values so they don't persist to the next request
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= h($book['title']) ?> - Details</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php include 'nav.php'; ?>

<main class="book-wrapper">
  <p><a href="catalog.php" class="back-link"><- Back to catalog</a></p>

  <?php if ($flash): ?>
    <p style="color: <?= h($flashColor) ?>; margin-bottom:.75rem;"><?= h($flash) ?></p> <!-- shows flash text in the chosen color-->
  <?php endif; ?>

  <div class="book-card">
    <div class="book-header">
      <div class="book-title-block">
        <h2><?= h($book['title']) ?></h2>
        <p>by <?= h($book['author'] ?? 'Unknown') ?></p>
      </div>
      <div>
        <?php if ($book['available']): ?>
          <span class="badge available">Available</span>
        <?php else: ?>
          <span class="badge unavailable">Checked Out</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="book-meta-grid">
      <?php if (!empty($book['genre'])): ?>
        <div class="book-meta-item">
          <small>Genre</small>
          <span><?= h($book['genre']) ?></span>
        </div>
      <?php endif; ?>
      <?php if (!empty($book['language'])): ?>
        <div class="book-meta-item">
          <small>Language</small>
          <span><?= h($book['language']) ?></span>
        </div>
      <?php endif; ?>
      <?php if (!empty($book['isbn'])): ?>
        <div class="book-meta-item">
          <small>ISBN</small>
          <span><?= h($book['isbn']) ?></span>
        </div>
      <?php endif; ?>
      <?php if (!empty($book['publication_year'])): ?>
        <div class="book-meta-item">
          <small>Publication Year</small>
          <span><?= h($book['publication_year']) ?></span>
        </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($book['summary'])): ?>
      <h3 class="section-title">Summary</h3>
      <div class="book-summary">
        <?= nl2br(h($book['summary'])) ?>
      </div>
    <?php endif; ?>

    <div class="actions-bar">
      <?php if (isset($_SESSION['user_id'])): ?>
        <form method="post" action="reserve.php" style="display:inline;">
          <input type="hidden" name="book_id" value="<?= (int)$book['book_id'] ?>">
          <button type="submit" class="primary-btn">Reserve this book</button>
        </form>
      <?php else: ?>
        <p class="muted-action">Please <a href="login.php">log in</a> to reserve this book.</p>
      <?php endif; ?>
    </div>
  </div>
</main>
</body>
</html>
