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

// If a queue_id is provided, show the management table for that queue
$queue_id = isset($_GET['queue_id']) ? intval($_GET['queue_id']) : null;

ob_start();
if ($queue_id) {
    // Load queue info
    $stmt = $pdo->prepare('SELECT * FROM queues WHERE id = ? AND teacher_id = ?');
    $stmt->execute([$queue_id, $user_id]);
    $queue = $stmt->fetch();
    if (!$queue) {
        echo '<div class="alert alert-danger">Queue not found or access denied.</div>';
    } else {
        // Handle actions (start meeting, mark done, etc.)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['entry_id'])) {
            $entry_id = intval($_POST['entry_id']);
            if ($_POST['action'] === 'start_meeting') {
                // Mark student as in_meeting and set started_at
                $stmt = $pdo->prepare('UPDATE queue_entries SET status = "in_meeting", started_at = NOW() WHERE id = ? AND queue_id = ?');
                $stmt->execute([$entry_id, $queue_id]);
            } elseif ($_POST['action'] === 'end_meeting') {
                // Mark student as done and set ended_at
                $stmt = $pdo->prepare('UPDATE queue_entries SET status = "done", ended_at = NOW() WHERE id = ? AND queue_id = ?');
                $stmt->execute([$entry_id, $queue_id]);
            } elseif ($_POST['action'] === 'skip') {
                // Mark student as skipped
                $stmt = $pdo->prepare('UPDATE queue_entries SET status = "skipped", ended_at = NOW() WHERE id = ? AND queue_id = ?');
                $stmt->execute([$entry_id, $queue_id]);
            }
            // Reorder positions for waiting students
            $pdo->exec("SET @pos = 0;");
            $stmt = $pdo->prepare('UPDATE queue_entries SET position = (@pos:=@pos+1) WHERE queue_id = ? AND status = "waiting" ORDER BY position');
            $stmt->execute([$queue_id]);
            // After handling actions, check if the queue should be marked as ended
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM queue_entries WHERE queue_id = ? AND status IN ('waiting', 'in_meeting')");
            $stmt_check->execute([$queue_id]);
            $remaining = $stmt_check->fetchColumn();
            if ($remaining == 0) {
                $stmt_end = $pdo->prepare("UPDATE queues SET is_active = 0 WHERE id = ?");
                $stmt_end->execute([$queue_id]);
            }
            header('Location: manage-queues.php?queue_id=' . $queue_id);
            exit();
        }
        // Get all students currently waiting or in meeting
        $sql = "SELECT qe.*, u.name as student_name FROM queue_entries qe JOIN users u ON qe.student_id = u.id WHERE qe.queue_id = ? AND qe.status IN ('waiting', 'in_meeting') ORDER BY qe.position ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$queue_id]);
        $entries = $stmt->fetchAll();
        echo '<h2>Manage Queue: ' . htmlspecialchars($queue['purpose']) . '</h2>';
        echo '<div class="mb-3">';
        if (!empty($queue['meeting_link'])) {
            echo '<a href="' . htmlspecialchars($queue['meeting_link']) . '" target="_blank" class="btn btn-success">Open Meeting Link</a> ';
        }
        echo '<a href="manage-queues.php" class="btn btn-secondary">Back to My Queues</a></div>';
        echo '<div class="table-responsive"><table class="table table-bordered align-middle"><thead class="table-light"><tr><th>Position</th><th>Name</th><th>Status</th><th>Action</th></tr></thead><tbody>';
        if (empty($entries)) {
            echo '<tr><td colspan="4" class="text-center">No students currently in queue.</td></tr>';
        } else {
            foreach ($entries as $entry) {
                echo '<tr>';
                echo '<td>' . $entry['position'] . '</td>';
                echo '<td>' . htmlspecialchars($entry['student_name']) . '</td>';
                echo '<td>' . ucfirst($entry['status']) . '</td>';
                echo '<td>';
                if ($entry['status'] === 'waiting') {
                    echo '<form method="POST" style="display:inline-block;"><input type="hidden" name="entry_id" value="' . $entry['id'] . '"><button type="submit" name="action" value="start_meeting" class="btn btn-primary btn-sm">Start Meeting</button></form> ';
                    echo '<form method="POST" style="display:inline-block;margin-left:4px;"><input type="hidden" name="entry_id" value="' . $entry['id'] . '"><button type="submit" name="action" value="skip" class="btn btn-warning btn-sm">Skip</button></form>';
                } elseif ($entry['status'] === 'in_meeting') {
                    echo '<form method="POST" style="display:inline-block;"><input type="hidden" name="entry_id" value="' . $entry['id'] . '"><button type="submit" name="action" value="end_meeting" class="btn btn-success btn-sm">End Meeting</button></form>';
                }
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table></div>';
    }
} else {
    // List all queues for this teacher
    echo '<h2>Manage Queues</h2>';
    echo '<div class="mt-4"><div class="row g-4">';
    $stmt = $pdo->prepare('SELECT * FROM queues WHERE teacher_id = ? ORDER BY start_time DESC');
    $stmt->execute([$user_id]);
    $queues = $stmt->fetchAll();
    if (empty($queues)) {
        echo '<div class="col-12"><div class="alert alert-info">You have not created any queues yet.</div></div>';
    } else {
        foreach ($queues as $queue) {
            echo '<div class="col-md-6"><div class="card shadow-sm"><div class="card-body">';
            echo '<h5 class="card-title">' . htmlspecialchars($queue['purpose']) . '</h5>';
            echo '<p class="card-text"><strong>Start:</strong> ' . date('M d, Y g:i A', strtotime($queue['start_time'])) . '<br><strong>Duration:</strong> ' . $queue['default_duration'] . ' min<br><strong>Max Students:</strong> ' . ($queue['max_students'] ?? '-') . '<br><strong>Status:</strong> ' . ($queue['is_active'] ? '<span class="text-success">Active</span>' : '<span class="text-danger">Inactive</span>') . '</p>';
            echo '<a href="manage-queues.php?queue_id=' . $queue['id'] . '" class="btn btn-primary btn-sm">Manage</a> ';
            echo '<a href="statistics.php?id=' . $queue['id'] . '" class="btn btn-outline-secondary btn-sm">Statistics</a>';
            echo '</div></div></div>';
        }
    }
    echo '</div></div>';
}
$content = ob_get_clean();
require 'layout.php';
?> 