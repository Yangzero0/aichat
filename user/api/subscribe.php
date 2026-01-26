<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-13
最后编辑时间：2025-10-13
文件描述：用于获取用户订阅的角色

*/
session_start();
require_once(__DIR__ . './../../config/config.php');
require_once(__DIR__ . './../../config/functions.php');

header('Content-Type: application/json');

// 检查用户是否登录
$user_id = requireLoginOrJsonError();

// 验证请求方法
requirePostOrJsonError();

// 获取参数
$character_id = get_int($_POST, 'character_id', 0);
$action = get_str($_POST, 'action', ''); // 'subscribe' or 'unsubscribe'

if ($character_id <= 0) {
	json_error('角色ID无效');
}

if (!in_array($action, ['subscribe', 'unsubscribe'])) {
	json_error('操作类型无效');
}

try {
    // 检查角色是否存在且可用
    $stmt = $db->prepare("SELECT id FROM ai_character WHERE id = ? AND status = 1");
    $stmt->execute([$character_id]);
    
    if (!$stmt->fetch()) {
		json_error('角色不存在或已被禁用');
    }
    
    if ($action === 'subscribe') {
        // 检查是否已订阅
        $stmt = $db->prepare("SELECT id FROM character_subscription WHERE user_id = ? AND character_id = ?");
        $stmt->execute([$user_id, $character_id]);
        
        if ($stmt->fetch()) {
            // 已存在，更新状态
            $stmt = $db->prepare("UPDATE character_subscription SET status = 1 WHERE user_id = ? AND character_id = ?");
            $stmt->execute([$user_id, $character_id]);
        } else {
            // 新订阅
            $stmt = $db->prepare("INSERT INTO character_subscription (user_id, character_id, status) VALUES (?, ?, 1)");
            $stmt->execute([$user_id, $character_id]);
        }
    } else {
        // 取消订阅
        $stmt = $db->prepare("UPDATE character_subscription SET status = 0 WHERE user_id = ? AND character_id = ?");
        $stmt->execute([$user_id, $character_id]);
    }
    
	json_success();
    
} catch (PDOException $e) {
    error_log("订阅操作错误: " . $e->getMessage());
	json_error('操作失败，请稍后重试');
}
?>