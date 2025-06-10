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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
.myqueues-title {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    text-align: left;
    margin: 2.5rem 0 2rem 0;
    letter-spacing: 0.01em;
    display: flex;
    align-items: center;
    gap: 0.7rem;
}
.myqueues-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    gap: 2.2rem;
    width: 100%;
    max-width: 980px;
    margin: 0 auto 2.5rem auto;
    justify-content: center;
}
.myqueue-card {
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
    min-width: 0;
}
.myqueue-card-title {
    font-size: 1.18rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 0.7rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.myqueue-card-meta {
    font-size: 1.04rem;
    color: #334155;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.myqueue-card-position {
    font-size: 0.98rem;
    color: #64748b;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.myqueue-card-status {
    font-size: 1.01rem;
    font-weight: 600;
    margin-bottom: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.myqueue-card-status.waiting { color: #6366f1; }
.myqueue-card-status.in_meeting { color: #2563eb; }
.myqueue-card-status.skipped { color: #f59e42; }
.myqueue-card-status.other { color: #64748b; }
.myqueue-card-actions {
    margin-top: 1.1rem;
    display: flex;
    justify-content: center;
    width: 100%;
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
    text-decoration: none;
    display: inline-block;
}
.btn-primary:hover, .btn-primary:focus {
    background: linear-gradient(90deg, #2563eb 0%, #6366f1 100%);
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 4px 16px rgba(99,102,241,0.13);
}
@media (max-width: 900px) {
    .myqueues-cards { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
    .myqueues-title { font-size: 1.3rem; margin: 1.2rem 0 1rem 0; }
    .myqueues-cards { gap: 1.2rem; }
    .myqueue-card { padding: 1.2rem 0.7rem; }
}
.cards-container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 24px;
  margin-bottom: 2.5rem;
}
@media (max-width: 900px) {
  .cards-container { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 600px) {
  .cards-container { grid-template-columns: 1fr; }
}
</style>
<div class="myqueues-title">📋 My Queues</div>
<div class="cards-container">
<?php
// Get all queues where this student is currently waiting or in a meeting
$sql = "SELECT q.id as queue_id, q.purpose, q.description, q.meeting_link, q.access_code, q.start_time, qe.position as my_position, qe.status, qe.estimated_start_time, u.name as teacher_name, u.email as teacher_email, u.subjects as teacher_subjects
        FROM queue_entries qe
        JOIN queues q ON qe.queue_id = q.id
        JOIN users u ON q.teacher_id = u.id
        WHERE qe.student_id = ? AND qe.status IN ('waiting', 'in_meeting', 'skipped')
        ORDER BY qe.position ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$my_queues = $stmt->fetchAll();
if (empty($my_queues)) {
    echo '<div style="grid-column:1/-1;text-align:center;color:#64748b;font-size:1.15rem;padding:2.5rem 0;">🚫 You are not currently in any queues.</div>';
} else {
    foreach ($my_queues as $queue) {
        $status = isset($queue['status']) ? strtolower($queue['status']) : 'waiting';
        $statusIcon = $status === 'waiting' ? '⏳' : ($status === 'in_meeting' ? '🟢' : ($status === 'skipped' ? '❌' : '🕓'));
        $statusClass = $status === 'waiting' ? 'waiting' : ($status === 'in_meeting' ? 'in_meeting' : ($status === 'skipped' ? 'skipped' : 'other'));
        echo '<div class="myqueue-card">';
        echo '<div class="myqueue-card-title"><i class="fa fa-list"></i> ' . htmlspecialchars($queue['purpose']) . '</div>';
        echo '<div class="myqueue-card-meta"><i class="fa fa-chalkboard-teacher"></i> ' . htmlspecialchars($queue['teacher_name']) . '</div>';
        if (!empty($queue['start_time'])) {
            echo '<div class="myqueue-card-meta"><i class="fa fa-clock"></i> ' . date('M d, Y', strtotime($queue['start_time'])) . ' &bull; ' . date('g:i A', strtotime($queue['start_time'])) . '</div>';
        }
        if (!empty($queue['meeting_link'])) {
            echo '<div class="myqueue-card-meta"><i class="fa fa-link"></i> <a href="' . htmlspecialchars($queue['meeting_link']) . '" target="_blank" style="color:#2563eb;text-decoration:underline;word-break:break-all;">Meeting Link</a></div>';
        }
        if (!empty($queue['access_code'])) {
            echo '<div class="myqueue-card-meta"><i class="fa fa-key"></i> Access Code: <span style="font-weight:600;letter-spacing:1px;">' . htmlspecialchars($queue['access_code']) . '</span></div>';
        }
        echo '<div class="myqueue-card-position"><i class="fa fa-list-ol"></i> Position: ' . htmlspecialchars($queue['my_position']) . '</div>';
        echo '<div class="myqueue-card-status ' . $statusClass . '">' . $statusIcon . ' ' . ucfirst(str_replace('_', ' ', $status)) . '</div>';
        echo '<div class="myqueue-card-actions">';
        echo '<a href="queue-members.php?id=' . $queue['queue_id'] . '" class="btn-primary"><i class="fa fa-users"></i> View Queue Members</a>';
        echo '</div>';
        echo '</div>';
    }
}
?>
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