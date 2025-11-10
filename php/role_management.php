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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Role Management</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php include 'admin_nav.php'; ?>

<main>
  <?php if (!empty($message)): ?> <!-- if a feedback message exists -->
    <p style="color:green;"><?= h($message) ?></p> <!-- output it (escaped) in green-->
  <?php endif; ?>

  <table class="list" width="100%">
    <thead>
      <tr>
        <th style="width:22%;">Name</th>
        <th style="width:28%;">Email</th>
        <th style="width:20%;">Role</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($result && mysqli_num_rows($result) > 0): ?> <!-- if the query returned any rows -->
      <?php while ($row = mysqli_fetch_assoc($result)): ?> <!-- iterate rows as associative arrays -->
        <tr>
          <td><?= h($row['name']) ?></td>
          <td><?= h($row['email']) ?></td>
          <td>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>">
              <select name="role">
                <option value="Admin"     <?= $row['role']==='Admin' ? 'selected' : '' ?>>Admin</option>
                <option value="Librarian" <?= $row['role']==='Librarian' ? 'selected' : '' ?>>Librarian</option>
                <option value="Member"    <?= $row['role']==='Member' ? 'selected' : '' ?>>Member</option>
              </select>
              <button type="submit" name="update_role">Update</button>
            </form>
          </td>
          <td>
            <form method="POST" onsubmit="return confirm('Remove this user?');" style="display:inline;">
              <input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>">
              <button type="submit" name="delete_user">Remove</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?> <!-- end of loop over users -->
    <?php else: ?> <!-- if there were no users returned -->
      <tr><td colspan="4">No users found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</main>

</body>
</html>