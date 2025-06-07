<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db.php';
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;
$user = null;
if ($user_id) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}
$activePage = $activePage ?? '';
?>
<nav class="sidebar d-flex flex-column" style="background:#192132;min-width:260px;max-width:260px;">
  <div class="sidebar-header" style="font-size:2rem;font-weight:800;letter-spacing:0.5px;padding:2rem 1.5rem 1.2rem 1.5rem;background:#192132;color:#fff;border-bottom:1px solid #232b3e;">
    <i class="fas fa-user-graduate me-2"></i> Virtual Office
    <div class="small mt-2" style="font-size:1rem;font-weight:400;">
      <?php echo htmlspecialchars($user['name'] ?? $user['email'] ?? ''); ?>
      <br><span style="font-size:0.9em;color:#b0b8c1;">(<?php echo htmlspecialchars($user_role); ?>)</span>
    </div>
  </div>
  <ul class="nav flex-column p-3" style="flex:1 1 auto;">
    <li class="nav-item mb-2">
      <a class="nav-link d-flex align-items-center<?php if($activePage=='profile')echo' active'; ?>" href="profile.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-user-cog me-2"></i> Profile / Settings</a>
    </li>
    <li class="nav-item mb-2">
      <a class="nav-link d-flex align-items-center<?php if($activePage=='queue-schedule')echo' active'; ?>" href="queue-schedule.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-calendar-alt me-2"></i> Upcoming Events</a>
    </li>
    <li class="nav-item mb-2">
      <a class="nav-link d-flex align-items-center<?php if($activePage=='notifications')echo' active'; ?>" href="notifications.php" style="font-size:1.15rem;padding:12px 18px;position:relative;">
        <i class="fas fa-bell me-2"></i> Notifications
      </a>
    </li>
    <?php if($user_role === 'student'): ?>
    <li class="nav-item mb-2">
      <a class="nav-link d-flex align-items-center<?php if($activePage=='my-queues')echo' active'; ?>" href="my-queues.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-list me-2"></i> My Queues</a>
    </li>
    <li class="nav-item mb-2">
      <a class="nav-link d-flex align-items-center<?php if($activePage=='history')echo' active'; ?>" href="history.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-history me-2"></i> History</a>
    </li>
    <li class="nav-item mb-2">
      <a class="nav-link d-flex align-items-center<?php if($activePage=='past-events')echo' active'; ?>" href="past-events.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-clock me-2"></i> Past Events</a>
    </li>
    <?php endif; ?>
    <?php if($user_role === 'teacher'): ?>
    <li class="nav-item mb-2">
      <a class="nav-link d-flex align-items-center<?php if($activePage=='create-room')echo' active'; ?>" href="create-room.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-plus me-2"></i> Create Meeting / Room</a>
    </li>
    <li class="nav-item mb-2">
      <a class="nav-link d-flex align-items-center<?php if($activePage=='manage-queues')echo' active'; ?>" href="manage-queues.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-tasks me-2"></i> Manage Queues</a>
    </li>
    <li class="nav-item mb-2">
      <a class="nav-link d-flex align-items-center<?php if($activePage=='stats')echo' active'; ?>" href="stats.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-chart-bar me-2"></i> Stats</a>
    </li>
    <?php endif; ?>
    <?php if($user_role === 'admin'): ?>
    <li class="nav-item mb-2">
      <a class="nav-link d-flex align-items-center<?php if($activePage=='admin')echo' active'; ?>" href="admin.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-tools me-2"></i> Admin Panel</a>
    </li>
    <?php endif; ?>
  </ul>
  <div class="sidebar-footer p-3" style="border-top:1px solid #232b3e;background:#192132;">
    <a href="logout.php" class="btn btn-danger w-100" style="font-size:1.1rem;font-weight:600;">Log out</a>
  </div>
</nav>
<style>
.sidebar .nav-link {
  color: #fff;
  background: none;
  border-radius: 12px;
  margin-bottom: 6px;
  font-weight: 500;
  transition: background 0.2s, color 0.2s;
}
.sidebar .nav-link.active, .sidebar .nav-link:hover {
  background: #2563eb;
  color: #fff;
}
.sidebar .nav-link .badge {
  margin-left: auto;
}
.sidebar .sidebar-header {
  font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
}
</style> 