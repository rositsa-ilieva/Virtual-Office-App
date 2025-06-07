<?php
require_once 'db.php';

try {
    // Add end_time column to queues table
    $pdo->exec("ALTER TABLE queues ADD COLUMN end_time DATETIME NULL AFTER start_time");
    
    // Update existing records to set end_time based on start_time and default_duration
    $pdo->exec("UPDATE queues 
                SET end_time = DATE_ADD(start_time, INTERVAL default_duration MINUTE) 
                WHERE start_time IS NOT NULL AND end_time IS NULL");
    
    echo "Database schema updated successfully!";
} catch (PDOException $e) {
    echo "Error updating schema: " . $e->getMessage();
}
?> 