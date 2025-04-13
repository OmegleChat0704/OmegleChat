<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// 仅处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = trim($_POST['username']);
    
    // 简单验证
    if (empty($username)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '用户名不能为空']);
        exit;
    }
    
    // 获取当前用户ID
    $userId = $_COOKIE['anon_user_id'] ?? null;
    if (!$userId) {
        $userId = uniqid('user_');
        setcookie('anon_user_id', $userId, time() + 60 * 60 * 24 * 30, '/');
    }
    
    // 设置新用户名
    $success = setUsername($userId, $username);
    
    // 返回结果
    echo json_encode(['success' => $success, 'username' => $username]);
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '方法不允许']);
} 