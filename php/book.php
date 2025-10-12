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

<header>
  <h1>Library Management System</h1>
  <nav>
    <ul>
      <li><a href="index.php">Home</a></li>
      <li><a href="catalog.php">Catalog</a></li>
      <li><a href="login.php">Login</a></li>
      <li><a href="signup.php">Sign Up</a></li>
      <li><a href="contact.php">Contact Us</a></li>
    </ul>
  </nav>
</header>

<main class="container">
  <p><a href="catalog.php">← Back to catalog</a></p>

  <?php if ($flash): ?>
    <p style="color: <?= h($flashColor) ?>;"><?= h($flash) ?></p> <!-- shows flash text in the chosen color-->
  <?php endif; ?>

  <h2><?= h($book['title']) ?></h2>
  <p class="muted">by <?= h($book['author'] ?? 'Unknown') ?></p> <!-- show author or unknown if empty-->
  <div class="badge"><?= $book['available'] ? 'Available' : 'Checked Out' ?></div> <!-- Status badge based on boolean available -->

  <div class="details">
    <?php if (!empty($book['genre'])): ?> <!-- only show genre if present-->
      <div><strong>Genre:</strong> <?= h($book['genre']) ?></div>
    <?php endif; ?>
    <?php if (!empty($book['language'])): ?> <!-- only show language if present-->
      <div><strong>Language:</strong> <?= h($book['language']) ?></div>
    <?php endif; ?>
    <?php if (!empty($book['isbn'])): ?> <!-- only show ISBN if present-->
      <div><strong>ISBN:</strong> <?= h($book['isbn']) ?></div>
    <?php endif; ?>
    <?php if (!empty($book['publication_year'])): ?> <!-- only show publication year if present -->
      <div><strong>Publication Year:</strong> <?= h($book['publication_year']) ?></div>
    <?php endif; ?>
  </div>

  <?php if (!empty($book['summary'])): ?>
    <h3 style="margin-top:1rem;">Summary</h3>
    <p><?= nl2br(h($book['summary'])) ?></p> <!-- summary text, escaped and with newlines convert to <br>-->
  <?php endif; ?>

  <!-- Reserve button -->
   <div style="margin-top:1rem;">
    <?php if (isset($_SESSION['user_id'])): ?> <!-- if user is logged in, show reserve form-->
      <form method="post" action="reserve.php" style="display:inline;"> <!-- POST to reserve handler -->
        <input type="hidden" name="book_id" value="<?= (int)$book['book_id'] ?>"> <!-- pass current book id as hidden field-->
        <button type="submit">Reserve this book</button> <!-- submit to create reservation -->
      </form>
    <?php else: ?> <!-- if not logged in, prompt to log in-->
      <p class="muted">Please <a href="login.php">log in</a> to reserve this book.</p>
    <?php endif; ?>
   </div>
</main>
</body>
</html>
