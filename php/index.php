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
  <style>
  /* main shell */
    .home-shell {
      max-width: 1100px;
      margin: 1.75rem auto 3rem;
      padding: 0 1rem;
    }

    /* hero */
    .hero {
      background: radial-gradient(circle at top, #dbeafe 0%, #ffffff 40%, #ffffff 100%);
      border: 1px solid #e5e7eb;
      border-radius: 1rem;
      padding: 2.5rem 2rem;
      display: grid;
      grid-template-columns: 1.1fr 0.9fr;
      gap: 1.5rem;
      align-items: center;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    }

    .hero-body h2 {
      font-size: 2.1rem;
      margin: 0.4rem 0 0.6rem;
      color: #111827;
    }

    .hero-text {
      margin: 0;
      color: #4b5563;
      max-width: 480px;
    }

    .hero-pill {
      display: inline-block;
      background: #e0ecff;
      color: #1d4ed8;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: .01em;
    }

    .hero-actions {
      margin-top: 1.2rem;
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .btn {
      border: none;
      border-radius: .6rem;
      padding: .55rem 1rem;
      cursor: pointer;
      font-weight: 600;
      font-size: .9rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: .35rem;
    }

    .btn.primary {
      background: #2563eb;
      color: #fff;
      box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
    }

    .btn.primary:hover {
      background: #1d4ed8;
    }

    .btn.ghost {
      background: #fff;
      color: #1f2937;
      border: 1px solid #e5e7eb;
    }

    .hero-illustration {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .hero-card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: .75rem;
      padding: 1rem 1.1rem;
      box-shadow: 0 6px 16px rgba(15, 23, 42, 0.03);
    }

    .hero-card-title {
      font-size: .75rem;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #6b7280;
      margin-bottom: .25rem;
    }

    .hero-card-value {
      font-weight: 600;
      color: #111827;
    }

    .hero-card-value.small {
      font-size: .8rem;
    }

    /* quick grid */
    .quick-grid {
      margin-top: 2.2rem;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
      gap: 1rem;
    }

    .quick-card {
      background: #fff;
      border: 1px solid #edf2f7;
      border-radius: .75rem;
      padding: 1.15rem 1.1rem 1.05rem;
      box-shadow: 0 4px 14px rgba(15,23,42,.02);
    }

    .quick-card h3 {
      margin-top: 0;
      margin-bottom: .35rem;
      font-size: 1rem;
      color: #111827;
    }

    .quick-card p {
      margin-top: 0;
      margin-bottom: .5rem;
      color: #6b7280;
      font-size: .9rem;
    }

    .quick-card a {
      font-weight: 600;
      color: #2563eb;
      text-decoration: none;
      font-size: .85rem;
    }

    .quick-card a:hover {
      text-decoration: underline;
    }

    /* small screens */
    @media (max-width: 768px) {
      .hero {
        grid-template-columns: 1fr;
      }
      .topbar-inner {
        flex-direction: column;
        align-items: flex-start;
      }
      .nav-links {
        flex-wrap: wrap;
      }
    }
  </style>
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