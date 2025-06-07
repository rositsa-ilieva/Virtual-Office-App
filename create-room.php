<?php
session_start();
require_once 'db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purpose = $_POST['purpose'] ?? '';
    $description = $_POST['description'] ?? '';
    $meeting_link = $_POST['meeting_link'] ?? '';
    $access_code = $_POST['access_code'] ?? '';
    $meeting_type = $_POST['meeting_type'] ?? '';
    $wait_time_method = $_POST['wait_time_method'] ?? 'automatic';
    $is_automatic = isset($_POST['is_automatic']) ? 1 : 0;
    $default_duration = $_POST['default_duration'] ?? 15;
    $start_time = $_POST['start_time'] ?? '';
    $start_time_db = null;
    $invalid_date = false;
    if ($start_time) {
        // Accept only valid datetime-local format (YYYY-MM-DDTHH:MM)
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $start_time)) {
            $start_time_db = date('Y-m-d H:i:s', strtotime($start_time));
        } else {
            $invalid_date = true;
        }
    }
    error_log('Start time raw: ' . $start_time);
    error_log('Start time parsed: ' . $start_time_db);

    if (empty($purpose) || empty($description) || empty($meeting_type) || empty($wait_time_method)) {
        $error = 'Please fill in all required fields';
    } elseif ($invalid_date) {
        $error = 'Invalid date/time format. Please use the date/time picker.';
    } else {
        try {
            $sql = "INSERT INTO queues (teacher_id, purpose, description, meeting_link, access_code, meeting_type, is_automatic, default_duration, wait_time_method, start_time) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$_SESSION['user_id'], $purpose, $description, $meeting_link, $access_code, $meeting_type, $is_automatic, $default_duration, $wait_time_method, $start_time_db])) {
                $success = 'Queue room created successfully!';
            } else {
                $error = 'Failed to create queue room. Please try again.';
            }
        } catch (PDOException $e) {
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Queue Room - Virtual Office Queue</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="nav">
        <div class="container nav-container">
            <h1>Virtual Office Queue</h1>
            <div class="nav-links">
                <a href="index.php">Back to Dashboard</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="form-container">
            <h2>Create New Queue Room</h2>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="purpose">Purpose:</label>
                    <input type="text" id="purpose" name="purpose" required placeholder="e.g., Project Defense, Consultation" value="<?php echo isset($_POST['purpose']) ? htmlspecialchars($_POST['purpose']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="description">Description:</label>
                    <textarea id="description" name="description" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>
                <div class="form-group">
                    <label for="meeting_link">Meeting Link:</label>
                    <input type="text" id="meeting_link" name="meeting_link" placeholder="https://meet.jit.si/your-room" value="<?php echo isset($_POST['meeting_link']) ? htmlspecialchars($_POST['meeting_link']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="access_code">Access Code (optional):</label>
                    <input type="text" id="access_code" name="access_code" placeholder="Meeting access code if required" value="<?php echo isset($_POST['access_code']) ? htmlspecialchars($_POST['access_code']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="start_time">Queue Start Time:</label>
                    <input type="datetime-local" id="start_time" name="start_time" value="<?php echo isset($_POST['start_time']) ? htmlspecialchars($_POST['start_time']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="meeting_type">Meeting Type:</label>
                    <select id="meeting_type" name="meeting_type" required>
                        <option value="">Select Type</option>
                        <option value="Zoom" <?php if(isset($_POST['meeting_type']) && $_POST['meeting_type']==='Zoom') echo 'selected'; ?>>Zoom</option>
                        <option value="Google Meet" <?php if(isset($_POST['meeting_type']) && $_POST['meeting_type']==='Google Meet') echo 'selected'; ?>>Google Meet</option>
                        <option value="Jitsi" <?php if(isset($_POST['meeting_type']) && $_POST['meeting_type']==='Jitsi') echo 'selected'; ?>>Jitsi</option>
                        <option value="BBB" <?php if(isset($_POST['meeting_type']) && $_POST['meeting_type']==='BBB') echo 'selected'; ?>>BBB</option>
                        <option value="Other" <?php if(isset($_POST['meeting_type']) && $_POST['meeting_type']==='Other') echo 'selected'; ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Wait Time Estimation:</label>
                    <label><input type="radio" name="wait_time_method" value="automatic" <?php if(!isset($_POST['wait_time_method']) || $_POST['wait_time_method']==='automatic') echo 'checked'; ?>> Automatic</label>
                    <label><input type="radio" name="wait_time_method" value="manual" <?php if(isset($_POST['wait_time_method']) && $_POST['wait_time_method']==='manual') echo 'checked'; ?>> Manual</label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_automatic" <?php if (isset($_POST['is_automatic'])) echo 'checked'; ?>>
                        Automatic Time Slots
                    </label>
                </div>
                <div class="form-group">
                    <label for="default_duration">Default Duration (minutes):</label>
                    <input type="number" id="default_duration" name="default_duration" value="<?php echo isset($_POST['default_duration']) ? htmlspecialchars($_POST['default_duration']) : '15'; ?>" min="1" required>
                </div>
                <button type="submit" class="btn">Create Queue Room</button>
            </form>
        </div>
    </div>
</body>
</html>
