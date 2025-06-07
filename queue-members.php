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

?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Queue Members - Virtual Office Queue</title>
    <link rel='stylesheet' href='style.css'>
</head>
<body>
    <div class='container'>
        <nav class='navbar'>
            <h1>Queue Members: <?php echo htmlspecialchars($queue['purpose']); ?></h1>
            <div class='nav-links'>
                <a href='index.php' class='btn btn-secondary'>Dashboard</a>
                <a href='logout.php' class='btn btn-danger'>Logout</a>
            </div>
        </nav>
        <div class='members-container'>
            <h2>Current Members</h2>
            <?php
            $show_end_button = false;
            foreach ($members as $entry) {
                if ($entry['student_id'] == $user_id && $entry['status'] === 'in_meeting') {
                    $show_end_button = true;
                    break;
                }
            }
            ?>
            <?php if ($show_end_button): ?>
                <form method="POST" style="margin-bottom: 1em;">
                    <button type="submit" name="end_meeting" class="btn btn-success">End My Meeting</button>
                </form>
            <?php endif; ?>
            <?php if (empty($members)): ?>
                <p>No students are currently in this queue.</p>
            <?php else: ?>
                <table class='schedule-table'>
                    <thead>
                        <tr>
                            <th>Position</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Estimated Start Time</th>
                            <th>Comment</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $entry): ?>
                            <tr>
                                <td><?php echo $entry['position']; ?></td>
                                <td><?php echo htmlspecialchars($entry['student_name']); ?></td>
                                <td><?php echo ucfirst($entry['status']); ?></td>
                                <td><?php echo $entry['estimated_start_time'] ? date('g:i A', strtotime($entry['estimated_start_time'])) : '-'; ?></td>
                                <td>
                                    <?php if ($entry['is_comment_public']): ?>
                                        <?php echo htmlspecialchars($entry['comment']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($entry['student_id'] != $user_id && $entry['status'] === 'waiting'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="request_swap" value="1">
                                            <input type="hidden" name="swap_with" value="<?php echo $entry['student_id']; ?>">
                                            <button type="submit" name="request_swap" class="btn btn-secondary">Request Swap</button>
                                        </form>
                                    <?php elseif (isset($_SESSION['swap_request']) && $_SESSION['swap_request']['to'] == $user_id && $entry['student_id'] == $user_id): ?>
                                        <form method="POST" style="display:inline;">
                                            <button type="submit" name="approve_swap" class="btn btn-primary">Approve Swap</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <?php
            // Show Mark as Away/Return button for logged-in student
            foreach ($members as $entry) {
                if ($entry['student_id'] == $user_id) {
                    if ($entry['status'] === 'waiting') {
                        echo '<form method="POST" style="margin-bottom:1em;"><button type="submit" name="mark_away" class="btn btn-warning">Mark as Away</button></form>';
                    } elseif ($entry['status'] === 'away') {
                        echo '<form method="POST" style="margin-bottom:1em;"><button type="submit" name="return_queue" class="btn btn-primary">Return to Queue</button></form>';
                    }
                    break;
                }
            }
            ?>
        </div>

        <!-- Notifications Section -->
        <?php if (!empty($notifications)): ?>
            <div class="notifications-container">
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card">
                        <div class="notification-content">
                            <p><strong><?php echo htmlspecialchars($notification['from_user_name']); ?></strong> 
                               <?php echo htmlspecialchars($notification['message']); ?></p>
                            <?php if ($notification['type'] === 'swap_request'): ?>
                                <div class="notification-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="approve_swap">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                        <input type="hidden" name="queue_id" value="<?php echo $notification['related_queue_id']; ?>">
                                        <input type="hidden" name="from_user_id" value="<?php echo $notification['related_user_id']; ?>">
                                        <button type="submit" class="btn btn-success">Approve</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="decline_swap">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                        <input type="hidden" name="queue_id" value="<?php echo $notification['related_queue_id']; ?>">
                                        <input type="hidden" name="from_user_id" value="<?php echo $notification['related_user_id']; ?>">
                                        <button type="submit" class="btn btn-danger">Decline</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 