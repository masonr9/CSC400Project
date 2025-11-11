<?php
include "connect.php";

$book_id = isset($_GET['book_id']) ? (int)$_GET['book_id'] : 0;
if ($book_id <= 0) exit("Invalid book.");

$stmt = $database->prepare("SELECT rating, review_text, created_at FROM reviews WHERE book_id=? ORDER BY created_at DESC");
$stmt->bind_param("i", $book_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    echo "<div class='review-box'>";
    echo "⭐ " . htmlspecialchars($row['rating']) . "/5<br>";
    echo nl2br(htmlspecialchars($row['review_text'])) . "<br>";
    echo "<small><em>" . $row['created_at'] . "</em></small>";
    echo "</div>";
}
$stmt->close();
?>
