<?php
session_start(); // start or resume the session so we can read and write to $_SESSION
include "connect.php"; // this is where $database comes from

// access control (Librarian/Admin only)
if (!isset($_SESSION['user_id'])) { // if user is not logged in
  header("Location: login.php"); // send them to the login page
  exit(); // stop executing this script
}
$role = $_SESSION['role'] ?? 'Member'; // read role from session, default to Member if missing
if (!in_array($role, ['Librarian','Admin'], true)) { // only Librarian/Admin may access this page
  http_response_code(403); // set HTTP status to 403 Forbidden
  echo "Forbidden: Librarian/Admin access only."; // show error message
  exit(); // stop executing
}

// helper function to escape output safely for HTML
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

// Small helper function to write to logs
function log_action(mysqli $db, string $what): void {
  $who = $_SESSION['name'] ?? ('User#' . (int)($_SESSION['user_id'] ?? 0));
  $stmt = mysqli_prepare($db, "INSERT INTO logs (`user`, `action`) VALUES (?, ?)");
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $who, $what);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  }
}


$flash = $_SESSION['flash_msg'] ?? ''; // flash message from session
$flashColor = $_SESSION['flash_color'] ?? 'green'; // flash color, default is green
unset($_SESSION['flash_msg'], $_SESSION['flash_color']); // clear flash values so they don't persist

// Handle Add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_book'])) { // if the add book form was submitted
  $title    = trim($_POST['title'] ?? ''); // title input, trims any whitespace
  $author   = trim($_POST['author'] ?? ''); // author input
  $genre    = trim($_POST['genre'] ?? ''); // genre input
  $language = trim($_POST['language'] ?? ''); // language input
  $isbn     = trim($_POST['isbn'] ?? ''); // ISBN input
  $pubyear  = trim($_POST['publication_year'] ?? ''); // publication year
  $summary  = trim($_POST['summary'] ?? ''); // summary input
  $qty      = (int)($_POST['available_qty'] ?? 0); // helper quantity used to compute boolean available
  $available = $qty > 0 ? 1 : 0; // boolean available flag derived from qty

  if ($title === '' || $author === '') { // validate required fields
    $_SESSION['flash_msg'] = "Title and Author are required."; // prepare error flash
    $_SESSION['flash_color'] = "red"; // mark as error
    header("Location: manage_books.php"); // redirect back
    exit(); // stop executing
  }

  // Validate publication year
  if ($pubyear !== '' && !ctype_digit($pubyear)) { // if provided, it must be all digits
    $_SESSION['flash_msg'] = "Publication year must be a number (or leave blank)."; // error message
    $_SESSION['flash_color'] = "red"; // error color
    header("Location: manage_books.php"); // redirect back
    exit(); // stop executing
  }
  $pubyearInt = ($pubyear === '') ? null : (int)$pubyear; // convert to int or NULL for database

  // Ensure unique ISBN if provided
  if ($isbn !== '') { // if ISBN was entered
    $chk = mysqli_prepare($database, "SELECT book_id FROM books WHERE isbn = ? LIMIT 1"); // prepare a SELECT to check duplicates
    mysqli_stmt_bind_param($chk, "s", $isbn); // bind ISBN as string
    mysqli_stmt_execute($chk); // execute the query
    mysqli_stmt_store_result($chk); // buffer results for num_rows()
    if (mysqli_stmt_num_rows($chk) > 0) { // if a row exists, the ISBN is taken
      mysqli_stmt_close($chk); // close checker statement
      $_SESSION['flash_msg'] = "ISBN already exists."; // set error flash message
      $_SESSION['flash_color'] = "red"; // set error color
      header("Location: manage_books.php"); // redirect back
      exit(); // stop executing
    }
    mysqli_stmt_close($chk); // close checker if no duplicate
  }

  // One prepare + one bind with the correct types string (8 placeholders "sssssisi")
  $stmt = mysqli_prepare( // Prepare the INSERT for new book
    $database,
    "INSERT INTO books (title, author, genre, language, isbn, publication_year, summary, available)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
  );
  $types = "sssssisi"; // title(s), author(s), genre(s), language(s), isbn(s), pubyear(i), summary(s), available(i)
  mysqli_stmt_bind_param($stmt, $types, // bind all values to the statement
    $title, $author, $genre, $language, $isbn, $pubyearInt, $summary, $available
  );

  if (mysqli_stmt_execute($stmt)) { // attempt to insert the row
    $newId = (int)mysqli_insert_id($database);
    log_action($database, "Added book #{$newId} \"{$title}\" by {$author}");
    $_SESSION['flash_msg'] = "Book added successfully."; // success flash message
    $_SESSION['flash_color'] = "green"; // success color
  } else {
    $_SESSION['flash_msg'] = "Server error adding book."; // failure flash message
    $_SESSION['flash_color'] = "red"; // error color
  }
  mysqli_stmt_close($stmt); // close the insert statement
  header("Location: manage_books.php"); // redirect to avoid form resubmission
  exit(); // stop execution
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_book'])) { // if a delete action was posted
  $id = (int)($_POST['delete_book'] ?? 0); // get book id to delete
  if ($id > 0) { // only proceed for a positive id
    // Grab details for logging first
    $titleForLog = '';
    $authorForLog = '';
    if ($stmtInfo = mysqli_prepare($database, "SELECT title, author FROM books WHERE book_id = ? LIMIT 1")) {
      mysqli_stmt_bind_param($stmtInfo, "i", $id);
      mysqli_stmt_execute($stmtInfo);
      $resInfo = mysqli_stmt_get_result($stmtInfo);
      if ($rowInfo = mysqli_fetch_assoc($resInfo)) {
        $titleForLog  = (string)($rowInfo['title'] ?? '');
        $authorForLog = (string)($rowInfo['author'] ?? '');
      }
      mysqli_stmt_close($stmtInfo);
    }

    // Attempt delete (may fail if FK constraints)
    $stmt = mysqli_prepare($database, "DELETE FROM books WHERE book_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $aff = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($aff > 0) {
      // Log after successful removal
      $desc = $titleForLog !== '' ? "Removed book #{$id} \"{$titleForLog}\" by {$authorForLog}"
                                  : "Removed book #{$id}";
      log_action($database, $desc);

      $_SESSION['flash_msg'] = "Book removed.";
      $_SESSION['flash_color'] = "green";
    } else {
      $_SESSION['flash_msg'] = "Unable to remove book (in use or not found)."; // failure flash message
      $_SESSION['flash_color'] = "red"; // error color
    }
  }
  header("Location: manage_books.php"); // redirect back to list
  exit(); // stop script
}

