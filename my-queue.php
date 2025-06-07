<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get all queues where this student is currently waiting
$sql = "SELECT q.id as queue_id, q.purpose, q.meeting_link, q.access_code, qe.position as my_position
        FROM queue_entries qe
        JOIN queues q ON qe.queue_id = q.id
        WHERE qe.student_id = ? AND qe.status = 'waiting'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$my_queues = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Queues - Virtual Office Queue</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <h1>My Queues</h1>
            <div class="nav-links">
                <a href="index.php" class="btn btn-secondary">Dashboard</a>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </nav>
        <div class="my-queues-container">
            <h2>Queues You're Waiting In</h2>
            <?php if (empty($my_queues)): ?>
                <p>You are not currently waiting in any queues.</p>
            <?php else: ?>
                <?php foreach ($my_queues as $queue): ?>
                    <div class="queue-block">
                        <h3><?php echo htmlspecialchars($queue['purpose']); ?></h3>
                        <?php if (!empty($queue['meeting_link'])): ?>
                            <p><strong>Meeting Link:</strong> <a href="<?php echo htmlspecialchars($queue['meeting_link']); ?>" target="_blank"><?php echo htmlspecialchars($queue['meeting_link']); ?></a></p>
                        <?php endif; ?>
                        <?php if (!empty($queue['access_code'])): ?>
                            <p><strong>Access Code:</strong> <?php echo htmlspecialchars($queue['access_code']); ?></p>
                        <?php endif; ?>
                        <?php
                        // Get all waiting students for this queue
                        $sql2 = "SELECT qe.position, qe.estimated_start_time, u.name as student_name
                                 FROM queue_entries qe
                                 JOIN users u ON qe.student_id = u.id
                                 WHERE qe.queue_id = ? AND qe.status = 'waiting'
                                 ORDER BY qe.position ASC";
                        $stmt2 = $pdo->prepare($sql2);
                        $stmt2->execute([$queue['queue_id']]);
                        $waiting = $stmt2->fetchAll();
                        ?>
                        <table class="schedule-table">
                            <thead>
                                <tr>
                                    <th>Position</th>
                                    <th>Name</th>
                                    <th>Estimated Start Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($waiting as $entry): ?>
                                    <tr<?php if ($entry['student_name'] === $_SESSION['user_name']) echo ' style="font-weight:bold;background:#e6f7ff"'; ?>>
                                        <td><?php echo $entry['position']; ?></td>
                                        <td><?php echo htmlspecialchars($entry['student_name']); ?></td>
                                        <td><?php echo $entry['estimated_start_time'] ? date('g:i A', strtotime($entry['estimated_start_time'])) : '-'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 