<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'student';
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
.past-title {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    text-align: left;
    margin: 2.5rem 0 2rem 0;
    letter-spacing: 0.01em;
    display: flex;
    align-items: center;
    gap: 0.7rem;
}
.past-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 2.2rem;
    width: 100%;
    max-width: 980px;
    margin: 0 auto 2.5rem auto;
    justify-content: center;
}
.past-card {
    background: linear-gradient(120deg, #f8fafc 60%, #e0e7ff 100%);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(30,41,59,0.13), 0 1.5px 6px rgba(99,102,241,0.08);
    padding: 2.2rem 1.7rem 1.7rem 1.7rem;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    text-align: left;
    transition: box-shadow 0.22s, transform 0.22s;
    position: relative;
}
.past-card-title {
    font-size: 1.18rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.3rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.past-card-meta {
    font-size: 1.05rem;
    color: #6366f1;
    margin-bottom: 0.5rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.past-card-time {
    font-size: 1.04rem;
    color: #334155;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.past-card-status {
    font-size: 1.01rem;
    font-weight: 600;
    margin-bottom: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.past-card-status.done { color: #10b981; }
.past-card-status.skipped { color: #f59e42; }
.past-card-status.other { color: #64748b; }
.past-card-link {
    color: #2563eb;
    text-decoration: underline;
    font-size: 1.01rem;
    margin-bottom: 0.7rem;
    display: inline-block;
}
.past-card-position {
    font-size: 0.98rem;
    color: #64748b;
    margin-top: 0.2rem;
}
@media (max-width: 900px) {
    .past-cards { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
    .past-title { font-size: 1.3rem; margin: 1.2rem 0 1rem 0; }
    .past-cards { gap: 1.2rem; }
    .past-card { padding: 1.2rem 0.7rem; }
}
</style>
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
require 'layout.php';
?> 