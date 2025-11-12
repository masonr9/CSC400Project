<?php
session_start(); // start or resume the session
require_once "connect.php"; // provides $database (mysqli link)

// Security: Admins only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') { // if no role in session or not Admin
  header("Location: login.php"); // redirect to login
  exit(); // stop executing
}

// small helper function to safely escape output
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

$message = ""; // message string used to show operation results to the admin

// Handle role updates or deletions
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // only process when a form is submitted via POST

  // Update role
  if (isset($_POST['update_role'])) { // if the update button was pressed in a row
    $targetId = (int)($_POST['user_id'] ?? 0); // get target user_id from the form and cast to int
    $newRole  = trim($_POST['role'] ?? ''); // get desired role from the form and trim whitespace

    // validate inputs
    $allowed = ['Admin','Librarian','Member']; // allowed role values to prevent arbitrary input
    if ($targetId > 0 && in_array($newRole, $allowed, true)) { // validate id and role
      $stmtUpdate = mysqli_prepare($database, "UPDATE users SET role = ? WHERE user_id = ?"); // prepare update
      mysqli_stmt_bind_param($stmtUpdate, "si", $newRole, $targetId); // bind role (string) and id (int)
      if (mysqli_stmt_execute($stmtUpdate)) { // execute the update
        $message = "Role updated successfully.";

        // insert into logs
        $stmtLog = mysqli_prepare($database, "INSERT INTO logs (`user`, `action`) VALUES (?, ?)"); // prepare an insert into logs table
        $who  = $_SESSION['name'] ?? ('User#'.(int)$_SESSION['user_id']); // who performed the change, fallback to user id
        $what = "Updated role for user #{$targetId} to {$newRole}"; // description of what happened
        mysqli_stmt_bind_param($stmtLog, "ss", $who, $what); // bind user and action strings
        mysqli_stmt_execute($stmtLog); // execute the Insert
        mysqli_stmt_close($stmtLog); // close the log statement

      } else {
        $message = "Failed to update role."; // if update didn't execute
      }
      mysqli_stmt_close($stmtUpdate); // close the update statement
    } else {
      $message = "Invalid user or role."; // validation error
    }
  }

  // Delete user
  if (isset($_POST['delete_user'])) { // if the remove button was pressed in a row
    $targetId = (int)($_POST['user_id'] ?? 0); // get target user_id and cast to int

    if ($targetId > 0) { // only proceed with a valid id
      $stmtDel = mysqli_prepare($database, "DELETE FROM users WHERE user_id = ?"); // prepare delete
      mysqli_stmt_bind_param($stmtDel, "i", $targetId); // bind id (int)
      if (mysqli_stmt_execute($stmtDel)) { // execute deletion
        $message = "User removed successfully.";

        // insert into logs
        $stmtLog = mysqli_prepare($database, "INSERT INTO logs (`user`, `action`) VALUES (?, ?)"); // prepare log insert
        $who  = $_SESSION['name'] ?? ('User#'.(int)$_SESSION['user_id']); // display name
        $what = "Deleted user #{$targetId}"; // action description
        mysqli_stmt_bind_param($stmtLog, "ss", $who, $what); // bind strings
        mysqli_stmt_execute($stmtLog); // execute the Insert
        mysqli_stmt_close($stmtLog); // close the log statement

      } else {
        $message = "Failed to remove user."; // if delete didn't execute
      }
      mysqli_stmt_close($stmtDel); // close delete statement
    } else {
      $message = "Invalid user."; // validation error
    }
  }
}

// Fetch users (ordered by role then name)
$result = mysqli_query($database, "SELECT user_id, name, email, role FROM users ORDER BY role, name"); // query all users for listing
$rows   = [];
if ($result) {
  while ($r = mysqli_fetch_assoc($result)) { $rows[] = $r; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Role Management</title>
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

    /* Grid helper */
    .mt-lg { margin-top: 1.25rem; }

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
    .form { margin-bottom: .8rem; }

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
    .muted { color: #6b7280; }

    .select {
      appearance: none;
      -webkit-appearance: none;
      -moz-appearance: none;
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: .5rem .75rem;
      font-size: .95rem;
      color: #111827;
    }
    .select:focus {
      outline: none;
      border-color: #c7d2fe;
      box-shadow: 0 0 0 3px rgba(99,102,241,.15);
    }

    /* Optional narrow widths used in table header */
    .th-22 { width: 22%; }
    .th-28 { width: 28%; }
  </style>
</head>
<body>

<?php include 'admin_nav.php'; ?>

<main class="shell">
  <!-- Title Bar -->
  <div class="page-title-bar">
    <h2 class="page-title">Role Management</h2>
    <p class="page-subtitle">Assign roles, promote librarians, and remove accounts as needed.</p>
  </div>

  <!-- Feedback -->
  <?php if (!empty($message)): ?>
    <div class="alert alert-success"><?= h($message) ?></div>
  <?php endif; ?>

  <!-- Users Table Card -->
  <section class="card mt-lg">
    <div class="card-header section-header">
      <h3 class="card-title section-title">All Users</h3>
      <?php if (!empty($rows)): ?>
        <span class="badge soft"><?= count($rows) ?> total</span>
      <?php endif; ?>
    </div>

    <div class="card-body table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th class="th-22">Name</th>
            <th class="th-28">Email</th>
            <th class="th-20">Role</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!empty($rows)): ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= h($row['name']) ?></td>
              <td><span class="mono"><?= h($row['email']) ?></span></td>
              <td>
                <form method="POST" class="inline-form">
                  <input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>">
                  <select name="role" class="select">
                    <option value="Admin"     <?= $row['role']==='Admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="Librarian" <?= $row['role']==='Librarian' ? 'selected' : '' ?>>Librarian</option>
                    <option value="Member"    <?= $row['role']==='Member' ? 'selected' : '' ?>>Member</option>
                  </select>
                  <button type="submit" name="update_role" class="btn primary" style="margin-left:.4rem;">Update</button>
                </form>
              </td>
              <td class="align-right">
                <form method="POST" class="inline-form" onsubmit="return confirm('Remove this user?');">
                  <input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>">
                  <button type="submit" name="delete_user" class="btn danger">Remove</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="4" class="muted">No users found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>

</body>
</html>