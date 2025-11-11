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
  <style>
    .book-reviews { margin-top: 2rem; }
    .book-reviews h3 { margin-bottom: .75rem; }
    .book-reviews form textarea { width: 100%; max-width: 600px; display: block; margin-bottom: .5rem; }
    .star-rating { text-align: left; margin-bottom: .5rem; }
    .star { font-size: 25px; cursor: pointer; color: lightgray; display: inline-block; }
    .review-box { background: #f9f9f9; padding: 10px; border-radius: 6px; margin-bottom: 10px; border-left: 3px solid #007bff; }
    .book-wrapper { max-width: 900px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
    .book-card { background: #fff; border-radius: 1rem; padding: 1.25rem 1.5rem 1.5rem; box-shadow: 0 4px 20px rgba(15, 23, 42, .05); }
    .book-header { display: flex; justify-content: space-between; gap: 1rem; align-items: center; }
    .book-title-block h2 { margin: 0; }
    .book-title-block p { margin: .25rem 0 0; color: #6b7280; }
    .book-meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: .85rem; margin-top: 1.1rem; }
    .book-meta-item small { display: block; font-size: .7rem; letter-spacing: .04em; color: #9ca3af; text-transform: uppercase; }
    .book-meta-item span { font-weight: 600; }
    .section-title { margin-top: 1.25rem; }
    .book-summary { background: #f9fafb; border-radius: .75rem; padding: .75rem .85rem; line-height: 1.5; }
    .actions-bar { margin-top: 1.25rem; }
    .primary-btn { background: #dc2626; border: none; padding: .45rem .9rem; border-radius: .5rem; color: #fff; font-weight: 600; cursor: pointer; }
    .primary-btn:hover { background: #b91c1c; }
    .badge.available { background: #ecfdf3; color: #166534; padding: .25rem .65rem; border-radius: 9999px; font-size: .7rem; font-weight: 600; }
    .badge.unavailable { background: #fef2f2; color: #b91c1c; padding: .25rem .65rem; border-radius: 9999px; font-size: .7rem; font-weight: 600; }
    .back-link { text-decoration: none; color: #1f2937; font-size: .85rem; }
    .back-link:hover { text-decoration: underline; }
  </style>
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
    <div class="book-reviews">
      <h3>📖 Book Reviews</h3>

      <?php if (isset($_SESSION['user_id'])): ?>
        <form method="POST" action="save_review.php" id="reviewForm">
          <input type="hidden" name="book_id" value="<?= (int)$book['book_id'] ?>">
          <div class="star-rating">
            <span class="star" data-value="1">★</span>
            <span class="star" data-value="2">★</span>
            <span class="star" data-value="3">★</span>
            <span class="star" data-value="4">★</span>
            <span class="star" data-value="5">★</span>
          </div>
          <input type="hidden" id="rating" name="rating" value="0">
          <textarea name="review_text" placeholder="Write your review..." rows="3" required></textarea>
          <button type="submit" class="primary-btn" style="margin-top:.35rem;">Submit Review</button>
        </form>
      <?php else: ?>
        <p class="muted-action">Please <a href="login.php">log in</a> to leave a review.</p>
      <?php endif; ?>

      <h4 style="margin-top:1.25rem;">Previous Reviews</h4>
      <div id="reviews-list">
        <?php
          // make the book id available to the included file
          $_GET['book_id'] = (int)$book['book_id'];
          include 'view_reviews.php';
        ?>
      </div>
    </div>
    <!-- ====== END BOOK REVIEWS ====== -->

    </div>
  </main>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      let currentRating = 0;
      const stars = document.querySelectorAll('.star');
      const ratingInput = document.getElementById('rating');
      const reviewForm = document.getElementById('reviewForm');

      stars.forEach(star => {
        star.addEventListener('click', function() {
          const value = parseInt(this.getAttribute('data-value'), 10);
          currentRating = value;
          ratingInput.value = value;
          stars.forEach((s, idx) => {
            s.style.color = idx < value ? 'gold' : 'lightgray';
          });
        });
      });

      if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
          if (currentRating === 0) {
            alert('Please select a star rating.');
            e.preventDefault();
          }
        });
      }
    });
    </script>
  </div>
</main>
</body>
</html>
