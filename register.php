<?php
session_start();
require_once 'db.php';

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
    $teacher_role = $_POST['teacher_role'] ?? '';
    $subjects = $_POST['subjects'] ?? '';

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } elseif ($role === 'student' && empty($faculty_number)) {
        $error = 'Faculty number is required for students';
    } elseif ($role === 'teacher' && (empty($teacher_role) || empty($subjects))) {
        $error = 'Role and subjects are required for teachers';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already registered';
        } else {
            // Create new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, faculty_number, teacher_role, subjects) VALUES (?, ?, ?, ?, ?, ?, ?)');
            if ($stmt->execute([$name, $email, $hashed_password, $role, $faculty_number, $teacher_role, $subjects])) {
                header('Location: login.php?message=registered');
                exit();
            } else {
                $error = 'Registration failed. Please try again.';
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
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="nav">
        <div class="container nav-container">
            <h1>Virtual Office Queue</h1>
            <div class="nav-links">
                <a href="login.php">Login</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="form-container">
            <h1>Register</h1>
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <!-- Removed success message display, as redirect will handle it -->
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="name">Full Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="form-group">
                    <label for="role">Role:</label>
                    <select id="role" name="role" required onchange="toggleRoleFields()">
                        <option value="">Select Role</option>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                    </select>
                </div>
                <div class="form-group student-fields" style="display: none;">
                    <label for="faculty_number">Faculty Number:</label>
                    <input type="text" id="faculty_number" name="faculty_number">
                </div>
                <div class="form-group teacher-fields" style="display: none;">
                    <label for="teacher_role">Role/Position:</label>
                    <input type="text" id="teacher_role" name="teacher_role" placeholder="e.g., Professor, Assistant Professor">
                </div>
                <div class="form-group teacher-fields" style="display: none;">
                    <label for="subjects">Subjects:</label>
                    <textarea id="subjects" name="subjects" placeholder="Enter subjects you teach, separated by commas"></textarea>
                </div>
                <div class="form-footer">
                    <button type="submit" class="btn btn-primary">Register</button>
                </div>
            </form>
            <div class="form-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>

    <script>
    function toggleRoleFields() {
        const role = document.getElementById('role').value;
        const studentFields = document.querySelectorAll('.student-fields');
        const teacherFields = document.querySelectorAll('.teacher-fields');
        
        studentFields.forEach(field => {
            field.style.display = role === 'student' ? 'block' : 'none';
            field.querySelector('input').required = role === 'student';
        });
        
        teacherFields.forEach(field => {
            field.style.display = role === 'teacher' ? 'block' : 'none';
            field.querySelector('input, textarea').required = role === 'teacher';
        });
    }
    </script>
</body>
</html> 