// Handle Load for Edit
$editId = null; // default is no edit in progress
$editRow = null; // default is no row loaded
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) { // if ?edit= is set to a numeric value
  $editId = (int)$_GET['edit']; // cast to int
  if ($editId > 0) { // only load if valid id
    $stmt = mysqli_prepare( // prepare a SELECT to fetch the book row
      $database,
      "SELECT book_id, title, author, genre, language, isbn, publication_year, summary, available
       FROM books WHERE book_id = ? LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "i", $editId); // bind id
    mysqli_stmt_execute($stmt); // execute the statement
    $res = mysqli_stmt_get_result($stmt); // get result cursor
    $editRow = mysqli_fetch_assoc($res) ?: null; // fetch row as associative array or null
    mysqli_stmt_close($stmt); // close the statement
  }
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_book'])) { // if the update form was submitted
  $id      = (int)($_POST['book_id'] ?? 0); // target book id
  $title   = trim($_POST['title'] ?? '');
  $author  = trim($_POST['author'] ?? '');
  $genre   = trim($_POST['genre'] ?? '');
  $language= trim($_POST['language'] ?? '');
  $isbn    = trim($_POST['isbn'] ?? '');
  $pubyear = trim($_POST['publication_year'] ?? '');
  $summary = trim($_POST['summary'] ?? '');
  $qty     = (int)($_POST['available_qty'] ?? 0); // quantity helper for boolean available
  $available = $qty > 0 ? 1 : 0; // compute available flag

  if ($id <= 0) { // validate id
    $_SESSION['flash_msg'] = "Invalid book id."; // error flash message
    $_SESSION['flash_color'] = "red"; // error color
    header("Location: manage_books.php"); // redirect back
    exit(); // stop
  }
  if ($title === '' || $author === '') { // validate required fields
    $_SESSION['flash_msg'] = "Title and Author are required."; // error message
    $_SESSION['flash_color'] = "red"; // error color
    header("Location: manage_books.php?edit=".$id); // return to edit view
    exit(); // stop
  }
  if ($pubyear !== '' && !ctype_digit($pubyear)) { // validate publication year digits
    $_SESSION['flash_msg'] = "Publication year must be a number (or leave blank)."; // error message
    $_SESSION['flash_color'] = "red"; // error color
    header("Location: manage_books.php?edit=".$id); // back to edit
    exit(); // stop
  }
  $pubyearInt = ($pubyear === '') ? null : (int)$pubyear; // convert to integer or NULL

  // Unique ISBN check excluding current row
  if ($isbn !== '') { // if ISBN was provided
    $chk = mysqli_prepare($database, "SELECT book_id FROM books WHERE isbn = ? AND book_id <> ? LIMIT 1"); // prepare duplicate check excluding this book_id
    mysqli_stmt_bind_param($chk, "si", $isbn, $id); // bind isbn and id
    mysqli_stmt_execute($chk); // execute
    mysqli_stmt_store_result($chk); // buffer for num_rows()
    if (mysqli_stmt_num_rows($chk) > 0) { // if another row uses this ISBN
      mysqli_stmt_close($chk); // close checker
      $_SESSION['flash_msg'] = "ISBN already exists for another book."; // error flash message
      $_SESSION['flash_color'] = "red"; // error color
      header("Location: manage_books.php?edit=".$id); // back to edit
      exit(); // stop
    }
    mysqli_stmt_close($chk); // close if unique OK
  }

  $stmt = mysqli_prepare( // prepare update statement for this book
    $database,
    "UPDATE books
        SET title = ?, author = ?, genre = ?, language = ?, isbn = ?, publication_year = ?, summary = ?, available = ?
      WHERE book_id = ?"
  );
  $types = "sssssisii"; // bind types
  mysqli_stmt_bind_param($stmt, $types, // bind values in order
    $title, $author, $genre, $language, $isbn, $pubyearInt, $summary, $available, $id
  );
  if (mysqli_stmt_execute($stmt)) { // execute update
    log_action($database, "Edited book #{$id} -> \"{$title}\" by {$author}");
    $_SESSION['flash_msg'] = "Book updated successfully."; // success flash message
    $_SESSION['flash_color'] = "green"; // success color
  } else {
    $_SESSION['flash_msg'] = "Server error updating book."; // failure flash message
    $_SESSION['flash_color'] = "red"; // error color
  }
  mysqli_stmt_close($stmt); // close update statement
  header("Location: manage_books.php"); // redirect back to list
  exit(); // stop executing
}

