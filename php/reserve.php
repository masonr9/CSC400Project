<?php
session_start(); // starts or resumes the session so we can access $_SESSION
include "connect.php"; // this is where $database comes from

if (!isset($_SESSION['user_id'])) { // if the user is not logged in
  header("Location: login.php"); // redirects to the login page
  exit(); // stops the rest of the script from executing
}

$userId = (int) $_SESSION['user_id']; // gets the current user id from session
$bookId = isset($_POST['book_id']) ? (int) $_POST['book_id'] : 0; // book id from POST; casts to int, default is 0 in case of missing

// basic validation
if ($bookId <= 0) { // so if the book is either invalid or missing
  $_SESSION['flash_msg'] = "Invalid book selection."; // sets an error message
  $_SESSION['flash_color'] = "red";
  header("Location: catalog.php"); // redirects back to the catalog page
  exit(); // stop executing
}

// ensure the book exists
$stmt = mysqli_prepare($database, "SELECT book_id FROM books WHERE book_id = ? LIMIT 1"); // prepares a lookup query
mysqli_stmt_bind_param($stmt, "i", $bookId); // binds the book id as an integer
mysqli_stmt_execute($stmt); // execute the query
$res = mysqli_stmt_get_result($stmt); // get the result set
$book = mysqli_fetch_assoc($res); // fetch a single row as an associative array
mysqli_stmt_close($stmt); // close the statement

// in the case if no matching book was found
if (!$book) {
  $_SESSION['flash_msg'] = "Book not found."; // set an error flash message
  $_SESSION['flash_color'] = "red";
  header("Location: catalog.php"); // redirects to the catalog page
  exit(); // stops executing
}

// Prevent duplicate active reservations by the same user for this book
$stmt = mysqli_prepare( // prepares a query checking for existing active reservations
  $database,
  "SELECT reservation_id
   FROM reservations
   WHERE user_id = ? AND book_id = ? AND status IN ('Pending','Approved')
   LIMIT 1"
);
mysqli_stmt_bind_param($stmt, "ii", $userId, $bookId); // binds the user id and book id as integers
mysqli_stmt_execute($stmt); // execute the query
mysqli_stmt_store_result($stmt); // buffers the results so it can count the rows

if (mysqli_stmt_num_rows($stmt) > 0) { // if at least one active reservation already exists
  mysqli_stmt_close($stmt); // it will close the statement
  $_SESSION['flash_msg'] = "You already have an active reservation for this book."; // sets the error flash message
  $_SESSION['flash_color'] = "red";
  header("Location: book.php?id=" . $bookId); // redirects back to the book detail page
  exit(); // stops executing
}
mysqli_stmt_close($stmt);

// Create the reservation
$stmt = mysqli_prepare( // prepares an insert statement for a new reservation
  $database,
  "INSERT INTO reservations (user_id, book_id, reservation_date, status)
   VALUES (?, ?, CURDATE(), 'Pending')"
);
mysqli_stmt_bind_param($stmt, "ii", $userId, $bookId); // binds the user id and book id as integers
if (mysqli_stmt_execute($stmt)) {  // executes the insert statement if it succeeds
  $_SESSION['flash_msg'] = "Reservation placed successfully!";
  $_SESSION['flash_color'] = "green";
} else { // in the case that the insert statement failed
  $_SESSION['flash_msg'] = "Could not place reservation. Please try again.";
  $_SESSION['flash_color'] = "red";
}
mysqli_stmt_close($stmt); // closes the insert statement

// redirect back to the book detail
header("Location: book.php?id=" . $bookId);
exit();
