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
<script>
// Disable approve/decline buttons after click for instant feedback
function disableSwapButtons(form, status) {
    var approveBtn = form.querySelector('button[name="action"][value="approve_swap"]');
    var declineBtn = form.querySelector('button[name="action"][value="decline_swap"]');
    if (approveBtn) {
        approveBtn.disabled = true;
        approveBtn.style.opacity = 0.6;
    }
    if (declineBtn) {
        declineBtn.disabled = true;
        declineBtn.style.opacity = 0.6;
    }
    // Show status label
    var statusSpan = document.createElement('span');
    statusSpan.style.marginLeft = '1em';
    statusSpan.style.fontWeight = '600';
    statusSpan.style.fontSize = '1.05em';
    if (status === 'approved') {
        statusSpan.innerHTML = '<span style="color:#10b981;">&#10003; Swap Accepted</span>';
    } else if (status === 'declined') {
        statusSpan.innerHTML = '<span style="color:#ef4444;">&#10007; Declined</span>';
    }
    form.parentNode.appendChild(statusSpan);
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.swap-action-form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            var action = e.submitter ? e.submitter.value : '';
            if (action === 'approve_swap') {
                e.preventDefault();
                var notificationId = form.querySelector('input[name="notification_id"]').value;
                var fromUserId = form.querySelector('input[name="from_user_id"]').value;
                var queueId = form.querySelector('input[name="queue_id"]').value;
                var formData = new URLSearchParams();
                formData.append('action', 'approve_swap');
                formData.append('notification_id', notificationId);
                formData.append('from_user_id', fromUserId);
                formData.append('queue_id', queueId);
                fetch('notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: formData.toString()
                }).then(function(res) {
                    // After swap, reload the queue table
                    loadQueueTable(queueId);
                    // Optionally, disable the buttons
                    disableSwapButtons(form, 'approved');
                });
            }
        });
    });
    document.querySelectorAll('.notification-delete-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var card = btn.closest('.notification-card');
            var notificationId = card.getAttribute('data-id');
            // Fade out
            card.style.transition = 'opacity 0.3s ease';
            card.style.opacity = '0';
            // AJAX delete
            fetch('delete-notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + encodeURIComponent(notificationId)
            }).then(function(res) {
                setTimeout(function() { card.remove(); }, 300);
            });
        });
    });
    // Function to load the queue table
    function loadQueueTable(queueId) {
        if (!queueId) return;
        fetch('queue-table.php?queue_id=' + encodeURIComponent(queueId))
            .then(res => res.text())
            .then(html => {
                document.getElementById('queue-table-container').innerHTML = html;
            });
    }
    // Optionally, load the table for the first notification's queue
    var firstQueueId = document.querySelector('.swap-action-form input[name="queue_id"]');
    if (firstQueueId) {
        loadQueueTable(firstQueueId.value);
    }
});
</script>
<style>
.notifications-container {
    max-width: 700px;
    margin: 0 auto;
    padding: 2rem 0;
}
.notifications-group-title {
    font-size: 1.08rem;
    font-weight: 700;
    color: #6366f1;
    margin: 2.2rem 0 1.1rem 0;
    letter-spacing: 0.01em;
}
.notification-card {
    background: linear-gradient(120deg, #f8fafc 60%, #e0e7ff 100%);
    border-radius: 18px;
    box-shadow: 0 4px 24px rgba(99,102,241,0.10), 0 1.5px 6px rgba(99,102,241,0.08);
    padding: 1.5rem 1.7rem 1.2rem 1.7rem;
    margin-bottom: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
    position: relative;
    transition: box-shadow 0.18s, transform 0.18s;
    min-height: 120px;
}
.notification-card:hover {
    box-shadow: 0 8px 32px rgba(99,102,241,0.18), 0 2px 12px rgba(99,102,241,0.10);
    transform: translateY(-2px) scale(1.01);
}
.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.2rem;
}
.notification-title {
    font-size: 1.13rem;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.notification-icon {
    font-size: 1.3rem;
    margin-right: 0.4rem;
}
.notification-body {
    font-size: 1.05rem;
    color: #334155;
    margin-bottom: 0.3rem;
    line-height: 1.6;
}
.notification-details {
    font-size: 0.98rem;
    color: #6366f1;
    margin-bottom: 0.2rem;
    font-weight: 500;
}
.notification-timestamp {
    font-size: 0.97rem;
    color: #64748b;
    font-weight: 400;
    margin-left: 0;
    margin-top: 0.7rem;
    white-space: nowrap;
    position: absolute;
    right: 1.7rem;
    bottom: 1.1rem;
    background: rgba(255,255,255,0.7);
    padding: 2px 10px;
    border-radius: 8px;
}
.notification-actions {
    margin-top: 0.5rem;
    display: flex;
    gap: 0.7rem;
    align-items: center;
}
.notification-badge {
    display: inline-block;
    background: #e0e7ff;
    color: #6366f1;
    font-size: 0.93rem;
    font-weight: 600;
    border-radius: 8px;
    padding: 3px 12px;
    margin-left: 0.5rem;
}
.notification-delete-btn {
    position: absolute;
    top: 14px;
    right: 18px;
    background: none;
    border: none;
    color: #64748b;
    font-size: 1.2rem;
    cursor: pointer;
    padding: 2px 6px;
    border-radius: 50%;
    transition: background 0.15s;
    z-index: 2;
}
.notification-delete-btn:hover {
    background: #e0e7ff;
    color: #ef4444;
}
@media (max-width: 600px) {
    .notifications-container { padding: 0.7rem 0; }
    .notification-card { padding: 1.1rem 0.6rem; }
    .notification-timestamp { right: 0.6rem; left: auto; bottom: 0.7rem; }
}
.notifications-title {
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
</style>
<div class="notifications-title">🔔 Notifications</div>
<div class="notifications-container">
<div class="mt-4">
    <div class="row g-4">
        <?php
        // Get notifications (use correct columns)
        $sql = "SELECT n.*, 
                       q.purpose as queue_purpose,
                       q.start_time as queue_start_time,
                       u.name as sender_name
                FROM notifications n
                LEFT JOIN queues q ON n.related_queue_id = q.id
                LEFT JOIN users u ON n.related_user_id = u.id
                WHERE n.user_id = ?
                ORDER BY n.created_at DESC
                LIMIT 50";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll();

        // Helper: icon for type
        function notificationTypeIcon($type) {
            switch ($type) {
                case 'swap_request': return '<span title="Swap Request" style="color:#f59e42;">&#x1F501;</span>';
                case 'swap_approved': return '<span title="Swap Approved" style="color:#10b981;">&#x2705;</span>';
                case 'swap_declined': return '<span title="Swap Declined" style="color:#ef4444;">&#x26A0;&#xFE0F;</span>';
                case 'teacher_reply': return '<span title="Teacher Reply" style="color:#6366f1;">&#x1F4AC;</span>';
                case 'student_message': return '<span title="Student Message" style="color:#2563eb;">&#x1F4E8;</span>';
                default: return '<span title="Notification" style="color:#6366f1;"><i class="fa fa-bell"></i></span>';
            }
        }
        // Helper: context message
        function notificationContext($type) {
            switch ($type) {
                case 'swap_request': return 'Position swap requested';
                case 'swap_approved': return 'Your swap request was approved';
                case 'swap_declined': return 'Your swap request was declined';
                case 'teacher_reply': return 'Reply from teacher';
                case 'student_message': return 'Message from student';
                default: return 'Notification';
            }
        }
        // Helper: group by date
        function groupNotificationsByDate($notifications) {
            $groups = [];
            foreach ($notifications as $n) {
                $date = date('Y-m-d', strtotime($n['created_at']));
                $today = date('Y-m-d');
                $yesterday = date('Y-m-d', strtotime('-1 day'));
                if ($date === $today) $label = 'Today';
                elseif ($date === $yesterday) $label = 'Yesterday';
                else $label = date('F j, Y', strtotime($n['created_at']));
                $groups[$label][] = $n;
            }
            return $groups;
        }
        $grouped = groupNotificationsByDate($notifications);
        if (empty($notifications)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No notifications found.
                </div>
            </div>
        <?php else:
            foreach ($grouped as $groupLabel => $groupList): ?>
                <div class="notifications-group-title"><?php echo htmlspecialchars($groupLabel); ?></div>
                <?php foreach ($groupList as $notification): ?>
                <div class="col-12">
                    <div class="notification-card" data-id="<?php echo $notification['id']; ?>">
                        <form method="POST" style="position:absolute;top:0;right:0;z-index:2;">
                            <input type="hidden" name="delete_notification" value="<?php echo $notification['id']; ?>">
                            <button type="submit" class="notification-delete-btn" title="Delete notification">&times;</button>
                        </form>
                        <div class="notification-header">
                            <div class="notification-title">
                                <span class="notification-icon"><?php echo notificationTypeIcon($notification['type']); ?></span>
                                <?php echo notificationContext($notification['type']); ?>
                            </div>
                        </div>
                        <?php if ($notification['queue_purpose']): ?>
                        <div class="notification-details">
                            <i class="fa fa-layer-group"></i> <b><?php echo htmlspecialchars($notification['queue_purpose']); ?></b>
                            <?php if ($notification['queue_start_time']): ?>
                                <span style="margin-left:1.1em;"><i class="fa fa-clock"></i> <?php echo date('M d, g:i A', strtotime($notification['queue_start_time'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div class="notification-body">
                            <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                        </div>
                        <div class="notification-actions">
                            <?php if ($notification['type'] === 'swap_request' && !$notification['is_read']): ?>
                                <form method="POST" class="d-inline swap-action-form">
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
                                <span class="text-muted" style="margin-left:0.7em;font-size:0.97em;">From: <?php echo htmlspecialchars($notification['sender_name']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="notification-timestamp">
                            <?php echo date('g:i A', strtotime($notification['created_at'])); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endforeach;
        endif; ?>
    </div>
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
                        <div class="notification-header">
                            <div class="notification-title">
                                <span class="notification-icon"><i class="fa fa-bell"></i></span>
                                <strong>Message from <?php echo htmlspecialchars($msg['student_name']); ?>:</strong>
                            </div>
                            <div class="notification-timestamp">
                                <?php echo date('M d, Y g:i A', strtotime($msg['created_at'])); ?>
                            </div>
                        </div>
                        <div class="notification-body">
                            <span><?php echo htmlspecialchars($msg['message']); ?></span>
                        </div>
                        <div class="notification-actions">
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
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<?php
$content = ob_get_clean();
require 'layout.php';
?> 