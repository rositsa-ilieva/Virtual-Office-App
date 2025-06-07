<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user information
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// If user not found, log them out and redirect to login
if (!$user) {
    session_destroy();
    header('Location: login.php?error=user_not_found');
    exit();
}

// Get the selected filter from URL parameter, default to 'upcoming'
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';

// Base SQL query
$base_sql = "SELECT q.*, 
            qe.status as user_status,
            qe.position,
            qe.estimated_start_time,
            qe.started_at,
            qe.ended_at,
            qe.comment,
            qe.is_comment_public,
            qe.student_id,
            q.teacher_id,
            (SELECT name FROM users WHERE id = q.teacher_id) as teacher_name,
            (SELECT COUNT(*) FROM queue_entries WHERE queue_id = q.id AND status = 'waiting') as waiting_count,
            (SELECT COUNT(*) FROM queue_entries WHERE queue_id = q.id AND status = 'in_meeting') as in_meeting_count
            FROM queues q 
            LEFT JOIN queue_entries qe ON q.id = qe.queue_id AND qe.student_id = ?";

// Add filter conditions
switch ($filter) {
    case 'past':
        // Only show queues where the student's entry is 'done' or 'skipped'
        $sql = $base_sql . " WHERE (qe.status IN ('done', 'skipped')) ORDER BY q.created_at DESC";
        break;
    case 'group':
        $sql = $base_sql . " WHERE q.is_active = 1 AND q.meeting_type IN ('group', 'conference', 'workshop') AND (qe.status IS NULL OR qe.status IN ('waiting', 'in_meeting')) ORDER BY q.created_at DESC";
        break;
    case 'upcoming':
    default:
        // Only show queues where the student has not joined or is still waiting/in_meeting
        $sql = $base_sql . " WHERE q.is_active = 1 AND (qe.status IS NULL OR qe.status IN ('waiting', 'in_meeting')) ORDER BY q.created_at DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$queues = $stmt->fetchAll();

// After fetching $queues, calculate estimated start time for the current user if in a queue
foreach ($queues as &$queue) {
    if (isset($queue['position']) && $queue['position'] && $queue['user_status'] === 'waiting') {
        // Get queue start time and default duration
        $meeting_duration = $queue['default_duration'] ?? 15;
        $base_time = null;
        $base_position = 1;
        // If a student is in_meeting, use their started_at and position as base
        $stmt = $pdo->prepare("SELECT started_at, position FROM queue_entries WHERE queue_id = ? AND status = 'in_meeting' ORDER BY started_at DESC LIMIT 1");
        $stmt->execute([$queue['id']]);
        $last_meeting = $stmt->fetch();
        if ($last_meeting && $last_meeting['started_at']) {
            $base_time = new DateTime($last_meeting['started_at']);
            $base_position = $last_meeting['position'];
        } elseif (!empty($queue['start_time'])) {
            $base_time = new DateTime($queue['start_time']);
            $base_position = 1;
        } else {
            $base_time = new DateTime();
            $base_position = 1;
        }
        $estimated_time = clone $base_time;
        $offset = ($queue['position'] - $base_position) * $meeting_duration;
        if ($offset > 0) {
            $estimated_time->add(new DateInterval('PT' . $offset . 'M'));
        }
        $queue['estimated_start_time'] = $estimated_time->format('Y-m-d H:i:s');
    }
}
unset($queue);

$success = '';
if (isset($_GET['message']) && $_GET['message'] === 'joined') {
    $success = "You've successfully joined the queue.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Virtual Office Queue</title>
    <link rel="stylesheet" href="style.css?v=2024.1">
</head>
<body>
    <div class="container">
        <nav class="navbar">
            <h1>Welcome, <?php echo htmlspecialchars($user['name'] ?? 'User'); ?></h1>
            <div class="nav-links">
                <?php if ($user['role'] === 'teacher'): ?>
                    <a href="create-room.php" class="btn btn-primary">Create New Queue</a>
                <?php endif; ?>
                <a href="logout.php" class="btn btn-danger">Logout</a>
            </div>
        </nav>

        <div class="dashboard">
            <div class="dashboard-header" style="display: flex; justify-content: space-between; align-items: center; gap: 24px; flex-wrap: wrap;">
                <?php if ($filter !== 'past'): ?>
                    <h2 style="margin: 0;"><?php echo $user['role'] === 'teacher' ? 'Your Queues' : 'Available Queues'; ?></h2>
                <?php endif; ?>
                <div class="queue-filters" style="margin-left: auto;">
                    <a href="?filter=upcoming" class="filter-btn <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        Upcoming Meetings
                    </a>
                    <a href="?filter=past" class="filter-btn <?php echo $filter === 'past' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i>
                        Past Meetings
                    </a>
                    <a href="?filter=group" class="filter-btn <?php echo $filter === 'group' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        Group Meetings
                    </a>
                </div>
            </div>
            
            <?php if ($success): ?>
                <div class="success" style="background:#d4edda;color:#155724;border:1.5px solid #c3e6cb;padding:12px 18px;border-radius:8px;margin-bottom:18px;font-weight:600;text-align:center;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if ($filter === 'past'): ?>
                <div class="queue-grid">
                    <?php foreach ($queues as $queue): ?>
                        <div class="queue-card">
                            <div class="queue-info-group">
                                <div class="queue-title"><?php echo htmlspecialchars($queue['purpose'] ?? 'Untitled Queue'); ?></div>
                                <?php if (!empty($queue['description'])): ?>
                                    <div class="queue-meta">Description: <?php echo htmlspecialchars($queue['description']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($queue['meeting_type'])): ?>
                                    <div class="queue-meta">
                                        <i class="fas fa-video"></i>
                                        <?php echo htmlspecialchars($queue['meeting_type']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($queue['wait_time_method'])): ?>
                                    <div class="queue-meta">
                                        <i class="fas fa-clock"></i>
                                        <?php echo htmlspecialchars(ucfirst($queue['wait_time_method'])); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="queue-meta">Teacher: <?php echo htmlspecialchars($queue['teacher_name'] ?? ''); ?></div>
                                <div class="queue-meta">Status: <span class="status-badge status-<?php echo $queue['user_status']; ?>"><?php echo ucfirst($queue['user_status']); ?></span></div>
                                <div class="queue-meta">Date: <?php echo $queue['ended_at'] ? date('M j, Y', strtotime($queue['ended_at'])) : '-'; ?></div>
                                <div class="queue-meta">Time: <?php echo $queue['ended_at'] ? date('g:i A', strtotime($queue['ended_at'])) : '-'; ?></div>
                                <?php if ($queue['started_at'] && $queue['ended_at']): ?>
                                    <div class="queue-meta"><i class="fas fa-hourglass-end"></i> Duration: <?php echo round((strtotime($queue['ended_at']) - strtotime($queue['started_at'])) / 60, 1); ?> min</div>
                                <?php endif; ?>
                                <?php if (!empty($queue['comment']) && $queue['is_comment_public']): ?>
                                    <div class="queue-meta"><i class="fas fa-sticky-note"></i> Note: <?php echo htmlspecialchars($queue['comment']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="queue-action-group">
                                <span class="stat"><i class="fas fa-history"></i> Past Meeting</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?php if (empty($queues)): ?>
                    <div class="no-queues">
                        <i class="fas fa-inbox"></i>
                        <p>No <?php echo $filter; ?> queues available.</p>
                    </div>
                <?php else: ?>
                    <div class="queue-grid">
                        <?php foreach ($queues as $queue): ?>
                            <div class="queue-card">
                                <?php if (isset($queue['position']) && $queue['position']): ?>
                                    <div class="queue-position-badge"><?php echo htmlspecialchars($queue['position']); ?></div>
                                <?php endif; ?>
                                <div class="queue-info-group">
                                    <div class="queue-title"><?php echo htmlspecialchars($queue['purpose'] ?? 'Untitled Queue'); ?></div>
                                    <?php if (!empty($queue['description'])): ?>
                                        <div class="queue-meta">Description: <?php echo htmlspecialchars($queue['description']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($queue['meeting_type'])): ?>
                                        <div class="queue-meta">
                                            <i class="fas fa-video"></i>
                                            <?php echo htmlspecialchars($queue['meeting_type']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($queue['wait_time_method'])): ?>
                                        <div class="queue-meta">
                                            <i class="fas fa-clock"></i>
                                            <?php echo htmlspecialchars(ucfirst($queue['wait_time_method'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($queue['user_status']) && $queue['user_status']): ?>
                                        <div class="status-badge status-<?php echo $queue['user_status']; ?>">
                                            <?php echo ucfirst($queue['user_status']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($queue['estimated_start_time']) && $queue['estimated_start_time']): ?>
                                        <div class="queue-estimated-pill">Est. <?php echo date('g:i A', strtotime($queue['estimated_start_time'])); ?></div>
                                    <?php endif; ?>
                                    <?php if ($queue['user_status'] === 'waiting'): ?>
                                        <div class="swap-requests" data-queue-id="<?php echo $queue['id']; ?>">
                                            <div class="swap-status"></div>
                                            <div class="swap-actions">
                                                <button class="btn btn-secondary swap-btn" onclick="showSwapModal(<?php echo $queue['id']; ?>)">
                                                    Request Swap
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($queue['meeting_link'])): ?>
                                        <div class="queue-link">
                                            <i class="fas fa-link"></i>
                                            <a href="<?php echo htmlspecialchars($queue['meeting_link']); ?>" target="_blank">
                                                <?php echo htmlspecialchars($queue['meeting_link']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($queue['access_code'])): ?>
                                        <div class="queue-access">
                                            <i class="fas fa-key"></i>
                                            Access Code: <span class="queue-access-copy" data-code="<?php echo htmlspecialchars($queue['access_code']); ?>"><?php echo htmlspecialchars($queue['access_code']); ?></span>
                                            <button type="button" class="copy-btn" onclick="copyAccessCode(this)">Copy</button>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($user['role'] === 'teacher'): ?>
                                        <div class="queue-stats">
                                            <span class="stat">
                                                <i class="fas fa-users"></i>
                                                <?php echo htmlspecialchars($queue['waiting_count'] ?? 0); ?> waiting
                                            </span>
                                            <span class="stat">
                                                <i class="fas fa-video"></i>
                                                <?php echo htmlspecialchars($queue['in_meeting_count'] ?? 0); ?> in meeting
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="queue-action-group">
                                    <?php if ($user['role'] === 'teacher'): ?>
                                        <a href="room.php?id=<?php echo htmlspecialchars($queue['id']); ?>" class="btn btn-primary">Manage Queue</a>
                                        <a href="statistics.php?id=<?php echo htmlspecialchars($queue['id']); ?>" class="btn btn-secondary">View Statistics</a>
                                        <form method="POST" action="delete-queue.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this queue? This cannot be undone.');">
                                            <input type="hidden" name="queue_id" value="<?php echo htmlspecialchars($queue['id']); ?>">
                                            <button type="submit" class="btn btn-danger">Delete Queue</button>
                                        </form>
                                    <?php else: ?>
                                        <?php if ($queue['user_status']): ?>
                                            <?php if (in_array($queue['user_status'], ['waiting', 'in_meeting'])): ?>
                                                <a href="queue-members.php?id=<?php echo htmlspecialchars($queue['id']); ?>" class="btn btn-secondary">View Queue Members</a>
                                            <?php endif; ?>
                                            <?php if ($queue['user_status'] === 'waiting'): ?>
                                                <a href="leave.php?id=<?php echo htmlspecialchars($queue['id']); ?>" class="btn btn-danger">Leave Queue</a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="join.php?id=<?php echo htmlspecialchars($queue['id']); ?>" class="btn btn-primary">Join Queue</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <script>
    function copyAccessCode(btn) {
        var codeSpan = btn.parentElement.querySelector('.queue-access-copy');
        var code = codeSpan.getAttribute('data-code');
        navigator.clipboard.writeText(code).then(function() {
            btn.textContent = 'Copied!';
            setTimeout(function() { btn.textContent = 'Copy'; }, 1200);
        });
    }

    // Swap request functions
    function showSwapModal(queueId) {
        // Get list of students in queue
        fetch('queue-members.php?id=' + queueId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                
                // Create modal with list of students
                const modal = document.createElement('div');
                modal.className = 'modal';
                modal.innerHTML = `
                    <div class="modal-content">
                        <h3>Request Swap With</h3>
                        <div class="student-list">
                            ${data.members.map(member => `
                                <div class="student-item">
                                    <span>${member.name}</span>
                                    <button onclick="sendSwapRequest(${queueId}, ${member.id})">
                                        Request Swap
                                    </button>
                                </div>
                            `).join('')}
                        </div>
                        <button onclick="this.parentElement.parentElement.remove()">Close</button>
                    </div>
                `;
                document.body.appendChild(modal);
            });
    }

    function sendSwapRequest(queueId, receiverId) {
        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('queue_id', queueId);
        formData.append('receiver_id', receiverId);

        fetch('swap-request.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                alert('Swap request sent successfully');
                updateSwapStatus(queueId);
            }
        });
    }

    function updateSwapStatus(queueId) {
        const formData = new FormData();
        formData.append('action', 'get_status');
        formData.append('queue_id', queueId);

        fetch('swap-request.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error(data.error);
                return;
            }

            const swapStatus = document.querySelector(`.swap-requests[data-queue-id="${queueId}"] .swap-status`);
            if (!swapStatus) return;

            let statusHtml = '';
            
            // Show received requests
            if (data.received_requests.length > 0) {
                statusHtml += '<div class="received-requests">';
                data.received_requests.forEach(request => {
                    statusHtml += `
                        <div class="swap-request">
                            <span>${request.sender_name} wants to swap positions</span>
                            <button onclick="handleSwapRequest(${request.id}, 'accept')">Accept</button>
                            <button onclick="handleSwapRequest(${request.id}, 'decline')">Decline</button>
                        </div>
                    `;
                });
                statusHtml += '</div>';
            }

            // Show sent requests
            if (data.sent_requests.length > 0) {
                statusHtml += '<div class="sent-requests">';
                data.sent_requests.forEach(request => {
                    statusHtml += `
                        <div class="swap-request">
                            <span>Swap request sent to ${request.receiver_name}</span>
                            <span class="status-badge">Pending</span>
                        </div>
                    `;
                });
                statusHtml += '</div>';
            }

            swapStatus.innerHTML = statusHtml;
        });
    }

    function handleSwapRequest(requestId, action) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('request_id', requestId);

        fetch('swap-request.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                alert(action === 'accept' ? 'Swap request accepted' : 'Swap request declined');
                // Refresh the page to show updated queue positions
                location.reload();
            }
        });
    }

    // Update swap status every 30 seconds
    setInterval(() => {
        document.querySelectorAll('.swap-requests').forEach(el => {
            updateSwapStatus(el.dataset.queueId);
        });
    }, 30000);

    // Initial update of swap status
    document.querySelectorAll('.swap-requests').forEach(el => {
        updateSwapStatus(el.dataset.queueId);
    });
    </script>
</body>
</html>