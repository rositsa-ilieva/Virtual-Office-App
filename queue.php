<?php require 'db.php';
$queue_id = $_GET['queue_id'];
$result = $mysqli->query("
    SELECT u.name, qe.comment, qe.status
    FROM queue_entries qe
    JOIN users u ON qe.student_id = u.id
    WHERE qe.queue_id = $queue_id
    ORDER BY qe.joined_at ASC
");
?>

<!DOCTYPE html>
<html>
<head><title>Опашка</title></head>
<body>
    <h2>Текуща опашка</h2>
    <ul>
        <?php while ($row = $result->fetch_assoc()) {
            echo "<li><b>{$row['name']}</b> - {$row['status']}<br><i>{$row['comment']}</i></li>";
        } ?>
    </ul>
</body>
</html>