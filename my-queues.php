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
                                    <button class="copy-btn" type="button" data-code="<?php echo htmlspecialchars($queue['meeting_link']); ?>">Copy</button><br>
                                <?php endif; ?>
                                <?php if (!empty($queue['access_code'])): ?>
                                    <div class="queue-access" style="margin-bottom: 10px;">
                                        <i class="fas fa-key"></i>
                                        Access Code: <span class="queue-access-copy" data-code="<?php echo htmlspecialchars($queue['access_code']); ?>"><?php echo htmlspecialchars($queue['access_code']); ?></span>
                                        <button type="button" class="btn btn-outline-primary btn-sm copy-btn" data-code="<?php echo htmlspecialchars($queue['access_code']); ?>">Copy</button>
                                    </div>
                                <?php endif; ?>
                            </p>
                            <div class="teacher-info" style="margin-bottom:10px;">
                                <strong>Teacher:</strong> <?php echo htmlspecialchars($queue['teacher_name']); ?><br>
                                <strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($queue['teacher_email']); ?>"><?php echo htmlspecialchars($queue['teacher_email']); ?></a><br>
                                <strong>Subjects:</strong> <?php echo htmlspecialchars($queue['teacher_subjects'] ?? ''); ?>
                            </div>
                            <div class="queue-actions" style="margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap;">
                                <a href="queue-members.php?id=<?php echo $queue['queue_id']; ?>" class="btn btn-primary">View Queue</a>
                                <button type="button" class="btn btn-info" onclick="showMessageModal(<?php echo $queue['queue_id']; ?>, '<?php echo htmlspecialchars(addslashes($queue['purpose'])); ?>')">Send Message to Teacher</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach;
        endif; ?>
    </div>
</div>

<!-- Modal for sending message -->
<div id="messageModal" class="modal" style="display:none;position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(0,0,0,0.3);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;padding:24px 32px;border-radius:8px;max-width:400px;width:100%;margin:auto;">
        <h4 id="modalQueueTitle">Send Message to Teacher</h4>
        <form method="POST" id="sendMessageForm">
            <input type="hidden" name="queue_id" id="modalQueueId" value="">
            <textarea name="teacher_message" class="form-control mb-3" rows="3" placeholder="Type your message..." required></textarea>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">Send</button>
                <button type="button" class="btn btn-secondary" onclick="closeMessageModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>
<script>
function copyMeetingLink(queueId) {
    var link = document.getElementById('meetingLink-' + queueId).innerText;
    navigator.clipboard.writeText(link).then(function() {
        var btn = document.querySelector('button[data-code=\'' + link + '\']');
        if (btn) {
            var old = btn.innerHTML;
            btn.innerHTML = 'Copied!';
            setTimeout(function() { btn.innerHTML = 'Copy'; }, 1200);
        }
    });
}
function showMessageModal(queueId, queueTitle) {
    document.getElementById('modalQueueId').value = queueId;
    document.getElementById('modalQueueTitle').innerText = 'Send Message to Teacher (' + queueTitle + ')';
    document.getElementById('messageModal').style.display = 'flex';
}
function closeMessageModal() {
    document.getElementById('messageModal').style.display = 'none';
}
document.getElementById('sendMessageForm').onsubmit = function(e) {
    if (!confirm('Send this message to the teacher?')) return false;
};

// Copy button logic
function handleCopyClick(e) {
    var code = e.target.getAttribute('data-code');
    if (code) {
        navigator.clipboard.writeText(code).then(function() {
            e.target.textContent = 'Copied!';
            setTimeout(function() { e.target.textContent = 'Copy'; }, 1200);
        });
    }
}
document.querySelectorAll('.copy-btn').forEach(function(btn) {
    btn.addEventListener('click', handleCopyClick);
});
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

<!-- Handle message send -->
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teacher_message']) && isset($_POST['queue_id'])) {
    $message = trim($_POST['teacher_message']);
    $queue_id = (int)$_POST['queue_id'];
    if ($message !== '' && $queue_id) {
        // Get teacher id for this queue
        $stmt = $pdo->prepare('SELECT teacher_id FROM queues WHERE id = ?');
        $stmt->execute([$queue_id]);
        $teacher_id = $stmt->fetchColumn();
        // Insert into notifications (or messages table if you have one)
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, message, related_queue_id, related_user_id) VALUES (?, "student_message", ?, ?, ?)');
        $stmt->execute([$teacher_id, $message, $queue_id, $user_id]);
        echo '<div class="alert alert-success">Message sent to teacher!</div>';
    }
}
?> 