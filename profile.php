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
<h2>Profile / Settings</h2>
<form class="mt-4" style="max-width: 500px;">
    <div class="mb-3">
        <label class="form-label">Name</label>
        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Faculty</label>
        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['faculty'] ?? ''); ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Student ID</label>
        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['student_id'] ?? ''); ?>">
    </div>
    <div class="mb-3">
        <label class="form-label">Email (optional)</label>
        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
    </div>
    <button type="submit" class="btn btn-primary">Save Changes</button>
</form>
<?php
$content = ob_get_clean();
require 'layout.php';
?> 