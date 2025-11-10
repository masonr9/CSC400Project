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
    body {
      background: #f1f5f9;
    }
    header.topbar {
      background: #0f172a;
      color: #fff;
      padding: 0.75rem 1.25rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 6px rgba(0,0,0,.1);
    }
    header.topbar h1 {
      font-size: 1.15rem;
      margin: 0;
    }
    header.topbar nav ul {
      list-style: none;
      margin: 0;
      padding: 0;
      display: flex;
      gap: 1rem;
    }
    header.topbar nav a {
      color: #fff;
      text-decoration: none;
      font-weight: 500;
    }
    .page-shell {
      max-width: 1200px;
      margin: 1.5rem auto 2.5rem;
      display: grid;
      grid-template-columns: 340px 1fr;
      gap: 1.5rem;
      align-items: flex-start;
    }
    @media (max-width: 992px) {
      .page-shell {
        grid-template-columns: 1fr;
      }
    }
    .card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(15,23,42,.04), 0 4px 6px rgba(15,23,42,.03);
      padding: 1.25rem 1.25rem 1.4rem;
    }
    h2.page-title {
      max-width: 1200px;
      margin: 1rem auto 0.75rem;
      font-size: 1.5rem;
      color: #0f172a;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
    }
    .flash {
      max-width: 1200px;
      margin: .3rem auto 1rem;
      padding: .65rem .9rem;
      border-radius: 8px;
      font-weight: 500;
    }
    .flash.green { 
      background: #dcfce7; 
      color: #166534; 
    }
    .flash.red { 
      background: #fee2e2; 
      color: #b91c1c; 
    }
    .form-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: .65rem;
    }
    .form-grid label {
      font-weight: 500;
      font-size: .82rem;
      color: #0f172a;
    }
    .form-grid input,
    .form-grid textarea {
      width: 100%;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      padding: .45rem .6rem;
      font-size: .85rem;
      background: #fff;
    }
    .form-grid textarea {
      min-height: 110px;
      resize: vertical;
    }
    .btn-primary {
      background: #2563eb;
      color: #fff;
      border: none;
      padding: .5rem .85rem;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      font-size: .85rem;
    }
    .btn-primary:hover {
      background: #1d4ed8;
    }
    .btn-link {
      background: transparent;
      border: none;
      color: #2563eb;
      cursor: pointer;
      font-size: .8rem;
      text-decoration: underline;
    }
    .search-card form {
      display: flex;
      gap: .5rem;
      flex-wrap: wrap;
    }
    .search-card input {
      flex: 1 1 220px;
    }
    .table-card h3 {
      margin-bottom: .65rem;
      font-size: 1rem;
    }
    table.data-table {
      width: 100%;
      border-collapse: collapse;
      font-size: .78rem;
      background: #fff;
    }
    table.data-table thead {
      background: #eff3fb;
    }
    table.data-table th,
    table.data-table td {
      padding: .55rem .5rem;
      border-bottom: 1px solid #e5e7eb;
      vertical-align: top;
    }
    table.data-table th {
      text-align: left;
      font-weight: 600;
      color: #475569;
    }
    table.data-table tbody tr:hover {
      background: #f8fafc;
    }
    .badge-yes {
      background: #dcfce7;
      color: #166534;
      padding: .2rem .4rem;
      border-radius: 9999px;
      font-size: .7rem;
      font-weight: 600;
      display: inline-block;
    }
    .badge-no {
      background: #fee2e2;
      color: #b91c1c;
      padding: .2rem .4rem;
      border-radius: 9999px;
      font-size: .7rem;
      font-weight: 600;
      display: inline-block;
    }
    .actions {
      display: flex;
      gap: .25rem;
      flex-wrap: wrap;
    }
  </style> 
</head>
<body>

<header class="topbar">
  <h1>Library Management System</h1>
  <nav>
    <ul>
      <li><a href="librarian.php">Librarian Dashboard</a></li>
      <li><a class="logout-btn" href="logout.php">Logout</a></li>
    </ul>
  </nav>
</header>

