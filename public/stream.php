<?php
/**
 * SSE stream for real-time messages
 * This file implements Server-Sent Events for realtime message delivery
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable buffering for Nginx

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: same-origin');

// Prevent output buffering
if (ob_get_level()) ob_end_clean();

// Get room ID
$room = isset($_GET['room']) ? $_GET['room'] : 'global';

// Normalize and sanitize room name for security
$room = preg_replace('/[^a-zA-Z0-9_-]/', '', $room);

// Convert PHP errors to exceptions
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Send initial connection event
echo "event: connected\n";
echo "data: " . json_encode(['status' => 'connected', 'room' => $room]) . "\n\n";
flush();

try {
    // If Redis is available, use Redis pub/sub for real-time messages
    if (USE_REDIS) {
        $redis = getRedis();
        
        // Subscribe to the room channel
        $redis->subscribe([REDIS_PREFIX . 'channel:' . $room], function($redis, $channel, $message) {
            // Decode the message
            $data = json_decode($message, true);
            
            if ($data) {
                // Format as SSE event
                echo "event: message\n";
                echo "data: " . $message . "\n\n";
                flush();
            }
        });
    } 
    // If MongoDB is used, implement a polling mechanism with low latency
    else if (defined('USE_MONGODB') && USE_MONGODB) {
        // Initial message count
        $lastCount = 0;
        $lastMessage = null;
        
        // Loop until client closes connection
        while (true) {
            // Get latest messages
            $messages = getMongoMessages($room, 1);
            
            if (!empty($messages)) {
                $latest = end($messages);
                
                // Only send if newer than last sent message
                if ($lastMessage === null || $latest['created_at'] > $lastMessage['created_at']) {
                    // Send as SSE event
                    echo "event: message\n";
                    echo "data: " . json_encode($latest) . "\n\n";
                    flush();
                    
                    // Update last message
                    $lastMessage = $latest;
                }
            }
            
            // Short sleep to reduce CPU usage
            usleep(500000); // 500ms
            
            // Check connection
            if (connection_aborted()) break;
        }
    }
    // Fallback to traditional polling with SQLite
    else {
        // Initial message count
        $lastCount = 0;
        
        // Loop until client closes connection
        while (true) {
            // Get current message count
            $db = getDB();
            $stmt = $db->prepare('SELECT COUNT(*) as count FROM messages WHERE room = :room');
            $stmt->bindParam(':room', $room, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $currentCount = (int)$result['count'];
            
            // If we have new messages
            if ($currentCount > $lastCount) {
                // Get latest message
                $stmt = $db->prepare('
                    SELECT * FROM messages 
                    WHERE room = :room 
                    ORDER BY id DESC 
                    LIMIT 1
                ');
                $stmt->bindParam(':room', $room, PDO::PARAM_STR);
                $stmt->execute();
                $message = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($message) {
                    // Send as SSE event
                    echo "event: message\n";
                    echo "data: " . json_encode($message) . "\n\n";
                    flush();
                    
                    // Update count
                    $lastCount = $currentCount;
                }
            }
            
            // Short sleep to reduce CPU usage
            usleep(1000000); // 1s
            
            // Check connection
            if (connection_aborted()) break;
        }
    }
} catch (Exception $e) {
    // Log error
    error_log('SSE Error: ' . $e->getMessage());
    
    // Send error event
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Connection error']) . "\n\n";
    flush();
}

// Restore error handler
restore_error_handler(); 