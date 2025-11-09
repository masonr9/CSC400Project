<?php
session_start();
require_once "connect.php";

// only admin can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
  header("Location: login.php");
  exit();
}

$config_file = "maintenance_status.txt";

// handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  $status = $_POST['maintenance'];
  file_put_contents($config_file, $status);
  $message = "Maintenance mode set to: " . strtoupper($status);
}

$current_status = file_exists($config_file) ? trim(file_get_contents($config_file)) : "off";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>System Configuration</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .config-shell {
      max-width: 700px;
      margin: 2rem auto 3rem;
      padding: 0 1rem;
    }

    /* hero */
    .hero {
      background: radial-gradient(circle at top, #fef3c7 0%, #ffffff 40%, #ffffff 100%);
      border: 1px solid #e5e7eb;
      border-radius: 1rem;
      padding: 2rem;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
      text-align: center;
    }

    .hero h2 {
      font-size: 1.8rem;
      margin-bottom: 0.5rem;
      color: #111827;
    }

    .hero-text {
      color: #4b5563;
      margin-bottom: 1.5rem;
      font-size: 1rem;
    }

    /* form */
    form {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: .75rem;
      padding: 1.5rem;
      box-shadow: 0 6px 16px rgba(15,23,42,0.03);
      text-align: center;
    }

    label {
      font-weight: 600;
      color: #111827;
      display: block;
      margin-bottom: 0.5rem;
    }

    select {
      padding: 0.5rem 0.75rem;
      border-radius: 0.5rem;
      border: 1px solid #d1d5db;
      font-size: 1rem;
      margin-bottom: 1rem;
    }

    button {
      background: #2563eb;
      color: white;
      border: none;
      border-radius: 0.5rem;
      padding: 0.55rem 1.2rem;
      font-weight: 600;
      cursor: pointer;
      font-size: .95rem;
      box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
    }

    button:hover {
      background: #1d4ed8;
    }

    .message {
      margin-top: 1rem;
      color: green;
      font-weight: 600;
    }

    .status-pill {
      display: inline-block;
      margin-top: 1rem;
      background: #e0f2fe;
      color: #0369a1;
      padding: 0.3rem 0.8rem;
      border-radius: 9999px;
      font-weight: 600;
      font-size: 0.85rem;
    }

    .status-pill.on {
      background: #fee2e2;
      color: #b91c1c;
    }

    @media (max-width: 600px) {
      .hero {
        padding: 1.5rem;
      }
    }
  </style>
</head>
<body>

<?php include 'nav.php'; ?>

<main class="config-shell">
  <section class="hero">
    <h2>System Configuration</h2>
    <p class="hero-text">Toggle maintenance mode to temporarily disable user access while performing system updates.</p>

    <?php if (isset($message)): ?>
      <p class="message"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="POST">
      <label for="maintenance">Maintenance Mode:</label>
      <select name="maintenance" id="maintenance">
        <option value="on" <?php if ($current_status == 'on') echo 'selected'; ?>>ON</option>
        <option value="off" <?php if ($current_status == 'off') echo 'selected'; ?>>OFF</option>
      </select>
      <br>
      <button type="submit">Save</button>
    </form>

    <div class="status-pill <?php if ($current_status == 'on') echo 'on'; ?>">
      Current Status: <?= strtoupper($current_status) ?>
    </div>
  </section>
</main>

</body>
</html>

