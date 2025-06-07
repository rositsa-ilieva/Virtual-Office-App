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
    $is_comment_public = isset($_POST['is_comment_public']) ? 1 : 0;

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
            $success = 'Successfully joined the queue!';
        } else {
            $error = 'Failed to join queue. Please try again.';
        }
    } catch (PDOException $e) {
        $error = 'An error occurred. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Queue - Virtual Office Queue</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <h1>Join Queue</h1>
            <div class="nav-links">
                <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </nav>

        <div class="form-container">
            <h2>Join Queue: <?php echo htmlspecialchars($queue['purpose']); ?></h2>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="comment">Comment (Optional):</label>
                    <textarea id="comment" name="comment" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Comment Visibility:</label>
                    <label><input type="radio" name="is_comment_public" value="1" checked> Public (visible to all)</label>
                    <label><input type="radio" name="is_comment_public" value="0"> Private (only teacher can see)</label>
                </div>
                <button type="submit" class="btn">Join Queue</button>
            </form>
        </div>
    </div>
</body>
</html>
