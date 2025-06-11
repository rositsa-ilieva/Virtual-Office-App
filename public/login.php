<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';
if (isset($_GET['message']) && $_GET['message'] === 'registered') {
    $success = 'Registration successful. You can now log in.';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Check if password is hashed (starts with $2y$)
            if (strpos($user['password'], '$2y$') === 0) {
                $valid = password_verify($password, $user['password']);
            } else {
                // Plain text password comparison
                $valid = ($password === $user['password']);
            }

            if ($valid) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_role'] = $user['role'];
                header('Location: index.php');
                exit();
            }
        }
        $error = 'Invalid email or password';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Virtual Office Queue</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="css/login.css" rel="stylesheet">
</head>
<body>
    <div class="login-outer">
        <div class="login-card">
            <div class="login-logo"><i class="fa fa-sign-in-alt"></i></div>
            <div class="login-title">Sign in to your account</div>
            <div class="login-subtitle">Virtual Office Queue</div>
            <?php if (isset($error) && $error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (isset($success) && $success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <span class="input-icon"><i class="fa fa-envelope"></i></span>
                    <input type="email" id="email" name="email" placeholder="Email" required autofocus>
                </div>
                <div class="form-group">
                    <span class="input-icon"><i class="fa fa-lock"></i></span>
                    <input type="password" id="password" name="password" placeholder="Password" required>
                </div>
                <button type="submit" class="btn-primary">Login</button>
            </form>
            <div class="form-footer">
                <span>Don't have an account?</span> <a href="register.php" class="btn-secondary">Register</a>
            </div>
        </div>
    </div>
</body>
</html> 