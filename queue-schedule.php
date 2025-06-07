<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$queue_id = $_GET['id'] ?? null;
if (!$queue_id) {
    header('Location: index.php');
    exit();
}

// Get queue info
$stmt = $pdo->prepare('SELECT * FROM queues WHERE id = ?');
$stmt->execute([$queue_id]);
$queue = $stmt->fetch();
if (!$queue) {
    header('Location: index.php');
    exit();
}

// Get all waiting students in this queue
$sql = "SELECT qe.position, qe.estimated_start_time, u.name as student_name
        FROM queue_entries qe
        JOIN users u ON qe.student_id = u.id
        WHERE qe.queue_id = ? AND qe.status = 'waiting'
        ORDER BY qe.position ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$queue_id]);
$waiting = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Schedule - Virtual Office Queue</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <h1>Queue Schedule: <?php echo htmlspecialchars($queue['purpose']); ?></h1>
            <div class="nav-links">
                <a href="index.php" class="btn btn-secondary">Dashboard</a>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </nav>
        <div class="schedule-container">
            <h2>Current Waiting List</h2>
            <?php if (empty($waiting)): ?>
                <p>No students are currently waiting in this queue.</p>
            <?php else: ?>
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
                            <tr>
                                <td><?php echo $entry['position']; ?></td>
                                <td><?php echo htmlspecialchars($entry['student_name']); ?></td>
                                <td><?php echo $entry['estimated_start_time'] ? date('g:i A', strtotime($entry['estimated_start_time'])) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 