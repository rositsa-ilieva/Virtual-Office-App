<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }
$activePage = 'events';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Upcoming Events</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  <style>body { background: #f7f9fb; }</style>
</head>
<body>
  <button class="sidebar-toggle" onclick="document.querySelector('.sidebar').classList.toggle('show')">
    <i class="fas fa-bars"></i>
  </button>
  <?php include 'sidebar.php'; ?>
  <main class="main-content">
    <h2>Upcoming Events</h2>
    <div class="mt-4">(Upcoming queues, consultations, and project defenses will be listed here.)</div>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('click', function(e) {
      const sidebar = document.querySelector('.sidebar');
      const toggle = document.querySelector('.sidebar-toggle');
      if (window.innerWidth < 900 && sidebar.classList.contains('show') && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
        sidebar.classList.remove('show');
      }
    });
  </script>
</body>
</html> 