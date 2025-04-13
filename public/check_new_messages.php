<?php
/**
 * Check for new messages (polling endpoint)
 * Used as a fallback when SSE is not supported by the browser
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Verify AJAX request
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['error' => 'Direct access not allowed']);
    exit;
}

// Get room ID
$room = $_GET['room'] ?? 'global';

// Normalize and sanitize room name for security
$room = preg_replace('/[^a-zA-Z0-9_-]/', '', $room);

// Get current message count the client knows about
$lastCount = isset($_GET['count']) ? (int)$_GET['count'] : 0;
if ($lastCount < 0) $lastCount = 0;

// Check if there are new messages
$hasNew = hasNewMessages($lastCount, $room);

// Return result
echo json_encode([
    'hasNew' => $hasNew,
    'timestamp' => time()
]); 