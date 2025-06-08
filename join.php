<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$queue_id = $_GET['id'] ?? null;

if (!$queue_id) {
    header('Location: index.php');
    exit();
}

// Check if queue exists and is active
$stmt = $pdo->prepare('SELECT * FROM queues WHERE id = ? AND is_active = TRUE');
$stmt->execute([$queue_id]);
$queue = $stmt->fetch();

if (!$queue) {
    header('Location: index.php');
    exit();
}

// Restrict join by specialization/year for students
if ($_SESSION['user_role'] === 'student') {
    $stmt = $pdo->prepare('SELECT specialization, year_of_study FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $student = $stmt->fetch();
    $allowed = (
        (in_array($student['specialization'], explode(',', $queue['target_specialization'])) || $queue['target_specialization'] === 'All') &&
        ($queue['target_year'] === 'All' || $queue['target_year'] === $student['year_of_study'])
    );
    if (!$allowed) {
        echo '<div class="alert alert-danger">You are not allowed to join this meeting. It is only for ' . htmlspecialchars($queue['target_specialization']) . ' (' . htmlspecialchars($queue['target_year']) . ').</div>';
        exit();
    }
}

// Check if user is already in queue
$stmt = $pdo->prepare('SELECT * FROM queue_entries WHERE queue_id = ? AND student_id = ?');
$stmt->execute([$queue_id, $user_id]);
if ($stmt->fetch()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $comment = $_POST['comment'] ?? '';
    $is_comment_public = isset($_POST['is_comment_public']) ? (int)$_POST['is_comment_public'] : 0;

    // Get next position
    $stmt = $pdo->prepare('SELECT MAX(position) as max_pos FROM queue_entries WHERE queue_id = ?');
    $stmt->execute([$queue_id]);
    $result = $stmt->fetch();
    $position = ($result['max_pos'] ?? 0) + 1;

    // Calculate estimated start time if queue is automatic
    $estimated_start_time = null;
    if ($queue['is_automatic']) {
        $stmt = $pdo->prepare('SELECT COUNT(*) as waiting_count FROM queue_entries WHERE queue_id = ? AND status = "waiting"');
        $stmt->execute([$queue_id]);
        $result = $stmt->fetch();
        $waiting_count = $result['waiting_count'];
        $estimated_start_time = date('Y-m-d H:i:s', strtotime("+{$waiting_count} minutes"));
    }

    try {
        $sql = "INSERT INTO queue_entries (queue_id, student_id, comment, is_comment_public, position, estimated_start_time) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([$queue_id, $user_id, $comment, $is_comment_public, $position, $estimated_start_time])) {
            header('Location: queue-members.php?id=' . $queue_id);
            exit();
        } else {
            $error = 'Failed to join queue. Please try again.';
        }
    } catch (PDOException $e) {
        $error = 'An error occurred. Please try again.';
    }
}

ob_start();
?>
<h2>Join Queue: <?php echo htmlspecialchars($queue['purpose']); ?></h2>
<div class="row mt-4">
    <div class="col-md-7">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="comment" class="form-label">Comment (Optional):</label>
                        <textarea id="comment" name="comment" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="is_comment_public" class="form-label">Comment Visibility:</label>
                        <select id="is_comment_public" name="is_comment_public" class="form-select" required>
                            <option value="0" selected>Visible to teacher only</option>
                            <option value="1">Visible to all (students + teacher)</option>
                        </select>
                    </div>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">Join Queue</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Queue Information</h5>
                <p><strong>Purpose:</strong> <?php echo htmlspecialchars($queue['purpose']); ?></p>
                <p><strong>Start Time:</strong> <?php echo date('M d, Y g:i A', strtotime($queue['start_time'])); ?></p>
                <p><strong>Duration:</strong> <?php echo $queue['default_duration'] ?? 15; ?> min per student</p>
                <p><strong>Max Students:</strong> <?php echo $queue['max_students'] ?? '-'; ?></p>
                <p><strong>Meeting Link:</strong> <a href="<?php echo htmlspecialchars($queue['meeting_link']); ?>" target="_blank"><?php echo htmlspecialchars($queue['meeting_link']); ?></a></p>
                <?php if (!empty($queue['access_code'])): ?>
                    <p><strong>Access Code:</strong> <?php echo htmlspecialchars($queue['access_code']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require 'layout.php';
?>
