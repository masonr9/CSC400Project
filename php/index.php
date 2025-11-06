<?php
include "maintenance.php"

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Library Management System - Home</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  
<?php include 'nav.php'; ?>

  <main class="home-shell">
    <section class="hero">
      <div class="hero-body">
        <p class="hero-pill">📚 Library Management System</p>
        <h2>Welcome to the digital library</h2>
        <p class="hero-text">
          Search the catalog, check your loans, and stay updated with announcements - all in one place.
        </p>
        <div class="hero-actions">
          <a href="catalog.php" class="btn primary">Browse Catalog</a>
          <a href="anounce.php" class="btn ghost">View Announcements</a>
        </div>
      </div>
        <div class="hero-card">
          <p class="hero-card-title">Tips</p>
          <p class="hero-card-value small">Return on time to avoid fines.</p>
        </div>
      </div>
    </section>

    <!-- quick links -->
    <section class="quick-grid">
      <article class="quick-card">
        <h3>Search the Catalog</h3>
        <p>Find books by title, author, or ISBN.</p>
        <a href="catalog.php">Go to catalog -></a>
      </article>
      <article class="quick-card">
        <h3>Announcements</h3>
        <p>See what's new in the library.</p>
        <a href="anounce.php">View announcements -></a>
      </article>
      <article class="quick-card">
        <h3>Contact Us</h3>
        <p>Need help? Send us a message.</p>
        <a href="contact.php">Contact -></a>
      </article>
    </section>
  </main>
</body>
</html>