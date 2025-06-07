<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get all meetings
$meetings_sql = "SELECT qe.*, q.purpose, q.meeting_link, q.access_code, q.meeting_type, 
                        u.name as teacher_name,
                        TIMESTAMPDIFF(MINUTE, qe.started_at, qe.ended_at) as duration
                 FROM queue_entries qe
                 JOIN queues q ON qe.queue_id = q.id
                 JOIN users u ON q.teacher_id = u.id
                 WHERE qe.student_id = ? 
                 ORDER BY 
                    CASE 
                        WHEN qe.status IN ('waiting', 'in_meeting') THEN 1
                        ELSE 2
                    END,
                    qe.estimated_start_time ASC,
                    qe.ended_at DESC
                 LIMIT 50"; // Show last 50 meetings
$meetings_stmt = $pdo->prepare($meetings_sql);
$meetings_stmt->execute([$user_id]);
$meetings = $meetings_stmt->fetchAll();

// Separate active and previous meetings
$active_meetings = array_filter($meetings, function($meeting) {
    return in_array($meeting['status'], ['waiting', 'in_meeting']);
});

$previous_meetings = array_filter($meetings, function($meeting) {
    return in_array($meeting['status'], ['done', 'skipped']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Meetings - Virtual Office Queue</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <h1>My Meetings</h1>
            <div class="nav-links">
                <a href="index.php" class="btn btn-secondary">Dashboard</a>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </nav>

        <div class="meetings-section">
            <h2>All Meetings</h2>
            <?php if (empty($meetings)): ?>
                <p>You don't have any meetings.</p>
            <?php else: ?>
                <div class="meetings-grid">
                    <?php foreach ($meetings as $meeting): ?>
                        <div class="meeting-card <?php echo in_array($meeting['status'], ['waiting', 'in_meeting']) ? 'active-meeting' : 'previous-meeting'; ?>">
                            <div class="meeting-header">
                                <h3><?php echo htmlspecialchars($meeting['purpose']); ?></h3>
                                <span class="status-badge status-<?php echo $meeting['status']; ?>">
                                    <?php echo ucfirst($meeting['status']); ?>
                                </span>
                            </div>
                            <div class="meeting-details">
                                <p><strong>Teacher:</strong> <?php echo htmlspecialchars($meeting['teacher_name']); ?></p>
                                <p><strong>Meeting Type:</strong> <?php echo htmlspecialchars($meeting['meeting_type']); ?></p>
                                
                                <?php if (in_array($meeting['status'], ['waiting', 'in_meeting'])): ?>
                                    <?php if ($meeting['estimated_start_time']): ?>
                                        <p><strong>Estimated Start:</strong> <?php echo date('g:i A', strtotime($meeting['estimated_start_time'])); ?></p>
                                    <?php endif; ?>
                                    <?php if ($meeting['meeting_link']): ?>
                                        <p><strong>Meeting Link:</strong> <a href="<?php echo htmlspecialchars($meeting['meeting_link']); ?>" target="_blank">Join Meeting</a></p>
                                    <?php endif; ?>
                                    <?php if ($meeting['access_code']): ?>
                                        <p><strong>Access Code:</strong> <?php echo htmlspecialchars($meeting['access_code']); ?></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if ($meeting['started_at']): ?>
                                        <p><strong>Started:</strong> <?php echo date('g:i A', strtotime($meeting['started_at'])); ?></p>
                                    <?php endif; ?>
                                    <?php if ($meeting['ended_at']): ?>
                                        <p><strong>Ended:</strong> <?php echo date('g:i A', strtotime($meeting['ended_at'])); ?></p>
                                    <?php endif; ?>
                                    <?php if ($meeting['duration']): ?>
                                        <p><strong>Duration:</strong> <?php echo $meeting['duration']; ?> minutes</p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($meeting['status'] === 'waiting'): ?>
                                <div class="meeting-actions">
                                    <a href="leave.php?id=<?php echo $meeting['queue_id']; ?>" class="btn btn-danger">Leave Queue</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 