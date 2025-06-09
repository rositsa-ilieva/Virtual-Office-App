<?php
require_once 'db.php';
$queue_id = $_GET['queue_id'] ?? 0;
$stmt = $pdo->prepare("SELECT qe.position, u.name as student_name, qe.status, qe.estimated_start_time
                       FROM queue_entries qe
                       JOIN users u ON qe.student_id = u.id
                       WHERE qe.queue_id = ?
                       ORDER BY qe.position ASC");
$stmt->execute([$queue_id]);
$members = $stmt->fetchAll();
?>
<table class="table-modern" style="margin-top:1.5rem;width:100%;max-width:700px;">
  <thead>
    <tr>
      <th>Position</th>
      <th>Name</th>
      <th>Status</th>
      <th>Estimated Start Time</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($members as $entry): ?>
      <tr>
        <td><?php echo htmlspecialchars($entry['position']); ?></td>
        <td><?php echo htmlspecialchars($entry['student_name']); ?></td>
        <td><?php echo htmlspecialchars($entry['status']); ?></td>
        <td><?php echo $entry['estimated_start_time'] ? date('g:i A', strtotime($entry['estimated_start_time'])) : '-'; ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table> 