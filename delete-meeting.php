<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'teacher') {
    header('Location: login.php');
    exit();
}

$meeting_id = $_GET['id'] ?? null;
if (!$meeting_id) {
    header('Location: index.php');
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // First, get the queue_id for this meeting
    $stmt = $pdo->prepare('SELECT queue_id FROM queue_entries WHERE id = ?');
    $stmt->execute([$meeting_id]);
    $queue_entry = $stmt->fetch();
    
    if (!$queue_entry) {
        throw new Exception("Meeting not found");
    }
    
    // Delete related notifications for this meeting
    $stmt = $pdo->prepare('DELETE FROM notifications WHERE related_queue_id = ? AND related_user_id = ?');
    $stmt->execute([$queue_entry['queue_id'], $_SESSION['user_id']]);
    
    // Delete the meeting (queue entry)
    $stmt = $pdo->prepare('DELETE FROM queue_entries WHERE id = ? AND queue_id = ?');
    $stmt->execute([$meeting_id, $queue_entry['queue_id']]);
    
    // Commit transaction
    $pdo->commit();
    
    header('Location: index.php');
    exit();
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    die("Error deleting meeting: " . $e->getMessage());
} 