// Search / List
$q = trim($_GET['q'] ?? ''); // search term from query string
$params = []; // init param array for prepared statement
$types = ''; // init types string
$sql = // Base query
  "SELECT book_id, title, author, genre, language, isbn, publication_year, summary, available
     FROM books ";

if ($q !== '') { // if a search term is provided
  $sql .= " WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ? "; // add WHERE filters
  $like = '%'.$q.'%'; // random search pattern
  $params = [$like, $like, $like]; // bind three LIKE parameters
  $types  = 'sss'; // all three are strings
}
$sql .= " ORDER BY title ASC, book_id ASC LIMIT 500"; // sort and limit results

$stmt = mysqli_prepare($database, $sql); // prepare final SELECT
if ($types !== '') { // if we have search params
  mysqli_stmt_bind_param($stmt, $types, ...$params); // bind them using spread operator
}
mysqli_stmt_execute($stmt); // execute query
$res = mysqli_stmt_get_result($stmt); // get result set cursor

$rows = []; // will collect rows to display
if ($res) { // if query ran successfully
  while ($r = mysqli_fetch_assoc($res)) { // fetch each row as associative array
    $rows[] = $r; // add to list
  }
}
mysqli_stmt_close($stmt); // close list statement
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Books</title>
  <link rel="stylesheet" href="styles.css">
  <style>
  /* main shell */
    .home-shell {
      max-width: 1100px;
      margin: 1.75rem auto 3rem;
      padding: 0 1rem;
    }

    /* hero */
    .hero {
      background: radial-gradient(circle at top, #dbeafe 0%, #ffffff 40%, #ffffff 100%);
      border: 1px solid #e5e7eb;
      border-radius: 1rem;
      padding: 2.5rem 2rem;
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      gap: 1.5rem;
      align-items: center;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    }

    .hero-body h2 {
      font-size: 2.1rem;
      margin: 0.4rem 0 0.6rem;
      color: #111827;
    }

    .hero-text {
      margin: 0;
      color: #4b5563;
      max-width: 480px;
    }

    .hero-pill {
      display: inline-block;
      background: #e0ecff;
      color: #1d4ed8;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: .01em;
    }

    .hero-actions {
      margin-top: 1.2rem;
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .btn {
      border: none;
      border-radius: .6rem;
      padding: .55rem 1rem;
      cursor: pointer;
      font-weight: 600;
      font-size: .9rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: .35rem;
    }

    .btn.primary {
      background: #2563eb;
      color: #fff;
      box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
    }

    .btn.primary:hover {
      background: #1d4ed8;
    }

    .btn.ghost {
      background: #fff;
      color: #1f2937;
      border: 1px solid #e5e7eb;
    }

    .hero-illustration {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .hero-card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: .75rem;
      padding: 1rem 1.1rem;
      box-shadow: 0 6px 16px rgba(15, 23, 42, 0.03);
    }

    .hero-card-title {
      font-size: .75rem;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #6b7280;
      margin-bottom: .25rem;
    }

    .hero-card-value {
      font-weight: 600;
      color: #111827;
    }

    .hero-card-value.small {
      font-size: .8rem;
    }

    /* quick grid */
    .quick-grid {
      margin-top: 2.2rem;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
      gap: 1rem;
    }

    .quick-card {
      background: #fff;
      border: 1px solid #edf2f7;
      border-radius: .75rem;
      padding: 1.15rem 1.1rem 1.05rem;
      box-shadow: 0 4px 14px rgba(15,23,42,.02);
    }

    .quick-card h3 {
      margin-top: 0;
      margin-bottom: .35rem;
      font-size: 1rem;
      color: #111827;
    }

    .quick-card p {
      margin-top: 0;
      margin-bottom: .5rem;
      color: #6b7280;
      font-size: .9rem;
    }

    .quick-card a {
      font-weight: 600;
      color: #2563eb;
      text-decoration: none;
      font-size: .85rem;
    }

    .quick-card a:hover {
      text-decoration: underline;
    }

    /* small screens */
    @media (max-width: 768px) {
      .hero {
        grid-template-columns: 1fr;
      }
      .topbar-inner {
        flex-direction: column;
        align-items: flex-start;
      }
      .nav-links {
        flex-wrap: wrap;
      }
    }
  </style>
</head>
<body>

<header>
  <h1>Library Management System</h1>
  <nav>
    <ul>
      <li><a href="librarian.php">Librarian Dashboard</a></li>
      <li><a class="logout-btn" href="logout.php">Logout</a></li>
    </ul>
  </nav>
</header>

<main class="container">
  <h2>Manage Books</h2>

  <?php if ($flash): ?> <!-- If a flash message exists -->
    <p style="color: <?= h($flashColor) ?>;"><?= h($flash) ?></p> <!-- show the flash message in chosen color -->
  <?php endif; ?> <!-- End flash block -->

  <!-- Add or Edit Form -->
  <section class="form-box" style="margin-bottom:1rem;">
    <?php if ($editRow): ?> <!-- if editing an existing book -->
      <h3>Edit Book</h3>
      <form method="post" action="manage_books.php">
        <input type="hidden" name="book_id" value="<?= (int)$editRow['book_id'] ?>"> <!-- Pass the book id-->
        <div class="grid-2"> <!-- Grid layout for inputs -->
          <div>
            <label>Book Title:</label>
            <input type="text" name="title" required value="<?= h($editRow['title']) ?>"> <!-- title input -->
          </div>
          <div>
            <label>Author:</label>
            <input type="text" name="author" required value="<?= h($editRow['author']) ?>">
          </div>
          <div>
            <label>Genre:</label>
            <input type="text" name="genre" value="<?= h($editRow['genre']) ?>">
          </div>
          <div>
            <label>Language:</label>
            <input type="text" name="language" value="<?= h($editRow['language']) ?>">
          </div>
          <div>
            <label>ISBN:</label>
            <input type="text" name="isbn" value="<?= h($editRow['isbn']) ?>">
          </div>
          <div>
            <label>Publication Year:</label>
            <input type="number" name="publication_year" min="0" value="<?= h($editRow['publication_year']) ?>">
          </div>
          <div>
            <label>Available Quantity:</label>
            <input type="number" name="available_qty" min="0" value="<?= ((int)$editRow['available'] === 1 ? 1 : 0) ?>">
            <small class="muted">If &gt;0, sets <code>available = TRUE</code>.</small> <!-- Hint about available flag -->
          </div>
          <div style="grid-column:1/-1;">
            <label>Summary:</label>
            <textarea name="summary"><?= h($editRow['summary']) ?></textarea>
          </div>
        </div>
        <div style="margin-top:.75rem;">
          <button type="submit" name="update_book">Save Changes</button>
          <a href="manage_books.php" class="btn-link">Cancel</a>
        </div>
      </form>
    <?php else: ?> <!-- else, show Add New form -->
      <h3>Add New Book</h3>
      <form method="post" action="manage_books.php">
        <div class="grid-2">
          <div>
            <label>Book Title:</label>
            <input type="text" name="title" required>
          </div>
          <div>
            <label>Author:</label>
            <input type="text" name="author" required>
          </div>
          <div>
            <label>Genre:</label>
            <input type="text" name="genre">
          </div>
          <div>
            <label>Language:</label>
            <input type="text" name="language">
          </div>
          <div>
            <label>ISBN:</label>
            <input type="text" name="isbn">
          </div>
          <div>
            <label>Publication Year:</label>
            <input type="number" name="publication_year" min="0">
          </div>
          <div>
            <label>Available Quantity:</label>
            <input type="number" name="available_qty" min="0" value="1">
            <small class="muted">If &gt;0, sets <code>available = TRUE</code>.</small> <!-- hint about available flag -->
          </div>
          <div style="grid-column:1/-1;">
            <label>Summary:</label>
            <textarea name="summary" placeholder="Short synopsis..."></textarea>
          </div>
        </div>
        <div style="margin-top:.75rem;">
          <button type="submit" name="add_book">Add Book</button>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <!-- Search -->
  <section class="form-box" style="margin-bottom: .5rem;">
    <form method="get" action="manage_books.php">
      <label for="q"><strong>Search Books:</strong></label>
      <input type="text" id="q" name="q" placeholder="Type to search by title, author, or ISBN..." value="<?= h($q) ?>">
      <button type="submit">Search</button>
      <?php if ($q !== ''): ?> <!-- if a query is active -->
        <a href="manage_books.php" class="btn-link">Clear</a> <!-- clear search link -->
      <?php endif; ?> <!-- End clear toggle -->
    </form>
  </section>

  <!-- Catalog Table -->
  <h3>Book Catalog</h3>
  <?php if (empty($rows)): ?> <!-- if no results -->
    <p class="muted">No books found.</p> <!-- show empty state -->
  <?php else: ?> <!-- else render table -->
    <table class="list" id="bookTable">
      <thead>
        <tr>
          <th style="width:18%;">Title</th>
          <th style="width:14%;">Author</th>
          <th style="width:10%;">Genre</th>
          <th style="width:10%;">Language</th>
          <th style="width:12%;">ISBN</th>
          <th style="width:8%;">Year</th>
          <th>Summary</th>
          <th style="width:8%;">Available</th>
          <th style="width:12%;">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $b): ?> <!-- loop each book row -->
        <tr>
          <td><?= h($b['title']) ?></td>
          <td><?= h($b['author']) ?></td>
          <td><?= h($b['genre']) ?></td>
          <td><?= h($b['language']) ?></td>
          <td><?= h($b['isbn']) ?></td>
          <td><?= h($b['publication_year']) ?></td>
          <td><?= h(mb_strimwidth($b['summary'] ?? '', 0, 160, '…')) ?></td> <!-- truncated summary -->
          <td><?= ((int)$b['available'] === 1 ? 'Yes' : 'No') ?></td>
          <td class="actions">
            <a href="manage_books.php?edit=<?= (int)$b['book_id'] ?>" class="btn-link">Edit</a> <!-- edit link -->
            <form method="post" action="manage_books.php" style="display:inline;" onsubmit="return confirm('Remove this book?');"> <!-- delete form -->
              <input type="hidden" name="delete_book" value="<?= (int)$b['book_id'] ?>"> <!-- hidden id -->
              <button type="submit" class="btn-link">Remove</button> <!-- submit delete -->
            </form>
          </td>
        </tr>
      <?php endforeach; ?> <!-- end loop -->
      </tbody>
    </table>
  <?php endif; ?> <!-- end results table -->

</main>
</body>
</html>


