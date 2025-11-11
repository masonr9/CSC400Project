<?php
include "maintenance.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Librarian Dashboard</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .dashboard-shell {
      max-width: 1100px;
      margin: 1.75rem auto 3rem;
      padding: 0 1rem;
    }

    /* hero section */
    .hero {
      background: radial-gradient(circle at top, #e0f2fe 0%, #ffffff 40%, #ffffff 100%);
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
      background: #dbeafe;
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

    /* task grid */
    .task-grid {
      margin-top: 2.2rem;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
      gap: 1rem;
    }

    .task-card {
      background: #fff;
      border: 1px solid #edf2f7;
      border-radius: .75rem;
      padding: 1.15rem 1.1rem 1.05rem;
      box-shadow: 0 4px 14px rgba(15,23,42,.02);
      transition: transform .2s;
    }

    .task-card:hover {
      transform: translateY(-3px);
    }

    .task-card h3 {
      margin-top: 0;
      margin-bottom: .35rem;
      font-size: 1rem;
      color: #111827;
    }

    .task-card p {
      margin-top: 0;
      margin-bottom: .5rem;
      color: #6b7280;
      font-size: .9rem;
    }

    .task-card a {
      font-weight: 600;
      color: #2563eb;
      text-decoration: none;
      font-size: .85rem;
    }

    .task-card a:hover {
      text-decoration: underline;
    }

    /* small screens */
    @media (max-width: 768px) {
      .hero {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>

<?php include 'nav.php'; ?>

<main class="dashboard-shell">
  <section class="hero">
    <div class="hero-body">
      <p class="hero-pill">🛠️ Librarian Dashboard</p>
      <h2>Welcome, Librarian</h2>
      <p class="hero-text">
        Manage your library efficiently — update books, oversee members, approve reservations, and track overdue returns.
      </p>
      <div class="hero-actions">
        <a href="manage_books.php" class="btn primary">Manage Books</a>
        <a href="overdue_tracking.php" class="btn ghost">Track Overdue</a>
      </div>
    </div>
    <div class="hero-card">
      <p class="hero-card-title">Tip</p>
      <p class="hero-card-value small">Review overdue books weekly to reduce fines.</p>
    </div>
  </section>

  <!-- task grid -->
  <section class="task-grid">
    <article class="task-card">
      <h3>Manage Books</h3>
      <p>Add, edit, or remove books from the catalog.</p>
      <a href="manage_books.php">Go to management →</a>
    </article>
    <article class="task-card">
      <h3>Approve Reservations</h3>
      <p>View and approve pending book reservations.</p>
      <a href="approve_reservations.php">Review requests →</a>
    </article>
    <article class="task-card">
      <h3>Overdue Tracking</h3>
      <p>Monitor overdue books and send reminders.</p>
      <a href="overdue_tracking.php">Track overdue →</a>
    </article>
    <article class="task-card">
      <h3>Member Management</h3>
      <p>Add or remove library members and update details.</p>
      <a href="member_management.php">Manage members →</a>
    </article>
  </section>
</main>

</body>
</html>
