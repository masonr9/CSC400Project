<?php
session_start();
require_once "connect.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'Admin') {
  header("Location: login.php");
  exit();
}

// Handle role updates or deletions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  if (isset($_POST['update_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['role'];
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
    $stmt->bind_param("si", $new_role, $user_id);
    $stmt->execute();
    $message = "Role updated successfully.";
  }

  if (isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $message = "User removed successfully.";
  }
}

// Fetch users
$result = $conn->query("SELECT * FROM users ORDER BY role, name");
?>
<!DOCTYPE html>
<html>
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
      <li><a href="admin_dashboard.php">Dashboard</a></li>
      <li><a href="logout.php" class="logout-btn">Logout</a></li>
    </ul>
  </nav>
</header>

<main>
  <?php if (isset($message)) echo "<p style='color:green;'>$message</p>"; ?>
  <table border="1" width="100%">
    <tr>
      <th>Name</th><th>Email</th><th>Role</th><th>Actions</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()) { ?>
    <tr>
      <td><?php echo htmlspecialchars($row['name']); ?></td>
      <td><?php echo htmlspecialchars($row['email']); ?></td>
      <td>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
          <select name="role">
            <option <?php if ($row['role']=='Admin') echo 'selected'; ?>>Admin</option>
            <option <?php if ($row['role']=='Librarian') echo 'selected'; ?>>Librarian</option>
            <option <?php if ($row['role']=='Member') echo 'selected'; ?>>Member</option>
          </select>
          <button type="submit" name="update_role">Update</button>
        </form>
      </td>
      <td>
        <form method="POST" onsubmit="return confirm('Remove this user?');" style="display:inline;">
          <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
          <button type="submit" name="delete_user">Remove</button>
        </form>
      </td>
    </tr>
    <?php } ?>
  </table>
</main>
</body>
</html>
