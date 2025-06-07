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

// Get queue entries with student names (active)
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

// Get past students (done/skipped)
$sql_past = "SELECT qe.*, u.name as student_name 
        FROM queue_entries qe 
        JOIN users u ON qe.student_id = u.id 
        WHERE qe.queue_id = ? 
        AND qe.status IN ('done', 'skipped')
        ORDER BY qe.ended_at DESC, qe.position ASC";
$stmt = $pdo->prepare($sql_past);
$stmt->execute([$queue_id]);
$past_entries = $stmt->fetchAll();

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

        <!-- Sticky Queue Info Bar -->
        <div class="queue-info-bar" style="position:sticky;top:0;z-index:10;background:#fff;padding:18px 24px 12px 24px;border-radius:12px;box-shadow:0 2px 8px rgba(40,83,107,0.07);margin-bottom:24px;display:flex;align-items:center;gap:32px;flex-wrap:wrap;">
            <div>
                <strong>Queue:</strong> <?php echo htmlspecialchars($queue['purpose']); ?>
                <?php if (!empty($queue['description'])): ?>
                    <span style="color:#888;font-style:italic;margin-left:12px;">(<?php echo htmlspecialchars($queue['description']); ?>)</span>
                <?php endif; ?>
            </div>
            <div><strong>Type:</strong> <?php echo htmlspecialchars($queue['meeting_type']); ?></div>
            <div><strong>Default Duration:</strong> <?php echo htmlspecialchars($queue['default_duration']); ?> min</div>
            <div><strong>Status:</strong> <?php echo $queue['is_active'] ? '<span style=\'color:#22C55E\'>Active</span>' : '<span style=\'color:#F87171\'>Inactive</span>'; ?></div>
            <?php if ($queue['is_automatic']): ?>
                <span class="badge badge-info">Automatic Time Slots</span>
            <?php endif; ?>
        </div>

        <!-- Current Students Section -->
        <div class="section-box" style="background:#F8FAFC;padding:24px 18px 18px 18px;border-radius:12px;margin-bottom:32px;box-shadow:0 2px 8px rgba(40,83,107,0.04);">
            <h2 style="margin-top:0;margin-bottom:18px;">Current Students</h2>
            <div class="queue-list">
                <?php if (empty($entries)): ?>
                    <p class="no-entries">No students in queue</p>
                <?php else: ?>
                    <?php foreach ($entries as $entry): ?>
                        <div class="queue-item" style="background:#fff;border-radius:8px;padding:18px 16px;margin-bottom:14px;box-shadow:0 1px 4px rgba(40,83,107,0.06);display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap;">
                            <div class="student-info" style="flex:1;min-width:180px;">
                                <span class="student-name" style="font-weight:600;font-size:1.1em;">👤 <?php echo htmlspecialchars($entry['student_name']); ?></span>
                                <?php if ($entry['status'] === 'waiting'): ?>
                                    <span class="position" style="margin-left:10px;color:#2563EB;">Position: <?php echo $entry['position']; ?></span>
                                <?php endif; ?>
                                <?php if ($entry['comment']): ?>
                                    <span class="comment" style="display:block;color:#888;margin-top:4px;"><?php echo htmlspecialchars($entry['comment']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="status-badge status-<?php echo $entry['status']; ?>" style="min-width:90px;text-align:center;">
                                <?php echo ucfirst($entry['status']); ?>
                            </div>
                            <?php if ($entry['status'] === 'waiting'): ?>
                                <div class="estimated-time" style="min-width:120px;color:#555;">
                                    <i class="fas fa-clock"></i> Est. Start: <?php echo date('g:i A', strtotime($entry['estimated_start_time'])); ?>
                                </div>
                            <?php endif; ?>
                            <div class="actions" style="display:flex;gap:10px;align-items:center;">
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
                                <?php if ($entry['status'] === 'waiting'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmAction('skip', '<?php echo htmlspecialchars($entry['student_name']); ?>')">
                                        <input type="hidden" name="entry_id" value="<?php echo $entry['id']; ?>">
                                        <input type="hidden" name="action" value="skip">
                                        <button type="submit" class="btn btn-warning">Skip</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Past Students Section -->
        <div class="queue-list" style="margin-top:32px;">
            <h2>Past Students</h2>
            <?php if (empty($past_entries)): ?>
                <p class="no-entries">No past students.</p>
            <?php else: ?>
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Started</th>
                            <th>Ended</th>
                            <th>Duration</th>
                            <th>Comment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($past_entries as $entry): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($entry['student_name']); ?></td>
                                <td><?php echo ucfirst($entry['status']); ?></td>
                                <td><?php echo $entry['started_at'] ? date('M j, Y g:i A', strtotime($entry['started_at'])) : '-'; ?></td>
                                <td><?php echo $entry['ended_at'] ? date('M j, Y g:i A', strtotime($entry['ended_at'])) : '-'; ?></td>
                                <td>
                                    <?php
                                    if ($entry['started_at'] && $entry['ended_at']) {
                                        $start = strtotime($entry['started_at']);
                                        $end = strtotime($entry['ended_at']);
                                        $duration = round(($end - $start) / 60, 1);
                                        echo $duration . ' min';
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($entry['comment']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
