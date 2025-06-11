<?php
session_start();
require_once 'config.php';
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
                // Do NOT update faculty_number
                $sql .= ', specialization = ?, year_of_study = ?';
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
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="css/profile.css">
<div class="profile-outer">
  <div class="profile-card">
    <div class="profile-header">
      <i class="fa fa-user-cog"></i>
      <div class="profile-title">Edit Profile</div>
    </div>
    <?php if ($error): ?>
      <div class="profile-alert profile-alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="profile-alert profile-alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <form method="POST" action="" style="width:100%;">
      <div class="form-group">
        <span class="input-icon"><i class="fa fa-user"></i></span>
        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required placeholder="Full Name">
      </div>
      <div class="form-group">
        <span class="input-icon"><i class="fa fa-envelope"></i></span>
        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required placeholder="Email">
      </div>
      <?php if ($user_role === 'student'): ?>
        <div class="form-group">
          <span class="input-icon"><i class="fa fa-id-card"></i></span>
          <input type="text" name="faculty_number" class="form-control" value="<?php echo htmlspecialchars($user['faculty_number'] ?? ''); ?>" required placeholder="Faculty Number" readonly>
        </div>
        <div class="form-group">
          <span class="input-icon"><i class="fa fa-graduation-cap"></i></span>
          <select name="specialization" class="form-control" required>
            <option value="">Select Specialization</option>
            <option value="Software Engineering" <?php if(($user['specialization'] ?? '')=='Software Engineering') echo 'selected'; ?>>Software Engineering</option>
            <option value="Information Systems" <?php if(($user['specialization'] ?? '')=='Information Systems') echo 'selected'; ?>>Information Systems</option>
            <option value="Computer Science" <?php if(($user['specialization'] ?? '')=='Computer Science') echo 'selected'; ?>>Computer Science</option>
            <option value="Applied Mathematics" <?php if(($user['specialization'] ?? '')=='Applied Mathematics') echo 'selected'; ?>>Applied Mathematics</option>
            <option value="Informatics" <?php if(($user['specialization'] ?? '')=='Informatics') echo 'selected'; ?>>Informatics</option>
          </select>
        </div>
        <div class="form-group">
          <span class="input-icon"><i class="fa fa-calendar-alt"></i></span>
          <select name="year_of_study" class="form-control" required>
            <option value="">Select Year</option>
            <option value="1st year" <?php if(($user['year_of_study'] ?? '')=='1st year') echo 'selected'; ?>>1st year</option>
            <option value="2nd year" <?php if(($user['year_of_study'] ?? '')=='2nd year') echo 'selected'; ?>>2nd year</option>
            <option value="3rd year" <?php if(($user['year_of_study'] ?? '')=='3rd year') echo 'selected'; ?>>3rd year</option>
            <option value="4th year" <?php if(($user['year_of_study'] ?? '')=='4th year') echo 'selected'; ?>>4th year</option>
          </select>
        </div>
      <?php elseif ($user_role === 'teacher'): ?>
        <div class="form-group">
          <span class="input-icon"><i class="fa fa-briefcase"></i></span>
          <input type="text" name="teacher_role" class="form-control" value="<?php echo htmlspecialchars($user['teacher_role'] ?? ''); ?>" placeholder="e.g., Professor, Assistant Professor" required>
        </div>
        <div class="form-group">
          <span class="input-icon"><i class="fa fa-book"></i></span>
          <textarea name="subjects" class="form-control" rows="3" placeholder="Enter subjects you teach, separated by commas" required><?php echo htmlspecialchars($user['subjects'] ?? ''); ?></textarea>
        </div>
      <?php endif; ?>
      <button type="submit" class="btn-primary">Save Changes</button>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
require '../src/Includes/layout.php';
?> 