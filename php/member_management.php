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

// Small helper function to write to logs
function log_action(mysqli $db, string $what): void {
  $who = $_SESSION['name'] ?? ('User#' . (int)($_SESSION['user_id'] ?? 0));
  if ($stmt = mysqli_prepare($db, "INSERT INTO logs (`user`, `action`) VALUES (?, ?)")) {
    mysqli_stmt_bind_param($stmt, "ss", $who, $what);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
  }
}

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
    $newId = (int)mysqli_insert_id($database);
    log_action($database, "Added member #{$newId} \"{$name}\" ({$email})");
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
  $forLogName = '';
  $forLogEmail = '';
  if ($info = mysqli_prepare($database, "SELECT name, email FROM users WHERE user_id = ? LIMIT 1")) {
    mysqli_stmt_bind_param($info, "i", $targetId);
    mysqli_stmt_execute($info);
    $rs = mysqli_stmt_get_result($info);
    if ($rowInfo = mysqli_fetch_assoc($rs)) {
      $forLogName  = (string)($rowInfo['name'] ?? '');
      $forLogEmail = (string)($rowInfo['email'] ?? '');
    }
    mysqli_stmt_close($info);
  }
  $del = mysqli_prepare($database, "DELETE FROM users WHERE user_id = ? AND role = 'Member'"); // delete only member
  mysqli_stmt_bind_param($del, "i", $targetId); // bind id
  mysqli_stmt_execute($del); // execute delete
  $affected = mysqli_stmt_affected_rows($del); // store rows affected
  mysqli_stmt_close($del); // close delete statement

  if ($affected > 0) { // if a row was deleted
    // Write to logs after a successful removal
    $desc = $forLogName !== '' ? "Removed member #{$targetId} \"{$forLogName}\" ({$forLogEmail})"
                              : "Removed member #{$targetId}";
    log_action($database, $desc);

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
  <style>
    /* Layout shell */
    .shell {
      max-width: 1100px;
      margin: 1.75rem auto 3rem;
      padding: 0 1rem;
    }

    /* Title bar */
    .page-title-bar {
      background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
      border: 1px solid #eef2f7;
      border-radius: 12px;
      padding: 1.25rem 1.25rem 1rem;
      box-shadow: 0 6px 18px rgba(15,23,42,.04);
      margin-bottom: 1rem;
    }
    .page-title { margin: 0; font-size: 1.5rem; color: #111827; }
    .page-subtitle { margin: .35rem 0 0; color: #6b7280; font-size: .95rem; }

    /* Grid helpers */
    .grid-2 {
      display: grid;
      grid-template-columns: repeat(2, minmax(0,1fr));
      gap: 1rem;
    }
    .gap-lg { gap: 1.25rem; }
    .mt-lg { margin-top: 1.25rem; }
    @media (max-width: 768px) {
      .grid-2 { grid-template-columns: 1fr; }
    }

    /* Cards */
    .card {
      background: #fff;
      border: 1px solid #edf2f7;
      border-radius: 12px;
      box-shadow: 0 4px 14px rgba(15,23,42,.03);
      overflow: hidden;
    }
    .card-header {
      padding: .9rem 1rem;
      border-bottom: 1px solid #eef2f7;
      background: #f9fafb;
    }
    .card-title {
      margin: 0;
      font-size: 1rem;
      color: #111827;
    }
    .card-body { padding: 1rem; }

    /* Forms */
    .form .form-field { margin-bottom: .8rem; }
    .label { display: block; margin-bottom: .35rem; color: #374151; font-weight: 600; font-size: .9rem; }
    .input {
      width: 100%;
      padding: .55rem .65rem;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      font-size: .95rem;
      background: #ffffff;
    }
    .input:focus {
      outline: none;
      border-color: #c7d2fe;
      box-shadow: 0 0 0 3px rgba(99,102,241,.15);
    }
    .form-actions { display: flex; gap: .5rem; align-items: center; }

    /* Buttons */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .55rem .9rem;
      border-radius: 10px;
      border: 1px solid #e5e7eb;
      background: #fff;
      color: #1f2937;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
    }
    .btn:hover { background: #f9fafb; }
    .btn.primary {
      background: #4f46e5;
      color: #fff;
      border-color: #4f46e5;
      box-shadow: 0 8px 18px rgba(79,70,229,.25);
    }
    .btn.primary:hover { background: #4338ca; border-color: #4338ca; }
    .btn.ghost { background: #fff; }
    .btn.danger { border-color: #fca5a5; color: #b91c1c; background: #fff; }
    .btn.danger:hover { background: #fef2f2; }
    .btn.linkish { border: none; background: transparent; padding: 0; }

    /* Alerts */
    .alert {
      padding: .75rem 1rem;
      border-radius: 10px;
      border: 1px solid;
      margin: .75rem 0 1rem;
      font-weight: 600;
    }
    .alert-success {
      background: #ecfdf5;
      border-color: #a7f3d0;
      color: #065f46;
    }
    .alert-danger {
      background: #fef2f2;
      border-color: #fecaca;
      color: #991b1b;
    }

    /* Table */
    .table-wrap {
      border: 1px solid #edf2f7;
      border-radius: 12px;
      overflow: hidden;
      background: #fff;
      box-shadow: 0 4px 14px rgba(15,23,42,.03);
    }
    .table {
      width: 100%;
      border-collapse: collapse;
    }
    .table thead th {
      background: #f9fafb;
      text-align: left;
      padding: .75rem 1rem;
      font-size: .9rem;
      color: #111827;
      border-bottom: 1px solid #eef2f7;
    }
    .table tbody td {
      padding: .75rem 1rem;
      border-bottom: 1px solid #f3f4f6;
      vertical-align: middle;
    }
    .align-right { text-align: right; }
    .th-40 { width: 40%; }
    .th-20 { width: 20%; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }

    /* Section header */
    .section-header {
      display: flex;
      align-items: center;
      gap: .5rem;
      margin-bottom: .6rem;
    }
    .section-title { margin: 0; font-size: 1.05rem; color: #111827; }
    .badge.soft {
      display: inline-block;
      padding: .2rem .5rem;
      font-size: .8rem;
      border-radius: 999px;
      background: #eef2ff;
      color: #3730a3;
      border: 1px solid #e0e7ff;
    }

    /* Utility */
    .inline-form { display: inline; }
    .stack > * + * { margin-top: .75rem; }
    .muted { color: #6b7280; }
  </style>
</head>
<body>

<?php include 'librarian_nav.php' ?>

<main class="shell">
  <!-- Title Bar -->
  <div class="page-title-bar">
    <h2 class="page-title">Member Management</h2>
    <p class="page-subtitle">Add new members, search existing ones, and remove accounts where appropriate.</p>
  </div>

  <!-- Flash -->
  <?php if ($flash): ?>
    <div class="alert <?= ($flashColor === 'red') ? 'alert-danger' : 'alert-success' ?>">
      <?= h($flash) ?>
    </div>
  <?php endif; ?>

  <!-- Forms Row -->
  <section class="row grid-2 gap-lg">
    <!-- Add Member -->
    <article class="card">
      <div class="card-header">
        <h3 class="card-title">Add Member</h3>
      </div>
      <form method="post" class="card-body form">
        <div class="form-field">
          <label class="label">Member Name</label>
          <input type="text" name="name" class="input" placeholder="Full name" required>
        </div>
        <div class="form-field">
          <label class="label">Email</label>
          <input type="email" name="email" class="input" placeholder="name@example.com" required>
        </div>
        <div class="form-field">
          <label class="label">Password</label>
          <input type="password" name="password" class="input" minlength="8" placeholder="Min 8 characters" required>
        </div>
        <div class="form-actions">
          <button type="submit" name="add_member" class="btn primary">Add Member</button>
        </div>
      </form>
    </article>

    <!-- Search -->
    <article class="card">
      <div class="card-header">
        <h3 class="card-title">Search Members</h3>
      </div>
      <form method="get" action="member_management.php" class="card-body form">
        <div class="form-field">
          <label class="label">Query</label>
          <input
            type="text"
            name="q"
            class="input"
            placeholder="Search by name or email..."
            value="<?= h($q) ?>"
          >
        </div>
        <div class="form-actions">
          <button type="submit" class="btn">Search</button>
          <?php if ($q !== ''): ?>
            <a href="member_management.php" class="btn ghost">Clear</a>
          <?php endif; ?>
        </div>
      </form>
    </article>
  </section>

  <!-- Members Table -->
  <section class="stack mt-lg">
    <div class="section-header">
      <h3 class="section-title">Registered Members</h3>
      <?php if (!empty($members)): ?>
        <span class="badge soft"><?= count($members) ?> total</span>
      <?php endif; ?>
    </div>

  <?php if (empty($members)): ?>
    <p class="muted">No members found.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th class="th-40">Name</th>
            <th class="th-40">Email</th>
            <th class="th-20 align-right">Remove</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($members as $m): ?>
            <tr>
              <td><?= h($m['name']) ?></td>
              <td><span class="mono"><?= h($m['email']) ?></span></td>
              <td class="align-right">
                <form method="post" action="member_management.php" class="inline-form"
                      onsubmit="return confirm('Remove this member?');">
                  <input type="hidden" name="member_id" value="<?= (int)$m['user_id'] ?>">
                  <button type="submit" name="remove_member" class="btn danger linkish">Remove</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  </section>
</main>

</body>
</html>