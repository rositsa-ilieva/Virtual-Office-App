<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$queue_id = $_GET['id'] ?? 0;

// Verify queue ownership
$stmt = $pdo->prepare('SELECT * FROM queues WHERE id = ? AND teacher_id = ?');
$stmt->execute([$queue_id, $user_id]);
$queue = $stmt->fetch();

if (!$queue) {
    die('Queue not found or access denied');
}

// Get queue statistics with precise time calculations
$stats = [
    'total_entries' => 0,
    'average_wait_time' => 0,
    'average_meeting_duration' => 0,
    'max_wait_time' => 0,
    'status_counts' => [
    'waiting' => 0,
    'in_meeting' => 0,
    'done' => 0,
        'skipped' => 0
    ],
    'time_info' => [
        'queue_start' => null,
        'queue_end' => null,
        'first_entry' => null,
        'last_entry' => null,
        'total_duration' => null
    ],
    'detailed_times' => [
        'completed_meetings' => 0,
        'total_meeting_duration' => 0,
        'total_wait_time' => 0,
        'meetings_with_wait_time' => 0
    ]
];

// Get total entries and status counts with precise timestamps
$stmt = $pdo->prepare('
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = "waiting" THEN 1 ELSE 0 END) as waiting,
        SUM(CASE WHEN status = "in_meeting" THEN 1 ELSE 0 END) as in_meeting,
        SUM(CASE WHEN status = "done" THEN 1 ELSE 0 END) as done,
        SUM(CASE WHEN status = "skipped" THEN 1 ELSE 0 END) as skipped,
        MIN(joined_at) as first_entry,
        MAX(joined_at) as last_entry,
        SUM(CASE 
            WHEN status IN ("done", "skipped") AND started_at IS NOT NULL AND ended_at IS NOT NULL 
            THEN TIMESTAMPDIFF(SECOND, started_at, ended_at)
            ELSE 0 
        END) as total_meeting_duration,
        COUNT(CASE 
            WHEN status IN ("done", "skipped") AND started_at IS NOT NULL AND ended_at IS NOT NULL 
            THEN 1 
        END) as completed_meetings,
        SUM(CASE 
            WHEN started_at IS NOT NULL 
            THEN TIMESTAMPDIFF(SECOND, joined_at, started_at)
            ELSE 0 
        END) as total_wait_time,
        COUNT(CASE 
            WHEN started_at IS NOT NULL 
            THEN 1 
        END) as meetings_with_wait_time
        FROM queue_entries 
    WHERE queue_id = ?
');
$stmt->execute([$queue_id]);
$counts = $stmt->fetch();

if ($counts) {
    $stats['total_entries'] = $counts['total'];
    $stats['status_counts'] = [
        'waiting' => $counts['waiting'],
        'in_meeting' => $counts['in_meeting'],
        'done' => $counts['done'],
        'skipped' => $counts['skipped']
    ];
    
    // Handle timestamps
    $stats['time_info']['first_entry'] = $counts['first_entry'];
    $stats['time_info']['last_entry'] = $counts['last_entry'];
    
    // Calculate precise durations
    $stats['detailed_times']['completed_meetings'] = $counts['completed_meetings'];
    $stats['detailed_times']['total_meeting_duration'] = $counts['total_meeting_duration'];
    $stats['detailed_times']['total_wait_time'] = $counts['total_wait_time'];
    $stats['detailed_times']['meetings_with_wait_time'] = $counts['meetings_with_wait_time'];
    
    // Calculate averages only if we have data
    if ($counts['completed_meetings'] > 0) {
        $stats['average_meeting_duration'] = round($counts['total_meeting_duration'] / $counts['completed_meetings'] / 60, 1);
    } else {
        $stats['average_meeting_duration'] = 0.0;
    }
    
    if ($counts['meetings_with_wait_time'] > 0) {
        $stats['average_wait_time'] = round($counts['total_wait_time'] / $counts['meetings_with_wait_time'] / 60, 1);
    } else {
        $stats['average_wait_time'] = 0.0;
    }
}

