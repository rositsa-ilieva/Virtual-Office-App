<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: index.php');
    exit;
}

$queue_id = $_GET['id'] ?? null;
if (!$queue_id) {
    header('Location: index.php');
    exit;
}

// Get queue information
$stmt = $pdo->prepare('SELECT * FROM queues WHERE id = ? AND teacher_id = ?');
$stmt->execute([$queue_id, $_SESSION['user_id']]);
$queue = $stmt->fetch();

if (!$queue) {
    header('Location: index.php');
    exit;
}

// Get queue statistics
$stats = [
    'total_entries' => 0,
    'waiting' => 0,
    'in_meeting' => 0,
    'done' => 0,
    'skipped' => 0,
    'avg_duration' => 0
];

// Get total entries and status counts
$sql = "SELECT 
            COUNT(*) as total_entries,
            SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting,
            SUM(CASE WHEN status = 'in_meeting' THEN 1 ELSE 0 END) as in_meeting,
            SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done,
            SUM(CASE WHEN status = 'skipped' THEN 1 ELSE 0 END) as skipped
        FROM queue_entries 
        WHERE queue_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$queue_id]);
$counts = $stmt->fetch();

if ($counts) {
    $stats['total_entries'] = $counts['total_entries'];
    $stats['waiting'] = $counts['waiting'];
    $stats['in_meeting'] = $counts['in_meeting'];
    $stats['done'] = $counts['done'];
    $stats['skipped'] = $counts['skipped'];
}

// Get average meeting duration
$sql = "SELECT AVG(TIMESTAMPDIFF(MINUTE, started_at, ended_at)) as avg_duration 
        FROM queue_entries 
        WHERE queue_id = ? AND status = 'done' AND started_at IS NOT NULL AND ended_at IS NOT NULL";
$stmt = $pdo->prepare($sql);
$stmt->execute([$queue_id]);
$duration = $stmt->fetch();
$stats['avg_duration'] = round($duration['avg_duration'] ?? 0);

// Get recent entries
$sql = "SELECT qe.*, u.name as student_name 
        FROM queue_entries qe 
        JOIN users u ON qe.student_id = u.id 
        WHERE qe.queue_id = ? 
        ORDER BY qe.joined_at DESC 
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$queue_id]);
$recent_entries = $stmt->fetchAll();

// Get average wait times by hour
$sql = "SELECT 
            HOUR(joined_at) as hour,
            AVG(TIMESTAMPDIFF(MINUTE, joined_at, started_at)) as avg_wait_time
        FROM queue_entries 
        WHERE queue_id = ? 
        AND status IN ('done', 'in_meeting')
        AND started_at IS NOT NULL
        GROUP BY HOUR(joined_at)
        ORDER BY hour";
$stmt = $pdo->prepare($sql);
$stmt->execute([$queue_id]);
$wait_times = $stmt->fetchAll();

// Prepare data for chart
$hours = [];
$avg_wait_times = [];
foreach ($wait_times as $time) {
    $hours[] = date('g A', strtotime($time['hour'] . ':00'));
    $avg_wait_times[] = round($time['avg_wait_time']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Statistics - Virtual Office Queue</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <h1>Queue Statistics: <?php echo htmlspecialchars($queue['purpose']); ?></h1>
            <div class="nav-links">
                <a href="room.php?id=<?php echo $queue_id; ?>" class="btn btn-secondary">Back to Queue</a>
            </div>
        </nav>

        <div class="stats-container">
            <div class="stats-header">
                <h2>Queue Overview</h2>
            </div>

            <div class="stats-grid">
                <div class="stats-card">
                    <h3>Overview</h3>
                    <div class="stats-list">
                        <div class="stat-item">
                            <span class="stat-label">Total Entries:</span>
                            <span class="stat-value"><?php echo $stats['total_entries']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Waiting:</span>
                            <span class="stat-value"><?php echo $stats['waiting']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">In Meeting:</span>
                            <span class="stat-value"><?php echo $stats['in_meeting']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Completed:</span>
                            <span class="stat-value"><?php echo $stats['done']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Skipped:</span>
                            <span class="stat-value"><?php echo $stats['skipped']; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Average Duration:</span>
                            <span class="stat-value"><?php echo $stats['avg_duration']; ?> minutes</span>
                        </div>
                    </div>
                </div>

                <div class="stats-card">
                    <h3>Average Wait Times by Hour</h3>
                    <canvas id="waitTimeChart"></canvas>
                </div>

                <div class="stats-card">
                    <h3>Recent Entries</h3>
                    <div class="recent-entries">
                        <?php foreach ($recent_entries as $entry): ?>
                            <div class="entry-item">
                                <div class="entry-info">
                                    <span class="student-name"><?php echo htmlspecialchars($entry['student_name']); ?></span>
                                    <span class="status-badge status-<?php echo $entry['status']; ?>">
                                        <?php echo ucfirst($entry['status']); ?>
                                    </span>
                                </div>
                                <span class="entry-time">
                                    <?php echo date('M j, g:i A', strtotime($entry['joined_at'])); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Create wait time chart
        const ctx = document.getElementById('waitTimeChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($hours); ?>,
                datasets: [{
                    label: 'Average Wait Time (minutes)',
                    data: <?php echo json_encode($avg_wait_times); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Minutes'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Hour'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 