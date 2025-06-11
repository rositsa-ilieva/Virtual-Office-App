<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit(); }

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo->beginTransaction();

        $purpose = $_POST["purpose"];
        $start_time = $_POST["start_time"];
        $duration = $_POST["duration"];
        $max_students = $_POST["max_students"];
        $meeting_link = $_POST["meeting_link"];
        $access_code = $_POST["access_code"];
        $specializations = $_POST["specializations"] ?? [];
        $specialization_year_map = $_POST["specialization_year_map"] ?? '';
        $teacher_id = $_SESSION["user_id"];

        $specializations_str = implode(",", $specializations);

        // Insert into queues table
        $stmt = $pdo->prepare("INSERT INTO queues (purpose, start_time, default_duration, max_students, meeting_link, access_code, teacher_id, is_active, created_at, target_specialization, specialization_year_map) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, ?)");
        $stmt->execute([
            $purpose,
            $start_time,
            $duration,
            $max_students,
            $meeting_link,
            $access_code,
            $teacher_id,
            $specializations_str,
            $specialization_year_map
        ]);

        $queue_id = $pdo->lastInsertId();

        // Create time slots for the meeting
        $start_datetime = new DateTime($start_time);
        $end_datetime = clone $start_datetime;
        $end_datetime->add(new DateInterval('PT' . $duration . 'M'));

        $stmt = $pdo->prepare("INSERT INTO time_slots (queue_id, start_time, end_time, is_available) VALUES (?, ?, ?, 1)");
        $stmt->execute([
            $queue_id,
            $start_datetime->format('Y-m-d H:i:s'),
            $end_datetime->format('Y-m-d H:i:s')
        ]);

        // Initialize queue statistics
        $stmt = $pdo->prepare("INSERT INTO queue_statistics (queue_id, total_entries, average_wait_time, average_meeting_duration) VALUES (?, 0, 0, ?)");
        $stmt->execute([$queue_id, $duration]);

        $pdo->commit();
        header("Location: queue-schedule.php?success=1");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = "Error creating meeting: " . $e->getMessage();
    }
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'teacher';
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Meeting Room - Virtual Office Queue</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="css/create-room.css" rel="stylesheet">
</head>
<body>
    <div class="create-room-outer">
      <div class="create-room-card">
        <div class="create-room-header">
          <i class="fa fa-layer-group"></i>
          <div class="create-room-title">Create Meeting Room</div>
          <div class="create-room-subtitle">Fill in the details to create a new meeting session.</div>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="error" style="background:#fee2e2;color:#b91c1c;border-radius:8px;padding:0.7rem 1rem;margin-bottom:1rem;text-align:center;font-size:1rem;">
                <?php foreach ($errors as $err) echo htmlspecialchars($err) . '<br>'; ?>
            </div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-pen"></i></span>
                <input type="text" name="purpose" placeholder="Purpose / Meeting Title" required>
            </div>
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-calendar-alt"></i></span>
                <input type="datetime-local" name="start_time" placeholder="Start Time" required>
            </div>
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-clock"></i></span>
                <input type="number" name="duration" placeholder="Duration (minutes)" min="1" required>
            </div>
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-users"></i></span>
                <input type="number" name="max_students" placeholder="Max Students" min="1" required>
            </div>
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-link"></i></span>
                <input type="text" name="meeting_link" placeholder="Meeting Link" required>
            </div>
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-key"></i></span>
                <input type="text" name="access_code" placeholder="Access Code (optional)">
            </div>
            <div class="form-group">
                <span class="input-icon"><i class="fa fa-layer-group"></i></span>
                <label for="specializations" style="margin-left:2.2rem;">Target Specializations</label>
                <select id="specializations" name="specializations[]" multiple required style="height: 110px;">
                    <option value="Software Engineering">Software Engineering</option>
                    <option value="Information Systems">Information Systems</option>
                    <option value="Computer Science">Computer Science</option>
                    <option value="Applied Mathematics">Applied Mathematics</option>
                    <option value="Informatics">Informatics</option>
                </select>
                <small style="margin-left:2.2rem;color:#64748b;">Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</small>
            </div>
            <div id="years-section"></div>
            <input type="hidden" name="specialization_year_map" id="specialization_year_map">
            <button type="submit" class="btn-primary">Create Room</button>
        </form>
      </div>
      <div class="tips-card">
        <div class="tips-title"><i class="fa fa-lightbulb"></i> Tips</div>
        <ul class="tips-list">
          <li>✓ Set a clear purpose for your meeting room.</li>
          <li>✓ Share the access code with students if needed.</li>
          <li>✓ Double-check the time and duration before creating.</li>
        </ul>
      </div>
    </div>
    <script>
    const specializationOptions = [
      "Software Engineering",
      "Information Systems",
      "Computer Science",
      "Applied Mathematics",
      "Informatics"
    ];
    const yearOptions = [
      "1st year",
      "2nd year",
      "3rd year",
      "4th year"
    ];
    const yearsSection = document.getElementById('years-section');
    const specializationsSelect = document.getElementById('specializations');
    const specYearMapInput = document.getElementById('specialization_year_map');

    function renderYearCheckboxes() {
      yearsSection.innerHTML = '';
      const selected = Array.from(specializationsSelect.selectedOptions).map(opt => opt.value);
      if (selected.length === 0) return;
      selected.forEach(spec => {
        const group = document.createElement('div');
        group.className = 'form-group';
        group.style.marginBottom = '0.7rem';
        group.innerHTML = `<label style="margin-left:2.2rem;font-weight:500;color:#334155;">${spec} - Target Years</label>`;
        yearOptions.forEach(year => {
          const id = `year_${spec.replace(/\s/g,'_')}_${year.replace(/\s/g,'_')}`;
          group.innerHTML += `
            <div style="display:inline-block;margin-right:1.2rem;">
              <input type="checkbox" id="${id}" name="years_for_${spec}" value="${year}" onchange="updateSpecYearMap()">
              <label for="${id}" style="font-weight:400;margin-left:0.2rem;">${year}</label>
            </div>
          `;
        });
        yearsSection.appendChild(group);
      });
      updateSpecYearMap();
    }
    function updateSpecYearMap() {
      const selected = Array.from(specializationsSelect.selectedOptions).map(opt => opt.value);
      const map = {};
      selected.forEach(spec => {
        const checkboxes = document.querySelectorAll(`input[name='years_for_${spec}']:checked`);
        map[spec] = Array.from(checkboxes).map(cb => cb.value);
      });
      specYearMapInput.value = JSON.stringify(map);
    }
    specializationsSelect.addEventListener('change', renderYearCheckboxes);
    document.addEventListener('DOMContentLoaded', renderYearCheckboxes);
    </script>
</body>
</html>
<?php
$content = ob_get_clean();
require '../src/Includes/layout.php';
?>
