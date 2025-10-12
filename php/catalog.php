<?php
include "connect.php"; // this is where $database comes from

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
  <form class="search-bar" method="get" action="catalog.php">
    <input type="text" name="q" placeholder="Search by title, author, or ISBN" value="<?= h($q) ?>"> <!-- search box; pre-fills with current query -->
    <button type="submit">Search</button>
  </form>

  <section class="book-list">
    <h2>Available Books</h2>
    
    <?php if (count($books) === 0): ?> <!-- If there are no results to show -->
      <p class="muted">No books found<?= $q !== '' ? ' for “'.h($q).'”' : '' ?>.</p> <!-- Message for none found -->
    <?php else: ?> <!-- Otherwise, show a grid of book cards -->
      <div class="book-grid">
        <?php foreach ($books as $b): ?> <!-- this loops over each book row -->
          <article class="book-card">
            <div class="badge"><?= $b['available'] ? 'Available' : 'Checked Out' ?></div> <!-- Status badge based on boolean available -->
            <h3><a href="book.php?id=<?= (int)$b['book_id'] ?>"><?= h($b['title']) ?></a></h3> <!-- title linking to book detail page, id cast to int, -->
            <p class="muted">by <?= h($b['author'] ?? 'Unknown') ?></p> <!-- Author or unknown if its empty -->
            <?php if (!empty($b['isbn'])): ?> <!-- conditionally show ISBN if present -->
              <p class="muted">ISBN: <?= h($b['isbn']) ?></p> <!-- display ISBN safely -->
            <?php endif; ?>
            <?php if (!empty($b['publication_year'])): ?> <!-- conditionally show publication year if present -->
              <p class="muted">Published: <?= h($b['publication_year']) ?></p>
            <?php endif; ?>
            <p><?= h(mb_strimwidth($b['summary'] ?? '', 0, 140, '…')) ?></p> <!-- short summary snippet, it cuts to 140 characters and escape if too long -->
            <div class="book-actions"> <!-- actions for this book -->
              <a href="book.php?id=<?= (int)$b['book_id'] ?>">View details →</a> <!-- links to book detail page -->
            </div>
          </article>
        <?php endforeach; ?> <!-- ends foreach book-->
      </div>
    <?php endif; ?> <!-- ends results conditional -->
  </section>
</main>
</body>
</html>