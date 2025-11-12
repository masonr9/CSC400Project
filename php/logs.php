<?php
session_start();
require_once "connect.php"; // provides $database (mysqli link)

// --- Security: Admins only ---
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
  header("Location: login.php");
  exit();
}

// Small HTML-escape helper
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }

// Fetch logs from DB (newest first)
$sql = "SELECT `user`, `action`, `timestamp` FROM logs ORDER BY `timestamp` DESC";
$result = mysqli_query($database, $sql);

$logs = [];
if ($result) {
  while ($row = mysqli_fetch_assoc($result)) {
    $logs[] = $row;
  }
}
$total = count($logs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Activity Logs</title>
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
    .th-25 { width: 25%; }
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

    .muted { color: #6b7280; }
  </style>
</head>
<body>

<?php include 'admin_nav.php'; ?>

<main class="shell">
  <!-- Title / Summary -->
  <div class="page-title-bar">
    <h2 class="page-title">User Activity Logs</h2>
    <p class="page-subtitle">Review recent actions performed by admins, librarians, and members.</p>
  </div>

  <?php if ($total === 0): ?>
    <!-- Empty state card -->
    <section class="card mt-lg center">
      <div class="card-body">
        <p class="muted">No activity has been recorded yet.</p>
      </div>
    </section>
  <?php else: ?>
    <!-- Logs table card -->
    <section class="card mt-lg">
      <div class="card-header section-header">
        <h3 class="card-title section-title">All Events</h3>
        <span class="badge soft"><?= (int)$total ?> entries</span>
      </div>

      <div class="card-body table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th class="th-25">User</th>
              <th>Action</th>
              <th class="th-20">Time</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $row): ?>
              <tr>
                <td><?= h($row['user']) ?></td>
                <td><?= h($row['action']) ?></td>
                <td><span class="mono"><?= h($row['timestamp']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>
</main>
</body>
</html>
