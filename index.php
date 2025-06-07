<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// If user not found, log them out and redirect to login
if (!$user) {
    session_destroy();
    header('Location: login.php?error=user_not_found');
    exit();
}

// Get the selected filter from URL parameter, default to 'upcoming'
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';

// Base SQL query
$base_sql = "SELECT q.*, 
            qe.status as user_status,
            qe.position,
            qe.estimated_start_time,
            (SELECT COUNT(*) FROM queue_entries WHERE queue_id = q.id AND status = 'waiting') as waiting_count,
            (SELECT COUNT(*) FROM queue_entries WHERE queue_id = q.id AND status = 'in_meeting') as in_meeting_count
            FROM queues q 
            LEFT JOIN queue_entries qe ON q.id = qe.queue_id AND qe.student_id = ?";

// Add filter conditions
switch ($filter) {
    case 'past':
        $sql = $base_sql . " WHERE q.is_active = 0 ORDER BY q.created_at DESC";
        break;
    case 'group':
        $sql = $base_sql . " WHERE q.is_active = 1 AND q.meeting_type IN ('group', 'conference', 'workshop') ORDER BY q.created_at DESC";
        break;
    case 'upcoming':
    default:
        $sql = $base_sql . " WHERE q.is_active = 1 ORDER BY q.created_at DESC";
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Virtual Office Queue</title>
    <link rel="stylesheet" href="style.css?v=2024.1">
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <h1>Welcome, <?php echo htmlspecialchars($user['name'] ?? 'User'); ?></h1>
            <div class="nav-links">
                <?php if ($user['role'] === 'teacher'): ?>
                    <a href="create-room.php" class="btn btn-primary">Create New Queue</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </nav>

        <div class="dashboard">
            <div class="dashboard-header">
                <h2><?php echo $user['role'] === 'teacher' ? 'Your Queues' : 'Available Queues'; ?></h2>
                <div class="queue-filters">
                    <a href="?filter=upcoming" class="filter-btn <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        Upcoming Meetings
                    </a>
                    <a href="?filter=past" class="filter-btn <?php echo $filter === 'past' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i>
                        Past Meetings
                    </a>
                    <a href="?filter=group" class="filter-btn <?php echo $filter === 'group' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        Group Meetings
                    </a>
                </div>
            </div>
            
            <?php if (empty($queues)): ?>
                <div class="no-queues">
                    <i class="fas fa-inbox"></i>
                    <p>No <?php echo $filter; ?> queues available.</p>
                </div>
            <?php else: ?>
                <div class="queue-grid">
                    <?php foreach ($queues as $queue): ?>
                        <div class="queue-card">
                            <?php if (isset($queue['position']) && $queue['position']): ?>
                                <div class="queue-position-badge"><?php echo htmlspecialchars($queue['position']); ?></div>
                            <?php endif; ?>
                            <div class="queue-info-group">
                                <div class="queue-title"><?php echo htmlspecialchars($queue['purpose'] ?? 'Untitled Queue'); ?></div>
                                <?php if (!empty($queue['description'])): ?>
                                    <div class="queue-meta">Description: <?php echo htmlspecialchars($queue['description']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($queue['meeting_type'])): ?>
                                    <div class="queue-meta">
                                        <i class="fas fa-video"></i>
                                        <?php echo htmlspecialchars($queue['meeting_type']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($queue['wait_time_method'])): ?>
                                    <div class="queue-meta">
                                        <i class="fas fa-clock"></i>
                                        <?php echo htmlspecialchars(ucfirst($queue['wait_time_method'])); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($queue['user_status']) && $queue['user_status']): ?>
                                    <div class="status-badge status-<?php echo htmlspecialchars($queue['user_status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($queue['user_status'])); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($queue['estimated_start_time']) && $queue['estimated_start_time']): ?>
                                    <div class="queue-estimated-pill">
                                        <i class="fas fa-hourglass-half"></i>
                                        Est. <?php echo htmlspecialchars(date('g:i A', strtotime($queue['estimated_start_time']))); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($queue['meeting_link'])): ?>
                                    <div class="queue-link">
                                        <i class="fas fa-link"></i>
                                        <a href="<?php echo htmlspecialchars($queue['meeting_link']); ?>" target="_blank">
                                            <?php echo htmlspecialchars($queue['meeting_link']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($queue['access_code'])): ?>
                                    <div class="queue-access">
                                        <i class="fas fa-key"></i>
                                        Access Code: <span class="queue-access-copy" data-code="<?php echo htmlspecialchars($queue['access_code']); ?>"><?php echo htmlspecialchars($queue['access_code']); ?></span>
                                        <button type="button" class="copy-btn" onclick="copyAccessCode(this)">Copy</button>
                                    </div>
                                <?php endif; ?>
                                <?php if ($user['role'] === 'teacher'): ?>
                                    <div class="queue-stats">
                                        <span class="stat">
                                            <i class="fas fa-users"></i>
                                            <?php echo htmlspecialchars($queue['waiting_count'] ?? 0); ?> waiting
                                        </span>
                                        <span class="stat">
                                            <i class="fas fa-video"></i>
                                            <?php echo htmlspecialchars($queue['in_meeting_count'] ?? 0); ?> in meeting
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="queue-action-group">
                                <?php if ($user['role'] === 'teacher'): ?>
                                    <a href="room.php?id=<?php echo htmlspecialchars($queue['id']); ?>" class="btn btn-primary">Manage Queue</a>
                                    <a href="statistics.php?id=<?php echo htmlspecialchars($queue['id']); ?>" class="btn btn-secondary">View Statistics</a>
                                    <form method="POST" action="delete-queue.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this queue? This cannot be undone.');">
                                        <input type="hidden" name="queue_id" value="<?php echo htmlspecialchars($queue['id']); ?>">
                                        <button type="submit" class="btn btn-danger">Delete Queue</button>
                                    </form>
                                <?php else: ?>
                                    <?php if ($queue['user_status']): ?>
                                        <?php if (in_array($queue['user_status'], ['waiting', 'in_meeting'])): ?>
                                            <a href="queue-members.php?id=<?php echo htmlspecialchars($queue['id']); ?>" class="btn btn-secondary">View Queue Members</a>
                                        <?php endif; ?>
                                        <?php if ($queue['user_status'] === 'waiting'): ?>
                                            <a href="leave.php?id=<?php echo htmlspecialchars($queue['id']); ?>" class="btn btn-danger">Leave Queue</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="join.php?id=<?php echo htmlspecialchars($queue['id']); ?>" class="btn btn-primary">Join Queue</a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
    function copyAccessCode(btn) {
        var codeSpan = btn.parentElement.querySelector('.queue-access-copy');
        var code = codeSpan.getAttribute('data-code');
        navigator.clipboard.writeText(code).then(function() {
            btn.textContent = 'Copied!';
            setTimeout(function() { btn.textContent = 'Copy'; }, 1200);
        });
    }
    </script>
</body>
</html>