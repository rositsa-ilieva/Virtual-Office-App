<?php
session_start();
require_once 'config.php';
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
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="css/manage-queues.css">
<style>
.manage-queue-container {
    max-width: 1100px;
    margin: 2.5rem auto;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.manage-queue-card {
    background: linear-gradient(120deg, #f8fafc 60%, #e0e7ff 100%);
    border-radius: 22px;
    box-shadow: 0 8px 40px rgba(30,41,59,0.13), 0 1.5px 6px rgba(99,102,241,0.08);
    padding: 2.5rem 2.2rem 2rem 2.2rem;
    width: 100%;
    margin-bottom: 2.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.manage-queue-header {
    display: flex;
    align-items: center;
    gap: 1.1rem;
    margin-bottom: 1.2rem;
}
.manage-queue-header i {
    font-size: 2.2rem;
    color: #6366f1;
    background: #e0e7ff;
    border-radius: 50%;
    padding: 0.5rem;
    box-shadow: 0 2px 8px rgba(99,102,241,0.10);
}
.manage-queue-title {
    font-size: 1.6rem;
    font-weight: 700;
    color: #1e293b;
}
.manage-queue-actions {
    display: flex;
    gap: 1.2rem;
    margin-bottom: 1.5rem;
}
.btn-modern {
    padding: 0.7rem 1.5rem;
    border: none;
    border-radius: 14px;
    background: linear-gradient(90deg, #6366f1 0%, #2563eb 100%);
    color: #fff;
    font-size: 1.1rem;
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
.table-modern {
    width: 100%;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(99,102,241,0.07);
    overflow: hidden;
    margin-bottom: 2rem;
}
.table-modern th, .table-modern td {
    padding: 1rem 1.1rem;
    text-align: center;
    font-size: 1.05rem;
}
.table-modern thead th {
    background: #e0e7ff;
    color: #334155;
    font-weight: 600;
    border-bottom: 2px solid #cbd5e1;
}
.table-modern tbody tr {
    transition: background 0.15s;
}
.table-modern tbody tr:hover {
    background: #f1f5f9;
}
.status-badge {
    display: inline-block;
    padding: 0.3em 0.9em;
    border-radius: 12px;
    font-size: 0.98em;
    font-weight: 600;
    color: #fff;
}
.status-waiting { background: #6366f1; }
.status-in_meeting { background: #2563eb; }
.status-done { background: #10b981; }
.status-skipped { background: #f59e42; }
.status-other { background: #64748b; }
.manage-queue-title-page {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    margin-bottom: 2.2rem;
    margin-left: 0.2rem;
}
.queue-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 2.2rem;
    width: 100%;
    margin: 0 auto;
    max-width: 1100px;
}
.queue-card {
    background: linear-gradient(120deg, #f8fafc 60%, #e0e7ff 100%);
    border-radius: 22px;
    box-shadow: 0 8px 40px rgba(30,41,59,0.13), 0 1.5px 6px rgba(99,102,241,0.08);
    padding: 2.2rem 1.7rem 1.7rem 1.7rem;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-width: 0;
}
.queue-card .manage-queue-header {
    margin-bottom: 1.1rem;
}
.queue-card .manage-queue-title {
    font-size: 1.25rem;
    font-weight: 700;
}
.queue-card .status-badge {
    margin-bottom: 0.7rem;
}
.queue-card .btn-modern {
    margin-bottom: 0.7rem;
}
</style>
<?php
if ($queue_id) {
    // Load queue info
    $stmt = $pdo->prepare('SELECT * FROM queues WHERE id = ? AND teacher_id = ?');
    $stmt->execute([$queue_id, $user_id]);
    $queue = $stmt->fetch();
    if (!$queue) {
        echo '<div class="alert alert-danger">Queue not found or access denied.</div>';
    } else {
        // Handle actions (start meeting, mark done, etc.)
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) &&
            (
                isset($_POST['entry_id']) || $_POST['action'] === 'start_all_meetings' || $_POST['action'] === 'end_all_meetings'
            )
        ) {
            if ($_POST['action'] === 'start_all_meetings') {
                // Set all waiting students to in_meeting and set started_at
                $stmt = $pdo->prepare('UPDATE queue_entries SET status = "in_meeting", started_at = NOW() WHERE queue_id = ? AND status = "waiting"');
                $stmt->execute([$queue_id]);
            } elseif ($_POST['action'] === 'end_all_meetings') {
                // Set all in_meeting students to done and set ended_at
                $stmt = $pdo->prepare('UPDATE queue_entries SET status = "done", ended_at = NOW() WHERE queue_id = ? AND status = "in_meeting"');
                $stmt->execute([$queue_id]);
                // After ending all, redirect to main manage-queues page
                header('Location: manage-queues.php');
                exit();
            } else {
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
        $sql = "SELECT qe.*, u.name as student_name, u.specialization as student_specialization FROM queue_entries qe JOIN users u ON qe.student_id = u.id WHERE qe.queue_id = ? AND qe.status IN ('waiting', 'in_meeting') ORDER BY qe.position ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$queue_id]);
        $entries = $stmt->fetchAll();
        echo '<div class="manage-queue-container">';
        echo '<div class="manage-queue-card">';
        echo '<div class="manage-queue-header"><i class="fa fa-layer-group"></i><span class="manage-queue-title">Manage Queue: ' . htmlspecialchars($queue['purpose']) . '</span></div>';
        echo '<div class="manage-queue-actions">';
        if (!empty($queue['meeting_link'])) {
            echo '<a href="' . htmlspecialchars($queue['meeting_link']) . '" target="_blank" class="btn-modern">Open Meeting Link</a>';
        }
        echo '<a href="manage-queues.php" class="btn-modern" style="background:linear-gradient(90deg,#f1f5f9 0%,#6366f1 100%);color:#6366f1;">Back to My Queues</a>';
        echo '</div>';
        echo '<div class="table-responsive"><table class="table-modern"><thead><tr><th>Position</th><th>Name</th><th>Specialization</th><th>Status</th><th>Comment</th><th>Action</th></tr></thead><tbody>';
        if (empty($entries)) {
            echo '<tr><td colspan="6" class="text-center">No students currently in queue.</td></tr>';
        } else {
            foreach ($entries as $entry) {
                $statusClass = 'status-other';
                if ($entry['status'] === 'waiting') $statusClass = 'status-waiting';
                elseif ($entry['status'] === 'in_meeting') $statusClass = 'status-in_meeting';
                elseif ($entry['status'] === 'done') $statusClass = 'status-done';
                elseif ($entry['status'] === 'skipped') $statusClass = 'status-skipped';
                echo '<tr>';
                echo '<td>' . $entry['position'] . '</td>';
                echo '<td>' . htmlspecialchars($entry['student_name']) . '</td>';
                echo '<td>' . (!empty($entry['student_specialization']) ? htmlspecialchars($entry['student_specialization']) : 'Not set') . '</td>';
                echo '<td><span class="status-badge ' . $statusClass . '">' . ucfirst(str_replace('_', ' ', $entry['status'])) . '</span></td>';
                echo '<td>' . (!empty($entry['comment']) ? htmlspecialchars($entry['comment']) : '-') . '</td>';
                echo '<td>';
                if ($entry['status'] === 'waiting') {
                    echo '<form method="POST" style="display:inline-block;"><input type="hidden" name="entry_id" value="' . $entry['id'] . '"><button type="submit" name="action" value="start_meeting" class="btn-modern" style="padding:0.4em 1em;font-size:0.98em;">Start Meeting</button></form> ';
                    echo '<form method="POST" style="display:inline-block;margin-left:4px;"><input type="hidden" name="entry_id" value="' . $entry['id'] . '"><button type="submit" name="action" value="skip" class="btn-modern" style="background:linear-gradient(90deg,#f59e42 0%,#fbbf24 100%);color:#fff;padding:0.4em 1em;font-size:0.98em;">Skip</button></form>';
                } elseif ($entry['status'] === 'in_meeting') {
                    echo '<form method="POST" style="display:inline-block;"><input type="hidden" name="entry_id" value="' . $entry['id'] . '"><button type="submit" name="action" value="end_meeting" class="btn-modern" style="background:linear-gradient(90deg,#10b981 0%,#22d3ee 100%);color:#fff;padding:0.4em 1em;font-size:0.98em;">End Meeting</button></form>';
                }
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table></div>';
        // Optionally, add past students table here with same style
        echo '</div>';
        echo '</div>';
        // Add the buttons in the queue management view
        echo '<div style="display:flex;gap:1rem;margin-bottom:1.5rem;">';
        echo '<form method="POST"><input type="hidden" name="action" value="start_all_meetings"><button type="submit" class="btn-modern" style="background:#10b981;">Start Meeting with All</button></form>';
        echo '<form method="POST"><input type="hidden" name="action" value="end_all_meetings"><button type="submit" class="btn-modern" style="background:#ef4444;">End Meeting with All</button></form>';
        echo '</div>';
    }
} else {
    // List all queues for this teacher
    echo '<div class="manage-queue-title-page">🛠️ Manage Queues</div>';
    
    // First, update any past queues to inactive status
    $stmt = $pdo->prepare('UPDATE queues SET is_active = 0 WHERE teacher_id = ? AND end_time IS NOT NULL AND end_time < NOW()');
    $stmt->execute([$user_id]);
    
    // Get all queues
    $stmt = $pdo->prepare('SELECT * FROM queues WHERE teacher_id = ? ORDER BY start_time DESC');
    $stmt->execute([$user_id]);
    $queues = $stmt->fetchAll();
    
    // Filter queues based on is_active flag
    $active_queues = array_filter($queues, function($q) { 
        return $q['is_active'] == 1; 
    });
    $inactive_queues = array_filter($queues, function($q) { 
        return $q['is_active'] == 0; 
    });
    
    // Active Queues
    echo '<h3 style="margin-bottom:1.2rem;color:#2563eb;">Active Queues</h3>';
    echo '<div class="queue-grid">';
    if (empty($active_queues)) {
        echo '<div class="col-12"><div class="alert alert-info">No active queues.</div></div>';
    } else {
        foreach ($active_queues as $queue) {
            echo '<div class="queue-card">';
            echo '<div class="manage-queue-header"><i class="fa fa-layer-group"></i><span class="manage-queue-title">' . htmlspecialchars($queue['purpose']) . '</span></div>';
            echo '<div style="color:#64748b;font-size:1.05rem;margin-bottom:1.1rem;"><strong>Start:</strong> ' . date('M d, Y g:i A', strtotime($queue['start_time'])) . '<br><strong>Duration:</strong> ' . $queue['default_duration'] . ' min<br><strong>Max Students:</strong> ' . ($queue['max_students'] ?? '-') . '<br><strong>Status:</strong> <span class="status-badge status-done">Active</span></div>';
            echo '<a href="manage-queues.php?queue_id=' . $queue['id'] . '" class="btn-modern">Manage</a> ';
            echo '<a href="statistics.php?id=' . $queue['id'] . '" class="btn-modern" style="background:linear-gradient(90deg,#f1f5f9 0%,#6366f1 100%);color:#6366f1;">Statistics</a>';
            echo '</div>';
        }
    }
    echo '</div>';
    
    // Inactive Queues
    echo '<h3 style="margin:2.5rem 0 1.2rem 0;color:#64748b;">Inactive Queues</h3>';
    echo '<div class="queue-grid">';
    if (empty($inactive_queues)) {
        echo '<div class="col-12"><div class="alert alert-info">No inactive queues.</div></div>';
    } else {
        foreach ($inactive_queues as $queue) {
            $status_text = 'Inactive';
            $status_class = 'status-skipped';
            if (!empty($queue['end_time']) && strtotime($queue['end_time']) < time()) {
                $status_text = 'Past Date';
                $status_class = 'status-other';
            }
            
            echo '<div class="queue-card">';
            echo '<div class="manage-queue-header"><i class="fa fa-layer-group"></i><span class="manage-queue-title">' . htmlspecialchars($queue['purpose']) . '</span></div>';
            echo '<div style="color:#64748b;font-size:1.05rem;margin-bottom:1.1rem;"><strong>Start:</strong> ' . date('M d, Y g:i A', strtotime($queue['start_time'])) . '<br><strong>Duration:</strong> ' . $queue['default_duration'] . ' min<br><strong>Max Students:</strong> ' . ($queue['max_students'] ?? '-') . '<br><strong>Status:</strong> <span class="status-badge ' . $status_class . '">' . $status_text . '</span></div>';
            echo '<a href="manage-queues.php?queue_id=' . $queue['id'] . '" class="btn-modern">Manage</a> ';
            echo '<a href="statistics.php?id=' . $queue['id'] . '" class="btn-modern" style="background:linear-gradient(90deg,#f1f5f9 0%,#6366f1 100%);color:#6366f1;">Statistics</a>';
            echo '</div>';
        }
    }
    echo '</div>';
}
?>
<?php
$content = ob_get_clean();
require '../src/Includes/layout.php';
?> 