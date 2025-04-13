<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Get room ID
$room = $_GET['room'] ?? 'global';

// Generate funny words for user ID
function generateFunnyId() {
    $firstWords = [
        'happy', 'silly', 'crazy', 'funny', 'wild', 'magic', 'super', 'mega', 
        'ultra', 'hyper', 'jumbo', 'ninja', 'power', 'wonder', 'cyber', 'brave', 
        'mighty', 'fancy', 'rapid', 'swift', 'cosmic', 'laser', 'neon', 'turbo',
        'rocket', 'fire', 'frost', 'shadow', 'royal', 'golden', 'silver', 'iron',
        'sonic', 'fuzzy', 'fluffy', 'dancing', 'hungry', 'flying', 'sneaky', 'mystic'
    ];
    
    $secondWords = [
        'panda', 'tiger', 'eagle', 'shark', 'dragon', 'monkey', 'rabbit', 'wolf', 
        'fox', 'bear', 'camel', 'moose', 'pigeon', 'turtle', 'beaver', 'raccoon', 
        'penguin', 'gecko', 'whale', 'duck', 'squid', 'koala', 'badger', 'otter',
        'wizard', 'knight', 'samurai', 'ninja', 'ranger', 'robot', 'alien', 'zombie',
        'ghost', 'pirate', 'viking', 'potato', 'cookie', 'muffin', 'taco', 'banana'
    ];
    
    $word1 = $firstWords[array_rand($firstWords)];
    $word2 = $secondWords[array_rand($secondWords)];
    $number = rand(100, 999);
    
    return $word1 . $word2 . $number;
}

// Set or get user information
if (!isset($_COOKIE['anon_user_id'])) {
    $userId = generateFunnyId();
    $username = generateFunnyId(); // Use the same function for username
    setcookie('anon_user_id', $userId, time() + 60 * 60 * 24 * 30, '/');
    setcookie('anon_username', $username, time() + 60 * 60 * 24 * 30, '/');
} else {
    $userId = $_COOKIE['anon_user_id'];
    $username = $_COOKIE['anon_username'] ?? 'Anonymous';
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    if (!empty($message)) {
        addMessage($userId, $username, $message, $room);
        
        // If AJAX request, return success status
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }
        
        // Otherwise redirect to prevent form resubmission
        header('Location: ' . $_SERVER['PHP_SELF'] . (isset($_GET['room']) ? '?room=' . urlencode($_GET['room']) : ''));
        exit;
    }
}

// Get messages
$messages = getMessages($room);

// If JSON format requested, return message data directly
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode($messages);
    exit;
}

