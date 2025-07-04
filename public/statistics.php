<?php
session_start();
require_once 'config.php';

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
        $stats['average_meeting_duration'] = (int) round($counts['total_meeting_duration'] / $counts['completed_meetings'] / 60, 1);
    } else {
        $stats['average_meeting_duration'] = 0;
    }
    
    if ($counts['meetings_with_wait_time'] > 0) {
        $stats['average_wait_time'] = (int) round($counts['total_wait_time'] / $counts['meetings_with_wait_time'] / 60, 1);
    } else {
        $stats['average_wait_time'] = 0;
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
    $stats['max_wait_time'] = (int) round($max_wait['max_wait_seconds'] / 60, 1);
} else {
    $stats['max_wait_time'] = 0;
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

// Fetch users by status for modals
$status_users = [];
foreach ([
    'waiting' => 'Waiting',
    'in_meeting' => 'In Meeting',
    'done' => 'Completed',
    'skipped' => 'Skipped'
] as $status_key => $status_label) {
    $stmt = $pdo->prepare('SELECT u.name, u.faculty_number FROM queue_entries qe JOIN users u ON qe.student_id = u.id WHERE qe.queue_id = ? AND qe.status = ?');
    $stmt->execute([$queue_id, $status_key]);
    $status_users[$status_key] = $stmt->fetchAll();
}

function formatDuration($minutes) {
    if (!is_numeric($minutes) || $minutes < 0.1) {
        return '0 minutes';
    }
    $totalMinutes = round((float)$minutes, 1);
    $hours = (int)($totalMinutes / 60);
    $mins = $totalMinutes % 60;
    $parts = [];
    if ($hours > 0) {
        $parts[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
    }
    if ($mins > 0) {
        $mins_display = (abs($mins - (int)$mins) < 0.01) ? (int)$mins : round($mins, 1);
        $parts[] = $mins_display . ' minute' . ($mins_display > 1 ? 's' : '');
    }
    return implode(' and ', $parts) ?: '0 minutes';
}

function formatExactTime($timestamp) {
    if (!$timestamp) return 'N/A';
    return date('M j, Y g:i:s A', strtotime($timestamp));
}

ob_start();
?>
<link rel="stylesheet" href="css/statistics.css">
<div class="stats-title-page">📈 Statistics<?php if (isset($queue['purpose'])) echo ': ' . htmlspecialchars($queue['purpose']); ?></div>
<div class="stats-grid">
    <div class="stats-card">
        <h5>Status Distribution</h5>
        <div style="margin-bottom:1.2rem;">
            <span style="cursor:pointer;" onclick="openModal('waiting')">Waiting <span class="stats-badge stats-badge-waiting"><?php echo $stats['status_counts']['waiting']; ?></span></span><br>
            <span style="cursor:pointer;" onclick="openModal('in_meeting')">In Meeting <span class="stats-badge stats-badge-in_meeting"><?php echo $stats['status_counts']['in_meeting']; ?></span></span><br>
            <span style="cursor:pointer;" onclick="openModal('done')">Completed <span class="stats-badge stats-badge-done"><?php echo $stats['status_counts']['done']; ?></span></span><br>
            <span style="cursor:pointer;" onclick="openModal('skipped')">Skipped <span class="stats-badge stats-badge-skipped"><?php echo $stats['status_counts']['skipped']; ?></span></span>
        </div>
        <h5>Time Information</h5>
        <div style="color:#64748b;font-size:1.05rem;">
            <div>Queue Start: <?php echo formatExactTime($stats['time_info']['queue_start']); ?></div>
            <div>Queue End: <?php echo formatExactTime($stats['time_info']['queue_end']); ?></div>
            <div>First Entry: <?php echo formatExactTime($stats['time_info']['first_entry']); ?></div>
            <div>Last Entry: <?php echo formatExactTime($stats['time_info']['last_entry']); ?></div>
        </div>
    </div>
    <div class="stats-card">
        <h5>Averages</h5>
        <div style="color:#64748b;font-size:1.05rem;">
            <div>Average Wait Time: <?php echo formatDuration($stats['average_wait_time']); ?></div>
            <div>Average Meeting Duration: <?php echo formatDuration($stats['average_meeting_duration']); ?></div>
            <div>Max Wait Time: <?php echo formatDuration($stats['max_wait_time']); ?></div>
        </div>
        <h5 style="margin-top:1.2rem;">Total Entries</h5>
        <span class="stats-badge stats-badge-in_meeting" style="font-size:1.2em;background:#6366f1;"> <?php echo $stats['total_entries']; ?> </span>
    </div>
    <div class="stats-card" style="align-items:center;justify-content:center;min-width:260px;">
        <h5 style="align-self:flex-start;">Meeting Status Breakdown</h5>
        <canvas id="statusPieChart" width="180" height="180"></canvas>
        <div class="stats-pie-legend">
            <span><span class="legend-dot" style="background:#10b981;"></span> Completed</span>
            <span><span class="legend-dot" style="background:#6366f1;"></span> Waiting</span>
            <span><span class="legend-dot" style="background:#2563eb;"></span> In Meeting</span>
            <span><span class="legend-dot" style="background:#f59e42;"></span> Skipped</span>
        </div>
    </div>
</div>
<div class="stats-card" style="margin:0 auto 2.5rem auto;max-width:900px;">
    <h5>Hourly Entries</h5>
    <canvas id="hourlyChart"></canvas>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const statusPieCtx = document.getElementById('statusPieChart').getContext('2d');
new Chart(statusPieCtx, {
    type: 'pie',
    data: {
        labels: ['Completed', 'Waiting', 'In Meeting', 'Skipped'],
        datasets: [{
            data: [
                <?php echo $stats['status_counts']['done']; ?>,
                <?php echo $stats['status_counts']['waiting']; ?>,
                <?php echo $stats['status_counts']['in_meeting']; ?>,
                <?php echo $stats['status_counts']['skipped']; ?>
            ],
            backgroundColor: [
                '#10b981', // Completed
                '#6366f1', // Waiting
                '#2563eb', // In Meeting
                '#f59e42'  // Skipped
            ],
            borderWidth: 2,
            borderColor: '#fff',
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        }
    }
});
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
<!-- Status Modals -->
<?php foreach ([
    'waiting' => 'Waiting',
    'in_meeting' => 'In Meeting',
    'done' => 'Completed',
    'skipped' => 'Skipped'
] as $status_key => $status_label): ?>
<div id="modal-<?php echo $status_key; ?>" class="status-modal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100vw;height:100vh;background:rgba(30,41,59,0.18);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:18px;box-shadow:0 8px 40px rgba(99,102,241,0.13);padding:2.2rem 2.5rem;min-width:320px;max-width:95vw;position:relative;">
    <button onclick="closeModal('<?php echo $status_key; ?>')" style="position:absolute;top:1.1rem;right:1.1rem;background:none;border:none;font-size:1.5rem;color:#64748b;cursor:pointer;">&times;</button>
    <h3 style="margin-bottom:1.2rem;color:#2563eb;font-size:1.25rem;font-weight:700;"><?php echo $status_label; ?> Members</h3>
    <?php if (empty($status_users[$status_key])): ?>
      <div style="color:#64748b;font-size:1.08rem;">No members in this status.</div>
    <?php else: ?>
      <table style="width:100%;border-collapse:separate;border-spacing:0;background:#f8fafc;border-radius:12px;box-shadow:0 2px 8px rgba(99,102,241,0.07);overflow:hidden;">
        <thead>
          <tr style="background:#e0e7ff;font-size:1.08rem;font-weight:700;color:#222;">
            <th style="padding:0.8em 1.1em;text-align:left;">Name</th>
            <th style="padding:0.8em 1.1em;text-align:left;">Faculty Number</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($status_users[$status_key] as $user): ?>
          <tr>
            <td style="padding:0.7em 1.1em;font-size:1.07rem;"> <?php echo htmlspecialchars($user['name']); ?> </td>
            <td style="padding:0.7em 1.1em;font-size:1.07rem;"> <?php echo htmlspecialchars($user['faculty_number'] ?? '-'); ?> </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
<script>
function openModal(status) {
  document.getElementById('modal-' + status).style.display = 'flex';
}
function closeModal(status) {
  document.getElementById('modal-' + status).style.display = 'none';
}
window.onclick = function(event) {
  ['waiting','in_meeting','done','skipped'].forEach(function(status) {
    var modal = document.getElementById('modal-' + status);
    if (modal && event.target === modal) {
      modal.style.display = 'none';
    }
  });
}
</script>
<?php
$content = ob_get_clean();
require '../src/Includes/layout.php';
?> 