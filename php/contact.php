<!DOCTYPE html> 
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Us</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>

<?php include 'nav.php'; ?>

<main>
  <form action="https://api.web3forms.com/submit" method="POST" class="contact-form-box">
    <h2>Contact Us</h2>
    <input type="hidden" name="access_key" value="28eeb1d3-21e3-4677-b86b-59f6c7009cd3">
    <label>Name:</label>
    <input type="text" name="name" placeholder="Please type your name" required>
    <label>Email:</label>
    <input type="email" name="email" placeholder="Please type your email" required>
    <label>Message:</label>
    <textarea rows="4" name="message" placeholder="Please type your message" required></textarea>
    <button type="submit">Send</button>
  </form>
</main>
</body>
</html>