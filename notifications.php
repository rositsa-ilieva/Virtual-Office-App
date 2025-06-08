<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'student';
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle swap request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notification_id = $_POST['notification_id'] ?? null;
    $queue_id = $_POST['queue_id'] ?? null;
    $from_user_id = $_POST['from_user_id'] ?? null;

    if ($notification_id && $queue_id && $from_user_id) {
        if ($action === 'approve_swap') {
            // Get both entries
            $stmt = $pdo->prepare('SELECT id, position FROM queue_entries WHERE queue_id = ? AND student_id IN (?, ?)');
            $stmt->execute([$queue_id, $from_user_id, $user_id]);
            $entries = $stmt->fetchAll();
            
            if (count($entries) == 2) {
                $pos1 = $entries[0]['position'];
                $id1 = $entries[0]['id'];
                $pos2 = $entries[1]['position'];
                $id2 = $entries[1]['id'];
                
                // Swap positions
                $pdo->prepare('UPDATE queue_entries SET position = ? WHERE id = ?')->execute([$pos2, $id1]);
                $pdo->prepare('UPDATE queue_entries SET position = ? WHERE id = ?')->execute([$pos1, $id2]);
                
                // Create notification for the requester
                $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, message, related_queue_id, related_user_id) VALUES (?, "swap_approved", ?, ?, ?)');
                $stmt->execute([$from_user_id, "Your position swap request was approved", $queue_id, $user_id]);
            }
        } elseif ($action === 'decline_swap') {
            // Create notification for the requester
            $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, message, related_queue_id, related_user_id) VALUES (?, "swap_declined", ?, ?, ?)');
            $stmt->execute([$from_user_id, "Your position swap request was declined", $queue_id, $user_id]);
        }
        
        // Mark the notification as read
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = TRUE WHERE id = ?');
        $stmt->execute([$notification_id]);
        
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
}

// Get notification count
$stmt = $pdo->prepare('SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE');
$stmt->execute([$user_id]);
$notification_count = $stmt->fetch()['count'];

// Fetch student messages
if ($user_role === 'teacher') {
    $stmt = $pdo->prepare('SELECT n.*, u.name as student_name FROM notifications n JOIN users u ON n.related_user_id = u.id WHERE n.user_id = ? AND n.type = "student_message" ORDER BY n.created_at DESC');
    $stmt->execute([$user_id]);
    $student_messages = $stmt->fetchAll();
}

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
    $stmt->execute([$_POST['mark_read'], $user_id]);
    header('Location: notifications.php');
    exit();
}

// Handle reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message']) && isset($_POST['reply_to']) && isset($_POST['queue_id'])) {
    $reply = trim($_POST['reply_message']);
    if ($reply !== '') {
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, message, related_queue_id, related_user_id) VALUES (?, "teacher_reply", ?, ?, ?)');
        $stmt->execute([$_POST['reply_to'], $reply, $_POST['queue_id'], $user_id]);
        echo '<div class="alert alert-success">Reply sent to student!</div>';
    }
}

// Handle AJAX reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_reply'])) {
    $reply = trim($_POST['reply_message'] ?? '');
    $student_id = (int)($_POST['student_id'] ?? 0);
    $queue_id = (int)($_POST['queue_id'] ?? 0);
    if ($reply !== '' && $student_id && $queue_id) {
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, message, related_queue_id, related_user_id) VALUES (?, "teacher_reply", ?, ?, ?)');
        $stmt->execute([$student_id, $reply, $queue_id, $user_id]);
        echo json_encode(['success' => true]);
        exit;
    }
    echo json_encode(['success' => false]);
    exit;
}

ob_start();
?>
<h2>Notifications</h2>
<div class="mt-4">
    <div class="row g-4">
        <?php
        // Get notifications (use correct columns)
        $sql = "SELECT n.*, 
                       q.purpose as queue_purpose,
                       u.name as sender_name
                FROM notifications n
                LEFT JOIN queues q ON n.related_queue_id = q.id
                LEFT JOIN users u ON n.related_user_id = u.id
                WHERE n.user_id = ?
                ORDER BY n.created_at DESC
                LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll();

        if (empty($notifications)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No notifications found.
                </div>
            </div>
        <?php else:
            foreach ($notifications as $notification): ?>
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title">
                                        <?php echo htmlspecialchars($notification['type']); ?>
                                    </h5>
                                    <p class="card-text">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </p>
                                    <?php if ($notification['queue_purpose']): ?>
                                        <p class="text-muted">
                                            Queue: <?php echo htmlspecialchars($notification['queue_purpose']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($notification['type'] === 'swap_request' && !$notification['is_read']): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <input type="hidden" name="from_user_id" value="<?php echo $notification['related_user_id']; ?>">
                                            <input type="hidden" name="queue_id" value="<?php echo $notification['related_queue_id']; ?>">
                                            <button type="submit" name="action" value="approve_swap" class="btn btn-success btn-sm">Approve</button>
                                            <button type="submit" name="action" value="decline_swap" class="btn btn-danger btn-sm">Decline</button>
                                        </form>
                                    <?php elseif ($notification['type'] === 'swap_request' && $notification['is_read']): ?>
                                        <span class="badge bg-secondary">Handled</span>
                                    <?php endif; ?>
                                    <?php if ($notification['sender_name']): ?>
                                        <p class="text-muted">
                                            From: <?php echo htmlspecialchars($notification['sender_name']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <?php echo date('M d, Y g:i A', strtotime($notification['created_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach;
        endif; ?>
    </div>
</div>

<?php if ($user_role === 'teacher'): ?>
    <h3>Student Messages</h3>
    <div class="mt-4">
        <div class="row g-4">
            <?php
            foreach ($student_messages as $msg): ?>
                <div class="col-12">
                    <div class="notification-card">
                        <strong>Message from <?php echo htmlspecialchars($msg['student_name']); ?>:</strong><br>
                        <span><?php echo htmlspecialchars($msg['message']); ?></span><br>
                        <?php if (!$msg['is_read']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="mark_read" value="<?php echo $msg['id']; ?>">
                                <button type="submit" class="btn btn-success btn-sm">Mark as Read</button>
                            </form>
                        <?php else: ?>
                            <span class="badge bg-secondary">Read</span>
                        <?php endif; ?>
                        <form method="POST" style="margin-top:8px;">
                            <input type="hidden" name="reply_to" value="<?php echo $msg['related_user_id']; ?>">
                            <input type="hidden" name="queue_id" value="<?php echo $msg['related_queue_id']; ?>">
                            <textarea name="reply_message" class="form-control mb-2" rows="2" placeholder="Reply..."></textarea>
                            <button type="submit" class="btn btn-primary btn-sm">Reply</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<script>
document.querySelectorAll('.reply-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        document.getElementById('reply-form-' + id).style.display = 'block';
    });
});
function hideReplyForm(id) {
    document.getElementById('reply-form-' + id).style.display = 'none';
}
document.querySelectorAll('.reply-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const notifId = this.getAttribute('data-id');
        const studentId = this.querySelector('input[name="student_id"]').value;
        const queueId = this.querySelector('input[name="queue_id"]').value;
        const message = this.querySelector('input[name="reply_message"]').value;
        const formData = new FormData();
        formData.append('ajax_reply', '1');
        formData.append('student_id', studentId);
        formData.append('queue_id', queueId);
        formData.append('reply_message', message);
        fetch('notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                form.reset();
                hideReplyForm(notifId);
                alert('Reply sent!');
            } else {
                alert('Error sending reply.');
            }
        });
    });
});
</script>
<?php
$content = ob_get_clean();
require 'layout.php';
?> 