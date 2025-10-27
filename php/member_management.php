<?php
session_start(); // start or resume the session so we can read and write to $_SESSION state 
include "connect.php"; // this is where $database come froms

// Access control (Librarian/Admin only)
if (!isset($_SESSION['user_id'])) { // if there's no logged-in user in session
  header("Location: login.php"); // redirect them to the login page
  exit(); // stop running the script
}
$role = $_SESSION['role'] ?? 'Member'; // read the logged-in user's role (default Member if not set)
if (!in_array($role, ['Librarian','Admin'], true)) { // only librarian or admin can access this page
  http_response_code(403); // set HTTP status to 403 Forbidden
  echo "Forbidden: Librarian/Admin access only."; // output a simple message
  exit(); // stop execution
}

// small helper function to escape output safely for HTML
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

// flash helpers
$flash = $_SESSION['flash_msg'] ?? ''; // pull any flash message from session
$flashColor = $_SESSION['flash_color'] ?? 'green'; // pull its color, green is default
unset($_SESSION['flash_msg'], $_SESSION['flash_color']); // clear flash so it doesn't show again on refresh

// Add Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) { // if the add member form was submitted
  $name  = trim($_POST['name'] ?? ''); // get name, trim any whitespace
  $email = trim($_POST['email'] ?? ''); // get email, trim any whitepsace
  $pass  = $_POST['password'] ?? ''; // get plaintext password, it will be hashed later

  // basic validation
  if ($name === '' || $email === '' || $pass === '') { // ensure all fields are provided
    $_SESSION['flash_msg'] = "Name, email, and password are required."; // set error message
    $_SESSION['flash_color'] = "red"; // error color
    header("Location: member_management.php"); // redirect to avoid resubmission
    exit(); // stop executing
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { // validate email format
    $_SESSION['flash_msg'] = "Please provide a valid email address."; // invalid email address message
    $_SESSION['flash_color'] = "red"; // error color
    header("Location: member_management.php"); // back to page
    exit(); // stop executing
  }
  if (strlen($pass) < 8) { // enforce password length
    $_SESSION['flash_msg'] = "Password must be at least 8 characters.";
    $_SESSION['flash_color'] = "red";
    header("Location: member_management.php"); // back to page
    exit(); // stop executing
  }

  // Check duplicate email
  $dup = mysqli_prepare($database, "SELECT user_id FROM users WHERE email = ? LIMIT 1"); // prepare query to find existing email
  mysqli_stmt_bind_param($dup, "s", $email); // bind email as string
  mysqli_stmt_execute($dup); // execute query
  mysqli_stmt_store_result($dup); // buffer result to use num_rows
  if (mysqli_stmt_num_rows($dup) > 0) { // if a row exists, email is already registered
    mysqli_stmt_close($dup); // close statement
    $_SESSION['flash_msg'] = "Email is already registered."; // set flash message
    $_SESSION['flash_color'] = "red"; // error color
    header("Location: member_management.php"); // redirect back to page
    exit(); // stop executing
  }
  mysqli_stmt_close($dup); // close duplicate-check statement

  // Hash password and insert as Member
  $hashed = password_hash($pass, PASSWORD_DEFAULT); // hash the provided password securely
  $stmt = mysqli_prepare( // prepare an INSERT for a new Member user
    $database,
    "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'Member')"
  );
  mysqli_stmt_bind_param($stmt, "sss", $name, $email, $hashed); // bind name, email, hash

  if (mysqli_stmt_execute($stmt)) { // try to insert the row
    $_SESSION['flash_msg'] = "Member added successfully."; // success message
    $_SESSION['flash_color'] = "green"; // success color
  } else {
    $_SESSION['flash_msg'] = "Server error while adding member."; // failure message
    $_SESSION['flash_color'] = "red"; // error color
  }
  mysqli_stmt_close($stmt); // close insert statement
  header("Location: member_management.php"); // redirect to page
  exit(); // stop executing
}

// Remove Member
// Only allow deletion of users with role 'Member', Prevent self-delete for safety.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_member'])) { // if the remove button was submitted
  $targetId = (int)($_POST['member_id'] ?? 0); // get the member id from the form

  if ($targetId <= 0) { // validate the target id 
    $_SESSION['flash_msg'] = "Invalid member selection."; // error message
    $_SESSION['flash_color'] = "red"; // error color
    header("Location: member_management.php"); // redirect back to page
    exit(); // stop executing
  }

  // Make sure the target is actually a Member
  $check = mysqli_prepare($database, "SELECT role FROM users WHERE user_id = ? LIMIT 1"); // query the user's role
  mysqli_stmt_bind_param($check, "i", $targetId); // bind target id
  mysqli_stmt_execute($check); // execute check
  $r = mysqli_stmt_get_result($check); // get result cursor
  $row = mysqli_fetch_assoc($r); // fetch single row
  mysqli_stmt_close($check); // close the statement

  if (!$row || ($row['role'] ?? '') !== 'Member') { // if no user or not a member role
    $_SESSION['flash_msg'] = "Only Member accounts can be removed here."; // block deletion
    $_SESSION['flash_color'] = "red";
    header("Location: member_management.php");
    exit();
  }

  // Attempt delete, will fail if there are FK references (loans/reservations/fines) without CASCADE
  $del = mysqli_prepare($database, "DELETE FROM users WHERE user_id = ? AND role = 'Member'"); // delete only member
  mysqli_stmt_bind_param($del, "i", $targetId); // bind id
  mysqli_stmt_execute($del); // execute delete
  $affected = mysqli_stmt_affected_rows($del); // store rows affected
  mysqli_stmt_close($del); // close delete statement

  if ($affected > 0) { // if a row was deleted
    $_SESSION['flash_msg'] = "Member removed."; // success message
    $_SESSION['flash_color'] = "green"; // success color
  } else {
    $_SESSION['flash_msg'] = "Unable to remove member (referenced by other records)."; // error message
    $_SESSION['flash_color'] = "red";
  }
  header("Location: member_management.php"); // redirect back to list
  exit(); // stop executing
}

