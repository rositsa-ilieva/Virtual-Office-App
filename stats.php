<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'teacher') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// If a queue id is provided, redirect to statistics.php?id=...
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    header('Location: statistics.php?id=' . intval($_GET['id']));
    exit();
}

ob_start();
?>
<h2>Queue Statistics</h2>
<div class="mt-4">
    <div class="row g-4">
        <?php
        $stmt = $pdo->prepare('SELECT * FROM queues WHERE teacher_id = ? ORDER BY start_time DESC');
        $stmt->execute([$user_id]);
        $queues = $stmt->fetchAll();
        if (empty($queues)): ?>
            <div class="col-12">
                <div class="alert alert-info">You have not created any queues yet.</div>
            </div>
        <?php else:
            foreach ($queues as $queue): ?>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($queue['purpose']); ?></h5>
                            <p class="card-text">
                                <strong>Start:</strong> <?php echo date('M d, Y g:i A', strtotime($queue['start_time'])); ?><br>
                                <strong>Duration:</strong> <?php echo $queue['default_duration']; ?> min<br>
                                <strong>Max Students:</strong> <?php echo $queue['max_students'] ?? '-'; ?><br>
                                <strong>Status:</strong> <?php echo $queue['is_active'] ? '<span class="text-success">Active</span>' : '<span class="text-danger">Inactive</span>'; ?>
                            </p>
                            <a href="statistics.php?id=<?php echo $queue['id']; ?>" class="btn btn-primary btn-sm">View Statistics</a>
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