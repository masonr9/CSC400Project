<?php
session_start(); // start or resume the session so we can manipulate session data
$_SESSION = []; // clear all session variables in memory so you log the user out
if (ini_get("session.use_cookies")) { // if PHP is configured to use a session cookie
  $params = session_get_cookie_params(); // get the current cookie parameters such as path or domain
  setcookie(session_name(), '', time() - 42000, // overwrite the session cookie to force it to expire
    $params["path"], $params["domain"], // use the same path and domain
    $params["secure"], $params["httponly"] // secure flag and HttpOnly flag as the original cookie
  );
}
session_destroy(); // Destroy the session data on the server
header("Location: index.php"); // send user back to home (or login.php)
exit();