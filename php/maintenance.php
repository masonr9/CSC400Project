<?php
// Check maintenance status
$config_file = "maintenance_status.txt";
$current_status = file_exists($config_file) ? trim(file_get_contents($config_file)) : "off";

// If not in maintenance mode, redirect to homepage
if ($current_status !== "on") {
  header("Location: index.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>System Under Maintenance</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<header>
  <h1>Library Management System</h1>
</header>

<main style="text-align:center; padding:50px;">
  <h2>🚧 System Under Maintenance</h2>
  <p>The Library System is currently undergoing maintenance.</p>
  <p>Please check back later. Thank you for your patience!</p>
</main>

</body>
</html>
