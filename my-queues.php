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
<h2>My Queues</h2>
<div class="mt-4">
    <div class="row g-4">
        <?php
        // Get all queues where this student is currently waiting or in a meeting
        $sql = "SELECT q.id as queue_id, q.purpose, q.meeting_link, q.access_code, qe.position as my_position, u.name as teacher_name, u.email as teacher_email, u.subjects as teacher_subjects
                FROM queue_entries qe
                JOIN queues q ON qe.queue_id = q.id
                JOIN users u ON q.teacher_id = u.id
                WHERE qe.student_id = ? AND qe.status IN ('waiting', 'in_meeting')
                ORDER BY qe.position ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $my_queues = $stmt->fetchAll();

        if (empty($my_queues)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    You are not currently in any queues.
                </div>
            </div>
        <?php else:
            foreach ($my_queues as $queue): ?>
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($queue['purpose']); ?></h5>
                            <p class="card-text">
                                <strong>Position:</strong> <?php echo $queue['my_position']; ?><br>
                                <?php if ($queue['meeting_link']): ?>
                                    <strong>Meeting Link:</strong> <span id="meetingLink-<?php echo $queue['queue_id']; ?>"><?php echo htmlspecialchars($queue['meeting_link']); ?></span>
                                    <button class="copy-btn" type="button" onclick="copyMeetingLink('<?php echo $queue['queue_id']; ?>')">Copy</button><br>
                                <?php endif; ?>
                                <?php if ($queue['access_code']): ?>
                                    <strong>Access Code:</strong> <span id="accessCode-<?php echo $queue['queue_id']; ?>"><?php echo htmlspecialchars($queue['access_code']); ?></span>
                                    <button class="copy-btn" type="button" onclick="copyAccessCode('<?php echo $queue['queue_id']; ?>')">Copy</button>
                                <?php endif; ?>
                            </p>
                            <div class="teacher-info" style="margin-bottom:10px;">
                                <strong>Teacher:</strong> <?php echo htmlspecialchars($queue['teacher_name']); ?><br>
                                <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($queue['teacher_email']); ?>"><?php echo htmlspecialchars($queue['teacher_email']); ?></a><br>
                                <strong>Subjects:</strong> <?php echo htmlspecialchars($queue['teacher_subjects'] ?? ''); ?>
                            </div>
                            <a href="queue-members.php?id=<?php echo $queue['queue_id']; ?>" class="btn btn-primary">
                                View Queue
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach;
        endif; ?>
    </div>
</div>
<script>
function copyAccessCode(queueId) {
    var code = document.getElementById('accessCode-' + queueId).innerText;
    navigator.clipboard.writeText(code).then(function() {
        var btn = document.querySelector('button[onclick=\"copyAccessCode(\\'' + queueId + '\\')\"]');
        if (btn) {
            var old = btn.innerHTML;
            btn.innerHTML = 'Copied!';
            setTimeout(function() { btn.innerHTML = 'Copy'; }, 1200);
        }
    });
}
function copyMeetingLink(queueId) {
    var link = document.getElementById('meetingLink-' + queueId).innerText;
    navigator.clipboard.writeText(link).then(function() {
        var btn = document.querySelector('button[onclick=\"copyMeetingLink(\\'' + queueId + '\\')\"]');
        if (btn) {
            var old = btn.innerHTML;
            btn.innerHTML = 'Copied!';
            setTimeout(function() { btn.innerHTML = 'Copy'; }, 1200);
        }
    });
}
</script>
<style>
.copy-btn {
  background: #2563EB;
  color: #fff;
  border: none;
  border-radius: 12px;
  padding: 6px 18px;
  font-size: 0.98em;
  font-weight: 600;
  margin-left: 10px;
  cursor: pointer;
  box-shadow: 0 1px 4px rgba(37,99,235,0.07);
  transition: background 0.2s, box-shadow 0.2s;
  outline: none;
  vertical-align: middle;
}
.copy-btn:hover, .copy-btn:focus {
  background: #1D8FFF;
  box-shadow: 0 2px 8px rgba(37,99,235,0.13);
}
</style>
<?php
$content = ob_get_clean();
require 'layout.php';
?> 