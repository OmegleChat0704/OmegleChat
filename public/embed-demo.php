<?php
// 获取当前URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . '://' . $host;
$chatUrl = $baseUrl . "/index.php?embedded=1";
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>匿名聊天系统 - 嵌入式演示</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f0f2f5;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            color: #333;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        
        .demo-section {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 30px;
        }
        
        .demo-content {
            flex: 1;
            min-width: 300px;
        }
        
        .chat-frame {
            width: 100%;
            height: 500px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .demo-code {
            flex: 1;
            min-width: 300px;
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        pre {
            margin: 0;
            overflow-x: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        code {
            font-family: Consolas, Monaco, 'Andale Mono', monospace;
            font-size: 14px;
            color: #333;
        }
        
        .controls {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        
        button {
            padding: 8px 15px;
            background-color: #4a89dc;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        button:hover {
            background-color: #5d9cec;
        }
        
        input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>匿名聊天系统 - 嵌入式演示</h1>
        
        <div class="demo-section">
            <div class="demo-content">
                <h2>聊天室预览</h2>
                <iframe src="<?php echo $chatUrl; ?>&room=demo" class="chat-frame" id="chat-frame"></iframe>
                
                <div class="controls">
                    <input type="text" id="username-input" placeholder="设置用户名">
                    <button id="set-username">设置</button>
                </div>
            </div>
            
            <div class="demo-code">
                <h2>嵌入代码示例</h2>
                <pre><code>&lt;!-- 基本嵌入 --&gt;
&lt;iframe 
  src="<?php echo $chatUrl; ?>" 
  width="100%" 
  height="500px" 
  frameborder="0"&gt;
&lt;/iframe&gt;

&lt;!-- 指定聊天室 --&gt;
&lt;iframe 
  src="<?php echo $chatUrl; ?>&room=你的房间ID" 
  width="100%" 
  height="500px" 
  frameborder="0"&gt;
&lt;/iframe&gt;

&lt;!-- JavaScript API 使用 --&gt;
&lt;script&gt;
  // 获取iframe引用
  const chatFrame = document.getElementById('chat-frame');
  
  // 设置用户名
  function setUsername(name) {
    chatFrame.contentWindow.AnonChat.setUsername(name);
  }
  
  // 获取消息列表
  function getMessages() {
    return chatFrame.contentWindow.AnonChat.getMessages();
  }
&lt;/script&gt;</code></pre>
            </div>
        </div>
    </div>
    
    <script>
        // 演示页面交互
        document.getElementById('set-username').addEventListener('click', function() {
            const usernameInput = document.getElementById('username-input');
            const username = usernameInput.value.trim();
            
            if (username) {
                const chatFrame = document.getElementById('chat-frame');
                chatFrame.contentWindow.AnonChat.setUsername(username);
                usernameInput.value = '';
            }
        });
    </script>
</body>
</html> 