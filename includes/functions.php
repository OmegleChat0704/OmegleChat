<?php
/**
 * 函数库文件
 */

/**
 * 获取Redis连接
 * @return Redis Redis连接对象
 */
function getRedis() {
    static $redis = null;
    if ($redis === null) {
        $redis = new Redis();
        $redis->connect(REDIS_HOST, REDIS_PORT);
        
        if (REDIS_AUTH !== null) {
            $redis->auth(REDIS_AUTH);
        }
        
        if (REDIS_DB !== 0) {
            $redis->select(REDIS_DB);
        }
        
        // Performance optimization - use persistent connections
        $redis->pconnect(REDIS_HOST, REDIS_PORT);
        
        // Set connection options for better performance
        $redis->setOption(Redis::OPT_READ_TIMEOUT, -1);
        $redis->setOption(Redis::OPT_TCP_KEEPALIVE, 1);
    }
    return $redis;
}

/**
 * 获取SQLite数据库连接
 * @return PDO 数据库连接对象
 */
function getDB() {
    try {
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        if (DEBUG) {
            die('数据库连接失败: ' . $e->getMessage());
        } else {
            die('服务器错误，请稍后再试。');
        }
    }
}

/**
 * 获取消息列表
 * @param string $room 房间ID
 * @param int $limit 最大消息数量
 * @return array 消息列表
 */
function getMessages($room = 'global', $limit = 50) {
    // 如果指定了GET参数中的room，使用它
    if (isset($_GET['room']) && !empty($_GET['room'])) {
        $room = $_GET['room'];
    }
    
    // Normalize and sanitize room name for security
    $room = preg_replace('/[^a-zA-Z0-9_-]/', '', $room);
    
    // Use MongoDB if enabled
    if (defined('USE_MONGODB') && USE_MONGODB && function_exists('getMongoMessages')) {
        return getMongoMessages($room, $limit);
    }
    // Use Redis if enabled
    else if (USE_REDIS) {
        $redis = getRedis();
        $messages = [];
        
        // Use cache key for this room
        $cacheKey = REDIS_PREFIX . 'cache:messages:' . $room;
        $roomKey = REDIS_PREFIX . 'room:' . $room;
        
        // Try to get from cache first (faster)
        $cachedData = $redis->get($cacheKey);
        if ($cachedData && !isset($_GET['nocache'])) {
            return json_decode($cachedData, true);
        }
        
        // If not in cache, get from Redis list
        $data = $redis->lRange($roomKey, -$limit, -1);
        if ($data) {
            foreach ($data as $item) {
                $messages[] = json_decode($item, true);
            }
            
            // Store in cache for 5 seconds
            $redis->setex($cacheKey, 5, json_encode($messages));
        }
        
        return $messages;
    } 
    // Fallback to SQLite
    else {
        $db = getDB();
        
        $stmt = $db->prepare('
            SELECT * FROM messages 
            WHERE room = :room 
            ORDER BY created_at DESC 
            LIMIT :limit
        ');
        
        $stmt->bindParam(':room', $room, PDO::PARAM_STR);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 反转结果，使最早的消息在前面
        return array_reverse($messages);
    }
}

/**
 * 添加新消息
 * @param string $userId 用户ID
 * @param string $username 用户名
 * @param string $message 消息内容
 * @param string $room 房间ID
 * @return bool 是否成功
 */
function addMessage($userId, $username, $message, $room = 'global') {
    // 如果指定了GET参数中的room，使用它
    if (isset($_GET['room']) && !empty($_GET['room'])) {
        $room = $_GET['room'];
    }
    
    // Normalize and sanitize room name for security
    $room = preg_replace('/[^a-zA-Z0-9_-]/', '', $room);
    
    // 安全性检查 - 限制长度
    $message = substr(trim($message), 0, 2000); // 限制消息长度
    $username = substr(trim($username), 0, 50); // 限制用户名长度
    
    // 防止XSS攻击 - 只转义一次，避免双重转义
    $username = htmlspecialchars($username, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // 对消息内容使用单独的XSS过滤
    $filteredMessage = htmlspecialchars($message, ENT_NOQUOTES, 'UTF-8'); // 不转义引号
    
    // Use MongoDB if enabled
    if (defined('USE_MONGODB') && USE_MONGODB && function_exists('addMongoMessage')) {
        return addMongoMessage($userId, $username, $filteredMessage, $room);
    }
    // Use Redis if enabled
    else if (USE_REDIS) {
        $redis = getRedis();
        $messageData = [
            'user_id' => $userId,
            'username' => $username,
            'message' => $filteredMessage,
            'room' => $room,
            'created_at' => date('Y-m-d h:i:s A')
        ];
        
        // Pipeline commands for better performance
        $redis->multi(Redis::PIPELINE);
        
        // Clear the cache to ensure users get new messages
        $redis->del(REDIS_PREFIX . 'cache:messages:' . $room);
        
        // 将消息添加到列表中
        $redis->rPush(REDIS_PREFIX . 'room:' . $room, json_encode($messageData));
        
        // 保持消息列表的大小限制 (200条消息)
        $redis->lTrim(REDIS_PREFIX . 'room:' . $room, -200, -1);
        
        // 发布消息通知
        $redis->publish(REDIS_PREFIX . 'channel:' . $room, json_encode($messageData));
        
        // Execute all commands at once
        $redis->exec();
        
        return true;
    } 
    // Fallback to SQLite
    else {
        $db = getDB();
        
        $stmt = $db->prepare('
            INSERT INTO messages (user_id, username, message, room) 
            VALUES (:user_id, :username, :message, :room)
        ');
        
        return $stmt->execute([
            ':user_id' => $userId,
            ':username' => $username,
            ':message' => $filteredMessage,
            ':room' => $room
        ]);
    }
}

/**
 * 检查是否有新消息
 * @param int $lastCount 当前消息数量
 * @param string $room 房间ID
 * @return bool 是否有新消息
 */
function hasNewMessages($lastCount, $room = 'global') {
    // 如果指定了GET参数中的room，使用它
    if (isset($_GET['room']) && !empty($_GET['room'])) {
        $room = $_GET['room'];
    }
    
    // Normalize and sanitize room name for security
    $room = preg_replace('/[^a-zA-Z0-9_-]/', '', $room);
    
    // Use MongoDB if enabled
    if (defined('USE_MONGODB') && USE_MONGODB && function_exists('hasMongoNewMessages')) {
        return hasMongoNewMessages($lastCount, $room);
    }
    // Use Redis if enabled
    else if (USE_REDIS) {
        $redis = getRedis();
        $count = $redis->lLen(REDIS_PREFIX . 'room:' . $room);
        return (int)$count > (int)$lastCount;
    }
    // Fallback to SQLite
    else {
        $db = getDB();
        
        $stmt = $db->prepare('
            SELECT COUNT(*) as count FROM messages 
            WHERE room = :room
        ');
        
        $stmt->bindParam(':room', $room, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'] > (int)$lastCount;
    }
}

/**
 * 设置用户名
 * @param string $userId 用户ID
 * @param string $username 新用户名
 * @return bool 是否成功
 */
function setUsername($userId, $username) {
    // 保存到Cookie
    setcookie('anon_username', $username, time() + 60 * 60 * 24 * 30, '/');
    
    // 如果使用Redis，可以将用户信息存储在Redis中
    if (USE_REDIS) {
        $redis = getRedis();
        $redis->hSet(REDIS_PREFIX . 'users', $userId, $username);
    }
    
    return true;
} 