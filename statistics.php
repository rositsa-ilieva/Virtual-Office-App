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

function formatDuration($minutes) {
    if (!is_numeric($minutes) || $minutes < 0.1) {
        return '0 minutes';
    }
    $totalMinutes = round((float)$minutes, 1);
    $hours = round($totalMinutes / 60, 0);
    $mins = round($totalMinutes % 60, 1);
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

ob_start();
?>
<h2>Queue Statistics: <?php echo htmlspecialchars($queue['purpose']); ?></h2>
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title">Status Distribution</h5>
                <ul class="list-group mb-3">
                    <li class="list-group-item d-flex justify-content-between align-items-center">Waiting <span class="badge bg-warning text-dark"><?php echo $stats['status_counts']['waiting']; ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">In Meeting <span class="badge bg-info text-dark"><?php echo $stats['status_counts']['in_meeting']; ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Completed <span class="badge bg-success"><?php echo $stats['status_counts']['done']; ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">Skipped <span class="badge bg-danger"><?php echo $stats['status_counts']['skipped']; ?></span></li>
                </ul>
                <h5 class="card-title mt-4">Time Information</h5>
                <ul class="list-group">
                    <li class="list-group-item">Queue Start: <?php echo formatExactTime($stats['time_info']['queue_start']); ?></li>
                    <li class="list-group-item">Queue End: <?php echo formatExactTime($stats['time_info']['queue_end']); ?></li>
                    <li class="list-group-item">First Entry: <?php echo formatExactTime($stats['time_info']['first_entry']); ?></li>
                    <li class="list-group-item">Last Entry: <?php echo formatExactTime($stats['time_info']['last_entry']); ?></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title">Averages</h5>
                <ul class="list-group mb-3">
                    <li class="list-group-item">Average Wait Time: <?php echo formatDuration($stats['average_wait_time']); ?></li>
                    <li class="list-group-item">Average Meeting Duration: <?php echo formatDuration($stats['average_meeting_duration']); ?></li>
                    <li class="list-group-item">Max Wait Time: <?php echo formatDuration($stats['max_wait_time']); ?></li>
                </ul>
                <h5 class="card-title mt-4">Total Entries</h5>
                <span class="badge bg-primary" style="font-size:1.2em;"><?php echo $stats['total_entries']; ?></span>
            </div>
        </div>
    </div>
</div>
<div class="row mt-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Hourly Entries</h5>
                <canvas id="hourlyChart"></canvas>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
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
</script>
<?php
$content = ob_get_clean();
require 'layout.php';
?> 