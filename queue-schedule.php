<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'student';
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

ob_start();
?>
<style>
.upcoming-title {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    text-align: center;
    margin: 2.5rem 0 2rem 0;
    letter-spacing: 0.01em;
}
.upcoming-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 2.2rem;
    width: 100%;
    max-width: 980px;
    margin: 0 auto 2.5rem auto;
    justify-content: center;
}
.upcoming-card {
    background: linear-gradient(120deg, #f8fafc 60%, #e0e7ff 100%);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(30,41,59,0.13), 0 1.5px 6px rgba(99,102,241,0.08);
    padding: 2.2rem 1.7rem 1.7rem 1.7rem;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    text-align: left;
    transition: box-shadow 0.22s, transform 0.22s;
    position: relative;
}
.upcoming-card:hover {
    box-shadow: 0 12px 40px rgba(99,102,241,0.18), 0 2px 12px rgba(99,102,241,0.10);
    transform: translateY(-4px) scale(1.025);
}
.upcoming-card-title {
    font-size: 1.18rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.3rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.upcoming-card-queue {
    font-size: 1.05rem;
    color: #6366f1;
    margin-bottom: 0.5rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.upcoming-card-time {
    font-size: 1.04rem;
    color: #334155;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.upcoming-card-status {
    font-size: 1.01rem;
    font-weight: 600;
    color: #2563eb;
    margin-bottom: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.upcoming-card-actions {
    margin-top: 0.7rem;
    display: flex;
    gap: 0.7rem;
    flex-wrap: wrap;
}
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
}
.btn-primary:hover, .btn-primary:focus {
    background: linear-gradient(90deg, #2563eb 0%, #6366f1 100%);
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 4px 16px rgba(99,102,241,0.13);
}
@media (max-width: 900px) {
    .upcoming-cards { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
    .upcoming-title { font-size: 1.3rem; margin: 1.2rem 0 1rem 0; }
    .upcoming-cards { gap: 1.2rem; }
    .upcoming-card { padding: 1.2rem 0.7rem; }
}
</style>
<div class="upcoming-title">Upcoming Meetings</div>
<div class="upcoming-cards">
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
            <div class="upcoming-card-queue"><i class="fa fa-users"></i> <?php echo htmlspecialchars($queue['purpose']); ?></div>
            <div class="upcoming-card-time"><i class="fa fa-clock"></i> <?php echo date('g:i A', strtotime($queue['start_time'])); ?></div>
            <div class="upcoming-card-status">
                <?php if ($queue['waiting_count'] > 0): ?>
                    <i class="fa fa-hourglass-half"></i> Waiting
                <?php else: ?>
                    <i class="fa fa-info-circle"></i> <?php echo htmlspecialchars(ucfirst($queue['my_status'])); ?>
                <?php endif; ?>
            </div>
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
require 'layout.php';
?> 