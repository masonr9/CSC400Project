<?php
session_start();
require_once "connect.php";

// Only allow Admin users
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
  header("Location: login.php");
  exit();
}

// Escape helper
function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES);
}

$message = "";

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Update role
  if (isset($_POST['update_role'])) {
    $targetId = (int)($_POST['user_id'] ?? 0);
    $newRole  = trim($_POST['role'] ?? '');

    $allowed = ['Admin', 'Librarian', 'Member'];

    if ($targetId > 0 && in_array($newRole, $allowed, true)) {
      $stmtUpdate = mysqli_prepare($database, "UPDATE users SET role = ? WHERE user_id = ?");
      mysqli_stmt_bind_param($stmtUpdate, "si", $newRole, $targetId);

      if (mysqli_stmt_execute($stmtUpdate)) {
        $message = "Role updated successfully.";

        // Log the change
        $stmtLog = mysqli_prepare($database, "INSERT INTO logs (`user`, `action`) VALUES (?, ?)");
        $who  = $_SESSION['name'] ?? ('User#' . (int)$_SESSION['user_id']);
        $what = "Updated role for user #{$targetId} to {$newRole}";
        mysqli_stmt_bind_param($stmtLog, "ss", $who, $what);
        mysqli_stmt_execute($stmtLog);
        mysqli_stmt_close($stmtLog);

      } else {
        $message = "Failed to update role.";
      }

      mysqli_stmt_close($stmtUpdate);
    } else {
      $message = "Invalid user or role.";
    }
  }

  // Delete user
  if (isset($_POST['delete_user'])) {
    $targetId = (int)($_POST['user_id'] ?? 0);

    if ($targetId > 0) {
      $stmtDel = mysqli_prepare($database, "DELETE FROM users WHERE user_id = ?");
      mysqli_stmt_bind_param($stmtDel, "i", $targetId);

      if (mysqli_stmt_execute($stmtDel)) {
        $message = "User removed successfully.";

        // Log the deletion
        $stmtLog = mysqli_prepare($database, "INSERT INTO logs (`user`, `action`) VALUES (?, ?)");
        $who  = $_SESSION['name'] ?? ('User#' . (int)$_SESSION['user_id']);
        $what = "Deleted user #{$targetId}";
        mysqli_stmt_bind_param($stmtLog, "ss", $who, $what);
        mysqli_stmt_execute($stmtLog);
        mysqli_stmt_close($stmtLog);

      } else {
        $message = "Failed to remove user.";
      }

      mysqli_stmt_close($stmtDel);
    } else {
      $message = "Invalid user.";
    }
  }
}

// Fetch user list
$result = mysqli_query($database, "SELECT user_id, name, email, role FROM users ORDER BY role, name");
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
  <?php if (!empty($message)): ?>
    <p style="color:green;"><?= h($message) ?></p>
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
      <?php if ($result && mysqli_num_rows($result) > 0): ?>
        <?php while ($row = mysqli_fetch_assoc($result)): ?>
          <tr>
            <td><?= h($row['name']) ?></td>
            <td><?= h($row['email']) ?></td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="user_id" value="<?= (int)$row['user_id'] ?>">
                <select name="role">
                  <option value="Admin" <?= $row['role'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
                  <option value="Librarian" <?= $row['role'] === 'Librarian' ? 'selected' : '' ?>>Librarian</option>
                  <option value="Member" <?= $row['role'] === 'Member' ? 'selected' : '' ?>>Member</option>
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
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="4">No users found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</main>

</body>
</html>
