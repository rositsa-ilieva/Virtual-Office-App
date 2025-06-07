<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$queue_id = $_GET['id'] ?? 0;

// Get queue entry
$sql = "SELECT qe.*, q.purpose 
        FROM queue_entries qe 
        JOIN queues q ON qe.queue_id = q.id 
        WHERE qe.queue_id = ? AND qe.student_id = ? AND qe.status IN ('waiting', 'in_meeting')";
$stmt = executeQuery($sql, [$queue_id, $_SESSION['user_id']]);
$entry = $stmt->fetch();

if (!$entry) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Delete the queue entry
        $sql = "DELETE FROM queue_entries WHERE id = ?";
        executeQuery($sql, [$entry['id']]);

        // Reorder remaining positions
        $sql = "SET @pos = 0;
                UPDATE queue_entries 
                SET position = (@pos:=@pos+1) 
                WHERE queue_id = ? AND status = 'waiting' 
                ORDER BY position";
        executeQuery($sql, [$queue_id]);

        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        $error = "Failed to leave queue. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Queue - Virtual Office Queue</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="nav">
        <div class="container nav-container">
            <h1>Virtual Office Queue</h1>
            <div class="nav-links">
                <a href="index.php">Back to Dashboard</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="form-container">
            <h2>Leave Queue</h2>
            
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="queue-info">
                <p>Queue: <?php echo htmlspecialchars($entry['purpose']); ?></p>
                <p>Your Status: <span class="status-badge status-<?php echo $entry['status']; ?>">
                    <?php echo ucfirst($entry['status']); ?>
                </span></p>
                <?php if ($entry['position']): ?>
                    <p>Your Position: <?php echo $entry['position']; ?></p>
                <?php endif; ?>
            </div>

            <form method="POST" action="">
                <p class="warning">Are you sure you want to leave this queue? This action cannot be undone.</p>
                <div class="form-actions">
                    <button type="submit" class="button danger">Yes, Leave Queue</button>
                    <a href="index.php" class="button secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 