<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'student';
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="css/history.css">
<div class="past-title">📜 Past Meetings</div>
<div class="past-cards">
<?php
// Get all past meetings for this student
$sql = "SELECT q.id as queue_id, q.purpose, q.meeting_link, q.access_code, 
               qe.position as my_position, qe.started_at, qe.ended_at, qe.status,
               TIMESTAMPDIFF(MINUTE, qe.started_at, qe.ended_at) as duration, u.name as teacher_name
        FROM queue_entries qe
        JOIN queues q ON qe.queue_id = q.id
        LEFT JOIN users u ON q.teacher_id = u.id
        WHERE qe.student_id = ? AND qe.status IN ('done', 'skipped')
        ORDER BY qe.ended_at DESC
        LIMIT 50";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$past_meetings = $stmt->fetchAll();
if (empty($past_meetings)) {
    echo '<div style="grid-column:1/-1;text-align:center;color:#64748b;font-size:1.15rem;padding:2.5rem 0;">You don\'t have any past meetings yet.</div>';
} else {
    foreach ($past_meetings as $meeting) {
        $status = isset($meeting['status']) ? strtolower($meeting['status']) : 'done';
        $statusIcon = $status === 'done' ? '✅' : ($status === 'skipped' ? '❌' : '🕓');
        $statusClass = $status === 'done' ? 'done' : ($status === 'skipped' ? 'skipped' : 'other');
        echo '<div class="past-card">';
        echo '<div class="past-card-title"><i class="fa fa-calendar-check"></i> ' . htmlspecialchars($meeting['purpose']) . '</div>';
        if (!empty($meeting['teacher_name'])) {
            echo '<div class="past-card-meta"><i class="fa fa-chalkboard-teacher"></i> ' . htmlspecialchars($meeting['teacher_name']) . '</div>';
        }
        echo '<div class="past-card-time"><i class="fa fa-calendar-alt"></i> ' . date('M d, Y', strtotime($meeting['ended_at'])) . '</div>';
        echo '<div class="past-card-time"><i class="fa fa-clock"></i> ' . date('g:i A', strtotime($meeting['started_at'])) . ' - ' . date('g:i A', strtotime($meeting['ended_at'])) . '</div>';
        echo '<div class="past-card-time"><i class="fa fa-hourglass-half"></i> Duration: ' . $meeting['duration'] . ' minutes</div>';
        if (!empty($meeting['meeting_link'])) {
            echo '<a class="past-card-link" href="' . htmlspecialchars($meeting['meeting_link']) . '" target="_blank"><i class="fa fa-link"></i> Meeting Link</a>';
        }
        echo '<div class="past-card-status ' . $statusClass . '">' . $statusIcon . ' ' . ucfirst($status) . '</div>';
        echo '</div>';
    }
}
?>
</div>
<?php
$content = ob_get_clean();
require '../src/Includes/layout.php';
?> 