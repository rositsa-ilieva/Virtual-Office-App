<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'db.php';
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'student';

// Fetch user info if needed
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Set active page for sidebar
$activePage = 'dashboard';

// Sidebar include logic
function renderSidebar($role, $activePage) {
    ?>
    <nav id="sidebar" class="sidebar bg-white shadow-sm">
        <div class="sidebar-header p-3 border-bottom">
            <div class="fw-bold mb-1"><i class="fa fa-user-circle me-2"></i><?php echo htmlspecialchars($_SESSION['user_role']); ?></div>
            <div class="small text-muted"><?php echo htmlspecialchars($GLOBALS['user']['email']); ?></div>
        </div>
        <ul class="nav flex-column mt-3">
            <li class="nav-item">
                <a class="nav-link <?php if($activePage=='dashboard') echo 'active'; ?>" href="index.php"><i class="fa fa-home me-2"></i>Dashboard</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php if($activePage=='profile') echo 'active'; ?>" href="profile.php"><i class="fa fa-user me-2"></i>Profile/Settings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php if($activePage=='queue-schedule') echo 'active'; ?>" href="queue-schedule.php"><i class="fa fa-calendar-alt me-2"></i>Upcoming Events</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php if($activePage=='notifications') echo 'active'; ?>" href="notifications.php"><i class="fa fa-bell me-2"></i>Notifications</a>
            </li>
            <?php if ($role === 'student'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='my-queues') echo 'active'; ?>" href="my-queues.php"><i class="fa fa-list me-2"></i>My Queues</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='history') echo 'active'; ?>" href="history.php"><i class="fa fa-history me-2"></i>History</a>
                </li>
            <?php elseif ($role === 'teacher'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='create-room') echo 'active'; ?>" href="create-room.php"><i class="fa fa-plus me-2"></i>Create Room</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='manage-queues') echo 'active'; ?>" href="manage-queues.php"><i class="fa fa-tasks me-2"></i>Manage Queues</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='stats') echo 'active'; ?>" href="stats.php"><i class="fa fa-chart-bar me-2"></i>Stats</a>
                </li>
            <?php endif; ?>
            <?php if ($role === 'admin'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='admin') echo 'active'; ?>" href="admin.php"><i class="fa fa-cog me-2"></i>Admin Panel</a>
                </li>
            <?php endif; ?>
            <li class="nav-item mt-3">
                <a class="nav-link text-danger" href="logout.php"><i class="fa fa-sign-out-alt me-2"></i>Logout</a>
            </li>
        </ul>
    </nav>
    <?php
}

// Get the selected filter from URL parameter, default to 'upcoming'
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';

// Base SQL query
$base_sql = "SELECT q.*, 
            qe.status as user_status,
            qe.position,
            qe.estimated_start_time,
            qe.started_at,
            qe.ended_at,
            qe.comment,
            qe.is_comment_public,
            qe.student_id,
            q.teacher_id,
            (SELECT name FROM users WHERE id = q.teacher_id) as teacher_name,
            (SELECT COUNT(*) FROM queue_entries WHERE queue_id = q.id AND status = 'waiting') as waiting_count,
            (SELECT COUNT(*) FROM queue_entries WHERE queue_id = q.id AND status = 'in_meeting') as in_meeting_count
            FROM queues q 
            LEFT JOIN queue_entries qe ON q.id = qe.queue_id AND qe.student_id = ?";

