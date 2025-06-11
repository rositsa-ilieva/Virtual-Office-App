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

// If a queue id is provided, redirect to statistics.php?id=...
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    header('Location: statistics.php?id=' . intval($_GET['id']));
    exit();
}

ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="css/stats.css">
<div class="stats-title-page">📈 Queue Statistics</div>
<?php
$stmt = $pdo->prepare('SELECT * FROM queues WHERE teacher_id = ? ORDER BY start_time DESC');
$stmt->execute([$user_id]);
$queues = $stmt->fetchAll();
$active_queues = array_filter($queues, function($q) { return $q['is_active']; });
$inactive_queues = array_filter($queues, function($q) { return !$q['is_active']; });
if (!empty($active_queues)) {
    echo '<h3 style="margin-bottom:1.2rem;color:#2563eb;">Active Queues</h3>';
    echo '<div class="stats-grid">';
    foreach ($active_queues as $queue) {
        echo '<div class="stats-card">';
        echo '<h5><i class="fa fa-layer-group"></i> ' . htmlspecialchars($queue['purpose']) . '</h5>';
        echo '<div style="color:#64748b;font-size:1.05rem;margin-bottom:1.1rem;"><strong>Start:</strong> ' . date('M d, Y g:i A', strtotime($queue['start_time'])) . '<br><strong>Duration:</strong> ' . $queue['default_duration'] . ' min<br><strong>Max Students:</strong> ' . ($queue['max_students'] ?? '-') . '<br><strong>Status:</strong> <span class="stats-badge stats-badge-active">Active</span></div>';
        echo '<a href="statistics.php?id=' . $queue['id'] . '" class="btn-primary">View Statistics</a>';
        echo '</div>';
    }
    echo '</div>';
}
if (!empty($inactive_queues)) {
    echo '<h3 style="margin:2.5rem 0 1.2rem 0;color:#64748b;">Inactive Queues</h3>';
    echo '<div class="stats-grid">';
    foreach ($inactive_queues as $queue) {
        echo '<div class="stats-card">';
        echo '<h5><i class="fa fa-layer-group"></i> ' . htmlspecialchars($queue['purpose']) . '</h5>';
        echo '<div style="color:#64748b;font-size:1.05rem;margin-bottom:1.1rem;"><strong>Start:</strong> ' . date('M d, Y g:i A', strtotime($queue['start_time'])) . '<br><strong>Duration:</strong> ' . $queue['default_duration'] . ' min<br><strong>Max Students:</strong> ' . ($queue['max_students'] ?? '-') . '<br><strong>Status:</strong> <span class="stats-badge stats-badge-inactive">Inactive</span></div>';
        echo '<a href="statistics.php?id=' . $queue['id'] . '" class="btn-primary">View Statistics</a>';
        echo '</div>';
    }
    echo '</div>';
}
?>
<?php
$content = ob_get_clean();
require '../src/Includes/layout.php';
?> 