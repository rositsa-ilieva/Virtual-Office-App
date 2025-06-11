<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'student';
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Schedule - Virtual Office Queue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="css/queue-schedule.css" rel="stylesheet">
</head>
<body>
    <div class="upcoming-title">🗓️ Upcoming Meetings</div>
    <form method="GET" class="search-form">
        <input type="text" name="search" class="search-input" placeholder="Search by meeting name..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
        <?php if (!empty($_GET['search'])): ?>
            <a href="queue-schedule.php" class="clear-button">Clear</a>
        <?php endif; ?>
    </form>
    <div class="cards-container">
<?php
if ($user_role === 'student') {
    // For students: only show events not finished by the student and matching specialization/year
    $sql = "SELECT q.*, 
                   (SELECT COUNT(*) FROM queue_entries WHERE queue_id = q.id AND status = 'waiting') as waiting_count,
                   (SELECT position FROM queue_entries WHERE queue_id = q.id AND student_id = ?) as my_position,
                   (SELECT status FROM queue_entries WHERE queue_id = q.id AND student_id = ?) as my_status
            FROM queues q
            WHERE q.start_time > NOW() AND q.is_active = 1
            ORDER BY q.start_time ASC
            LIMIT 30";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id, $user_id]);
    $all_queues = $stmt->fetchAll();
    $upcoming_queues = array_filter($all_queues, function($queue) use ($user) {
        // Hide if student has status done, skipped, or completed
        if (in_array($queue['my_status'], ['done', 'skipped', 'completed'])) return false;
        // New filtering logic: specialization-year mapping
        if (!empty($queue['specialization_year_map'])) {
            $map = json_decode($queue['specialization_year_map'], true);
            if (!is_array($map)) return false;
            $spec = $user['specialization'] ?? '';
            $year = $user['year_of_study'] ?? '';
            return isset($map[$spec]) && in_array($year, $map[$spec]);
        } else {
            // Fallback to old logic if mapping not present
            $spec = $user['specialization'] ?? '';
            $year = $user['year_of_study'] ?? '';
            $specMatch = (empty($queue['target_specialization']) || $queue['target_specialization'] === 'All' || in_array($spec, explode(',', $queue['target_specialization'])));
            $yearMatch = (empty($queue['target_year']) || $queue['target_year'] === 'All' || $queue['target_year'] === $year);
            return $specMatch && $yearMatch;
        }
    });
} else {
    // For teachers: show all future, active events
    $sql = "SELECT q.*, 
                   (SELECT COUNT(*) FROM queue_entries WHERE queue_id = q.id AND status = 'waiting') as waiting_count
            FROM queues q
            WHERE q.start_time > NOW() AND q.is_active = 1
            ORDER BY q.start_time ASC
            LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $upcoming_queues = $stmt->fetchAll();
}

// Apply search filter if set
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search !== '') {
    $upcoming_queues = array_filter($upcoming_queues, function($queue) use ($search) {
        return stripos($queue['purpose'], $search) !== false;
    });
}

if (empty($upcoming_queues)): ?>
    <div class="col-12">
        <div class="alert alert-info">
            No upcoming meetings found.
        </div>
    </div>
<?php else:
    foreach ($upcoming_queues as $queue): ?>
        <div class="upcoming-card">
            <div class="upcoming-card-title"><i class="fa fa-calendar-alt"></i> <?php echo htmlspecialchars($queue['purpose']); ?></div>
            <div class="upcoming-card-meta"><i class="fa fa-chalkboard-teacher"></i> <?php 
                $teacher_stmt = $pdo->prepare('SELECT name FROM users WHERE id = ?');
                $teacher_stmt->execute([$queue['teacher_id']]);
                $teacher = $teacher_stmt->fetch();
                echo htmlspecialchars($teacher['name'] ?? '');
            ?></div>
            <div class="upcoming-card-meta"><i class="fa fa-clock"></i> <?php echo date('M d, Y', strtotime($queue['start_time'])); ?> &bull; <?php echo date('g:i A', strtotime($queue['start_time'])); ?></div>
            <?php if (!empty($queue['meeting_link'])): ?>
                <div class="upcoming-card-link"><i class="fa fa-link"></i> <a href="<?php echo htmlspecialchars($queue['meeting_link']); ?>" target="_blank" style="color:#2563eb;text-decoration:underline;word-break:break-all;">Meeting Link</a></div>
            <?php endif; ?>
            <?php if (!empty($queue['access_code'])): ?>
                <div class="upcoming-card-access"><i class="fa fa-key"></i> <span style="font-weight:600;letter-spacing:1px;"> <?php echo htmlspecialchars($queue['access_code']); ?></span></div>
            <?php endif; ?>
            <div class="upcoming-card-status"><i class="fa fa-hourglass-half"></i> Waiting</div>
            <div class="upcoming-card-actions">
                <?php if ($user_role === 'student' && empty($queue['my_status'])): ?>
                    <a href="join.php?id=<?php echo $queue['id']; ?>" class="btn-primary">
                        Join Queue
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach;
endif; ?>
</div>
<?php
$content = ob_get_clean();
require '../src/Includes/layout.php';
?> 
</body>
</html> 