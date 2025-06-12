<?php
session_start();
require_once 'config.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    $faculty_number = $_POST['faculty_number'] ?? '';
    $specialization = $_POST['specialization'] ?? '';
    $year_of_study = $_POST['year_of_study'] ?? '';

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($role === 'student' && (empty($faculty_number) || empty($specialization) || empty($year_of_study))) {
        $error = 'Faculty number, specialization, and year of study are required for students';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already registered';
        } else {
            // Check if faculty number already exists (for students)
            if ($role === 'student' && !empty($faculty_number)) {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE faculty_number = ?');
                $stmt->execute([$faculty_number]);
                if ($stmt->fetch()) {
                    $error = 'A user with this faculty number already exists.';
                }
            }
            // Only insert if no error
            if (empty($error)) {
                // Create new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, faculty_number, specialization, year_of_study) VALUES (?, ?, ?, ?, ?, ?, ?)');
                if ($stmt->execute([$name, $email, $hashed_password, $role, $faculty_number, $specialization, $year_of_study])) {
                    header('Location: login.php?message=registered');
                    exit();
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Virtual Office Queue</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="css/register.css" rel="stylesheet">
</head>
<body>
    <div class="register-outer">
      <div class="register-card">
            <div class="register-logo">
                <i class="fa fa-user-plus"></i>
            </div>
            <div class="register-title">Create Account</div>
            <div class="register-subtitle">Join the virtual office queue system</div>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-user"></i></span>
                    <input type="text" name="name" placeholder="Full Name" required>
            </div>
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-envelope"></i></span>
                    <input type="email" name="email" placeholder="Email Address" required>
            </div>
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-lock"></i></span>
                    <input type="password" name="password" placeholder="Password" required>
            </div>
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-lock"></i></span>
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            </div>
            <div class="form-group">
                    <span class="input-icon"><i class="fa fa-user-tag"></i></span>
                    <select name="role" required>
                    <option value="">Select Role</option>
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                </select>
            </div>
                <div id="student-fields" style="display: none;">
                    <div class="form-group">
                <span class="input-icon"><i class="fa fa-id-card"></i></span>
                        <input type="text" name="faculty_number" placeholder="Faculty Number">
            </div>
                    <div class="form-group">
                        <span class="input-icon"><i class="fa fa-graduation-cap"></i></span>
                        <select name="specialization">
                    <option value="">Select Specialization</option>
                    <option value="Software Engineering">Software Engineering</option>
                    <option value="Information Systems">Information Systems</option>
                    <option value="Computer Science">Computer Science</option>
                    <option value="Applied Mathematics">Applied Mathematics</option>
                    <option value="Informatics">Informatics</option>
                </select>
            </div>
                    <div class="form-group">
                        <span class="input-icon"><i class="fa fa-calendar"></i></span>
                        <select name="year_of_study">
                    <option value="">Select Year</option>
                            <option value="1st year">1st Year</option>
                            <option value="2nd year">2nd Year</option>
                            <option value="3rd year">3rd Year</option>
                            <option value="4th year">4th Year</option>
                </select>
            </div>
            </div>
            <button type="submit" class="btn-primary">Register</button>
                <div class="form-footer">
                    Already have an account? <a href="login.php">Login here</a>
                </div>
        </form>
      </div>
    </div>
    <script>
        document.querySelector('select[name="role"]').addEventListener('change', function() {
            const studentFields = document.getElementById('student-fields');
            studentFields.style.display = this.value === 'student' ? 'block' : 'none';
        });
    </script>
</body>
</html> 