// Add filter conditions
switch ($filter) {
    case 'past':
        // Only show queues where the student's entry is 'done' or 'skipped'
        $sql = $base_sql . " WHERE (qe.status IN ('done', 'skipped')) ORDER BY q.created_at DESC";
        break;
    case 'group':
        $sql = $base_sql . " WHERE q.is_active = 1 AND q.meeting_type IN ('group', 'conference', 'workshop') AND (qe.status IS NULL OR qe.status IN ('waiting', 'in_meeting')) ORDER BY q.created_at DESC";
        break;
    case 'upcoming':
    default:
        // Only show queues where the student has not joined or is still waiting/in_meeting
        $sql = $base_sql . " WHERE q.is_active = 1 AND (qe.status IS NULL OR qe.status IN ('waiting', 'in_meeting')) ORDER BY q.created_at DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$queues = $stmt->fetchAll();

// After fetching $queues, calculate estimated start time for the current user if in a queue
foreach ($queues as &$queue) {
    if (isset($queue['position']) && $queue['position'] && $queue['user_status'] === 'waiting') {
        // Get queue start time and default duration
        $meeting_duration = $queue['default_duration'] ?? 15;
        $base_time = null;
        $base_position = 1;
        // If a student is in_meeting, use their started_at and position as base
        $stmt = $pdo->prepare("SELECT started_at, position FROM queue_entries WHERE queue_id = ? AND status = 'in_meeting' ORDER BY started_at DESC LIMIT 1");
        $stmt->execute([$queue['id']]);
        $last_meeting = $stmt->fetch();
        if ($last_meeting && $last_meeting['started_at']) {
            $base_time = new DateTime($last_meeting['started_at']);
            $base_position = $last_meeting['position'];
        } elseif (!empty($queue['start_time'])) {
            $base_time = new DateTime($queue['start_time']);
            $base_position = 1;
        } else {
            $base_time = new DateTime();
            $base_position = 1;
        }
        $estimated_time = clone $base_time;
        $offset = ($queue['position'] - $base_position) * $meeting_duration;
        if ($offset > 0) {
            $estimated_time->add(new DateInterval('PT' . $offset . 'M'));
        }
        $queue['estimated_start_time'] = $estimated_time->format('Y-m-d H:i:s');
    }
}
unset($queue);

// Fetch notifications for the logged-in user
$notifications = [];
try {
    $stmt = $pdo->prepare('
        SELECT n.*, u.name as from_user_name, q.purpose as queue_purpose, qe1.position as from_position, qe2.position as to_position
        FROM notifications n
        JOIN users u ON n.related_user_id = u.id
        JOIN queues q ON n.related_queue_id = q.id
        LEFT JOIN queue_entries qe1 ON qe1.queue_id = n.related_queue_id AND qe1.student_id = n.related_user_id
        LEFT JOIN queue_entries qe2 ON qe2.queue_id = n.related_queue_id AND qe2.student_id = n.user_id
        WHERE n.user_id = ? AND n.is_read = FALSE
        ORDER BY n.created_at DESC
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    // If table doesn't exist, notifications will be empty
    if ($e->getCode() != '42S02') {
        throw $e;
    }
}
$notif_count = count($notifications);

$success = '';
if (isset($_GET['message']) && $_GET['message'] === 'joined') {
    $success = "You've successfully joined the queue.";
}

// Add handler for 'Mark all as read'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE');
    $stmt->execute([$_SESSION['user_id']]);
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Virtual Office Queue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="style.css?v=2" rel="stylesheet">
    <style>
        body { background: #f7f9fb; }
        .sidebar { width: 260px; min-height: 100vh; position: fixed; }
        .main-content { margin-left: 260px; padding: 2rem; }
        @media (max-width: 991px) {
            .sidebar { position: static; width: 100%; min-height: auto; }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <?php renderSidebar($user_role, $activePage); ?>
    <div class="main-content">
        <h1 class="mb-4">Welcome, <?php echo htmlspecialchars($user['name'] ?? $user['email']); ?>!</h1>
        <?php if ($user_role === 'student'): ?>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card p-4 shadow-sm">
                        <h5>My Queues</h5>
                        <p>View and manage your current queues.</p>
                        <a href="my-queues.php" class="btn btn-primary">Go to My Queues</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card p-4 shadow-sm">
                        <h5>Upcoming Events</h5>
                        <p>See your upcoming meetings and events.</p>
                        <a href="queue-schedule.php" class="btn btn-primary">View Events</a>
                    </div>
                </div>
            </div>
        <?php elseif ($user_role === 'teacher'): ?>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card p-4 shadow-sm">
                        <h5>Create Room</h5>
                        <p>Set up a new meeting room for your students.</p>
                        <a href="create-room.php" class="btn btn-primary">Create Room</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card p-4 shadow-sm">
                        <h5>Manage Queues</h5>
                        <p>View and manage all queues you oversee.</p>
                        <a href="manage-queues.php" class="btn btn-primary">Manage Queues</a>
                    </div>
                </div>
            </div>
        <?php elseif ($user_role === 'admin'): ?>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card p-4 shadow-sm">
                        <h5>Admin Panel</h5>
                        <p>Manage users, queues, and system settings.</p>
                        <a href="admin.php" class="btn btn-primary">Go to Admin Panel</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>