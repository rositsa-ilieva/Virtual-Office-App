<?php
session_start();
require_once 'db.php';

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

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            header('Location: index.php');
            exit();
        } else {
            $error = 'Invalid email or password';
        }
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
        .login-outer {
            width: 100vw;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
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
        .login-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.1rem;
        }
        .login-logo i {
            font-size: 2.5rem;
            color: #6366f1;
            background: #e0e7ff;
            border-radius: 50%;
            padding: 0.7rem;
            box-shadow: 0 2px 8px rgba(99,102,241,0.10);
        }
        .login-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
            text-align: center;
            color: #1e293b;
        }
        .login-subtitle {
            font-size: 1.1rem;
            color: #64748b;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.2rem;
            position: relative;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            font-size: 1rem;
            background: #f1f5f9;
            transition: border 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }
        .form-group input:focus {
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
        @media (max-width: 600px) {
            .login-card { padding: 1.2rem 0.7rem; }
        }
    </style>
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