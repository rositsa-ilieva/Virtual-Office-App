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
<h2>Upcoming Events</h2>
<div class="mt-4">
    <div class="row g-4">
        <?php
        // Get upcoming queues
        $sql = "SELECT q.*, 
                       (SELECT COUNT(*) FROM queue_entries WHERE queue_id = q.id AND status = 'waiting') as waiting_count,
                       (SELECT position FROM queue_entries WHERE queue_id = q.id AND student_id = ?) as my_position
                FROM queues q
                WHERE q.start_time > NOW()
                ORDER BY q.start_time ASC
                LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $upcoming_queues = $stmt->fetchAll();

        if (empty($upcoming_queues)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No upcoming events found.
                </div>
            </div>
        <?php else:
            foreach ($upcoming_queues as $queue): ?>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($queue['purpose']); ?></h5>
                            <p class="card-text">
                                <strong>Date:</strong> <?php echo date('M d, Y', strtotime($queue['start_time'])); ?><br>
                                <strong>Time:</strong> <?php echo date('g:i A', strtotime($queue['start_time'])); ?><br>
                                <strong>Waiting:</strong> <?php echo $queue['waiting_count']; ?> students<br>
                                <?php if ($queue['my_position']): ?>
                                    <strong>Your Position:</strong> <?php echo $queue['my_position']; ?>
                                <?php endif; ?>
                            </p>
                            <?php if (!$queue['my_position']): ?>
                                <a href="join.php?id=<?php echo $queue['id']; ?>" class="btn btn-primary">
                                    Join Queue
                                </a>
                            <?php endif; ?>
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