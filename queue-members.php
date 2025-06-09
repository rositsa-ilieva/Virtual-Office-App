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
<link rel="stylesheet" href="style.css">
<div style="display:flex;justify-content:center;align-items:center;min-height:100vh;background:#f4f7fb;">
  <div style="background:#eef1fd;border-radius:32px;box-shadow:0 8px 40px rgba(99,102,241,0.10);padding:2.5rem 2.5rem 2.2rem 2.5rem;max-width:1100px;width:100%;margin:2.5rem auto;">
    <div style="display:flex;flex-direction:column;align-items:center;gap:1.2rem;">
      <div style="display:flex;align-items:center;gap:1.1rem;margin-bottom:0.2rem;">
        <span style="background:#e0e7ff;border-radius:50%;padding:0.7rem;display:flex;align-items:center;justify-content:center;"><i class="fa fa-layer-group" style="font-size:2.1rem;color:#6366f1;"></i></span>
        <span style="font-size:2.1rem;font-weight:800;color:#222;letter-spacing:-0.5px;">Manage Queue: <?php echo htmlspecialchars($queue['purpose']); ?></span>
      </div>
      <div style="display:flex;gap:1.2rem;margin-bottom:0.7rem;">
        <a href="<?php echo htmlspecialchars($queue['meeting_link'] ?? '#'); ?>" target="_blank" class="btn" style="background:#6366f1;color:#fff;font-size:1.18rem;font-weight:700;padding:0.85em 2.2em;border-radius:18px;box-shadow:0 2px 12px rgba(99,102,241,0.10);border:none;">Open Meeting Link</a>
        <a href="my-queues.php" class="btn" style="background:#e0e7ff;color:#6366f1;font-size:1.18rem;font-weight:700;padding:0.85em 2.2em;border-radius:18px;box-shadow:none;">Back to My Queues</a>
      </div>
    </div>
    <div style="margin-top:2.2rem;">
      <table style="width:100%;border-collapse:separate;border-spacing:0;background:#fff;border-radius:22px;box-shadow:0 4px 24px rgba(99,102,241,0.10);overflow:hidden;">
        <thead>
          <tr style="background:#e0e7ff;font-size:1.13rem;font-weight:700;color:#222;">
            <th style="padding:1.2em 1.3em;text-align:left;border-top-left-radius:18px;">Position</th>
            <th style="padding:1.2em 1.3em;text-align:left;">Name</th>
            <th style="padding:1.2em 1.3em;text-align:left;">Specialization</th>
            <th style="padding:1.2em 1.3em;text-align:left;">Status</th>
            <th style="padding:1.2em 1.3em;text-align:left;">Comment</th>
            <th style="padding:1.2em 1.3em;text-align:left;">Estimated Start Time</th>
            <th style="padding:1.2em 1.3em;text-align:left;border-top-right-radius:18px;">Action</th>
          </tr>
        </thead>
        <tbody>
<?php if (empty($members)): ?>
          <tr><td colspan="7" style="text-align:center;color:#64748b;font-size:1.15rem;padding:2.5rem 0;">No students currently in queue.</td></tr>
<?php else:
    foreach ($members as $entry):
        $isMe = $entry['student_id'] == $user_id;
        $status = strtolower($entry['status']);
        $statusClass = $status === 'waiting' ? 'waiting' : ($status === 'in_meeting' ? 'in_meeting' : ($status === 'away' ? 'away' : 'other'));
            $statusColor = $status === 'waiting' ? '#6366f1' : ($status === 'in_meeting' ? '#2563eb' : ($status === 'away' ? '#f59e42' : '#64748b'));
        $isSwapPending = ($pending_swap_with !== null);
        $isThisPending = ($pending_swap_with !== null && $entry['student_id'] == $pending_swap_with);
            $spec_stmt = $pdo->prepare('SELECT specialization FROM users WHERE id = ?');
            $spec_stmt->execute([$entry['student_id']]);
            $spec = $spec_stmt->fetchColumn();
        ?>
          <tr<?php if ($isMe) echo ' style="background:#f4f7fb;"'; ?>>
            <td style="padding:1.1em 1.3em;font-weight:600;font-size:1.09rem;"> <?php echo $entry['position']; ?> </td>
            <td style="padding:1.1em 1.3em;font-size:1.09rem;"> <?php echo htmlspecialchars($entry['student_name']); ?> </td>
            <td style="padding:1.1em 1.3em;font-size:1.09rem;"> <?php echo htmlspecialchars($spec); ?> </td>
            <td style="padding:1.1em 1.3em;">
              <span style="display:inline-block;background:<?php echo $statusColor; ?>20;color:<?php echo $statusColor; ?>;font-weight:700;font-size:1.07rem;padding:0.45em 1.3em;border-radius:16px;min-width:90px;text-align:center;letter-spacing:0.01em;">
                <?php echo ucfirst($status); ?>
              </span>
            </td>
            <td style="padding:1.1em 1.3em;max-width:180px;overflow-wrap:break-word;font-size:1.07rem;"> <?php echo ($entry['is_comment_public'] || $isMe) ? htmlspecialchars($entry['comment']) : '-'; ?> </td>
            <td style="padding:1.1em 1.3em;font-size:1.07rem;white-space:nowrap;"> <?php echo $entry['estimated_start_time'] ? date('g:i A', strtotime($entry['estimated_start_time'])) : '-'; ?> </td>
            <td style="padding:1.1em 1.3em;">
              <div style="display:flex;gap:0.7rem;justify-content:center;align-items:center;">
            <?php if ($isMe): ?>
                <?php if ($entry['status'] === 'waiting'): ?>
                    <form method="POST" style="display:inline-block;"><button type="submit" name="mark_away" style="background:#fbbf24;color:#fff;font-size:1.09rem;font-weight:700;padding:0.7em 1.7em;border-radius:14px;border:none;box-shadow:0 2px 8px rgba(251,191,36,0.10);">Mark Away</button></form>
                <?php elseif ($entry['status'] === 'away'): ?>
                    <form method="POST" style="display:inline-block;"><button type="submit" name="return_queue" style="background:#6366f1;color:#fff;font-size:1.09rem;font-weight:700;padding:0.7em 1.7em;border-radius:14px;border:none;box-shadow:0 2px 8px rgba(99,102,241,0.10);">Return</button></form>
                <?php endif; ?>
            <?php elseif ($entry['status'] === 'waiting' && !$isMe): ?>
                <form method="POST" style="display:inline-block;">
                    <input type="hidden" name="swap_with" value="<?php echo $entry['student_id']; ?>">
                    <?php if ($isSwapPending): ?>
                      <button type="button" style="background:#6366f1;color:#fff;font-size:1.09rem;font-weight:700;padding:0.7em 1.7em;border-radius:14px;border:none;opacity:0.7;cursor:not-allowed;">
                            <?php if ($isThisPending): ?>
                          Pending <span style="margin-left:0.5em;font-size:0.98em;color:#6366f1;vertical-align:middle;">⏳</span>
                            <?php else: ?>
                          Request Swap
                            <?php endif; ?>
                        </button>
                    <?php else: ?>
                      <button type="submit" name="request_swap" style="background:#6366f1;color:#fff;font-size:1.09rem;font-weight:700;padding:0.7em 1.7em;border-radius:14px;border:none;box-shadow:0 2px 8px rgba(99,102,241,0.10);">Request Swap</button>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
require 'layout.php'; 