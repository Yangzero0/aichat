<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-10
最后编辑时间：2025-10-13
文件描述：用于删除用户创建的角色

*/
session_start();
require_once(__DIR__ . './../../config/config.php');
require_once(__DIR__ . './../../config/functions.php');

// 登录校验
$user_id = requireLoginOrJsonError();

$character_id = get_int($_POST, 'character_id', 0);

if ($character_id <= 0) {
	json_error('参数错误');
}

try {
    // 先获取角色信息，包括头像路径
    $stmt = $db->prepare("SELECT avatar FROM ai_character WHERE id = ? AND user_id = ?");
    $stmt->execute([$character_id, $user_id]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$character) {
		json_error('角色不存在或无权删除');
    }
    
    // 删除头像文件（如果不是默认头像）
    $avatar = $character['avatar'] ?? '';
    if (!empty($avatar)) {
        // 排除默认头像（支持多种格式）
        $default_avatars = [
            '/static/ai-images/ai.png',
            '\\static\\ai-images\\ai.png',
            'static/ai-images/ai.png',
            'static\\ai-images\\ai.png'
        ];
        
        // 标准化路径进行比较
        $normalized_avatar = str_replace(['\\', '/'], '/', strtolower(trim($avatar)));
        $is_default = false;
        foreach ($default_avatars as $default) {
            $normalized_default = str_replace(['\\', '/'], '/', strtolower(trim($default)));
            if ($normalized_avatar === $normalized_default || basename($normalized_avatar) === 'ai.png') {
                $is_default = true;
                break;
            }
        }
        
        if (!$is_default) {
            // 提取文件名
            $filename = basename($avatar);
            // 构建完整路径
            $full_path = __DIR__ . '/../../static/ai-images/' . $filename;
            
            // 如果文件存在，删除它
            if (file_exists($full_path)) {
                @unlink($full_path);
            }
        }
    }
    
    // 删除角色
    $stmt = $db->prepare("DELETE FROM ai_character WHERE id = ? AND user_id = ?");
    $stmt->execute([$character_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
		json_success(['message' => '角色删除成功']);
    } else {
		json_error('角色不存在或无权删除');
    }
} catch (PDOException $e) {
    error_log("删除角色失败: " . $e->getMessage());
	json_error('删除失败：' . $e->getMessage());
}
?>