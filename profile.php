<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'student';
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $faculty_number = $_POST['faculty_number'] ?? '';
    $teacher_role = $_POST['teacher_role'] ?? '';
    $subjects = $_POST['subjects'] ?? '';

    if (empty($name)) {
        $error = 'Name is required';
    } else {
        try {
            $sql = 'UPDATE users SET name = ?, email = ?';
            $params = [$name, $email];

            if ($user_role === 'student') {
                $sql .= ', faculty_number = ?';
                $params[] = $faculty_number;
            } else if ($user_role === 'teacher') {
                $sql .= ', teacher_role = ?, subjects = ?';
                $params[] = $teacher_role;
                $params[] = $subjects;
            }

            $sql .= ' WHERE id = ?';
            $params[] = $user_id;

            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $success = 'Profile updated successfully';
                // Refresh user data
                $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            } else {
                $error = 'Failed to update profile';
            }
        } catch (PDOException $e) {
            $error = 'An error occurred while updating profile';
        }
    }
}

ob_start();
?>
<h2>Profile Settings</h2>
<div class="mt-4">
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <form method="POST" action="" class="card shadow-sm p-4" style="max-width: 600px;">
        <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
        </div>
        <?php if ($user_role === 'student'): ?>
            <div class="mb-3">
                <label class="form-label">Faculty Number</label>
                <input type="text" name="faculty_number" class="form-control" value="<?php echo htmlspecialchars($user['faculty_number'] ?? ''); ?>" required>
            </div>
        <?php elseif ($user_role === 'teacher'): ?>
            <div class="mb-3">
                <label class="form-label">Role/Position</label>
                <input type="text" name="teacher_role" class="form-control" value="<?php echo htmlspecialchars($user['teacher_role'] ?? ''); ?>" placeholder="e.g., Professor, Assistant Professor" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Subjects</label>
                <textarea name="subjects" class="form-control" rows="3" placeholder="Enter subjects you teach, separated by commas" required><?php echo htmlspecialchars($user['subjects'] ?? ''); ?></textarea>
            </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
</div>
<?php
$content = ob_get_clean();
require 'layout.php';
?> 