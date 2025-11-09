<?php
include "connect.php"; // this is where $database comes from
include "maintenance.php";

$q = trim($_GET['q'] ?? ''); // reads the search query from the URL, the default is empty string, it also trims any whitespace
$like = '%' . $q . '%'; // builds a like pattern for SQL partial matching

$sql = "
  SELECT book_id, title, author, genre, language, isbn, publication_year, summary, available
  FROM books
"; // the base SQL statement to fetch book fields from the books table
$params = []; // this will hold bound parameters values
$types  = ''; // this will hold the mysqli bind types string

if ($q !== '') { // if a search term was provided
  $sql .= " WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ? "; // it will add a WHERE filter across title, author or isbn
  $params = [$like, $like, $like]; // values for the three placeholders which is the same like pattern
  $types  = 'sss'; // bind types are 3 strings
}
$sql .= " ORDER BY title ASC LIMIT 100"; // sorts the books from A-Z by title and limits to 100 results for safety and performance

$stmt = mysqli_prepare($database, $sql); // creates a prepared statement from the SQL string
if ($types !== '') { // if we added filters, it have to bind the parameters
  mysqli_stmt_bind_param($stmt, $types, ...$params); // binds all parameters with their types using argument
}
mysqli_stmt_execute($stmt); // executes the prepared statement 
$result = mysqli_stmt_get_result($stmt); // gets a buffered result set we can fetch from

$books = []; // this will collect each row as an associative array
if ($result) { // if the query returned a valid result set
  while ($row = mysqli_fetch_assoc($result)) { // fetchs each row into an associative array
    $books[] = $row; // appends row to the $books list
  }
}
mysqli_stmt_close($stmt); // closes the prepared statement

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES); } // this helps to escape output to prevent XSS

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Library Catalog</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .catalog-shell {
      max-width: 1100px;
      margin: 1.75rem auto 3rem;
      padding: 0 1.25rem;
    }

    .catalog-header {
      text-align: center;
      margin-bottom: 1.5rem;
    }

    .catalog-header h2 {
      margin-bottom: .25rem;
      font-size: 2rem;
      color: #1f2937;
    }

    .catalog-header p {
      color: #6b7280;
      font-size: .95rem;
    }

    .catalog-search {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: .75rem;
      padding: .6rem .6rem .6rem .8rem;
      display: flex;
      gap: .5rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 12px 35px rgba(15,23,42,0.03);
    }

    .catalog-search input {
      flex: 1;
      border: none;
      font-size: .95rem;
      outline: none;
      background: transparent;
    }

    .catalog-search button {
      background: #1d4ed8;
      color: #fff;
      border: none;
      border-radius: .55rem;
      padding: .5rem 1.15rem;
      font-weight: 600;
      cursor: pointer;
    }

    .catalog-search button:hover {
      background: #153d9b;
    }
  </style>
</head>
<body>

<?php include 'nav.php'; ?>

<main class="catalog-shell">
  <section class="catalog-header">
    <h2>Browser the Catalog</h2>
    <p>Search by title, author, or ISBN and open a book to reserve it.</p>
  </section>

  <form class="catalog-search" method="get" action="catalog.php">
    <input type="text" name="q" placeholder="Search by title, author, or ISBN" value="<?= h($q) ?>" aria-label="Search catalog"> <!-- search box, pre-fills with current query -->
    <button type="submit">Search</button>
  </form>

  <section class="book-list">
    <div class="list-header">
      <h3>
        <?= $q === '' ? 'All Books' : 'Results for “' . h($q) . '”' ?>
      </h3>
      <p class="muted">
        <?= count($books) ?> item<?= count($books) === 1 ? '' : 's' ?>
      </p>
    </div>
    
    <?php if (count($books) === 0): ?> <!-- If there are no results to show -->
      <p class="muted">No books found<?= $q !== '' ? ' for “'.h($q).'”' : '' ?>.</p> <!-- Message for none found -->
    <?php else: ?> <!-- Otherwise, show a grid of book cards -->
      <div class="book-grid">
        <?php foreach ($books as $b): ?> <!-- this loops over each book row -->
          <article class="book-card">
            <div class="book-card-top">
              <span class="badge <?= $b['available'] ? 'badge-available' : 'badge-out' ?>">
                <?= $b['available'] ? 'Available' : 'Checked Out' ?> <!-- Status badge based on boolean available -->
              </span>
            <?php if (!empty($b['genre'])): ?>
                <span class="chip"><?= h($b['genre']) ?></span>
              <?php endif; ?>
            </div>

            <h3 class="book-title">
              <a href="book.php?id=<?= (int)$b['book_id'] ?>">
                <?= h($b['title']) ?>
              </a>
            </h3>
            <p class="book-author">by <?= h($b['author'] ?? 'Unknown') ?></p>

            <div class="book-meta">
              <?php if (!empty($b['isbn'])): ?>
                <span>ISBN: <?= h($b['isbn']) ?></span>
              <?php endif; ?>
              <?php if (!empty($b['publication_year'])): ?>
                <span>• <?= h($b['publication_year']) ?></span>
              <?php endif; ?>
            </div>

            <p class="book-summary">
              <?= h(mb_strimwidth($b['summary'] ?? '', 0, 150, '…')) ?>
            </p>

            <div class="book-actions">
              <a class="btn-inline" href="book.php?id=<?= (int)$b['book_id'] ?>">View details -></a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
</body>
</html>