<h2 class="page-title">Manage Books</h2>

  <?php if ($flash): ?> <!-- If a flash message exists -->
    <p style="color: <?= h($flashColor) ?>;"><?= h($flash) ?></p> <!-- show the flash message in chosen color -->
  <?php endif; ?> <!-- End flash block -->

  <div class="page-shell">
    <!-- left-side: Add/Edit -->
    <section class="card">
      <?php if ($editRow): ?>
        <h3 style="margin-top:0;margin-bottom:.5rem;">Edit Book</h3>
        <form method="post" action="manage_books.php" class="form-grid">
          <input type="hidden" name="book_id" value="<?= (int)$editRow['book_id'] ?>">
          <div>
            <label>Book Title</label>
            <input type="text" name="title" required value="<?= h($editRow['title']) ?>">
          </div>
          <div>
            <label>Author</label>
            <input type="text" name="author" required value="<?= h($editRow['author']) ?>">
          </div>
          <div>
            <label>Genre</label>
            <input type="text" name="genre" value="<?= h($editRow['genre']) ?>">
          </div>
          <div>
            <label>Language</label>
            <input type="text" name="language" value="<?= h($editRow['language']) ?>">
          </div>
          <div>
            <label>ISBN</label>
            <input type="text" name="isbn" value="<?= h($editRow['isbn']) ?>">
          </div>
          <div>
            <label>Publication Year</label>
            <input type="number" min="0" name="publication_year" value="<?= h($editRow['publication_year']) ?>">
          </div>
          <div>
            <label>Available Quantity</label>
            <input type="number" min="0" name="available_qty" value="<?= ((int)$editRow['available'] === 1 ? 1 : 0) ?>">
            <small style="color:#94a3b8;">If &gt;0, book is marked available.</small>
          </div>
          <div>
            <label>Summary</label>
            <textarea name="summary"><?= h($editRow['summary']) ?></textarea>
          </div>
          <div style="display:flex;gap:.5rem;margin-top:.4rem;">
            <button type="submit" name="update_book" class="btn-primary">Save Changes</button>
            <a href="manage_books.php" class="btn-link">Cancel</a>
          </div>
        </form>
      <?php else: ?>
        <h3 style="margin-top:0;margin-bottom:.5rem;">Add New Book</h3>
        <form method="post" action="manage_books.php" class="form-grid">
          <div>
            <label>Book Title</label>
            <input type="text" name="title" required>
          </div>
          <div>
            <label>Author</label>
            <input type="text" name="author" required>
          </div>
          <div>
            <label>Genre</label>
            <input type="text" name="genre">
          </div>
          <div>
            <label>Language</label>
            <input type="text" name="language">
          </div>
          <div>
            <label>ISBN</label>
            <input type="text" name="isbn">
          </div>
          <div>
            <label>Publication Year</label>
            <input type="number" min="0" name="publication_year">
          </div>
          <div>
            <label>Available Quantity</label>
            <input type="number" min="0" name="available_qty" value="1">
            <small style="color:#94a3b8;">If &gt;0, book is marked available.</small>
          </div>
          <div>
            <label>Summary</label>
            <textarea name="summary" placeholder="Short synopsis..."></textarea>
          </div>
          <div>
            <button type="submit" name="add_book" class="btn-primary">Add Book</button>
          </div>
        </form>
      <?php endif; ?>
    </section>

    <!-- right side: Search + Table -->
    <section style="display:flex;flex-direction:column;gap:1rem;">
      <div class="card search-card">
        <form method="get" action="manage_books.php">
          <label for="q" style="font-weight:600;">Search Books</label>
          <input type="text" id="q" name="q" placeholder="Search by title, author, or ISBN..." value="<?= h($q) ?>">
          <button type="submit" class="btn-primary">Search</button>
          <?php if ($q !== ''): ?>
            <a href="manage_books.php" class="btn-link">Clear</a>
          <?php endif; ?>
        </form>
      </div>

      <div class="card table-card">
        <h3>Book Catalog</h3>
        <?php if (empty($rows)): ?>
          <p class="muted">No books found.</p>
        <?php else: ?>
          <div style="overflow-x:auto;">
            <table class="data-table" id="bookTable">
              <thead>
                <tr>
                  <th style="min-width:140px;">Title</th>
                  <th style="min-width:120px;">Author</th>
                  <th>Genre</th>
                  <th>Lang</th>
                  <th>ISBN</th>
                  <th>Year</th>
                  <th>Summary</th>
                  <th>Avail</th>
                  <th style="min-width:110px;">Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($rows as $b): ?>
                <tr>
                  <td><?= h($b['title']) ?></td>
                  <td><?= h($b['author']) ?></td>
                  <td><?= h($b['genre']) ?></td>
                  <td><?= h($b['language']) ?></td>
                  <td><?= h($b['isbn']) ?></td>
                  <td><?= h($b['publication_year']) ?></td>
                  <td><?= h(mb_strimwidth($b['summary'] ?? '', 0, 140, '…')) ?></td>
                  <td>
                    <?php if ((int)$b['available'] === 1): ?>
                      <span class="badge-yes">Yes</span>
                    <?php else: ?>
                      <span class="badge-no">No</span>
                    <?php endif; ?>
                  </td>
                  <td class="actions">
                    <a href="manage_books.php?edit=<?= (int)$b['book_id'] ?>" class="btn-link">Edit</a>
                    <form method="post" action="manage_books.php" style="display:inline;" onsubmit="return confirm('Remove this book?');">
                      <input type="hidden" name="delete_book" value="<?= (int)$b['book_id'] ?>">
                      <button type="submit" class="btn-link">Remove</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</body>
</html>