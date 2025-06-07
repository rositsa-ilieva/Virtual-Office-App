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

// Get queues based on user role
if ($user['role'] === 'teacher') {
    // For teachers, show their queues with counts
    $sql = "SELECT q.*, 
            (SELECT COUNT(*) FROM queue_entries WHERE queue_id = q.id AND status = 'waiting') as waiting_count,
            (SELECT COUNT(*) FROM queue_entries WHERE queue_id = q.id AND status = 'in_meeting') as in_meeting_count
            FROM queues q 
            WHERE q.teacher_id = ? 
            ORDER BY q.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);
} else {
    // For students, show all active queues with their status
    $sql = "SELECT q.*, 
            qe.status as user_status,
            qe.position,
            qe.estimated_start_time,
            (SELECT COUNT(*) FROM queue_entries WHERE queue_id = q.id AND status = 'waiting') as waiting_count
            FROM queues q 
            LEFT JOIN queue_entries qe ON q.id = qe.queue_id AND qe.student_id = ?
            WHERE q.is_active = 1 
            ORDER BY q.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);
}
$queues = $stmt->fetchAll();
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
            <h1>Welcome, <?php echo htmlspecialchars($user['name']); ?></h1>
            <div class="nav-links">
                <?php if ($user['role'] === 'teacher'): ?>
                    <a href="create-room.php" class="btn btn-primary">Create New Queue</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </nav>

        <div class="dashboard">
            <h2><?php echo $user['role'] === 'teacher' ? 'Your Queues' : 'Available Queues'; ?></h2>
            
            <?php if (empty($queues)): ?>
                <p class="no-queues">No queues available.</p>
            <?php else: ?>
                <div class="queue-grid">
                    <?php foreach ($queues as $queue): ?>
                        <div class="queue-card">
                            <?php if (isset($queue['position']) && $queue['position']): ?>
                                <div class="queue-position-badge"><?php echo $queue['position']; ?></div>
                            <?php endif; ?>
                            <div class="queue-info-group">
                                <div class="queue-title"><?php echo htmlspecialchars($queue['purpose']); ?></div>
                                <?php if (!empty($queue['description'])): ?>
                                    <div class="queue-meta">Description: <?php echo htmlspecialchars($queue['description']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($queue['meeting_type'])): ?>
                                    <div class="queue-meta">Meeting Type: <?php echo htmlspecialchars($queue['meeting_type']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($queue['wait_time_method'])): ?>
                                    <div class="queue-meta">Wait Time: <?php echo ucfirst($queue['wait_time_method']); ?></div>
                                <?php endif; ?>
                                <?php if (isset($queue['user_status']) && $queue['user_status']): ?>
                                    <div class="status-badge status-<?php echo $queue['user_status']; ?>">
                                        <?php echo ucfirst($queue['user_status']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (isset($queue['estimated_start_time']) && $queue['estimated_start_time']): ?>
                                    <div class="queue-estimated-pill">Est. <?php echo date('g:i A', strtotime($queue['estimated_start_time'])); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($queue['meeting_link'])): ?>
                                    <div class="queue-link">
                                        <a href="<?php echo htmlspecialchars($queue['meeting_link']); ?>" target="_blank">
                                            <?php echo htmlspecialchars($queue['meeting_link']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($queue['access_code'])): ?>
                                    <div class="queue-access">
                                        Access Code: <span class="queue-access-copy" data-code="<?php echo htmlspecialchars($queue['access_code']); ?>"><?php echo htmlspecialchars($queue['access_code']); ?></span>
                                        <button type="button" class="copy-btn" onclick="copyAccessCode(this)">Copy</button>
                                    </div>
                                <?php endif; ?>
                                <?php if ($user['role'] === 'teacher'): ?>
                                    <div class="queue-stats">
                                        <span class="stat">
                                            <i class="fas fa-users"></i>
                                            <?php echo $queue['waiting_count']; ?> waiting
                                        </span>
                                        <span class="stat">
                                            <i class="fas fa-video"></i>
                                            <?php echo $queue['in_meeting_count']; ?> in meeting
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="queue-action-group">
                                <?php if ($user['role'] === 'teacher'): ?>
                                    <a href="room.php?id=<?php echo $queue['id']; ?>" class="btn btn-primary">Manage Queue</a>
                                    <a href="statistics.php?id=<?php echo $queue['id']; ?>" class="btn btn-secondary">View Statistics</a>
                                    <form method="POST" action="delete-queue.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this queue? This cannot be undone.');">
                                        <input type="hidden" name="queue_id" value="<?php echo $queue['id']; ?>">
                                        <button type="submit" class="btn btn-danger">Delete Queue</button>
                                    </form>
                                <?php else: ?>
                                    <?php if ($queue['user_status']): ?>
                                        <?php if (in_array($queue['user_status'], ['waiting', 'in_meeting'])): ?>
                                            <a href="queue-members.php?id=<?php echo $queue['id']; ?>" class="btn btn-secondary">View Queue Members</a>
                                        <?php endif; ?>
                                        <?php if ($queue['user_status'] === 'waiting'): ?>
                                            <a href="leave.php?id=<?php echo $queue['id']; ?>" class="btn btn-danger">Leave Queue</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="join.php?id=<?php echo $queue['id']; ?>" class="btn btn-primary">Join Queue</a>
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