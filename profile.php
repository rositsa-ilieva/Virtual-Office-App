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
    $specialization = $_POST['specialization'] ?? ($user['specialization'] ?? '');
    $year_of_study = $_POST['year_of_study'] ?? ($user['year_of_study'] ?? '');

    if (empty($name)) {
        $error = 'Name is required';
    } else {
        try {
            $sql = 'UPDATE users SET name = ?, email = ?';
            $params = [$name, $email];

            if ($user_role === 'student') {
                $sql .= ', faculty_number = ?, specialization = ?, year_of_study = ?';
                $params[] = $faculty_number;
                $params[] = $specialization;
                $params[] = $year_of_study;
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
            <div class="mb-3">
                <label class="form-label">Specialization</label>
                <select name="specialization" class="form-control" required>
                    <option value="">Select Specialization</option>
                    <option value="Software Engineering" <?php if(($user['specialization'] ?? '')=='Software Engineering') echo 'selected'; ?>>Software Engineering</option>
                    <option value="Information Systems" <?php if(($user['specialization'] ?? '')=='Information Systems') echo 'selected'; ?>>Information Systems</option>
                    <option value="Computer Science" <?php if(($user['specialization'] ?? '')=='Computer Science') echo 'selected'; ?>>Computer Science</option>
                    <option value="Applied Mathematics" <?php if(($user['specialization'] ?? '')=='Applied Mathematics') echo 'selected'; ?>>Applied Mathematics</option>
                    <option value="Informatics" <?php if(($user['specialization'] ?? '')=='Informatics') echo 'selected'; ?>>Informatics</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Year of Study</label>
                <select name="year_of_study" class="form-control" required>
                    <option value="">Select Year</option>
                    <option value="1st year" <?php if(($user['year_of_study'] ?? '')=='1st year') echo 'selected'; ?>>1st year</option>
                    <option value="2nd year" <?php if(($user['year_of_study'] ?? '')=='2nd year') echo 'selected'; ?>>2nd year</option>
                    <option value="3rd year" <?php if(($user['year_of_study'] ?? '')=='3rd year') echo 'selected'; ?>>3rd year</option>
                    <option value="4th year" <?php if(($user['year_of_study'] ?? '')=='4th year') echo 'selected'; ?>>4th year</option>
                </select>
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