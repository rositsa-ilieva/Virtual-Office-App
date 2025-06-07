<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: index.php');
    exit;
}

$queue_id = $_GET['id'] ?? null;
if (!$queue_id) {
    header('Location: index.php');
    exit;
}

// Get queue information
$stmt = $pdo->prepare('SELECT * FROM queues WHERE id = ? AND teacher_id = ?');
$stmt->execute([$queue_id, $_SESSION['user_id']]);
$queue = $stmt->fetch();

if (!$queue) {
    header('Location: index.php');
    exit;
}

// Handle actions
$show_start_form = null;
$custom_start_time_for_estimation = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $entry_id = $_POST['entry_id'] ?? null;

    if ($entry_id && $action) {
        switch ($action) {
            case 'show_start_form':
                $show_start_form = $entry_id;
                break;
            case 'start':
                $stmt = $pdo->prepare('UPDATE queue_entries SET status = "in_meeting", started_at = NOW() WHERE id = ? AND queue_id = ?');
                $stmt->execute([$entry_id, $queue_id]);
                $custom_start_time_for_estimation = date('Y-m-d H:i:s');
                break;
            case 'end':
                $stmt = $pdo->prepare('UPDATE queue_entries SET status = "done", ended_at = NOW() WHERE id = ? AND queue_id = ?');
                $stmt->execute([$entry_id, $queue_id]);
                break;
            case 'skip':
                $stmt = $pdo->prepare('UPDATE queue_entries SET status = "skipped" WHERE id = ? AND queue_id = ?');
                $stmt->execute([$entry_id, $queue_id]);
                break;
            case 'remove':
                $stmt = $pdo->prepare('DELETE FROM queue_entries WHERE id = ? AND queue_id = ?');
                $stmt->execute([$entry_id, $queue_id]);
                break;
        }

        // After any status change, update positions for all waiting students
        $stmt = $pdo->prepare('
            SET @pos = 0;
            UPDATE queue_entries 
            SET position = (@pos:=@pos+1) 
            WHERE queue_id = ? AND status = "waiting" 
            ORDER BY joined_at ASC;
        ');
        $stmt->execute([$queue_id]);
    }
}

// Get queue entries with student names
$sql = "SELECT qe.*, u.name as student_name 
        FROM queue_entries qe 
        JOIN users u ON qe.student_id = u.id 
        WHERE qe.queue_id = ? 
        AND qe.status IN ('waiting', 'in_meeting')
        ORDER BY 
            CASE 
                WHEN qe.status = 'in_meeting' THEN 1
                WHEN qe.status = 'waiting' THEN 2
                ELSE 3
            END,
            qe.position";
$stmt = $pdo->prepare($sql);
$stmt->execute([$queue_id]);
$entries = $stmt->fetchAll();

// Check if there is a student currently in a meeting
$in_meeting_entry = null;
foreach ($entries as $e) {
    if ($e['status'] === 'in_meeting') {
        $in_meeting_entry = $e;
        break;
    }
}

// Calculate estimated times for waiting students
$meeting_duration = $queue['default_duration'] ?? 15; // Default to 15 minutes if not set

// Use the custom start time of the current meeting as the base for estimation
if ($in_meeting_entry && $in_meeting_entry['started_at']) {
    $base_time = new DateTime($in_meeting_entry['started_at']);
    $base_position = $in_meeting_entry['position'];
} elseif (!empty($queue['start_time'])) {
    $base_time = new DateTime($queue['start_time']);
    $base_position = 1;
} else {
    $base_time = new DateTime();
    $base_position = 1;
}

foreach ($entries as &$entry) {
    if ($entry['status'] === 'waiting') {
        $estimated_time = clone $base_time;
        $offset = ($entry['position'] - $base_position) * $meeting_duration;
        if ($offset > 0) {
            $estimated_time->add(new DateInterval('PT' . $offset . 'M'));
        }
        $entry['estimated_start_time'] = $estimated_time->format('Y-m-d H:i:s');
    }
}
unset($entry);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Queue - Virtual Office Queue</title>
    <link rel="stylesheet" href="style.css">
    <script>
        function confirmAction(action, studentName) {
            const messages = {
                start: `Start meeting with ${studentName}?`,
                end: `End meeting with ${studentName}?`,
                skip: `Skip ${studentName}?`,
                remove: `Remove ${studentName} from the queue?`
            };
            return confirm(messages[action]);
        }
    </script>
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <h1>Manage Queue: <?php echo htmlspecialchars($queue['purpose']); ?></h1>
            <div class="nav-links">
                <a href="statistics.php?id=<?php echo $queue_id; ?>" class="btn btn-secondary">View Statistics</a>
                <a href="index.php" class="btn btn-secondary">Dashboard</a>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </nav>

        <div class="queue-container">
            <div class="queue-header">
                <h2>Current Queue</h2>
                <?php if ($queue['is_automatic']): ?>
                    <span class="badge badge-info">Automatic Time Slots</span>
                <?php endif; ?>
            </div>

            <div class="queue-list">
                <?php if (empty($entries)): ?>
                    <p class="no-entries">No students in queue</p>
                <?php else: ?>
                    <?php foreach ($entries as $entry): ?>
                        <div class="queue-item">
                            <div class="student-info">
                                <span class="student-name"><?php echo htmlspecialchars($entry['student_name']); ?></span>
                                <?php if ($entry['status'] === 'waiting'): ?>
                                    <span class="position">Position: <?php echo $entry['position']; ?></span>
                                <?php endif; ?>
                                <?php if ($entry['comment']): ?>
                                    <span class="comment"><?php echo htmlspecialchars($entry['comment']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="status-badge status-<?php echo $entry['status']; ?>">
                                <?php echo ucfirst($entry['status']); ?>
                            </div>
                            <?php if ($entry['status'] === 'waiting'): ?>
                                <div class="estimated-time">
                                    Est. Start: <?php echo date('g:i A', strtotime($entry['estimated_start_time'])); ?>
                                </div>
                            <?php endif; ?>
                            <div class="actions">
                                <?php if ($entry['status'] === 'waiting'): ?>
                                    <?php if (!$in_meeting_entry): ?>
                                        <?php if ($show_start_form == $entry['id']): ?>
                                            <form method="POST" style="display:inline-block; margin-right:8px;">
                                                <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                                <input type="hidden" name="action" value="start">
                                                <button type="submit" class="btn btn-primary">Confirm Start</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline-block;" onsubmit="return confirmAction('start', '<?php echo htmlspecialchars($entry['student_name']); ?>')">
                                                <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                                <input type="hidden" name="action" value="show_start_form">
                                                <button type="submit" class="btn btn-primary">Start Meeting</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button class="btn btn-primary" disabled>Start Meeting</button>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmAction('skip', '<?php echo htmlspecialchars($entry['student_name']); ?>')">
                                        <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                        <input type="hidden" name="action" value="skip">
                                        <button type="submit" class="btn btn-warning">Skip</button>
                                    </form>
                                <?php elseif ($entry['status'] === 'in_meeting'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmAction('end', '<?php echo htmlspecialchars($entry['student_name']); ?>')">
                                        <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                        <input type="hidden" name="action" value="end">
                                        <button type="submit" class="btn btn-success">End Meeting</button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirmAction('remove', '<?php echo htmlspecialchars($entry['student_name']); ?>')">
                                    <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <button type="submit" class="btn btn-danger">Remove</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
