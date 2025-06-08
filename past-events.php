<?php
session_start();
require_once 'db.php';
$activePage = 'past-events';
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
<div class="past-title"><i class="fa fa-history"></i> Past Meetings</div>
<div class="past-cards">
<?php
if ($user_role === 'teacher') {
    $sql = "SELECT q.*, COUNT(qe.id) as queue_size
            FROM queues q
            LEFT JOIN queue_entries qe ON qe.queue_id = q.id
            WHERE q.teacher_id = ? AND q.is_active = 0
            GROUP BY q.id
            ORDER BY q.start_time DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $past_queues = $stmt->fetchAll();
} else {
    $sql = "SELECT q.*, qe.status, qe.ended_at, qe.position, qe.teacher_id, u.name as teacher_name
            FROM queue_entries qe
            JOIN queues q ON qe.queue_id = q.id
            JOIN users u ON q.teacher_id = u.id
            WHERE qe.student_id = ? AND qe.status IN ('done', 'completed', 'skipped')
            ORDER BY qe.ended_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $past_queues = $stmt->fetchAll();
}
if (empty($past_queues)) {
    echo '<div style="grid-column:1/-1;text-align:center;color:#64748b;font-size:1.15rem;padding:2.5rem 0;">\uD83D\uDCC4 You have no past meetings yet.</div>';
} else {
    foreach ($past_queues as $queue) {
        $status = isset($queue['status']) ? strtolower($queue['status']) : 'done';
        $statusIcon = $status === 'done' || $status === 'completed' ? '✅' : ($status === 'skipped' ? '❌' : '🕓');
        $statusClass = $status === 'done' || $status === 'completed' ? 'done' : ($status === 'skipped' ? 'skipped' : 'other');
        echo '<div class="past-card">';
        echo '<div class="past-card-title"><i class="fa fa-calendar-check"></i> ' . htmlspecialchars($queue['purpose']) . '</div>';
        if (isset($queue['teacher_name'])) {
            echo '<div class="past-card-meta"><i class="fa fa-chalkboard-teacher"></i> ' . htmlspecialchars($queue['teacher_name']) . '</div>';
        }
        echo '<div class="past-card-time"><i class="fa fa-clock"></i> ' . ($queue['ended_at'] ? date('M d, Y', strtotime($queue['ended_at'])) : ($queue['start_time'] ? date('M d, Y', strtotime($queue['start_time'])) : '-')) . '</div>';
        if (isset($queue['position'])) {
            echo '<div class="past-card-position">Position in queue: ' . htmlspecialchars($queue['position']) . '</div>';
        }
        if (!empty($queue['meeting_link'])) {
            echo '<a class="past-card-link" href="' . htmlspecialchars($queue['meeting_link']) . '" target="_blank"><i class="fa fa-link"></i> Meeting Link</a>';
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