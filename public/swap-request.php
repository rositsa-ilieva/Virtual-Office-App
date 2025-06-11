<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

// Get the action from POST data
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'send':
        handleSendRequest();
        break;
    case 'accept':
        handleAcceptRequest();
        break;
    case 'decline':
        handleDeclineRequest();
        break;
    case 'get_status':
        getSwapStatus();
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid action']);
        exit();
}

function handleSendRequest() {
    global $pdo;
    
    $queue_id = $_POST['queue_id'] ?? 0;
    $receiver_id = $_POST['receiver_id'] ?? 0;
    $sender_id = $_SESSION['user_id'];

    // Validate input
    if (!$queue_id || !$receiver_id) {
        echo json_encode(['error' => 'Missing required parameters']);
        exit();
    }

    try {
        // Check if sender and receiver are in the queue
        $stmt = $pdo->prepare('SELECT id FROM queue_entries WHERE queue_id = ? AND student_id IN (?, ?)');
        $stmt->execute([$queue_id, $sender_id, $receiver_id]);
        if ($stmt->rowCount() !== 2) {
            echo json_encode(['error' => 'Both users must be in the queue']);
            exit();
        }

        // Check if there's already a pending request
        $stmt = $pdo->prepare('SELECT id FROM swap_requests WHERE queue_id = ? AND sender_id = ? AND status = "pending"');
        $stmt->execute([$queue_id, $sender_id]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['error' => 'You already have a pending swap request']);
            exit();
        }

        // Create new swap request
        $stmt = $pdo->prepare('INSERT INTO swap_requests (queue_id, sender_id, receiver_id) VALUES (?, ?, ?)');
        $stmt->execute([$queue_id, $sender_id, $receiver_id]);

        echo json_encode(['success' => true, 'message' => 'Swap request sent successfully']);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleAcceptRequest() {
    global $pdo;
    
    $request_id = $_POST['request_id'] ?? 0;
    $user_id = $_SESSION['user_id'];

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Get the swap request
        $stmt = $pdo->prepare('SELECT * FROM swap_requests WHERE id = ? AND receiver_id = ? AND status = "pending"');
        $stmt->execute([$request_id, $user_id]);
        $request = $stmt->fetch();

        if (!$request) {
            throw new Exception('Invalid or expired request');
        }

        // Get current positions
        $stmt = $pdo->prepare('SELECT position FROM queue_entries WHERE queue_id = ? AND student_id IN (?, ?)');
        $stmt->execute([$request['queue_id'], $request['sender_id'], $request['receiver_id']]);
        $positions = $stmt->fetchAll();
        
        if (count($positions) !== 2) {
            throw new Exception('Queue entries not found');
        }

        // Swap positions
        $stmt = $pdo->prepare('UPDATE queue_entries SET position = CASE 
            WHEN student_id = ? THEN ? 
            WHEN student_id = ? THEN ? 
            END 
            WHERE queue_id = ? AND student_id IN (?, ?)');
        $stmt->execute([
            $request['sender_id'], $positions[1]['position'],
            $request['receiver_id'], $positions[0]['position'],
            $request['queue_id'], $request['sender_id'], $request['receiver_id']
        ]);

        // Update request status
        $stmt = $pdo->prepare('UPDATE swap_requests SET status = "accepted" WHERE id = ?');
        $stmt->execute([$request_id]);

        // Update estimated start times
        updateEstimatedTimes($request['queue_id']);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Swap request accepted']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function handleDeclineRequest() {
    global $pdo;
    
    $request_id = $_POST['request_id'] ?? 0;
    $user_id = $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare('UPDATE swap_requests SET status = "declined" WHERE id = ? AND receiver_id = ? AND status = "pending"');
        $stmt->execute([$request_id, $user_id]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('Invalid or expired request');
        }

        echo json_encode(['success' => true, 'message' => 'Swap request declined']);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function getSwapStatus() {
    global $pdo;
    
    $queue_id = $_POST['queue_id'] ?? 0;
    $user_id = $_SESSION['user_id'];

    try {
        // Get pending requests where user is sender
        $stmt = $pdo->prepare('
            SELECT sr.*, u.name as receiver_name 
            FROM swap_requests sr 
            JOIN users u ON sr.receiver_id = u.id 
            WHERE sr.queue_id = ? AND sr.sender_id = ? AND sr.status = "pending"
        ');
        $stmt->execute([$queue_id, $user_id]);
        $sent_requests = $stmt->fetchAll();

        // Get pending requests where user is receiver
        $stmt = $pdo->prepare('
            SELECT sr.*, u.name as sender_name 
            FROM swap_requests sr 
            JOIN users u ON sr.sender_id = u.id 
            WHERE sr.queue_id = ? AND sr.receiver_id = ? AND sr.status = "pending"
        ');
        $stmt->execute([$queue_id, $user_id]);
        $received_requests = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'sent_requests' => $sent_requests,
            'received_requests' => $received_requests
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function updateEstimatedTimes($queue_id) {
    global $pdo;
    
    // Get queue settings
    $stmt = $pdo->prepare('SELECT default_duration FROM queues WHERE id = ?');
    $stmt->execute([$queue_id]);
    $queue = $stmt->fetch();

    // Get all waiting entries ordered by position
    $stmt = $pdo->prepare('
        SELECT id, position 
        FROM queue_entries 
        WHERE queue_id = ? AND status = "waiting" 
        ORDER BY position ASC
    ');
    $stmt->execute([$queue_id]);
    $entries = $stmt->fetchAll();

    // Calculate and update estimated times
    $current_time = new DateTime();
    foreach ($entries as $entry) {
        $estimated_time = clone $current_time;
        $estimated_time->modify('+' . ($entry['position'] - 1) * $queue['default_duration'] . ' minutes');
        
        $stmt = $pdo->prepare('UPDATE queue_entries SET estimated_start_time = ? WHERE id = ?');
        $stmt->execute([$estimated_time->format('Y-m-d H:i:s'), $entry['id']]);
    }
}
?> 