// Search and List Members
$q = trim($_GET['q'] ?? ''); // read search query from URL (?q=)
$params = []; // parameters for prepared statement
$types  = ''; // types string for binding
$sql = "SELECT user_id, name, email FROM users WHERE role = 'Member'"; // base query with members only

if ($q !== '') { // if there's a search term
  $sql .= " AND (name LIKE ? OR email LIKE ?)"; // add filters for name/email LIKE
  $like = '%'.$q.'%'; // wildcard pattern
  $params = [$like, $like]; // bind both placeholders with the pattern
  $types  = 'ss'; // two string parameters
}
$sql .= " ORDER BY name ASC, user_id ASC LIMIT 500"; // order list and limit rows

$stmt = mysqli_prepare($database, $sql); // prepare the final SELECT
if ($types !== '') { // if we have dynamic filters
  mysqli_stmt_bind_param($stmt, $types, ...$params); // bind parameters using spread operator
}
mysqli_stmt_execute($stmt); // execute the SELECT
$res = mysqli_stmt_get_result($stmt); // get result set cursor

$members = []; // accumlate result rows here
if ($res) { // if query ran successfully
  while ($row = mysqli_fetch_assoc($res)) { // fetch each row as associative array
    $members[] = $row; // add to array
  }
}
mysqli_stmt_close($stmt); // close the SELECT statement
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Member Management</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<header>
  <h1>Library Management System</h1>
  <nav>
    <ul>
      <li><a href="librarian.php">Dashboard</a></li>
      <li><a class="logout-btn" href="logout.php">Logout</a></li>
    </ul>
  </nav>
</header>

<main>
  <h2>Member Management</h2>

  <?php if ($flash): ?> <!-- if a flash message exists -->
    <p style="color: <?= h($flashColor) ?>;"><?= h($flash) ?></p> <!-- displays it with its color-->
  <?php endif; ?>

  <!-- Add Member -->
  <form method="post" class="form-box">
    <h3>Add Member</h3>
    <label>Member Name:</label>
    <input type="text" name="name" style="width:55%" required>
    <label>Email:</label>
    <input type="email" name="email" style="width:72%" required>
    <label>Password:</label>
    <input type="password" name="password" minlength="8" style="width:65%" required>
    <button type="submit" name="add_member">Add Member</button>
  </form>

  <!-- Search -->
  <h3>Search Members</h3>
  <form method="get" action="member_management.php" class="form-box" style="padding:12px;">
    <input
      type="text"
      name="q"
      placeholder="Search by name or email..."
      value="<?= h($q) ?>"
      style="width:90%; padding:10px; border:1px solid #ccc; border-radius:5px; margin-bottom:10px;"
    > <!-- the value preserve the current query in the input -->
    <button type="submit">Search</button>
    <?php if ($q !== ''): ?> <!-- if currently filtered -->
      <a href="member_management.php" class="btn-link" style="margin-left:.5rem;">Clear</a> <!-- provide clear link -->
    <?php endif; ?>
  </form>

  <!-- Members Table -->
  <h3>Registered Members</h3>
  <?php if (empty($members)): ?> <!-- if no rows returned -->
    <p class="muted">No members found.</p> <!-- show empty state -->
  <?php else: ?> <!-- otherwise render the table -->
    <table class="list" id="memberTable">
      <thead>
        <tr>
          <th style="width:40%;">Name</th>
          <th style="width:40%;">Email</th>
          <th style="width:20%;">Remove</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m): ?> <!-- loop through each member row -->
          <tr>
            <td><?= h($m['name']) ?></td>
            <td><?= h($m['email']) ?></td>
            <td>
              <form method="post" action="member_management.php" onsubmit="return confirm('Remove this member?');" style="display:inline;">
                <input type="hidden" name="member_id" value="<?= (int)$m['user_id'] ?>"> <!-- hidden field with user id -->
                <button type="submit" name="remove_member" class="btn-link">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?> <!-- end foreach -->
      </tbody>
    </table>
  <?php endif; ?>
</main>

</body>
</html>