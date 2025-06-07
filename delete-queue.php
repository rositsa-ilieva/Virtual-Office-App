<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['queue_id'])) {
    $queue_id = $_POST['queue_id'];

    // Only allow the teacher who owns the queue to delete it
    $stmt = $pdo->prepare('SELECT * FROM queues WHERE id = ? AND teacher_id = ?');
    $stmt->execute([$queue_id, $_SESSION['user_id']]);
    $queue = $stmt->fetch();

    if ($queue) {
        // Delete all related queue entries first (to avoid foreign key constraint errors)
        $stmt = $pdo->prepare('DELETE FROM queue_entries WHERE queue_id = ?');
        $stmt->execute([$queue_id]);

        // Delete the queue itself
        $stmt = $pdo->prepare('DELETE FROM queues WHERE id = ?');
        $stmt->execute([$queue_id]);
    }
}

header('Location: index.php');
exit(); 