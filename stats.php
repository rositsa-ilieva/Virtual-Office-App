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

// If a queue id is provided, redirect to statistics.php?id=...
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    header('Location: statistics.php?id=' . intval($_GET['id']));
    exit();
}

ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
.stats-title-page {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 0.7rem;
    margin-bottom: 2.2rem;
    margin-left: 0.2rem;
}
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 2.2rem;
    width: 100%;
    margin: 0 auto 2.5rem auto;
    max-width: 1100px;
}
.stats-card {
    background: linear-gradient(120deg, #f8fafc 60%, #e0e7ff 100%);
    border-radius: 22px;
    box-shadow: 0 8px 40px rgba(30,41,59,0.13), 0 1.5px 6px rgba(99,102,241,0.08);
    padding: 2.2rem 1.7rem 1.7rem 1.7rem;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-width: 0;
}
.stats-card h5 {
    font-size: 1.18rem;
    font-weight: 700;
    color: #334155;
    margin-bottom: 1.1rem;
}
.stats-badge {
    display: inline-block;
    padding: 0.3em 0.9em;
    border-radius: 12px;
    font-size: 0.98em;
    font-weight: 600;
    color: #fff;
    margin-left: 0.5em;
}
.stats-badge-active { background: #10b981; }
.stats-badge-inactive { background: #f59e42; }
.btn-primary {
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
    margin-top: 1.1rem;
}
.btn-primary:hover, .btn-primary:focus {
    background: linear-gradient(90deg, #2563eb 0%, #6366f1 100%);
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 4px 16px rgba(99,102,241,0.13);
}
@media (max-width: 700px) {
    .stats-grid { grid-template-columns: 1fr; }
}
</style>
<div class="stats-title-page">📈 Queue Statistics</div>
<div class="stats-grid">
<?php
$stmt = $pdo->prepare('SELECT * FROM queues WHERE teacher_id = ? ORDER BY start_time DESC');
$stmt->execute([$user_id]);
$queues = $stmt->fetchAll();
if (empty($queues)) {
    echo '<div style="grid-column:1/-1;text-align:center;color:#64748b;font-size:1.15rem;padding:2.5rem 0;">You have not created any queues yet.</div>';
} else {
    foreach ($queues as $queue) {
        echo '<div class="stats-card">';
        echo '<h5><i class="fa fa-layer-group"></i> ' . htmlspecialchars($queue['purpose']) . '</h5>';
        echo '<div style="color:#64748b;font-size:1.05rem;margin-bottom:1.1rem;"><strong>Start:</strong> ' . date('M d, Y g:i A', strtotime($queue['start_time'])) . '<br><strong>Duration:</strong> ' . $queue['default_duration'] . ' min<br><strong>Max Students:</strong> ' . ($queue['max_students'] ?? '-') . '<br><strong>Status:</strong> ' . ($queue['is_active'] ? '<span class="stats-badge stats-badge-active">Active</span>' : '<span class="stats-badge stats-badge-inactive">Inactive</span>') . '</div>';
        echo '<a href="statistics.php?id=' . $queue['id'] . '" class="btn-primary">View Statistics</a>';
        echo '</div>';
    }
}
?>
</div>
<?php
$content = ob_get_clean();
require 'layout.php';
?> 