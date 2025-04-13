document.addEventListener('DOMContentLoaded', function() {
    // 初始化变量
    const messagesContainer = document.getElementById('messages-container');
    const messagesDiv = document.getElementById('messages');
    const messageInput = document.getElementById('message-input');
    const messageForm = document.getElementById('message-form');
    const sendButton = document.getElementById('send-button');
    const usernameDisplay = document.getElementById('username-display');
    
    // 获取查询参数
    const urlParams = new URLSearchParams(window.location.search);
    const room = urlParams.get('room') || 'global';
    const isEmbedded = urlParams.get('embedded') === '1';
    
    // 检查是否在iframe中
    if (window.self !== window.top || isEmbedded) {
        document.body.classList.add('embedded');
    }
    
    // 自动滚动到底部
    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }
    
    // 添加消息到DOM
    function addMessageToDOM(message) {
        const isOwnMessage = message.user_id === getUserId();
        
        const messageEl = document.createElement('div');
        messageEl.classList.add('message');
        messageEl.classList.add(isOwnMessage ? 'own-message' : 'other-message');
        
        const time = new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        messageEl.innerHTML = `
            <div class="message-meta">
                <span class="message-username">${escapeHtml(message.username)}</span>
                <span class="message-time">${time}</span>
            </div>
            <div class="message-content">${escapeHtml(message.message)}</div>
        `;
        
        messagesDiv.appendChild(messageEl);
        scrollToBottom();
    }
    
    // 连接SSE实时消息流
    function connectEventSource() {
        if (window.EventSource) {
            const protocol = window.location.protocol;
            const host = window.location.host;
            let eventSource = new EventSource(`${protocol}//${host}/stream.php?room=${room}`);
            
            eventSource.addEventListener('connected', function(e) {
                console.log('SSE连接成功:', JSON.parse(e.data));
            });
            
            eventSource.addEventListener('message', function(e) {
                const message = JSON.parse(e.data);
                addMessageToDOM(message);
            });
            
            eventSource.addEventListener('error', function(e) {
                console.error('SSE连接错误:', e);
                eventSource.close();
                
                // 如果SSE连接失败，回退到轮询
                setTimeout(function() {
                    fetchExistingMessages();
                    startPolling();
                }, 2000);
            });
            
            return eventSource;
        } else {
            console.warn('浏览器不支持EventSource，将使用轮询方式');
            fetchExistingMessages();
            startPolling();
            return null;
        }
    }
    
    // 获取消息列表
    async function fetchExistingMessages() {
        try {
            const response = await fetch(`index.php?room=${room}&format=json`);
            if (!response.ok) throw new Error('获取消息失败');
            
            const messages = await response.json();
            
            // 清空现有消息
            messagesDiv.innerHTML = '';
            
            // 添加所有消息
            messages.forEach(message => addMessageToDOM(message));
            
            // 滚动到底部
            scrollToBottom();
        } catch (error) {
            console.error('加载历史消息失败:', error);
        }
    }
    
    // 启动轮询（当SSE不可用时的备选方案）
    function startPolling() {
        let lastMessageCount = document.querySelectorAll('.message').length;
        
        // 轮询新消息
        setInterval(function() {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `check_new_messages.php?count=${lastMessageCount}&room=${room}`, true);
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.hasNew) {
                            fetchExistingMessages();
                            lastMessageCount = document.querySelectorAll('.message').length;
                        }
                    } catch (e) {
                        console.error('解析响应失败:', e);
                    }
                }
            };
            xhr.send();
        }, 5000);
    }
    
    // 获取当前用户ID
    function getUserId() {
        return getCookie('anon_user_id') || '';
    }
    
    // 获取Cookie值
    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return '';
    }
    
    // HTML转义防止XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // 初始化消息区域
    fetchExistingMessages().then(() => {
        // 尝试使用SSE，如果不可用则回退到轮询
        const eventSource = connectEventSource();
        
        // 表单提交事件处理
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = messageInput.value.trim();
            if (message !== '') {
                // 使用fetch API提交消息
                fetch('index.php?room=' + room, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `message=${encodeURIComponent(message)}`
                }).then(response => {
                    if (response.ok) {
                        messageInput.value = '';
                        messageInput.focus();
                    }
                }).catch(error => {
                    console.error('发送消息失败:', error);
                });
            }
        });
    });
    
    // API接口，供外部调用
    window.AnonChat = {
        // 修改用户名
        setUsername: function(username) {
            if (username && typeof username === 'string') {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'set_username.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                document.getElementById('username-display').textContent = username;
                            }
                        } catch (e) {
                            console.error('解析响应失败:', e);
                        }
                    }
                };
                xhr.send('username=' + encodeURIComponent(username));
            }
        },
        
        // 获取消息列表
        getMessages: function() {
            const messageElements = document.querySelectorAll('.message');
            return Array.from(messageElements).map(el => ({
                username: el.querySelector('.message-username').textContent,
                text: el.querySelector('.message-content').textContent,
                time: el.querySelector('.message-time').textContent,
                isOwn: el.classList.contains('own-message')
            }));
        }
    };
}); 