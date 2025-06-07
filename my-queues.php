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
<h2>My Queues</h2>
<div class="mt-4">
    <div class="row g-4">
        <?php
        // Get all queues where this student is currently waiting or in a meeting
        $sql = "SELECT q.id as queue_id, q.purpose, q.meeting_link, q.access_code, qe.position as my_position
                FROM queue_entries qe
                JOIN queues q ON qe.queue_id = q.id
                WHERE qe.student_id = ? AND qe.status IN ('waiting', 'in_meeting')
                ORDER BY qe.position ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $my_queues = $stmt->fetchAll();

        if (empty($my_queues)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    You are not currently in any queues.
                </div>
            </div>
        <?php else:
            foreach ($my_queues as $queue): ?>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($queue['purpose']); ?></h5>
                            <p class="card-text">
                                <strong>Position:</strong> <?php echo $queue['my_position']; ?><br>
                                <?php if ($queue['meeting_link']): ?>
                                    <strong>Meeting Link:</strong> 
                                    <a href="<?php echo htmlspecialchars($queue['meeting_link']); ?>" target="_blank">
                                        Join Meeting
                                    </a><br>
                                <?php endif; ?>
                                <?php if ($queue['access_code']): ?>
                                    <strong>Access Code:</strong> <?php echo htmlspecialchars($queue['access_code']); ?>
                                <?php endif; ?>
                            </p>
                            <a href="queue-members.php?id=<?php echo $queue['queue_id']; ?>" class="btn btn-primary">
                                View Queue
                            </a>
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