<?php
// handle-swap.php
include 'db.php';
session_start();

$current_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['swap_id'], $_POST['action'])) {
    $swap_id = intval($_POST['swap_id']);
    $action = $_POST['action'];

    // Use PDO for all DB operations
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM swap_requests WHERE id = ? AND receiver_id = ? AND status = 'pending'");
    $stmt->execute([$swap_id, $current_user_id]);
    $swap = $stmt->fetch();

    if ($swap) {
        $queue_id = $swap['queue_id'];
        $sender_id = $swap['sender_id'];
        $receiver_id = $swap['receiver_id'];

        if ($action === 'accept') {
            try {
                $pdo->beginTransaction();
                // Fetch queue entries for both users
                $stmt = $pdo->prepare("SELECT id, position FROM queue_entries WHERE queue_id = ? AND student_id IN (?, ?)");
                $stmt->execute([$queue_id, $sender_id, $receiver_id]);
                $entries = $stmt->fetchAll();
                if (count($entries) == 2) {
                    $id1 = $entries[0]['id'];
                    $pos1 = $entries[0]['position'];
                    $id2 = $entries[1]['id'];
                    $pos2 = $entries[1]['position'];
                    // Swap positions
                    $stmt = $pdo->prepare("UPDATE queue_entries SET position = CASE WHEN id = ? THEN ? WHEN id = ? THEN ? END WHERE id IN (?, ?)");
                    $stmt->execute([$id1, $pos2, $id2, $pos1, $id1, $id2]);
                }
                // Update swap request status
                $stmt = $pdo->prepare("UPDATE swap_requests SET status = 'accepted' WHERE id = ?");
                $stmt->execute([$swap_id]);

                // Recalculate estimated_start_time for all waiting students in this queue
                $stmt = $pdo->prepare('SELECT default_duration, start_time FROM queues WHERE id = ?');
                $stmt->execute([$queue_id]);
                $queue = $stmt->fetch();
                $meeting_duration = $queue['default_duration'] ?? 15;
                $base_time = null;
                $base_position = 1;
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
                // Get all waiting entries ordered by position
                $stmt = $pdo->prepare('SELECT id, position FROM queue_entries WHERE queue_id = ? AND status = "waiting" ORDER BY position ASC');
                $stmt->execute([$queue_id]);
                $entries = $stmt->fetchAll();
                foreach ($entries as $entry) {
                    $estimated = clone $base_time;
                    $offset = ($entry['position'] - $base_position) * $meeting_duration;
                    if ($offset > 0) {
                        $estimated->add(new DateInterval('PT' . $offset . 'M'));
                    }
                    $stmt2 = $pdo->prepare('UPDATE queue_entries SET estimated_start_time = ? WHERE id = ?');
                    $stmt2->execute([$estimated->format('Y-m-d H:i:s'), $entry['id']]);
                }
                $pdo->commit();
                $_SESSION['message'] = "Swap successful: your positions have been updated.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['message'] = "Swap failed: " . $e->getMessage();
            }
        } elseif ($action === 'decline') {
            $stmt = $pdo->prepare("UPDATE swap_requests SET status = 'declined' WHERE id = ?");
            $stmt->execute([$swap_id]);
            $_SESSION['message'] = "Swap request declined.";
        }
    } else {
        $_SESSION['message'] = "Invalid swap request or already handled.";
    }
} else {
    $_SESSION['message'] = "Invalid request.";
}

header("Location: my-queue.php");
exit();
