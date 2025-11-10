</div> <!-- end of actions-bar -->

<!-- ====== START BOOK REVIEWS ====== -->

<div class="book-reviews">
  <h3>📖 Book Reviews</h3>

  <?php if (isset($_SESSION['user_id'])): ?>
  <form method="POST" action="save_reviews.php" onsubmit="return checkRating()">
    <input type="hidden" name="book_id" value="<?= (int)$book['book_id'] ?>">
    <div class="star-rating">
      <span class="star" onclick="setRating(1)">★</span>
      <span class="star" onclick="setRating(2)">★</span>
      <span class="star" onclick="setRating(3)">★</span>
      <span class="star" onclick="setRating(4)">★</span>
      <span class="star" onclick="setRating(5)">★</span>
    </div>
    <input type="hidden" id="rating" name="rating" value="0">
    <textarea name="review_text" placeholder="Write your review..." rows="3" required></textarea>
    <button type="submit">Submit Review</button>
  </form>
  <?php else: ?>
    <p>Please <a href="login.php">log in</a> to leave a review.</p>
  <?php endif; ?>

  <h4>Previous Reviews</h4>
  <div id="reviews-list">
    <?php include 'view_reviews.php'; ?>
  </div>
</div>

<script>
let currentRating = 0;
const stars = document.querySelectorAll('.star');

function setRating(value) {
  currentRating = value;
  document.getElementById('rating').value = value;
  stars.forEach((star, index) => {
    star.style.color = index < value ? 'gold' : 'lightgray';
  });
}

function checkRating() {
  if (currentRating === 0) {
    alert('Please select a star rating.');
    return false;
  }
  return true;
}
</script>

<style>
.book-reviews { margin-top: 2rem; }
.star-rating { text-align: left; margin-bottom: .5rem; }
.star { font-size: 25px; cursor: pointer; color: lightgray; }
.review-box { background: #f9f9f9; padding: 10px; border-radius: 6px; margin-bottom: 10px; border-left: 3px solid #007bff; }
</style>
