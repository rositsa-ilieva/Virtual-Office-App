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
<h2>History / Past Meetings</h2>
<div class="mt-4">
    <div class="row g-4">
        <?php
        // Get all past meetings for this student
        $sql = "SELECT q.id as queue_id, q.purpose, q.meeting_link, q.access_code, 
                       qe.position as my_position, qe.started_at, qe.ended_at,
                       TIMESTAMPDIFF(MINUTE, qe.started_at, qe.ended_at) as duration
                FROM queue_entries qe
                JOIN queues q ON qe.queue_id = q.id
                WHERE qe.student_id = ? AND qe.status IN ('done', 'skipped')
                ORDER BY qe.ended_at DESC
                LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $past_meetings = $stmt->fetchAll();

        if (empty($past_meetings)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No past meetings found.
                </div>
            </div>
        <?php else:
            foreach ($past_meetings as $meeting): ?>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($meeting['purpose']); ?></h5>
                            <p class="card-text">
                                <strong>Date:</strong> <?php echo date('M d, Y', strtotime($meeting['ended_at'])); ?><br>
                                <strong>Time:</strong> <?php echo date('g:i A', strtotime($meeting['started_at'])); ?> - 
                                                     <?php echo date('g:i A', strtotime($meeting['ended_at'])); ?><br>
                                <strong>Duration:</strong> <?php echo $meeting['duration']; ?> minutes<br>
                                <strong>Position:</strong> <?php echo $meeting['my_position']; ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach;
        endif; ?>
    </div>
</div>
<?php
$content = ob_get_clean();
require 'layout.php';
?> 