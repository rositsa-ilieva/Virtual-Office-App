<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get past meetings based on user role
if ($user_role === 'student') {
    $sql = "SELECT qe.*, q.purpose as queue_purpose, q.description, u.name as teacher_name,
            TIMESTAMPDIFF(MINUTE, qe.started_at, qe.ended_at) as duration
            FROM queue_entries qe
            JOIN queues q ON qe.queue_id = q.id
            JOIN users u ON q.teacher_id = u.id
            WHERE qe.student_id = ? AND qe.status = 'done'
            ORDER BY qe.ended_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
} else {
    $sql = "SELECT qe.*, q.purpose as queue_purpose, q.description, u.name as student_name,
            TIMESTAMPDIFF(MINUTE, qe.started_at, qe.ended_at) as duration
            FROM queue_entries qe
            JOIN queues q ON qe.queue_id = q.id
            JOIN users u ON qe.student_id = u.id
            WHERE q.teacher_id = ? AND qe.status = 'done'
            ORDER BY qe.ended_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
}

$past_meetings = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Past Meetings - Virtual Office Queue</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <h1>Past Meetings</h1>
            <div class="nav-links">
                <a href="index.php" class="btn btn-secondary">Dashboard</a>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </nav>

        <div class="meetings-container">
            <?php if (empty($past_meetings)): ?>
                <p>No past meetings found.</p>
            <?php else: ?>
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Duration</th>
                            <th>Queue</th>
                            <th><?php echo $user_role === 'student' ? 'Teacher' : 'Student'; ?></th>
                            <th>Comments</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($past_meetings as $meeting): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($meeting['ended_at'])); ?></td>
                                <td><?php echo date('g:i A', strtotime($meeting['started_at'])); ?></td>
                                <td><?php echo $meeting['duration']; ?> min</td>
                                <td><?php echo htmlspecialchars($meeting['queue_purpose']); ?></td>
                                <td><?php echo htmlspecialchars($user_role === 'student' ? $meeting['teacher_name'] : $meeting['student_name']); ?></td>
                                <td>
                                    <?php if ($meeting['comment']): ?>
                                        <div class="comment-tooltip">
                                            <?php echo htmlspecialchars($meeting['comment']); ?>
                                        </div>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 