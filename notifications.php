<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle swap request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $notification_id = $_POST['notification_id'] ?? null;
    $queue_id = $_POST['queue_id'] ?? null;
    $from_user_id = $_POST['from_user_id'] ?? null;

    if ($notification_id && $queue_id && $from_user_id) {
        if ($action === 'approve_swap') {
            // Get both entries
            $stmt = $pdo->prepare('SELECT id, position FROM queue_entries WHERE queue_id = ? AND student_id IN (?, ?)');
            $stmt->execute([$queue_id, $from_user_id, $user_id]);
            $entries = $stmt->fetchAll();
            
            if (count($entries) == 2) {
                $pos1 = $entries[0]['position'];
                $id1 = $entries[0]['id'];
                $pos2 = $entries[1]['position'];
                $id2 = $entries[1]['id'];
                
                // Swap positions
                $pdo->prepare('UPDATE queue_entries SET position = ? WHERE id = ?')->execute([$pos2, $id1]);
                $pdo->prepare('UPDATE queue_entries SET position = ? WHERE id = ?')->execute([$pos1, $id2]);
                
                // Create notification for the requester
                $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, message, related_queue_id, related_user_id) VALUES (?, "swap_approved", ?, ?, ?)');
                $stmt->execute([$from_user_id, "Your position swap request was approved", $queue_id, $user_id]);
            }
        } elseif ($action === 'decline_swap') {
            // Create notification for the requester
            $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, message, related_queue_id, related_user_id) VALUES (?, "swap_declined", ?, ?, ?)');
            $stmt->execute([$from_user_id, "Your position swap request was declined", $queue_id, $user_id]);
        }
        
        // Mark the notification as read
        $stmt = $pdo->prepare('UPDATE notifications SET is_read = TRUE WHERE id = ?');
        $stmt->execute([$notification_id]);
        
        header('Location: ' . $_SERVER['HTTP_REFERER']);
        exit();
    }
}

// Get unread notifications
$stmt = $pdo->prepare('
    SELECT n.*, u.name as from_user_name, q.purpose as queue_purpose 
    FROM notifications n
    JOIN users u ON n.related_user_id = u.id
    JOIN queues q ON n.related_queue_id = q.id
    WHERE n.user_id = ? AND n.is_read = FALSE
    ORDER BY n.created_at DESC
');
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();

// Get notification count
$stmt = $pdo->prepare('SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE');
$stmt->execute([$user_id]);
$notification_count = $stmt->fetch()['count'];
?> 