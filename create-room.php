<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'teacher';
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

ob_start();
?>
<h2>Create Meeting Room</h2>
<div class="mt-4">
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form action="create-queue.php" method="POST">
                        <div class="mb-3">
                            <label for="purpose" class="form-label">Meeting Purpose</label>
                            <input type="text" class="form-control" id="purpose" name="purpose" required>
                        </div>
                        <div class="mb-3">
                            <label for="start_time" class="form-label">Start Time</label>
                            <input type="datetime-local" class="form-control" id="start_time" name="start_time" required>
                        </div>
                        <div class="mb-3">
                            <label for="duration" class="form-label">Duration (minutes)</label>
                            <input type="number" class="form-control" id="duration" name="duration" min="5" max="120" value="30" required>
                        </div>
                        <div class="mb-3">
                            <label for="max_students" class="form-label">Maximum Students</label>
                            <input type="number" class="form-control" id="max_students" name="max_students" min="1" max="50" value="10" required>
                        </div>
                        <div class="mb-3">
                            <label for="meeting_link" class="form-label">Meeting Link (Google Meet/Zoom)</label>
                            <input type="url" class="form-control" id="meeting_link" name="meeting_link" required>
                        </div>
                        <div class="mb-3">
                            <label for="access_code" class="form-label">Access Code (if any)</label>
                            <input type="text" class="form-control" id="access_code" name="access_code">
                        </div>
                        <button type="submit" class="btn btn-primary">Create Meeting Room</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Tips</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2">✓ Set a clear purpose for your meeting</li>
                        <li class="mb-2">✓ Choose an appropriate duration</li>
                        <li class="mb-2">✓ Set a reasonable maximum number of students</li>
                        <li class="mb-2">✓ Test your meeting link before creating</li>
                        <li class="mb-2">✓ Share access codes if required</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
require 'layout.php';
?>
