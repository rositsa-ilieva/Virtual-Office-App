<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$queue_id = $_GET['id'] ?? null;
if (!$queue_id) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle end meeting and away/return actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['end_meeting'])) {
        $stmt = $pdo->prepare('UPDATE queue_entries SET status = "done", ended_at = NOW() WHERE queue_id = ? AND student_id = ? AND status = "in_meeting"');
        $stmt->execute([$queue_id, $user_id]);
        header('Location: queue-members.php?id=' . $queue_id);
        exit();
    }
    if (isset($_POST['mark_away'])) {
        $stmt = $pdo->prepare('UPDATE queue_entries SET status = "away" WHERE queue_id = ? AND student_id = ? AND status = "waiting"');
        $stmt->execute([$queue_id, $user_id]);
        header('Location: queue-members.php?id=' . $queue_id);
        exit();
    }
    if (isset($_POST['return_queue'])) {
        $stmt = $pdo->prepare('UPDATE queue_entries SET status = "waiting" WHERE queue_id = ? AND student_id = ? AND status = "away"');
        $stmt->execute([$queue_id, $user_id]);
        header('Location: queue-members.php?id=' . $queue_id);
        exit();
    }
    if (isset($_POST['request_swap']) && isset($_POST['swap_with'])) {
        $swap_with_id = (int)$_POST['swap_with'];
        
        try {
            // Create notification for the swap request
            $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, message, related_queue_id, related_user_id) VALUES (?, "swap_request", ?, ?, ?)');
            $stmt->execute([
                $swap_with_id,
                "wants to swap positions with you in the queue",
                $queue_id,
                $user_id
            ]);
            
            // Redirect to refresh the page
            header('Location: queue-members.php?id=' . $queue_id);
            exit();
        } catch (PDOException $e) {
            // If table doesn't exist, create it
            if ($e->getCode() == '42S02') {
                $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    type ENUM('swap_request', 'swap_approved', 'swap_declined') NOT NULL,
                    message TEXT NOT NULL,
                    related_queue_id INT NOT NULL,
                    related_user_id INT NOT NULL,
                    is_read BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id),
                    FOREIGN KEY (related_queue_id) REFERENCES queues(id),
                    FOREIGN KEY (related_user_id) REFERENCES users(id)
                )");
                
                // Try the insert again
                $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, message, related_queue_id, related_user_id) VALUES (?, "swap_request", ?, ?, ?)');
                $stmt->execute([
                    $swap_with_id,
                    "wants to swap positions with you in the queue",
                    $queue_id,
                    $user_id
                ]);
                
                // Redirect to refresh the page
                header('Location: queue-members.php?id=' . $queue_id);
                exit();
            } else {
                throw $e;
            }
        }
    }
    if (isset($_POST['approve_swap'])) {
        if (isset($_SESSION['swap_request']) && $_SESSION['swap_request']['to'] == $user_id && $_SESSION['swap_request']['queue_id'] == $queue_id) {
            // Get both entries
            $from_id = $_SESSION['swap_request']['from'];
            $to_id = $_SESSION['swap_request']['to'];
            $stmt = $pdo->prepare('SELECT id, position FROM queue_entries WHERE queue_id = ? AND student_id IN (?, ?)');
            $stmt->execute([$queue_id, $from_id, $to_id]);
            $entries = $stmt->fetchAll();
            if (count($entries) == 2) {
                $pos1 = $entries[0]['position'];
                $id1 = $entries[0]['id'];
                $pos2 = $entries[1]['position'];
                $id2 = $entries[1]['id'];
                // Swap positions
                $pdo->prepare('UPDATE queue_entries SET position = ? WHERE id = ?')->execute([$pos2, $id1]);
                $pdo->prepare('UPDATE queue_entries SET position = ? WHERE id = ?')->execute([$pos1, $id2]);
            }
            unset($_SESSION['swap_request']);
            header('Location: queue-members.php?id=' . $queue_id);
            exit();
        }
    }
}

