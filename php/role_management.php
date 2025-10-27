<?php
session_start(); // start or resume the session so we can read $_SESSION values
require_once "connect.php"; // provides $database (mysqli link)

// admins only
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'Admin') { // if there's no logged-in user or role in session or the role is not exactly 'Admin'
  header("Location: login.php"); // redirect non-admins to the login page
  exit(); // stop executing after redirect
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); } // helper function to safely escape any text for HTML output

$adminId = (int)$_SESSION['user_id']; // store current admin's user_id (cast to int for safety)
$message = ''; // feedback message text
$messageColor = 'green'; // default message color

// allowed roles whitelist
$ALLOWED_ROLES = ['Admin','Librarian','Member']; // valid role name allowed in updates

// handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // handle form submissions only on POST

  // update role
  if (isset($_POST['update_role'])) { // if the update button was pressed
    $user_id = (int)($_POST['user_id'] ?? 0); // target user id, cast to int
    $new_role = trim($_POST['role'] ?? ''); // desired role string

    if ($user_id <= 0 || !in_array($new_role, $ALLOWED_ROLES, true)) { // validate id and role value
      $message = "Invalid input."; // show error if invalid
      $messageColor = "red"; // color red for error
    } elseif ($user_id === $adminId) { // prevent changing your own role for safety
      $message = "You cannot change your own role."; // feedback for disallowed action
      $messageColor = "red"; // error color
    } else {
      $stmt = mysqli_prepare($database, "UPDATE users SET role = ? WHERE user_id = ?"); // prepare an UPDATE statement
      mysqli_stmt_bind_param($stmt, "si", $new_role, $user_id); // bind role as string and user_id as int
      if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) >= 0) { // execute the UPDATE, if query ran (>=0 means even if same role)
        $message = "Role updated successfully."; // success feedback
        $messageColor = "green"; // success color
      } else {
        $message = "Server error updating role."; // failure feedback
        $messageColor = "red"; // error color
      }
      mysqli_stmt_close($stmt); // close the statement
    }
  }

  // delete user
  if (isset($_POST['delete_user'])) { // if the remove button was pressed
    $user_id = (int)($_POST['user_id'] ?? 0); // target user id, cast to int

    if ($user_id <= 0) { // validate user id
      $message = "Invalid user."; // error message for bad id
      $messageColor = "red"; // error color
    } elseif ($user_id === $adminId) { // prevent deleting yourself for safety
      $message = "You cannot remove your own account."; // feedback for not allowed action
      $messageColor = "red"; // error color
    } else {
      $stmt = mysqli_prepare($database, "DELETE FROM users WHERE user_id = ?"); // prepare a DELETE statement
      mysqli_stmt_bind_param($stmt, "i", $user_id); // bind user_id as int
      if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) { // execute the DELETE and check that a row was actually deleted
        $message = "User removed successfully."; // success message
        $messageColor = "green"; // success color
      } else {
        $message = "Unable to remove user (not found or in use)."; // failure due to constraints or not found
        $messageColor = "red"; // error color
      }
      mysqli_stmt_close($stmt); // close the statement
    }
  }
}

// Fetch users list 
$sql = "SELECT user_id, name, email, role
          FROM users
      ORDER BY FIELD(role,'Admin','Librarian','Member'), name ASC"; // order admins first, then librarians, then Members, then by name
$result = mysqli_query($database, $sql); // run the SELECT query
if (!$result) { // if the query failed
  $message = "Error loading users."; // set an error message
  $messageColor = "red"; // error color
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Role Management</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<header>
  <h1>Role Management</h1>
  <nav>
    <ul>
      <li><a href="admin.php">Dashboard</a></li>
      <li><a href="logout.php" class="logout-btn">Logout</a></li>
    </ul>
  </nav>
</header>

<main>
  <?php if ($message !== ''): ?> <!-- if there is feedback to show -->
    <p style="color: <?= h($messageColor) ?>;"><?= h($message) ?></p> <!-- Render it with escaped text chosen color -->
  <?php endif; ?>

  <table class="list">
    <thead>
      <tr>
        <th style="width:22%;">Name</th>
        <th style="width:28%;">Email</th>
        <th style="width:22%;">Role</th>
        <th style="width:28%;">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result && mysqli_num_rows($result) > 0): ?> <!-- if we got user rows back -->
        <?php while ($row = mysqli_fetch_assoc($result)): ?> <!-- loop each user record -->
          <tr>
            <td><?= h($row['name']) ?></td> <!-- display escaped name -->
            <td><?= h($row['email']) ?></td> <!-- display escaped email -->
            <td>
              <form method="post" class="inline"> <!-- inline form to update a user's role -->
                <input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>"> <!-- hidden target id -->
                <select name="role">
                  <?php foreach ($ALLOWED_ROLES as $r): ?> <!-- loop allowed roles to build options -->
                    <option value="<?= h($r) ?>" <?= ($row['role'] === $r ? 'selected' : '') ?>>
                      <?= h($r) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" name="update_role" class="btn-link"
                  <?= ($row['user_id'] == $adminId) ? 'disabled title="Cannot change your own role"' : '' ?>>
                  Update
                </button> <!-- disable update on your own account -->
              </form>
            </td>
            <td>
              <form method="post" class="inline" onsubmit="return confirm('Remove this user? This cannot be undone.');">
                <input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>">
                <button type="submit" name="delete_user" class="btn-link"
                  <?= ($row['user_id'] == $adminId) ? 'disabled title="Cannot remove your own account"' : '' ?>>
                  Remove
                </button> <!-- disable remove on your own account -->
              </form>
            </td>
          </tr>
        <?php endwhile; ?> <!-- end users loop -->
      <?php else: ?> <!-- if no users were found -->
        <tr><td colspan="4" class="muted">No users found.</td></tr> <!-- Empty-state row -->
      <?php endif; ?>
    </tbody>
  </table>
</main>

</body>
</html>
