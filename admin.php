<?php require 'inc/db.php'; ?>
<h2>Админ панел</h2>
<?php
$queue_id = $_GET['queue_id'] ?? 1;
$result = $mysqli->query("SELECT qe.id, u.name, qe.comment, qe.visibility, qe.status
  FROM queue_entries qe
  JOIN users u ON qe.user_id = u.id
  WHERE qe.queue_id = $queue_id
  ORDER BY qe.joined_at ASC");
?>
<table border="1">
<tr><th>Име</th><th>Коментар</th><th>Статус</th><th>Действие</th></tr>
<?php while ($row = $result->fetch_assoc()) {
  echo "<tr>
    <td>{$row['name']}</td>
    <td>" . ($row['visibility'] === 'private' ? '[лично]' : $row['comment']) . "</td>
    <td>{$row['status']}</td>
    <td><a href='room.php?action=enter&id={$row['id']}&queue_id=$queue_id'>Влез</a></td>
  </tr>";
} ?>
</table>
<a href="room.php?action=invite_all&queue_id=<?= $queue_id ?>">Покани всички</a>