// Handle swap approval/decline
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['notification_id'])) {
    $action = $_POST['action'];
    $notification_id = $_POST['notification_id'];
    $from_user_id = $_POST['from_user_id'];
    
    if ($action === 'approve_swap') {
        // Get both entries
        $stmt = $pdo->prepare('SELECT id, position FROM queue_entries WHERE queue_id = ? AND student_id IN (?, ?)');
        $stmt->execute([$queue_id, $from_user_id, $user_id]);
        $entries = $stmt->fetchAll();
        
        if (count($entries) == 2) {
            // Start transaction
            $pdo->beginTransaction();
            try {
                // Get the positions
                $pos1 = $entries[0]['position'];
                $id1 = $entries[0]['id'];
                $pos2 = $entries[1]['position'];
                $id2 = $entries[1]['id'];
                
                // Swap positions
                $stmt = $pdo->prepare('UPDATE queue_entries SET position = ? WHERE id = ?');
                $stmt->execute([$pos2, $id1]);
                $stmt->execute([$pos1, $id2]);
                
                // Create notification for the requester
                $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, message, related_queue_id, related_user_id) VALUES (?, "swap_approved", ?, ?, ?)');
                $stmt->execute([$from_user_id, "Your position swap request was approved", $queue_id, $user_id]);
                
                // Commit transaction
                $pdo->commit();
            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                throw $e;
            }
        }
    } elseif ($action === 'decline_swap') {
        // Create notification for the requester
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, message, related_queue_id, related_user_id) VALUES (?, "swap_declined", ?, ?, ?)');
        $stmt->execute([$from_user_id, "Your position swap request was declined", $queue_id, $user_id]);
    }
    
    // Mark the notification as read
    $stmt = $pdo->prepare('UPDATE notifications SET is_read = TRUE WHERE id = ?');
    $stmt->execute([$notification_id]);
    
    header('Location: queue-members.php?id=' . $queue_id);
    exit();
}

// Get queue info
$stmt = $pdo->prepare('SELECT * FROM queues WHERE id = ?');
$stmt->execute([$queue_id]);
$queue = $stmt->fetch();
if (!$queue) {
    header('Location: index.php');
    exit();
}

// Get all students in the waiting room, in a meeting, or away for this queue
$sql = "SELECT qe.position, u.name as student_name, qe.status, qe.started_at, qe.ended_at, qe.estimated_start_time, qe.student_id, qe.comment, qe.is_comment_public
        FROM queue_entries qe
        JOIN users u ON qe.student_id = u.id
        WHERE qe.queue_id = ? AND qe.status IN ('waiting', 'in_meeting', 'away')
        ORDER BY qe.position ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$queue_id]);
$members = $stmt->fetchAll();

// Get past meetings (done/skipped)
$sql_past = "SELECT qe.position, u.name as student_name, qe.status, qe.started_at, qe.ended_at, qe.student_id, qe.comment, qe.is_comment_public
        FROM queue_entries qe
        JOIN users u ON qe.student_id = u.id
        WHERE qe.queue_id = ? AND qe.status IN ('done', 'skipped')
        ORDER BY qe.ended_at DESC, qe.position ASC";
$stmt = $pdo->prepare($sql_past);
$stmt->execute([$queue_id]);
$past_meetings = $stmt->fetchAll();

// Calculate estimated start times for all waiting students if not set
$meeting_duration = $queue['default_duration'] ?? 15; // in minutes

// Find the last in-meeting student's start time and position
$stmt2 = $pdo->prepare("SELECT started_at, position FROM queue_entries WHERE queue_id = ? AND status = 'in_meeting' ORDER BY started_at DESC LIMIT 1");
$stmt2->execute([$queue_id]);
$last_meeting = $stmt2->fetch();
if ($last_meeting && $last_meeting['started_at']) {
    $base_time = new DateTime($last_meeting['started_at']);
    $base_position = $last_meeting['position'];
} elseif (!empty($queue['start_time'])) {
    $base_time = new DateTime($queue['start_time']);
    $base_position = 1;
} else {
    $base_time = new DateTime();
    $base_position = 1;
}

foreach ($members as $i => &$entry) {
    if ($entry['status'] === 'waiting') {
        $estimated = clone $base_time;
        $offset = ($entry['position'] - $base_position) * $meeting_duration;
        if ($offset > 0) {
            $estimated->add(new DateInterval('PT' . $offset . 'M'));
        }
        $entry['estimated_start_time'] = $estimated->format('Y-m-d H:i:s');
    }
}
unset($entry);

