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
<style>
.profile-outer {
    width: 100vw;
    min-height: 100vh;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    flex-direction: column;
    gap: 2.5rem;
    padding-top: 3rem;
    padding-bottom: 3rem;
    box-sizing: border-box;
    max-width: 1100px;
    margin: 0 auto;
}
.profile-card {
    background: linear-gradient(120deg, #f8fafc 60%, #e0e7ff 100%);
    border-radius: 22px;
    box-shadow: 0 8px 40px rgba(30,41,59,0.13), 0 1.5px 6px rgba(99,102,241,0.08);
    padding: 2.8rem 2.5rem 2.2rem 2.5rem;
    max-width: 520px;
    width: 100%;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.profile-header {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 1.2rem;
}
.profile-header i {
    font-size: 2.5rem;
    color: #6366f1;
    background: #e0e7ff;
    border-radius: 50%;
    padding: 0.7rem;
    box-shadow: 0 2px 8px rgba(99,102,241,0.10);
    margin-bottom: 0.7rem;
}
.profile-title {
    font-size: 2rem;
    font-weight: 700;
    text-align: center;
    color: #1e293b;
}
.form-group {
    margin-bottom: 1.2rem;
    position: relative;
    width: 100%;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1px solid #cbd5e1;
    border-radius: 14px;
    font-size: 1rem;
    background: #f1f5f9;
    transition: border 0.2s, box-shadow 0.2s;
    box-sizing: border-box;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 2px #6366f133;
    background: #fff;
}
.form-group .input-icon {
    position: absolute;
    left: 0.9rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 1.1em;
    pointer-events: none;
}
.btn-primary {
    width: 100%;
    padding: 0.8rem 0;
    border: none;
    border-radius: 14px;
    background: linear-gradient(90deg, #6366f1 0%, #2563eb 100%);
    color: #fff;
    font-size: 1.1rem;
    font-weight: 600;
    margin-top: 0.5rem;
    box-shadow: 0 2px 8px rgba(99,102,241,0.08);
    transition: background 0.2s, transform 0.15s;
    cursor: pointer;
}
.btn-primary:hover, .btn-primary:focus {
    background: linear-gradient(90deg, #2563eb 0%, #6366f1 100%);
    transform: translateY(-2px) scale(1.03);
}
.profile-alert {
    width: 100%;
    border-radius: 10px;
    padding: 0.8rem 1.2rem;
    margin-bottom: 1.2rem;
    font-size: 1.05rem;
    text-align: center;
}
.profile-alert-success {
    background: #d1fae5;
    color: #047857;
    border: 1.5px solid #10b981;
}
.profile-alert-error {
    background: #fee2e2;
    color: #b91c1c;
    border: 1.5px solid #ef4444;
}
@media (max-width: 700px) {
    .profile-card { padding: 1.2rem 0.7rem; }
    .profile-outer { padding-top: 1.2rem; padding-bottom: 1.2rem; }
}
</style>
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
require 'layout.php';
?> 