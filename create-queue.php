<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'teacher') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_id = $_SESSION['user_id'];
    $purpose = trim($_POST['purpose'] ?? '');
    $start_time = $_POST['start_time'] ?? '';
    $duration = (int)($_POST['duration'] ?? 30);
    $max_students = (int)($_POST['max_students'] ?? 10);
    $meeting_link = trim($_POST['meeting_link'] ?? '');
    $access_code = trim($_POST['access_code'] ?? '');
    $target_specialization = isset($_POST['target_specialization']) ? implode(',', $_POST['target_specialization']) : 'All';
    $target_year = $_POST['target_year'] ?? 'All';

    $errors = [];
    if ($purpose === '') $errors[] = 'Purpose is required.';
    if ($start_time === '') $errors[] = 'Start time is required.';
    if ($duration < 5 || $duration > 120) $errors[] = 'Duration must be between 5 and 120 minutes.';
    if ($max_students < 1 || $max_students > 50) $errors[] = 'Maximum students must be between 1 and 50.';
    if ($meeting_link === '') $errors[] = 'Meeting link is required.';

    if (empty($errors)) {
        $stmt = $pdo->prepare('INSERT INTO queues (purpose, start_time, default_duration, max_students, meeting_link, access_code, teacher_id, is_active, created_at, target_specialization, target_year) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, ?)');
        $stmt->execute([
            $purpose,
            $start_time,
            $duration,
            $max_students,
            $meeting_link,
            $access_code,
            $teacher_id,
            $target_specialization,
            $target_year
        ]);
        header('Location: index.php?message=room_created');
        exit();
    } else {
        // Store errors in session and redirect back
        $_SESSION['create_room_errors'] = $errors;
        header('Location: create-room.php');
        exit();
    }
} else {
    header('Location: create-room.php');
    exit();
}

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Queue - Virtual Office Queue</title>
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
        .create-queue-outer {
            width: 100vw;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .create-queue-card {
            background: linear-gradient(120deg, #f8fafc 60%, #e0e7ff 100%);
            border-radius: 22px;
            box-shadow: 0 8px 40px rgba(30,41,59,0.13), 0 1.5px 6px rgba(99,102,241,0.08);
            padding: 2.8rem 2.5rem 2.2rem 2.5rem;
            max-width: 440px;
            width: 100%;
            margin: 40px 0;
            animation: fadeInUp 0.8s cubic-bezier(.39,.575,.56,1.000);
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: none; }
        }
        .create-queue-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.1rem;
        }
        .create-queue-logo i {
            font-size: 2.5rem;
            color: #6366f1;
            background: #e0e7ff;
            border-radius: 50%;
            padding: 0.7rem;
            box-shadow: 0 2px 8px rgba(99,102,241,0.10);
        }
        .create-queue-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
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
        label {
            font-weight: 500;
            color: #334155;
            margin-bottom: 0.3rem;
            display: block;
        }
        @media (max-width: 600px) {
            .create-queue-card { padding: 1.2rem 0.7rem; }
        }
    </style>
</head>
<body>
    <div class="create-queue-outer">
      <div class="create-queue-card">
        <div class="create-queue-logo"><i class="fa fa-layer-group"></i></div>
        <div class="create-queue-title">Create a New Queue</div>
        <?php if (!empty($errors)): ?>
            <div class="error" style="background:#fee2e2;color:#b91c1c;border-radius:8px;padding:0.7rem 1rem;margin-bottom:1rem;text-align:center;font-size:1rem;">
                <?php foreach ($errors as $err) echo htmlspecialchars($err) . '<br>'; ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-pen"></i></span>
                <input type="text" name="purpose" placeholder="Queue Title" required>
            </div>
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-align-left"></i></span>
                <textarea name="description" placeholder="Description (optional)"></textarea>
            </div>
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-door-open"></i></span>
                <input type="text" name="room" placeholder="Select Room" required>
            </div>
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-cogs"></i></span>
                <select name="type" required>
                    <option value="manual">Manual</option>
                    <option value="automatic">Automatic</option>
                </select>
            </div>
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-clock"></i></span>
                <input type="number" name="duration" placeholder="Time Slot Duration (minutes)" min="1" required>
            </div>
            <button type="submit" class="btn-primary">Create Queue</button>
        </form>
      </div>
    </div>
</body>
</html> 