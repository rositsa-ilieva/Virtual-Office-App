<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config.php';
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
<nav class="sidebar d-flex flex-column" style="background:#192132;min-width:240px;max-width:260px;box-shadow:2px 0 16px rgba(40,83,107,0.07);">
  <div class="sidebar-header" style="font-size:1.7rem;font-weight:800;letter-spacing:0.5px;padding:2rem 1.5rem 1.2rem 1.5rem;background:#192132;color:#fff;border-bottom:1px solid #232b3e;text-align:left;">
    <i class="fas fa-user-graduate me-2"></i> Virtual Office
    <div class="small mt-2" style="font-size:1rem;font-weight:400;">
      <?php echo htmlspecialchars($user['name'] ?? $user['email'] ?? ''); ?>
      <br><span style="font-size:0.9em;color:#b0b8c1;">(<?php echo htmlspecialchars($user_role); ?>)</span>
    </div>
  </div>
  <ul class="nav flex-column p-3" style="flex:1 1 auto;">
    <?php if($user_role === 'student'): ?>
      <li class="nav-item mb-2">
        <a class="nav-link d-flex align-items-center<?php if($activePage=='dashboard')echo' active'; ?>" href="index.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-home me-2"></i> Dashboard</a>
      </li>
      <li class="nav-item mb-2">
        <a class="nav-link d-flex align-items-center<?php if($activePage=='notifications')echo' active'; ?>" href="notifications.php" style="font-size:1.15rem;padding:12px 18px;position:relative;"><i class="fas fa-bell me-2"></i> Notifications</a>
      </li>
      <li class="nav-item mb-2">
        <a class="nav-link d-flex align-items-center<?php if($activePage=='queue-schedule')echo' active'; ?>" href="queue-schedule.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-calendar-alt me-2"></i> Upcoming Meetings</a>
      </li>
      <li class="nav-item mb-2">
        <a class="nav-link d-flex align-items-center<?php if($activePage=='my-queues')echo' active'; ?>" href="my-queues.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-list me-2"></i> My Queues</a>
      </li>
      <li class="nav-item mb-2">
        <a class="nav-link d-flex align-items-center<?php if($activePage=='history')echo' active'; ?>" href="history.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-history me-2"></i> Past Meetings</a>
      </li>
      <li class="nav-item mb-2">
        <a class="nav-link d-flex align-items-center<?php if($activePage=='profile')echo' active'; ?>" href="profile.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-user-cog me-2"></i> Profile Settings</a>
      </li>
      <li class="nav-item mt-3">
        <a class="nav-link text-danger" href="logout.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fa fa-sign-out-alt me-2"></i> Logout</a>
      </li>
    <?php elseif($user_role === 'teacher'): ?>
      <li class="nav-item mb-2">
        <a class="nav-link d-flex align-items-center<?php if($activePage=='dashboard')echo' active'; ?>" href="index.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-home me-2"></i> Dashboard</a>
      </li>
      <li class="nav-item mb-2">
        <a class="nav-link d-flex align-items-center<?php if($activePage=='notifications')echo' active'; ?>" href="notifications.php" style="font-size:1.15rem;padding:12px 18px;position:relative;"><i class="fas fa-bell me-2"></i> Notifications</a>
      </li>
      <li class="nav-item mb-2">
        <a class="nav-link d-flex align-items-center<?php if($activePage=='queue-schedule')echo' active'; ?>" href="queue-schedule.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-calendar-alt me-2"></i> Upcoming Meetings</a>
      </li>
      <li class="nav-item mb-2">
        <a class="nav-link d-flex align-items-center<?php if($activePage=='create-room')echo' active'; ?>" href="create-room.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-plus me-2"></i> Create Room</a>
      </li>
      <li class="nav-item mb-2">
        <a class="nav-link d-flex align-items-center<?php if($activePage=='manage-queues')echo' active'; ?>" href="manage-queues.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-tasks me-2"></i> Manage Queues</a>
      </li>
      <li class="nav-item mb-2">
        <a class="nav-link d-flex align-items-center<?php if($activePage=='stats')echo' active'; ?>" href="stats.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-chart-bar me-2"></i> Statistics</a>
      </li>
      <li class="nav-item mb-2">
        <a class="nav-link d-flex align-items-center<?php if($activePage=='profile')echo' active'; ?>" href="profile.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fas fa-user-cog me-2"></i> Profile Settings</a>
      </li>
      <li class="nav-item mt-3">
        <a class="nav-link text-danger" href="logout.php" style="font-size:1.15rem;padding:12px 18px;"><i class="fa fa-sign-out-alt me-2"></i> Logout</a>
      </li>
    <?php endif; ?>
  </ul>
</nav>
<style>
.sidebar {
  background: #192132;
  min-width: 240px;
  max-width: 260px;
  display: flex;
  flex-direction: column;
  height: 100vh;
  box-shadow: 2px 0 16px rgba(40,83,107,0.07);
}
.sidebar-header {
  font-size: 1.7rem;
  font-weight: 800;
  letter-spacing: 0.5px;
  padding: 2rem 1.5rem 1.2rem 1.5rem;
  background: #192132;
  color: #fff;
  border-bottom: 1px solid #232b3e;
  text-align: left;
}
.sidebar .nav {
  flex: 1 1 auto;
  padding: 0 0 24px 0;
}
.sidebar .nav-item {
  margin-bottom: 14px;
}
.sidebar .nav-item:last-child {
  margin-bottom: 0;
}
.sidebar .nav-link {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 1.07rem;
  padding: 12px 18px 12px 24px;
  color: #fff;
  background: none;
  border-radius: 12px;
  font-weight: 500;
  transition: background 0.2s, color 0.2s;
  margin-bottom: 0;
  position: relative;
  text-align: left;
}
.sidebar .nav-link .fa, .sidebar .nav-link .fas {
  font-size: 1.18em;
  min-width: 22px;
  text-align: left;
}
.sidebar .nav-link.active, .sidebar .nav-link:hover {
  background: #2563eb;
  color: #fff;
  font-weight: 700;
}
.sidebar .nav-link.active {
  box-shadow: 0 2px 12px rgba(37,99,235,0.08);
}
.sidebar .nav-link.text-danger {
  color: #ef4444 !important;
  background: none;
}
.sidebar .nav-link.text-danger:hover {
  background: #f87171 !important;
  color: #fff !important;
}
.sidebar .sidebar-header .small {
  font-size: 1rem;
  font-weight: 400;
  color: #b0b8c1;
  margin-top: 0.5rem;
}
</style> 