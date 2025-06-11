<?php
session_start();
require_once 'config.php';

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
    <title>Create Queue - Virtual Office</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/create-queue.css">
</head>
<body>
    <div class="create-queue-outer">
      <div class="create-queue-card">
        <div class="create-queue-logo"><i class="fa fa-layer-group"></i></div>
        <div class="create-queue-title">Create a New Queue</div>
        <?php if (!empty($errors)): ?>
            <div class="error">
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