// Check if embedded mode
$isEmbedded = isset($_GET['embedded']) && $_GET['embedded'] == '1';
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>匿名聊天</title>
    <style>
        /* Windows 3.2 Retro Style */
        :root {
            --win-bg: #c0c0c0;
            --win-border: #808080;
            --win-header: #000080;
            --win-header-text: #ffffff;
            --win-button: #c0c0c0;
            --win-button-shadow-dark: #808080;
            --win-button-shadow-light: #ffffff;
            --win-text: #000000;
            --win-window-bg: #ffffff;
        }
        
        * {
            margin: 0; 
            padding: 0; 
            box-sizing: border-box;
            font-family: 'MS Sans Serif', 'Tahoma', sans-serif;
        }
        
        body {
            background-color: var(--win-bg);
            color: var(--win-text);
            padding: 20px;
            height: 100vh;
            width: 100%;
        }
        
        .win-window {
            border: 2px solid var(--win-border);
            border-radius: 0;
            box-shadow: 2px 2px 0 #000000;
            background-color: var(--win-bg);
            overflow: hidden;
            max-width: 900px;
            margin: 0 auto;
            height: 90vh;
            display: flex;
            flex-direction: column;
        }
        
        .win-titlebar {
            background-color: var(--win-header);
            color: var(--win-header-text);
            padding: 4px 8px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .win-button {
            background-color: var(--win-button);
            border-top: 2px solid var(--win-button-shadow-light);
            border-left: 2px solid var(--win-button-shadow-light);
            border-right: 2px solid var(--win-button-shadow-dark);
            border-bottom: 2px solid var(--win-button-shadow-dark);
            padding: 4px 10px;
            font-size: 14px;
            cursor: pointer;
        }
        
        .win-button:active {
            border-top: 2px solid var(--win-button-shadow-dark);
            border-left: 2px solid var(--win-button-shadow-dark);
            border-right: 2px solid var(--win-button-shadow-light);
            border-bottom: 2px solid var(--win-button-shadow-light);
        }
        
        .win-content {
            background-color: var(--win-window-bg);
            border: 2px solid var(--win-border);
            margin: 8px;
            flex: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
            background-color: var(--win-window-bg);
            border-bottom: 2px solid var(--win-border);
        }
        
        .messages {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .message {
            padding: 8px;
            max-width: 80%;
            word-break: break-word;
            position: relative;
            border-top: 2px solid var(--win-button-shadow-light);
            border-left: 2px solid var(--win-button-shadow-light);
            border-right: 2px solid var(--win-button-shadow-dark);
            border-bottom: 2px solid var(--win-button-shadow-dark);
            background-color: var(--win-bg);
        }
        
        .message-content {
            margin-top: 5px;
        }
        
        .message-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            border-bottom: 1px solid var(--win-border);
            padding-bottom: 3px;
        }
        
        .message-username {
            font-weight: bold;
            color: var(--win-text);
        }
        
        .message-time {
            font-size: 12px;
            color: var(--win-border);
        }
        
        .own-message {
            align-self: flex-end;
            background-color: #d4d0c8;
        }
        
        .other-message {
            align-self: flex-start;
        }
        
        .input-area {
            padding: 8px;
            display: flex;
        }
        
        .input-area form {
            display: flex;
            width: 100%;
        }
        
        #message-input {
            flex: 1;
            padding: 6px;
            border-top: 2px solid var(--win-button-shadow-dark);
            border-left: 2px solid var(--win-button-shadow-dark);
            border-right: 2px solid var(--win-button-shadow-light);
            border-bottom: 2px solid var(--win-button-shadow-light);
        }
        
        #send-button {
            margin-left: 6px;
            padding: 4px 10px;
            background-color: var(--win-button);
            color: var(--win-text);
            border-top: 2px solid var(--win-button-shadow-light);
            border-left: 2px solid var(--win-button-shadow-light);
            border-right: 2px solid var(--win-button-shadow-dark);
            border-bottom: 2px solid var(--win-button-shadow-dark);
            cursor: pointer;
            position: relative;
        }
        
        #send-button:active {
            border-top: 2px solid var(--win-button-shadow-dark);
            border-left: 2px solid var(--win-button-shadow-dark);
            border-right: 2px solid var(--win-button-shadow-light);
            border-bottom: 2px solid var(--win-button-shadow-light);
        }
        
        .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top-color: #000080;
            animation: spin 1s ease-in-out infinite;
            position: absolute;
            top: 50%;
            left: 50%;
            margin-top: -8px;
            margin-left: -8px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .user-info {
            font-size: 14px;
            color: var(--win-header-text);
        }
        
        .status-bar {
            border-top: 2px solid var(--win-border);
            padding: 4px 8px;
            display: flex;
            justify-content: space-between;
            background-color: var(--win-bg);
            font-size: 12px;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .win-window {
            height: 100vh;
                max-width: 100%;
                margin: 0;
            }
            
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body class="<?php echo $isEmbedded ? 'embedded' : ''; ?>">
    <div class="win-window">
        <div class="win-titlebar">
            <div class="title">匿名聊天 - <?php echo htmlspecialchars($room); ?> 房间</div>
            <div class="user-info">
                <span id="username-display"><?php echo htmlspecialchars($username); ?></span>
            </div>
        </div>
        
        <div class="win-content">
        <div class="messages-container" id="messages-container">
            <div class="messages" id="messages">
                <?php if (empty($messages)): ?>
                    <div class="system-message">没有消息。成为第一个发言的人！</div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                    <div class="message <?php echo ($msg['user_id'] === $userId) ? 'own-message' : 'other-message'; ?>">
                        <div class="message-meta">
                            <span class="message-username"><?php echo htmlspecialchars($msg['username']); ?></span>
                            <span class="message-time"><?php echo htmlspecialchars($msg['formatted_time']); ?></span>
                        </div>
                        <div class="message-content"><?php echo htmlspecialchars($msg['message']); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="input-area">
            <form method="post" action="" id="message-form">
                    <input type="text" name="message" id="message-input" placeholder="输入消息..." autocomplete="off" required>
                    <button type="submit" id="send-button">发送</button>
            </form>
            </div>
        </div>
        
        <div class="status-bar">
            <div id="powered-by">Powered by Soltoshi</div>
            <div id="message-count"><?php echo count($messages); ?> messages</div>
        </div>
    </div>

    <!-- Popup dialogs for changing username and room -->
    <div id="username-dialog" class="dialog">
        <div class="dialog-content">
            <div class="dialog-titlebar">
                <span>更改用户名</span>
                <button class="close-button">&times;</button>
            </div>
            <div class="dialog-body">
                <form id="username-form">
                    <label for="new-username">新用户名:</label>
                    <input type="text" id="new-username" value="<?php echo htmlspecialchars($username); ?>" required>
                    <button type="submit" class="win-button">保存</button>
                </form>
            </div>
        </div>
    </div>

    <div id="room-dialog" class="dialog">
        <div class="dialog-content">
            <div class="dialog-titlebar">
                <span>切换房间</span>
                <button class="close-button">&times;</button>
            </div>
            <div class="dialog-body">
                <form id="room-form">
                    <label for="new-room">房间名称:</label>
                    <input type="text" id="new-room" value="<?php echo htmlspecialchars($room); ?>" required>
                    <button type="submit" class="win-button">进入</button>
                </form>
                <div class="recent-rooms">
                    <h4>最近的房间:</h4>
                    <ul id="recent-rooms-list">
                        <!-- JavaScript will populate this -->
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize variables
            const messagesContainer = document.getElementById('messages-container');
            const messagesDiv = document.getElementById('messages');
            const messageInput = document.getElementById('message-input');
            const messageForm = document.getElementById('message-form');
            const sendButton = document.getElementById('send-button');
            const spinner = document.getElementById('spinner');
            const usernameDisplay = document.getElementById('username-display');
            const messageCount = document.getElementById('message-count');
            
            // Get query parameters - with validation
            const urlParams = new URLSearchParams(window.location.search);
            const room = (urlParams.get('room') || 'global').replace(/[^a-zA-Z0-9_-]/g, '');
            const isEmbedded = urlParams.get('embedded') === '1';
            
            // Check if in iframe
            if (window.self !== window.top || isEmbedded) {
                document.body.classList.add('embedded');
            }
            
            // Scroll to bottom
            function scrollToBottom() {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
            
            // Add message to DOM
            function addMessageToDOM(message) {
                if (!message || typeof message !== 'object') return;
                
                // Validate required fields
                if (!message.username || !message.message || !message.created_at) return;
                
                const isOwnMessage = message.user_id === getUserId();
                
                const messageEl = document.createElement('div');
                messageEl.classList.add('message');
                messageEl.classList.add(isOwnMessage ? 'own-message' : 'other-message');
                
                let time;
                try {
                    time = new Date(message.created_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                } catch (e) {
                    time = '?:??';
                }
                
                // Create elements safely to prevent XSS
                const metaDiv = document.createElement('div');
                metaDiv.className = 'message-meta';
                
                const usernameSpan = document.createElement('span');
                usernameSpan.className = 'message-username';
                usernameSpan.textContent = message.username;
                
                const timeSpan = document.createElement('span');
                timeSpan.className = 'message-time';
                timeSpan.textContent = time;
                
                metaDiv.appendChild(usernameSpan);
                metaDiv.appendChild(timeSpan);
                
                const contentDiv = document.createElement('div');
                contentDiv.className = 'message-content';
                
                // Decode HTML entities before displaying
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = message.message;
                contentDiv.textContent = tempDiv.textContent;
                
                messageEl.appendChild(metaDiv);
                messageEl.appendChild(contentDiv);
                
                messagesDiv.appendChild(messageEl);
                scrollToBottom();
                messageCount.textContent = document.querySelectorAll('.message').length + ' messages';
            }
            
            // Connect SSE real-time message stream with optimized performance
            function connectEventSource() {
                if (window.EventSource) {
                    const protocol = window.location.protocol;
                    const host = window.location.host;
                    const safeRoom = encodeURIComponent(room);
                    let eventSource = new EventSource(`${protocol}//${host}/stream.php?room=${safeRoom}`);
                    
                    eventSource.addEventListener('connected', function(e) {
                        try {
                            console.log('SSE Connected:', JSON.parse(e.data));
                        } catch (err) {
                            console.error('Invalid JSON data:', err);
                        }
                    });
                    
                    eventSource.addEventListener('message', function(e) {
                        try {
                            const message = JSON.parse(e.data);
                            addMessageToDOM(message);
                        } catch (err) {
                            console.error('Error processing message:', err);
                        }
                    });
                    
                    eventSource.addEventListener('error', function(e) {
                        console.error('SSE Error:', e);
                        eventSource.close();
                        
                        // If SSE fails, fallback to polling
                        setTimeout(function() {
                            fetchExistingMessages();
                            startPolling();
                        }, 1000);
                    });
                    
                    return eventSource;
                } else {
                    console.warn('Browser does not support EventSource, using polling');
                    fetchExistingMessages();
                    startPolling();
                    return null;
                }
            }
            
            // Get message list with optimized performance
            async function fetchExistingMessages() {
                try {
                    const safeRoom = encodeURIComponent(room);
                    const response = await fetch(`index.php?room=${safeRoom}&format=json`, {
                        cache: 'no-store',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (!response.ok) throw new Error('Failed to get messages');
                    
                    const messages = await response.json();
                    
                    if (!Array.isArray(messages)) {
                        throw new Error('Invalid response format');
                    }
                    
                    // Clear existing messages
                    messagesDiv.innerHTML = '';
                    
                    // Add all messages
                    messages.forEach(message => addMessageToDOM(message));
                    
                    // Scroll to bottom
                    scrollToBottom();
                    messageCount.textContent = messages.length + ' messages';
                } catch (error) {
                    console.error('Failed to load history:', error);
                }
            }
            
            // Start polling (fallback when SSE is unavailable)
            function startPolling() {
                let lastMessageCount = document.querySelectorAll('.message').length;
                
                // Poll for new messages
                setInterval(function() {
                    const safeRoom = encodeURIComponent(room);
                    fetch(`check_new_messages.php?count=${lastMessageCount}&room=${safeRoom}`, {
                        cache: 'no-store',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.hasNew) {
                            fetchExistingMessages();
                            lastMessageCount = document.querySelectorAll('.message').length;
                        }
                    })
                    .catch(error => {
                        console.error('Polling error:', error);
                    });
                }, 3000); // Reduced polling interval for better performance
            }
            
            // Get current user ID
            function getUserId() {
                return getCookie('anon_user_id') || '';
            }
            
            // Get Cookie value
            function getCookie(name) {
                const value = `; ${document.cookie}`;
                const parts = value.split(`; ${name}=`);
                if (parts.length === 2) return parts.pop().split(';').shift();
                return '';
            }
            
            // HTML escape to prevent XSS
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            // Initialize message area
            fetchExistingMessages().then(() => {
                // Try using SSE, fallback to polling if unavailable
                const eventSource = connectEventSource();
                
                // Form submission event handling
                messageForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const message = messageInput.value.trim();
                    if (message !== '') {
                        // Show spinner during send
                        spinner.style.display = 'block';
                        sendButton.textContent = '';
                        
                        // Use fetch API to submit message with performance optimization
                        const safeRoom = encodeURIComponent(room);
                        fetch('index.php?room=' + safeRoom, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: `message=${encodeURIComponent(message)}`
                        }).then(response => {
                            if (response.ok) {
                                messageInput.value = '';
                                messageInput.focus();
                            }
                            // Hide spinner after send
                            setTimeout(() => {
                                spinner.style.display = 'none';
                                sendButton.textContent = 'Send ';
                            }, 300);
                        }).catch(error => {
                            console.error('Failed to send message:', error);
                            // Hide spinner after error
                            spinner.style.display = 'none';
                            sendButton.textContent = 'Send ';
                        });
                    }
                });
            });
            
            // Scroll to bottom initially
            scrollToBottom();
            
            // Change username
            document.getElementById('change-username').addEventListener('click', function() {
                document.getElementById('username-dialog').style.display = 'flex';
            });
            
            // Switch room
            document.getElementById('change-room').addEventListener('click', function() {
                document.getElementById('room-dialog').style.display = 'flex';
                loadRecentRooms();
            });
            
            // Close dialog
            document.querySelectorAll('.close-button').forEach(function(button) {
                button.addEventListener('click', function() {
                    this.closest('.dialog').style.display = 'none';
                });
            });
            
            // Save username
            document.getElementById('username-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const newUsername = document.getElementById('new-username').value.trim();
                if (newUsername) {
                    setCookie('anon_username', newUsername, 30);
                    document.getElementById('username-dialog').style.display = 'none';
                    showNotification('用户名已更新为 ' + newUsername);
                }
            });
            
            // Switch room
            document.getElementById('room-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const newRoom = document.getElementById('new-room').value.trim();
                if (newRoom) {
                    // Save to recent rooms
                    saveRecentRoom(newRoom);
                    // Redirect to new room
                    window.location.href = '?room=' + encodeURIComponent(newRoom) + 
                        (<?php echo $isEmbedded ? 'true' : 'false'; ?> ? '&embedded=1' : '');
                }
            });
            
            // Message form submission
            document.getElementById('message-form').addEventListener('submit', function(e) {
                const messageInput = document.getElementById('message-input');
                const message = messageInput.value.trim();
                
                if (!message) {
                    e.preventDefault();
                    return;
                }
                
                if (window.fetch) {
                    e.preventDefault();
                    sendMessageAsync(message);
                    messageInput.value = '';
                }
            });
            
            // Send message asynchronously
            function sendMessageAsync(message) {
                const formData = new FormData();
                formData.append('message', message);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadMessages();
                    }
                })
                .catch(error => {
                    console.error('Error sending message:', error);
                });
            }
            
            // Load messages
            function loadMessages() {
                const messagesUrl = window.location.pathname + '?room=' + encodeURIComponent('<?php echo $room; ?>') + '&format=json';
                
                fetch(messagesUrl)
                .then(response => response.json())
                .then(messages => {
                    const messagesContainer = document.getElementById('messages');
                    messagesContainer.innerHTML = '';
                    
                    if (messages.length === 0) {
                        messagesContainer.innerHTML = '<div class="system-message">没有消息。成为第一个发言的人！</div>';
                        return;
                    }
                    
                    const userId = getCookie('anon_user_id');
                    
                    messages.forEach(msg => {
                        const messageDiv = document.createElement('div');
                        messageDiv.className = 'message ' + (msg.user_id === userId ? 'own-message' : 'other-message');
                        
                        const metaDiv = document.createElement('div');
                        metaDiv.className = 'message-meta';
                        
                        const usernameSpan = document.createElement('span');
                        usernameSpan.className = 'message-username';
                        usernameSpan.textContent = msg.username;
                        
                        const timeSpan = document.createElement('span');
                        timeSpan.className = 'message-time';
                        timeSpan.textContent = msg.formatted_time;
                        
                        metaDiv.appendChild(usernameSpan);
                        metaDiv.appendChild(timeSpan);
                        
                        const contentDiv = document.createElement('div');
                        contentDiv.className = 'message-content';
                        contentDiv.textContent = msg.message;
                        
                        messageDiv.appendChild(metaDiv);
                        messageDiv.appendChild(contentDiv);
                        
                        messagesContainer.appendChild(messageDiv);
                    });
                    
                    scrollToBottom();
                })
                .catch(error => {
                    console.error('Error loading messages:', error);
                });
            }
            
            // Auto scroll to bottom
            function scrollToBottom() {
                const messagesContainer = document.querySelector('.messages-container');
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
            
            // Show notification
            function showNotification(message) {
                const notification = document.createElement('div');
                notification.className = 'notification';
                notification.textContent = message;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.opacity = '1';
                }, 10);
                
                setTimeout(() => {
                    notification.style.opacity = '0';
                    setTimeout(() => {
                        document.body.removeChild(notification);
                    }, 500);
                }, 3000);
            }
            
            // Save recent room
            function saveRecentRoom(room) {
                let recentRooms = JSON.parse(localStorage.getItem('recent_rooms') || '[]');
                
                // Remove existing entry (if any)
                recentRooms = recentRooms.filter(r => r !== room);
                
                // Add to beginning of array
                recentRooms.unshift(room);
                
                // Keep array no longer than 5
                if (recentRooms.length > 5) {
                    recentRooms = recentRooms.slice(0, 5);
                }
                
                localStorage.setItem('recent_rooms', JSON.stringify(recentRooms));
            }
            
            // Load recent rooms
            function loadRecentRooms() {
                const recentRoomsList = document.getElementById('recent-rooms-list');
                recentRoomsList.innerHTML = '';
                
                const recentRooms = JSON.parse(localStorage.getItem('recent_rooms') || '[]');
                
                if (recentRooms.length === 0) {
                    const li = document.createElement('li');
                    li.textContent = '无最近记录';
                    recentRoomsList.appendChild(li);
                    return;
                }
                
                recentRooms.forEach(room => {
                    const li = document.createElement('li');
                    const a = document.createElement('a');
                    a.href = '?room=' + encodeURIComponent(room) + 
                        (<?php echo $isEmbedded ? 'true' : 'false'; ?> ? '&embedded=1' : '');
                    a.textContent = room;
                    
                    li.appendChild(a);
                    recentRoomsList.appendChild(li);
                });
            }
            
            // Set cookie
            function setCookie(name, value, days) {
                let expires = '';
                if (days) {
                    const date = new Date();
                    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                    expires = '; expires=' + date.toUTCString();
                }
                document.cookie = name + '=' + (value || '') + expires + '; path=/';
            }
            
            // Initial load
            loadMessages();
            scrollToBottom();
            
            // Periodic refresh
            setInterval(loadMessages, 5000);
        });
    </script>
</body>
</html> 