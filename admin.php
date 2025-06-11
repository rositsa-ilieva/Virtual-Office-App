<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'student';
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Check if user is admin
if ($user_role !== 'admin') {
    header('Location: index.php');
    exit();
}

ob_start();
?>
<h2>Admin Panel</h2>
<div class="mt-4">
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">User Management</h5>
                    <div class="list-group">
                        <a href="admin-users.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-users me-2"></i> Manage Users
                        </a>
                        <a href="admin-roles.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-user-tag me-2"></i> Manage Roles
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Queue Management</h5>
                    <div class="list-group">
                        <a href="admin-queues.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-list me-2"></i> Manage Queues
                        </a>
                        <a href="admin-reports.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-chart-bar me-2"></i> View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">System Settings</h5>
                    <div class="list-group">
                        <a href="admin-settings.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-cog me-2"></i> General Settings
                        </a>
                        <a href="admin-logs.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-history me-2"></i> System Logs
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Quick Stats</h5>
                    <?php
                    // Get quick stats
                    $stats = [
                        'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
                        'active_queues' => $pdo->query("SELECT COUNT(*) FROM queues WHERE status = 'active'")->fetchColumn(),
                        'waiting_students' => $pdo->query("SELECT COUNT(*) FROM queue_entries WHERE status = 'waiting'")->fetchColumn(),
                        'total_meetings' => $pdo->query("SELECT COUNT(*) FROM queue_entries WHERE status = 'done'")->fetchColumn()
                    ];
                    ?>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="border rounded p-3 text-center">
                                <h3 class="mb-0"><?php echo $stats['total_users']; ?></h3>
                                <small class="text-muted">Total Users</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3 text-center">
                                <h3 class="mb-0"><?php echo $stats['active_queues']; ?></h3>
                                <small class="text-muted">Active Queues</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3 text-center">
                                <h3 class="mb-0"><?php echo $stats['waiting_students']; ?></h3>
                                <small class="text-muted">Waiting Students</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="border rounded p-3 text-center">
                                <h3 class="mb-0"><?php echo $stats['total_meetings']; ?></h3>
                                <small class="text-muted">Total Meetings</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require 'layout.php';
?>
