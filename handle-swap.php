<?php
// handle-swap.php
include 'db.php';
session_start();

$current_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['swap_id'], $_POST['action'])) {
    $swap_id = intval($_POST['swap_id']);
    $action = $_POST['action'];

    $swap = $db->query("SELECT * FROM swap_requests WHERE id = $swap_id AND receiver_id = $current_user_id AND status = 'pending'")->fetch_assoc();

    if ($swap) {
        $queue_id = $swap['queue_id'];
        $sender_id = $swap['sender_id'];
        $receiver_id = $swap['receiver_id'];

        if ($action === 'accept') {
            // Fetch queue entries for both users
            $sender_entry = $db->query("SELECT * FROM queue_entries WHERE queue_id = $queue_id AND student_id = $sender_id")->fetch_assoc();
            $receiver_entry = $db->query("SELECT * FROM queue_entries WHERE queue_id = $queue_id AND student_id = $receiver_id")->fetch_assoc();

            if ($sender_entry && $receiver_entry) {
                // Swap their positions
                $db->query("UPDATE queue_entries SET position = {$receiver_entry['position']} WHERE id = {$sender_entry['id']}");
                $db->query("UPDATE queue_entries SET position = {$sender_entry['position']} WHERE id = {$receiver_entry['id']}");

                // Update swap request status
                $db->query("UPDATE swap_requests SET status = 'accepted' WHERE id = $swap_id");

                $_SESSION['message'] = "Swap request accepted. Positions updated.";
            }
        } elseif ($action === 'decline') {
            $db->query("UPDATE swap_requests SET status = 'declined' WHERE id = $swap_id");
            $_SESSION['message'] = "Swap request declined.";
        }
    } else {
        $_SESSION['message'] = "Invalid swap request or already handled.";
    }
} else {
    $_SESSION['message'] = "Invalid request.";
}

header("Location: my-queue.php");
exit();
