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
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, faculty_number, teacher_role, subjects, specialization, year_of_study) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            if ($stmt->execute([$name, $email, $hashed_password, $role, $faculty_number, $teacher_role, $subjects, $specialization, $year_of_study])) {
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0e7ff 0%, #f8fafc 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', 'Roboto', Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .register-outer {
            width: 100vw;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .register-card {
            background: linear-gradient(120deg, #f8fafc 60%, #e0e7ff 100%);
            border-radius: 22px;
            box-shadow: 0 8px 40px rgba(30,41,59,0.13), 0 1.5px 6px rgba(99,102,241,0.08);
            padding: 2.8rem 2.5rem 2.2rem 2.5rem;
            max-width: 420px;
            width: 100%;
            margin: 40px 0;
            animation: fadeInUp 0.8s cubic-bezier(.39,.575,.56,1.000);
            position: relative;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: none; }
        }
        .register-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.1rem;
        }
        .register-logo i {
            font-size: 2.5rem;
            color: #6366f1;
            background: #e0e7ff;
            border-radius: 50%;
            padding: 0.7rem;
            box-shadow: 0 2px 8px rgba(99,102,241,0.10);
        }
        .register-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
            text-align: center;
            color: #1e293b;
        }
        .register-subtitle {
            font-size: 1.1rem;
            color: #64748b;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.2rem;
            position: relative;
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
        .btn-secondary {
            display: inline-block;
            padding: 0.55rem 1.2rem;
            border: 1.5px solid #6366f1;
            border-radius: 12px;
            background: #f8fafc;
            color: #6366f1;
            font-size: 1rem;
            font-weight: 500;
            margin-top: 0.7rem;
            margin-bottom: 0.2rem;
            transition: background 0.18s, color 0.18s, border 0.18s;
            cursor: pointer;
        }
        .btn-secondary:hover, .btn-secondary:focus {
            background: #6366f1;
            color: #fff;
            border-color: #6366f1;
        }
        .form-footer {
            text-align: center;
            margin-top: 1.2rem;
            color: #64748b;
        }
        .form-footer .btn-secondary {
            margin-top: 0.2rem;
        }
        .error {
            background: #fee2e2;
            color: #b91c1c;
            border-radius: 8px;
            padding: 0.7rem 1rem;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 1rem;
        }
        .success {
            background: #d1fae5;
            color: #047857;
            border-radius: 8px;
            padding: 0.7rem 1rem;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 1rem;
        }
        label {
            font-weight: 500;
            color: #334155;
            margin-bottom: 0.3rem;
            display: block;
        }
        .form-text {
            font-size: 0.95em;
            color: #64748b;
            margin-top: 0.2rem;
        }
        @media (max-width: 600px) {
            .register-card { padding: 1.2rem 0.7rem; }
        }
    </style>
</head>
<body>
    <div class="register-outer">
      <div class="register-card">
        <div class="register-logo"><i class="fa fa-users"></i></div>
        <div class="register-title">Create your account</div>
        <div class="register-subtitle">Join the Virtual Office Queue</div>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-user"></i></span>
                <input type="text" id="name" name="name" placeholder="Full Name" required>
            </div>
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-envelope"></i></span>
                <input type="email" id="email" name="email" placeholder="Email" required>
            </div>
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-lock"></i></span>
                <input type="password" id="password" name="password" placeholder="Password" required>
            </div>
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-lock"></i></span>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" required>
            </div>
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-user-graduate"></i></span>
                <select id="role" name="role" required onchange="toggleRoleFields()">
                    <option value="">Select Role</option>
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                </select>
            </div>
            <div class="form-group student-fields" style="display: none;">
                <span class="input-icon"><i class="fa fa-id-card"></i></span>
                <input type="text" id="faculty_number" name="faculty_number" placeholder="Faculty Number">
            </div>
            <div class="form-group student-fields" style="display: none;">
                <span class="input-icon"><i class="fa fa-laptop-code"></i></span>
                <select id="specialization" name="specialization">
                    <option value="">Select Specialization</option>
                    <option value="Software Engineering">Software Engineering</option>
                    <option value="Information Systems">Information Systems</option>
                    <option value="Computer Science">Computer Science</option>
                    <option value="Applied Mathematics">Applied Mathematics</option>
                    <option value="Informatics">Informatics</option>
                </select>
            </div>
            <div class="form-group student-fields" style="display: none;">
                <span class="input-icon"><i class="fa fa-calendar-alt"></i></span>
                <select id="year_of_study" name="year_of_study">
                    <option value="">Select Year</option>
                    <option value="1st year">1st year</option>
                    <option value="2nd year">2nd year</option>
                    <option value="3rd year">3rd year</option>
                    <option value="4th year">4th year</option>
                </select>
            </div>
            <div class="form-group teacher-fields" style="display: none;">
                <span class="input-icon"><i class="fa fa-briefcase"></i></span>
                <input type="text" id="teacher_role" name="teacher_role" placeholder="e.g., Professor, Assistant Professor">
            </div>
            <div class="form-group teacher-fields" style="display: none;">
                <span class="input-icon"><i class="fa fa-book"></i></span>
                <textarea id="subjects" name="subjects" placeholder="Enter subjects you teach, separated by commas"></textarea>
            </div>
            <button type="submit" class="btn-primary">Register</button>
        </form>
        <div class="form-footer">
            <span>Already have an account?</span> <a href="login.php" class="btn-secondary">Login</a>
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
            field.querySelectorAll('input, select').forEach(el => {
                el.required = (role === 'student');
            });
        });

        teacherFields.forEach(field => {
            field.style.display = role === 'teacher' ? 'block' : 'none';
            field.querySelectorAll('input, textarea').forEach(el => {
                el.required = (role === 'teacher');
            });
        });
    }
    // Call once on page load to set correct visibility
    document.addEventListener('DOMContentLoaded', toggleRoleFields);
    </script>
</body>
</html> 