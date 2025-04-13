# PHP Anonymous Chat System

This is a PHP-based anonymous real-time chat system that can be easily embedded into any website. The system supports Redis for high-concurrency real-time message processing, with SQLite as an alternative storage option.

## Features

- Anonymous chat: No registration required, random usernames automatically generated
- Multi-room support: Different chat rooms can be specified via URL parameters
- Real-time communication: Uses Redis pub/sub and Server-Sent Events for real-time message delivery
- High concurrency support: Redis storage and pub/sub mechanism can handle high-concurrency scenarios
- Graceful degradation: Automatically falls back to SQLite and polling if Redis is unavailable
- Responsive design: Adapts to various devices and screen sizes
- Embedding support: Can be embedded as an iframe in any website
- JavaScript API: Provides API for interacting with the embedded chat interface

## System Requirements

- PHP 7.4+
- Redis extension (high concurrency mode, recommended)
- SQLite3 support (fallback mode)
- Web server (Apache, Nginx, etc.)

## Installation

1. Upload all files to your web server
2. Ensure the `data` directory is writable by the web server
3. Configure Redis connection information (in `includes/config.php`)
4. Access `public/index.php` to start using

```bash
# Ensure data directory is writable
chmod 755 data

# Install Redis extension
sudo apt install php-redis    # Debian/Ubuntu
sudo yum install php-redis    # CentOS/RHEL
pecl install redis            # Using PECL
```

## Redis Configuration

The system uses Redis for high-concurrency real-time message processing by default. Configuration is in `includes/config.php`:

```php
// Whether to use Redis (set to false to fall back to SQLite mode)
define('USE_REDIS', true);

// Redis connection configuration
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_AUTH', null); // Redis password, null if none
define('REDIS_PREFIX', 'anonchat:');
define('REDIS_DB', 0); // Database index to use
```

## Embedding in Other Websites

Embedding the chat system in your website is simple, just add the following code:

```html
<!-- Basic embedding -->
<iframe 
  src="https://fe.ttx.cx/?embedded=1" 
  width="100%" 
  height="500px" 
  frameborder="0">
</iframe>
```

### Advanced Usage

#### Specifying a Room

```html
<iframe 
  src="https://your-domain/public/index.php?embedded=1&room=specific-room-id" 
  width="100%" 
  height="500px" 
  frameborder="0">
</iframe>
```

#### Using JavaScript API

You can interact with the embedded chat system via JavaScript:

```html
<iframe 
  id="chat-frame"
  src="https://your-domain/public/index.php?embedded=1" 
  width="100%" 
  height="500px" 
  frameborder="0">
</iframe>

<script>
  // Get iframe reference
  const chatFrame = document.getElementById('chat-frame');
  
  // Set username
  function setUsername(name) {
    chatFrame.contentWindow.AnonChat.setUsername(name);
  }
  
  // Get message list
  function getMessages() {
    return chatFrame.contentWindow.AnonChat.getMessages();
  }
</script>
```

## Directory Structure

```
php-anon-chat/
├── data/                  # Data storage directory (SQLite database)
├── includes/              # PHP core functions and configuration
│   ├── config.php         # Configuration file
│   └── functions.php      # Core functions
├── public/                # Publicly accessible files
│   ├── assets/            # Static resources (CSS, JS)
│   ├── index.php          # Main page
│   ├── check_new_messages.php  # AJAX check for new messages
│   ├── set_username.php   # Set username API
│   ├── stream.php         # Real-time message stream (SSE)
│   └── embed-demo.php     # Embedding demo page
└── README.md              # Documentation
```

## Performance Optimization

### Nginx Configuration

For real-time message streams using SSE, the following Nginx configuration is recommended:

```nginx
location /stream.php {
    proxy_set_header Connection '';
    proxy_http_version 1.1;
    chunked_transfer_encoding off;
    proxy_buffering off;
    proxy_cache off;
    proxy_read_timeout 3600s;
}
```

### Redis Persistence

To prevent message loss, configure Redis persistence (AOF or RDB):

```
# redis.conf
appendonly yes
appendfsync everysec
```

### High Concurrency Settings

For high-concurrency environments, consider:

1. Redis cluster deployment
2. PHP-FPM process management optimization
3. Using dedicated message queue (like RabbitMQ) for message publishing

## Customization

### Modifying Appearance

Edit `public/assets/css/style.css` to customize the chat interface appearance.

### Modifying Functionality

All core functionality is in `includes/functions.php` and can be modified as needed.

## Security Notes

- No message moderation by default, recommend adding appropriate content filtering for public environments
- Ensure Redis server security, recommend enabling password authentication and restricting network access
- To add user authentication, you can extend the existing Cookie mechanism

## License

MIT 

## Add to config.php

define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_PREFIX', 'anonchat:');

// Connect to Redis
function getRedis() {
    static $redis = null;
    if ($redis === null) {
        $redis = new Redis();
        $redis->connect(REDIS_HOST, REDIS_PORT);
    }
    return $redis;
}

// Store message in Redis
function addMessage($userId, $username, $message, $room = 'global') {
    $redis = getRedis();
    $messageData = [
        'user_id' => $userId,
        'username' => $username,
        'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
        'room' => $room,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Add message to list
    $redis->rPush(REDIS_PREFIX . 'room:' . $room, json_encode($messageData));
    
    // Maintain message list size limit
    $redis->lTrim(REDIS_PREFIX . 'room:' . $room, -100, -1);
    
    // Publish message notification
    $redis->publish(REDIS_PREFIX . 'room:' . $room, json_encode($messageData));
    
    return true;
}

// Get message list
function getMessages($room = 'global', $limit = 50) {
    $redis = getRedis();
    $messages = [];
    
    $data = $redis->lRange(REDIS_PREFIX . 'room:' . $room, -$limit, -1);
    foreach ($data as $item) {
        $messages[] = json_decode($item, true);
    }
    
    return $messages;
}

// Create new file: public/stream.php
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$room = $_GET['room'] ?? 'global';
$redis = getRedis();

// Subscribe to room messages
$redis->subscribe([REDIS_PREFIX . 'room:' . $room], function($redis, $channel, $message) {
    echo "data: " . $message . "\n\n";
    ob_flush();
    flush();
}); 