// Get maximum wait time
$stmt = $pdo->prepare('
    SELECT MAX(TIMESTAMPDIFF(SECOND, joined_at, started_at)) as max_wait_seconds
        FROM queue_entries 
    WHERE queue_id = ? AND started_at IS NOT NULL
');
$stmt->execute([$queue_id]);
$max_wait = $stmt->fetch();

if ($max_wait && $max_wait['max_wait_seconds'] !== null) {
    $stats['max_wait_time'] = round($max_wait['max_wait_seconds'] / 60, 1);
} else {
    $stats['max_wait_time'] = 0.0;
}

// Get queue time info with precise calculations
$stmt = $pdo->prepare('
    SELECT 
        MIN(joined_at) as queue_start,
        MAX(CASE 
            WHEN status IN ("done", "skipped") THEN ended_at 
            WHEN status = "in_meeting" THEN started_at
            ELSE NULL 
        END) as queue_end
    FROM queue_entries 
    WHERE queue_id = ?
');
$stmt->execute([$queue_id]);
$time_info = $stmt->fetch();

if ($time_info) {
    $stats['time_info']['queue_start'] = $time_info['queue_start'];
    $stats['time_info']['queue_end'] = $time_info['queue_end'];
    
    // Calculate total queue duration if we have both start and end times
    if ($time_info['queue_start'] && $time_info['queue_end']) {
        $start = new DateTime($time_info['queue_start']);
        $end = new DateTime($time_info['queue_end']);
        $duration = $end->diff($start);
        $totalMinutes = ($duration->days * 24 * 60) + ($duration->h * 60) + $duration->i;
        $stats['time_info']['total_duration'] = [
            'hours' => floor($totalMinutes / 60),
            'minutes' => round($totalMinutes % 60, 1)
        ];
    }
}

// Get detailed entry data for the chart
$stmt = $pdo->prepare('
    SELECT 
        DATE_FORMAT(joined_at, "%Y-%m-%d %H:00") as hour,
        COUNT(*) as count
        FROM queue_entries 
        WHERE queue_id = ? 
    GROUP BY hour
    ORDER BY hour
');
$stmt->execute([$queue_id]);
$hourly_data = $stmt->fetchAll();

// Format time display function
function formatDuration($minutes) {
    if (!is_numeric($minutes) || $minutes < 0.1) {
        return '0 minutes';
    }
    // Convert to hours and minutes
    $totalMinutes = round((float)$minutes, 1); // Ensure float and round to 1 decimal place
    $hours = round($totalMinutes / 60, 0); // Use round instead of (int) or floor
    $mins = round($totalMinutes % 60, 1); // Keep as float with 1 decimal place
    $parts = [];
    if ($hours > 0) {
        $parts[] = number_format($hours, 0) . ' hour' . ($hours > 1 ? 's' : '');
    }
    if ($mins > 0) {
        $parts[] = number_format($mins, 1) . ' minute' . ($mins > 1 ? 's' : '');
    }
    return implode(' and ', $parts) ?: '0 minutes';
}

function formatExactTime($timestamp) {
    if (!$timestamp) return 'N/A';
    return date('M j, Y g:i:s A', strtotime($timestamp));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Statistics - Virtual Office Queue</title>
    <link rel="stylesheet" href="style.css?v=2024.1">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <h1>Queue Statistics</h1>
            <div class="nav-links">
                <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </nav>

        <div class="statistics-container">
            <div class="stats-header">
                <h2><?php echo htmlspecialchars($queue['purpose']); ?></h2>
                <p class="queue-description"><?php echo htmlspecialchars($queue['description'] ?? ''); ?></p>
            </div>

            <div class="stats-grid">
                <!-- Status Distribution -->
                <div class="stats-card">
                    <h3>Status Distribution</h3>
                    <div class="stats-table">
                        <div class="stats-row status-waiting">
                            <span>Waiting:</span>
                            <span><?php echo $stats['status_counts']['waiting']; ?></span>
                        </div>
                        <div class="stats-row status-in-meeting">
                            <span>In Meeting:</span>
                            <span><?php echo $stats['status_counts']['in_meeting']; ?></span>
                        </div>
                        <div class="stats-row status-done">
                            <span>Completed:</span>
                            <span><?php echo $stats['status_counts']['done']; ?></span>
                        </div>
                        <div class="stats-row status-skipped">
                            <span>Skipped:</span>
                            <span><?php echo $stats['status_counts']['skipped']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Time Information -->
                <div class="stats-card">
                    <h3>Time Information</h3>
                    <div class="stats-table">
                        <div class="stats-row">
                            <span>Queue Start:</span>
                            <span><?php echo formatExactTime($stats['time_info']['queue_start']); ?></span>
                        </div>
                        <div class="stats-row">
                            <span>Queue End:</span>
                            <span><?php echo formatExactTime($stats['time_info']['queue_end']); ?></span>
                </div>
                        <div class="stats-row">
                            <span>First Entry:</span>
                            <span><?php echo formatExactTime($stats['time_info']['first_entry']); ?></span>
                                </div>
                        <div class="stats-row">
                            <span>Last Entry:</span>
                            <span><?php echo formatExactTime($stats['time_info']['last_entry']); ?></span>
                            </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="charts-container">
                <div class="chart-card">
                    <h3>Status Distribution</h3>
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="chart-card">
                    <h3>Hourly Entries</h3>
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Status Distribution Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'pie',
        data: {
            labels: ['Waiting', 'In Meeting', 'Completed', 'Skipped'],
            datasets: [{
                data: [
                    <?php echo $stats['status_counts']['waiting']; ?>,
                    <?php echo $stats['status_counts']['in_meeting']; ?>,
                    <?php echo $stats['status_counts']['done']; ?>,
                    <?php echo $stats['status_counts']['skipped']; ?>
                ],
                backgroundColor: [
                    '#ffc107', // waiting
                    '#17a2b8', // in meeting
                    '#28a745', // done
                    '#dc3545'  // skipped
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // Hourly Entries Chart
    const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
    new Chart(hourlyCtx, {
        type: 'bar',
            data: {
            labels: <?php echo json_encode(array_column($hourly_data, 'hour')); ?>,
                datasets: [{
                label: 'Entries per Hour',
                data: <?php echo json_encode(array_column($hourly_data, 'count')); ?>,
                backgroundColor: '#007bff'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                    }
                }
            }
        });

    // Auto-refresh every 30 seconds
    setInterval(() => {
        location.reload();
    }, 30000);
    </script>
</body>
</html> 