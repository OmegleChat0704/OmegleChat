<?php
/**
 * 配置文件
 */

// 调试模式
define('DEBUG', true);

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 错误显示（仅开发阶段启用）
if (DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// 数据库类型选择
define('USE_REDIS', false); // 是否使用Redis
define('USE_MONGODB', false); // 是否使用MongoDB

// Redis配置 - 已抹去敏感信息
define('REDIS_HOST', '***********');
define('REDIS_PORT', 0000);
define('REDIS_AUTH', null); // Redis密码，已抹去
define('REDIS_PREFIX', 'anonchat:');
define('REDIS_DB', 0); // 使用的数据库索引

// MongoDB配置 - 已抹去敏感信息
define('MONGO_HOST', '***********');
define('MONGO_PORT', 0000);
define('MONGO_USER', '***********'); // MongoDB用户名，已抹去
define('MONGO_PASS', '***********'); // MongoDB密码，已抹去
define('MONGO_DB', '***********'); // MongoDB数据库名，已抹去

// SQLite配置（作为备选）
define('DB_FILE', __DIR__ . '/../data/chat.db');

// 创建数据目录
if (!file_exists(__DIR__ . '/../data')) {
    mkdir(__DIR__ . '/../data', 0755, true);
}

// 初始化数据库连接
if (USE_MONGODB && extension_loaded('mongodb')) {
    // 如果启用MongoDB并且扩展已加载
    require_once __DIR__ . '/mongodb.php';
    
    try {
        $mongo = getMongoDB();
        if ($mongo) {
            // 创建索引（如需要）
            createMongoIndexes();
        } else {
            die('MongoDB连接失败，请检查配置');
        }
    } catch (Exception $e) {
        if (DEBUG) {
            die('MongoDB连接失败: ' . $e->getMessage());
        } else {
            die('服务器错误，请稍后再试。');
        }
    }
}
// 初始化Redis连接
else if (USE_REDIS) {
    if (!extension_loaded('redis')) {
        die('需要安装PHP Redis扩展');
    }
    
    try {
        $redis = new Redis();
        $redis->connect(REDIS_HOST, REDIS_PORT);
        
        if (REDIS_AUTH !== null) {
            $redis->auth(REDIS_AUTH);
        }
        
        if (REDIS_DB !== 0) {
            $redis->select(REDIS_DB);
        }
        
        // 检查Redis连接
        $redis->ping();
    } catch (Exception $e) {
        if (DEBUG) {
            die('Redis连接失败: ' . $e->getMessage());
        } else {
            die('服务器错误，请稍后再试。');
        }
    }
} 
// 初始化SQLite（当Redis不可用时）
else {
    try {
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 创建消息表
        $db->exec('
            CREATE TABLE IF NOT EXISTS messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id TEXT NOT NULL,
                username TEXT NOT NULL,
                message TEXT NOT NULL,
                room TEXT DEFAULT "global",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');
    } catch (PDOException $e) {
        if (DEBUG) {
            die('数据库连接失败: ' . $e->getMessage());
        } else {
            die('服务器错误，请稍后再试。');
        }
    }
} 