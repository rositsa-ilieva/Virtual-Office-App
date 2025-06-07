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
<h2>Past Events</h2>
<div class="table-responsive mt-4">
    <table class="table table-bordered align-middle">
        <thead class="table-light">
            <tr>
                <th>Title</th>
                <th>Date</th>
                <th>Queue Size</th>
                <th>Meeting Link</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
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
            $sql = "SELECT q.*, qe.status, qe.ended_at
                    FROM queue_entries qe
                    JOIN queues q ON qe.queue_id = q.id
                    WHERE qe.student_id = ? AND qe.status IN ('done', 'completed', 'skipped')
                    ORDER BY qe.ended_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $past_queues = $stmt->fetchAll();
        }
        if (empty($past_queues)) {
            echo '<tr><td colspan="5" class="text-center">No past events found.</td></tr>';
        } else {
            foreach ($past_queues as $queue) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($queue['purpose']) . '</td>';
                echo '<td>' . ($queue['ended_at'] ? date('M d, Y', strtotime($queue['ended_at'])) : ($queue['start_time'] ? date('M d, Y', strtotime($queue['start_time'])) : '-')) . '</td>';
                echo '<td>' . (isset($queue['queue_size']) ? $queue['queue_size'] : '-') . '</td>';
                echo '<td>';
                if (!empty($queue['meeting_link'])) {
                    echo '<a href="' . htmlspecialchars($queue['meeting_link']) . '" target="_blank">View Link</a>';
                } else {
                    echo '-';
                }
                echo '</td>';
                echo '<td>' . (isset($queue['status']) ? ucfirst($queue['status']) : 'Completed') . '</td>';
                echo '</tr>';
            }
        }
        ?>
        </tbody>
    </table>
</div>
<?php
$content = ob_get_clean();
require 'layout.php';
?> 