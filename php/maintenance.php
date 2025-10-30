<?php
// Check maintenance status
$config_file = __DIR__ . "/maintenance_status.txt";
$current_status = (file_exists($config_file) ? trim((string)file_get_contents($config_file)) : "off");

// If maintenance is ON, serve a 503 page (no redirect so no loops)
if (strcasecmp($current_status, "on") === 0) {
  // Tell browsers/search engines this is temporary
  header("HTTP/1.1 503 Service Unavailable");
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

<main style="text-align:center; padding:50px;">
  <h2>🚧 System Under Maintenance</h2>
  <p>The Library System is currently undergoing maintenance.</p>
  <p>Please check back later. Thank you for your patience!</p>
</main>

</body>
</html>
<?php
 exit();
}
?>