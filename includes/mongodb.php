<?php
/**
 * MongoDB Adapter for Anonymous Chat
 * This file provides MongoDB implementations for chat system
 */

// Check if MongoDB extension is available
if (!extension_loaded('mongodb')) {
    error_log('MongoDB PHP extension is not installed');
}

/**
 * Get MongoDB connection
 * @return MongoDB\Client MongoDB client instance
 */
function getMongoDB() {
    static $mongo = null;
    
    if ($mongo === null) {
        try {
            // Create MongoDB client with connection string
            $connectionString = 'mongodb://' . MONGO_HOST . ':' . MONGO_PORT;
            
            // Add authentication if configured
            if (MONGO_USER && MONGO_PASS) {
                $connectionString = 'mongodb://' . MONGO_USER . ':' . MONGO_PASS . '@' . 
                                   MONGO_HOST . ':' . MONGO_PORT;
            }
            
            // Create client with options
            $options = [
                'connectTimeoutMS' => 3000,
                'socketTimeoutMS' => 60000,
                'w' => 1,
                'retryWrites' => true
            ];
            
            $mongo = new MongoDB\Client($connectionString, $options);
        } catch (Exception $e) {
            error_log('MongoDB connection error: ' . $e->getMessage());
            return null;
        }
    }
    
    return $mongo;
}

/**
 * Get messages from MongoDB
 * @param string $room Room ID
 * @param int $limit Maximum number of messages
 * @return array Array of messages
 */
function getMongoMessages($room = 'global', $limit = 50) {
    $mongo = getMongoDB();
    if (!$mongo) return [];
    
    try {
        // Get database and collection
        $db = $mongo->selectDatabase(MONGO_DB);
        $collection = $db->messages;
        
        // Create query for messages in this room
        $query = ['room' => $room];
        
        // Set options for sorting and limiting
        $options = [
            'sort' => ['created_at' => 1],
            'limit' => $limit
        ];
        
        // Execute query
        $cursor = $collection->find($query, $options);
        
        // Convert to array
        $messages = [];
        foreach ($cursor as $document) {
            $messages[] = [
                'user_id' => $document['user_id'],
                'username' => $document['username'],
                'message' => $document['message'],
                'room' => $document['room'],
                'created_at' => $document['created_at']->toDateTime()->format('Y-m-d h:i:s A')
            ];
        }
        
        return $messages;
    } catch (Exception $e) {
        error_log('MongoDB query error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Add message to MongoDB
 * @param string $userId User ID
 * @param string $username Username
 * @param string $message Message content
 * @param string $room Room ID
 * @return bool Success or failure
 */
function addMongoMessage($userId, $username, $message, $room = 'global') {
    $mongo = getMongoDB();
    if (!$mongo) return false;
    
    try {
        // Get database and collection
        $db = $mongo->selectDatabase(MONGO_DB);
        $collection = $db->messages;
        
        // Prepare document
        $document = [
            'user_id' => $userId,
            'username' => $username,
            'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
            'room' => $room,
            'created_at' => new MongoDB\BSON\UTCDateTime(time() * 1000)
        ];
        
        // Insert document
        $result = $collection->insertOne($document);
        
        return $result->getInsertedCount() > 0;
    } catch (Exception $e) {
        error_log('MongoDB insert error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if there are new messages in MongoDB
 * @param int $lastCount Current message count
 * @param string $room Room ID
 * @return bool Whether there are new messages
 */
function hasMongoNewMessages($lastCount, $room = 'global') {
    $mongo = getMongoDB();
    if (!$mongo) return false;
    
    try {
        // Get database and collection
        $db = $mongo->selectDatabase(MONGO_DB);
        $collection = $db->messages;
        
        // Count documents in room
        $count = $collection->countDocuments(['room' => $room]);
        
        return (int)$count > (int)$lastCount;
    } catch (Exception $e) {
        error_log('MongoDB count error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Create MongoDB indexes for better performance
 * Should be called during setup/initialization
 */
function createMongoIndexes() {
    $mongo = getMongoDB();
    if (!$mongo) return false;
    
    try {
        // Get database and collection
        $db = $mongo->selectDatabase(MONGO_DB);
        $collection = $db->messages;
        
        // Create compound index on room and created_at
        $collection->createIndex(
            ['room' => 1, 'created_at' => 1],
            ['background' => true]
        );
        
        // Create TTL index to automatically expire old messages after 30 days
        $collection->createIndex(
            ['created_at' => 1],
            ['expireAfterSeconds' => 2592000, 'background' => true]
        );
        
        return true;
    } catch (Exception $e) {
        error_log('MongoDB index creation error: ' . $e->getMessage());
        return false;
    }
} 