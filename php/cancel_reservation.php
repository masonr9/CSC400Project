<?php
session_start(); // starts or resumes the session so we can read or write $_SESSION values
include "connect.php"; // this is where $database comes from

// if the user is not logged in, it will block access and send them to the login page
if (!isset($_SESSION['user_id'])) { // checks if a user_id exists in the session
  header("Location: login.php"); // redirects you to login page
  exit(); // stops executing the script after redirect
}

$userId = (int) $_SESSION['user_id']; // gets the current user's id from the session and casts to int for safety
// reads reservation_id from POST if its present, casts to int to prevent injection or invalid types, if not present, it defaults to 0 meaning invalid
$resId  = isset($_POST['reservation_id']) ? (int) $_POST['reservation_id'] : 0;

// validate the incoming reservation id
if ($resId <= 0) { // if the id is missing or invalid
  $_SESSION['flash_msg'] = "Invalid reservation."; // stores an error message to show on the next page
  $_SESSION['flash_color'] = "red";
  header("Location: reservations.php"); // send the user back to the reservations page
  exit();
}

// it will only allow deleting user's own reservation when it's still pending
$stmt = mysqli_prepare( // prepares a delete statement with parameters
  $database,
  "DELETE FROM reservations
   WHERE reservation_id = ? AND user_id = ? AND status = 'Pending'" // it only deletes if it belongs to this user and has a status of pending
);
mysqli_stmt_bind_param($stmt, "ii", $resId, $userId); // binds the reservation id and user id as integers
mysqli_stmt_execute($stmt); // executes the delete query

if (mysqli_stmt_affected_rows($stmt) > 0) { // if at least one row was deleted meaning it was successful
  $_SESSION['flash_msg'] = "Reservation cancelled."; // success message
  $_SESSION['flash_color'] = "green";
} else {
  // this means either not found, not owned by user, or not pending
  $_SESSION['flash_msg'] = "Unable to cancel this reservation."; // error message to be displayed
  $_SESSION['flash_color'] = "red";
}
mysqli_stmt_close($stmt); // closes the prepared statement 

header("Location: reservations.php"); // redirects back to the reservations list to show the result
exit();
