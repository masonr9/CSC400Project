<?php
include "connect.php"; // use your existing DB connection

$book_id = isset($_POST['book_id']) ? (int)$_POST['book_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$review_text = $_POST['review_text'] ?? '';

if ($book_id <= 0 || $rating <= 0 || empty($review_text)) {
    $_SESSION['flash_msg'] = "Invalid review submission.";
    $_SESSION['flash_color'] = "red";
    header("Location: book.php?id=".$book_id);
    exit();
}

$stmt = $database->prepare("INSERT INTO reviews (book_id, rating, review_text) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $book_id, $rating, $review_text);
$stmt->execute();
$stmt->close();

$_SESSION['flash_msg'] = "Review submitted successfully!";
$_SESSION['flash_color'] = "green";
header("Location: book.php?id=".$book_id);
exit();
?>