// Get notifications
$notifications = [];
try {
    $stmt = $pdo->prepare('
        SELECT n.*, u.name as from_user_name, q.purpose as queue_purpose 
        FROM notifications n
        JOIN users u ON n.related_user_id = u.id
        JOIN queues q ON n.related_queue_id = q.id
        WHERE n.user_id = ? AND n.is_read = FALSE
        ORDER BY n.created_at DESC
    ');
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    // If table doesn't exist, notifications will be empty
    if ($e->getCode() != '42S02') {
        throw $e;
    }
}

// Find if the current user has a pending swap request as sender
$pending_swap_with = null;
// Check for outgoing swap requests (where current user is sender and status is pending)
$pending_swap_stmt = $pdo->prepare('SELECT receiver_id FROM swap_requests WHERE queue_id = ? AND sender_id = ? AND status = "pending" LIMIT 1');
$pending_swap_stmt->execute([$queue_id, $user_id]);
if ($row = $pending_swap_stmt->fetch()) {
    $pending_swap_with = $row['receiver_id'];
}

// Calculate dynamic position for the current user
function getStudentPosition($members, $user_id) {
    $pos = 1;
    foreach ($members as $entry) {
        if ($entry['student_id'] == $user_id) {
            return $pos;
        }
        if (in_array($entry['status'], ['waiting'])) {
            $pos++;
        }
    }
    return $pos;
}

$current_user_position = getStudentPosition($members, $user_id);

$activePage = 'profile'; // in profile.php
$activePage = 'events'; // in queue-schedule.php
$activePage = 'notifications'; // in notifications.php
$activePage = 'my-queues'; // in my-queues.php
$activePage = 'history'; // in history.php
$activePage = 'create-room'; // in create-room.php

ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
.queue-title {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin: 2.5rem 0 1.2rem 0;
    letter-spacing: 0.01em;
}
.queue-position-badge {
    display: inline-flex;
    align-items: center;
    background: linear-gradient(90deg, #6366f1 0%, #e0e7ff 100%);
    color: #fff;
    font-weight: 600;
    font-size: 1.08rem;
    border-radius: 16px;
    padding: 0.5em 1.3em;
    margin-bottom: 2.2rem;
    box-shadow: 0 2px 8px rgba(99,102,241,0.10);
    gap: 0.7em;
}
.queue-members-list {
    display: flex;
    flex-direction: column;
    gap: 1.3rem;
    width: 100%;
    max-width: 900px;
    margin: 0 auto 2.5rem auto;
}
.queue-member-card {
    background: linear-gradient(120deg, #f8fafc 60%, #e0e7ff 100%);
    border-radius: 18px;
    box-shadow: 0 4px 24px rgba(30,41,59,0.10), 0 1.5px 6px rgba(99,102,241,0.08);
    padding: 1.3rem 1.5rem 1.1rem 1.5rem;
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
    min-width: 0;
}
.queue-member-card.me {
    border: 2.5px solid #6366f1;
    background: linear-gradient(120deg, #e0e7ff 60%, #f8fafc 100%);
}
.queue-member-info {
    flex: 1 1 220px;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    min-width: 0;
}
.queue-member-row {
    display: flex;
    align-items: center;
    gap: 1.1rem;
    flex-wrap: wrap;
}
.queue-member-label {
    font-size: 1.01rem;
    color: #334155;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.4em;
}
.queue-member-status {
    font-size: 1.01rem;
    font-weight: 600;
    margin-left: 0.7rem;
    display: flex;
    align-items: center;
    gap: 0.4em;
}
.queue-member-status.waiting { color: #6366f1; }
.queue-member-status.in_meeting { color: #2563eb; }
.queue-member-status.away { color: #f59e42; }
.queue-member-status.other { color: #64748b; }
.queue-member-actions {
    display: flex;
    gap: 0.7rem;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
}
.btn-modern {
    padding: 0.6rem 1.3rem;
    border: none;
    border-radius: 14px;
    background: linear-gradient(90deg, #6366f1 0%, #2563eb 100%);
    color: #fff;
    font-size: 1.05rem;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(99,102,241,0.08);
    transition: background 0.2s, transform 0.15s, box-shadow 0.18s;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}
.btn-modern:hover, .btn-modern:focus {
    background: linear-gradient(90deg, #2563eb 0%, #6366f1 100%);
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 4px 16px rgba(99,102,241,0.13);
}
.btn-modern-warning {
    background: linear-gradient(90deg, #f59e42 0%, #fbbf24 100%);
    color: #fff;
}
.btn-modern-success {
    background: linear-gradient(90deg, #10b981 0%, #22d3ee 100%);
    color: #fff;
}
.btn-modern-danger {
    background: linear-gradient(90deg, #ef4444 0%, #f87171 100%);
    color: #fff;
}
@media (max-width: 700px) {
    .queue-members-list { gap: 0.7rem; }
    .queue-member-card { flex-direction: column; align-items: flex-start; gap: 0.7rem; padding: 1.1rem 0.7rem; }
}
</style>
<div class="queue-title"><i class="fa fa-users"></i> Queue: <?php echo htmlspecialchars($queue['purpose']); ?></div>
<div class="queue-position-badge"><i class="fa fa-thumbtack"></i> Your Position: <?php echo $current_user_position; ?></div>
<?php
// Show pending swap requests for the current user
$pending_swaps = $pdo->prepare('SELECT n.*, u.name as from_user_name FROM notifications n JOIN users u ON n.related_user_id = u.id WHERE n.user_id = ? AND n.type = "swap_request" AND n.related_queue_id = ? AND n.is_read = 0 ORDER BY n.created_at DESC');
$pending_swaps->execute([$user_id, $queue_id]);
$swap_requests = $pending_swaps->fetchAll();
if (!empty($swap_requests)) {
    echo '<div class="alert alert-info mb-4"><strong>Swap Requests:</strong><ul class="mb-0">';
    foreach ($swap_requests as $swap) {
        echo '<li>' . htmlspecialchars($swap['from_user_name']) . ' wants to swap positions with you.';
        echo '<form method="POST" style="display:inline-block;margin-left:10px;">';
        echo '<input type="hidden" name="notification_id" value="' . $swap['id'] . '">';
        echo '<input type="hidden" name="from_user_id" value="' . $swap['related_user_id'] . '">';
        echo '<button type="submit" name="action" value="approve_swap" class="btn-modern btn-modern-success btn-sm"><i class="fa fa-check"></i> Approve</button> ';
        echo '<button type="submit" name="action" value="decline_swap" class="btn-modern btn-modern-danger btn-sm"><i class="fa fa-times"></i> Decline</button>';
        echo '</form></li>';
    }
    echo '</ul></div>';
}
?>
<div class="queue-members-list">
<?php if (empty($members)): ?>
    <div style="text-align:center;color:#64748b;font-size:1.15rem;padding:2.5rem 0;">No students currently in queue.</div>
<?php else:
    foreach ($members as $entry):
        $isMe = $entry['student_id'] == $user_id;
        $status = strtolower($entry['status']);
        $statusIcon = $status === 'waiting' ? '⏳' : ($status === 'in_meeting' ? '🟢' : ($status === 'away' ? '🚶' : '🕓'));
        $statusClass = $status === 'waiting' ? 'waiting' : ($status === 'in_meeting' ? 'in_meeting' : ($status === 'away' ? 'away' : 'other'));
        $isSwapPending = ($pending_swap_with !== null);
        $isThisPending = ($pending_swap_with !== null && $entry['student_id'] == $pending_swap_with);
?>
    <div class="queue-member-card<?php if ($isMe) echo ' me'; ?>">
        <div class="queue-member-info">
            <div class="queue-member-row">
                <span class="queue-member-label"><i class="fa fa-thumbtack"></i> Position: <?php echo $entry['position']; ?></span>
                <span class="queue-member-label"><i class="fa fa-user"></i> <?php echo htmlspecialchars($entry['student_name']); ?></span>
                <span class="queue-member-status <?php echo $statusClass; ?>"><?php echo $statusIcon . ' ' . ucfirst(str_replace('_', ' ', $status)); ?></span>
            </div>
            <div class="queue-member-row">
                <span class="queue-member-label"><i class="fa fa-clock"></i> Estimated Start: <?php echo $entry['estimated_start_time'] ? date('g:i A', strtotime($entry['estimated_start_time'])) : '-'; ?></span>
                <span class="queue-member-label"><i class="fa fa-comment"></i> <?php echo ($entry['is_comment_public'] || $isMe) ? htmlspecialchars($entry['comment']) : '-'; ?></span>
            </div>
        </div>
        <div class="queue-member-actions">
            <?php if ($isMe): ?>
                <?php if ($entry['status'] === 'waiting'): ?>
                    <form method="POST" style="display:inline-block;"><button type="submit" name="mark_away" class="btn-modern btn-modern-warning btn-sm"><i class="fa fa-walking"></i> Mark Away</button></form>
                <?php elseif ($entry['status'] === 'away'): ?>
                    <form method="POST" style="display:inline-block;"><button type="submit" name="return_queue" class="btn-modern btn-primary btn-sm"><i class="fa fa-undo"></i> Return</button></form>
                <?php elseif ($entry['status'] === 'in_meeting'): ?>
                    <form method="POST" style="display:inline-block;"><button type="submit" name="end_meeting" class="btn-modern btn-modern-success btn-sm"><i class="fa fa-check"></i> End Meeting</button></form>
                <?php endif; ?>
            <?php elseif ($entry['status'] === 'waiting' && !$isMe): ?>
                <form method="POST" style="display:inline-block;">
                    <input type="hidden" name="swap_with" value="<?php echo $entry['student_id']; ?>">
                    <?php if ($isSwapPending): ?>
                        <button type="button" class="btn-modern btn-primary btn-sm" disabled style="opacity:0.7;cursor:not-allowed;">
                            <?php if ($isThisPending): ?>
                                <i class="fa fa-clock"></i> Pending
                                <span style="margin-left:0.5em;font-size:0.98em;color:#6366f1;vertical-align:middle;">⏳ Waiting for Response</span>
                            <?php else: ?>
                                <i class="fa fa-random"></i> Request Swap
                            <?php endif; ?>
                        </button>
                    <?php else: ?>
                        <button type="submit" name="request_swap" class="btn-modern btn-primary btn-sm"><i class="fa fa-random"></i> Request Swap</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; endif; ?>
</div>
<?php
$content = ob_get_clean();
require 'layout.php'; 