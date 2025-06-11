<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'student';
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get the current page name for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$activePage = $current_page === 'index' ? 'dashboard' : $current_page;

// Function to render the sidebar
function renderSidebar($role, $activePage, $user) {
    ?>
    <nav id="sidebar" class="sidebar bg-white shadow-sm">
        <div class="sidebar-header p-3 border-bottom">
            <div class="fw-bold mb-1"><i class="fa fa-user-circle me-2"></i><?php echo htmlspecialchars($user['name'] ?? $user['email']); ?></div>
            <div class="small text-muted"><?php echo htmlspecialchars($role); ?></div>
        </div>
        <ul class="nav flex-column mt-3">
            <?php if ($role === 'student'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='dashboard') echo 'active'; ?>" href="index.php">
                        <i class="fa fa-home me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='notifications') echo 'active'; ?>" href="notifications.php">
                        <i class="fa fa-bell me-2"></i>Notifications
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='queue-schedule') echo 'active'; ?>" href="queue-schedule.php">
                        <i class="fa fa-calendar-alt me-2"></i>Upcoming Meetings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='my-queues') echo 'active'; ?>" href="my-queues.php">
                        <i class="fa fa-list me-2"></i>My Queues
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='history') echo 'active'; ?>" href="history.php">
                        <i class="fa fa-history me-2"></i>Past Meetings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='profile') echo 'active'; ?>" href="profile.php">
                        <i class="fa fa-user-cog me-2"></i>Profile Settings
                    </a>
                </li>
            <?php elseif ($role === 'teacher'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='dashboard') echo 'active'; ?>" href="index.php">
                        <i class="fa fa-home me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='notifications') echo 'active'; ?>" href="notifications.php">
                        <i class="fa fa-bell me-2"></i>Notifications
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='queue-schedule') echo 'active'; ?>" href="queue-schedule.php">
                        <i class="fa fa-calendar-alt me-2"></i>Upcoming Meetings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='create-room') echo 'active'; ?>" href="create-room.php">
                        <i class="fa fa-plus me-2"></i>Create Room
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='manage-queues') echo 'active'; ?>" href="manage-queues.php">
                        <i class="fa fa-tasks me-2"></i>Manage Queues
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='stats') echo 'active'; ?>" href="stats.php">
                        <i class="fa fa-chart-bar me-2"></i>Statistics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php if($activePage=='profile') echo 'active'; ?>" href="profile.php">
                        <i class="fa fa-user-cog me-2"></i>Profile Settings
                    </a>
                </li>
            <?php endif; ?>
            <li class="nav-item mt-3">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="fa fa-sign-out-alt me-2"></i>Logout
                </a>
            </li>
        </ul>
    </nav>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($activePage); ?> - Virtual Office Queue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="style.css?v=2" rel="stylesheet">
    <style>
        body { 
            background: #f7f9fb;
            min-height: 100vh;
            display: flex;
        }
        .sidebar { 
            width: 260px; 
            min-height: 100vh; 
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
        }
        .main-content { 
            margin-left: 260px; 
            padding: 2rem;
            flex: 1;
        }
        @media (max-width: 991px) {
            .sidebar { 
                position: static; 
                width: 100%; 
                min-height: auto; 
            }
            .main-content { 
                margin-left: 0; 
            }
        }
        .nav-link {
            color: #1e293b;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        .nav-link:hover {
            background: #f1f5f9;
        }
        .nav-link.active {
            background: #2563eb;
            color: white;
        }
        .nav-link.active:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>
    <?php renderSidebar($user_role, $activePage, $user); ?>
    <div class="main-content">
        <?php if (isset($content)) echo $content; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 