<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-12
最后编辑时间：2025-10-12
文件描述：用于上传角色头像图片

*/
session_start();
require_once(__DIR__ . './../../config/config.php');
require_once(__DIR__ . './../../config/functions.php');

if (!isset($_SESSION['user_id'])) {
	json_error('未登录');
}

$user_id = $_SESSION['user_id'];
$character_id = get_int($_POST, 'character_id', 0);
$name = get_str($_POST, 'name', '');
$category_id = get_int($_POST, 'category_id', 0);
$introduction = get_str($_POST, 'introduction', '');
$prompt = get_str($_POST, 'prompt', '');
$is_public = isset($_POST['is_public']) ? 1 : 0;

// 验证必填字段
if (empty($name) || empty($prompt) || $category_id <= 0) {
	json_error('请填写完整信息');
}

try {
    // 检查角色名称是否已存在
    if ($character_id > 0) {
        // 更新角色：检查名称是否与其他角色重复（排除自己）
        $stmt = $db->prepare("SELECT id FROM ai_character WHERE name = ? AND id != ?");
        $stmt->execute([$name, $character_id]);
    } else {
        // 新增角色：检查名称是否已存在
        $stmt = $db->prepare("SELECT id FROM ai_character WHERE name = ?");
        $stmt->execute([$name]);
    }
    
    if ($stmt->fetch()) {
        json_error('角色名称已存在，请使用其他名称');
    }
    // 处理头像上传
    $avatar_path = null; // 初始化为null，表示未设置新头像
    
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
		$upload_dir = __DIR__ . './../../static/ai-images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png'];
        
        // 检查文件类型
        if (!in_array(strtolower($file_extension), $allowed_extensions)) {
			json_error('只支持 JPG 和 PNG 格式的图片');
        }
        
        // 检查文件大小 (10MB)
        $max_size = 10 * 1024 * 1024;
        if ($_FILES['avatar']['size'] > $max_size) {
			json_error('图片大小不能超过 10MB');
        }
        
        $filename = $user_id . '-ai-' . time() . '.' . $file_extension;
        $target_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $target_path)) {
			$avatar_path = '\\static\\ai-images\\' . $filename;
        }
    }
    
    if ($character_id > 0) {
        // 更新角色
        if ($avatar_path !== null) {
            // 用户上传了新头像，使用新头像
            $stmt = $db->prepare("
                UPDATE ai_character 
                SET name = ?, category_id = ?, introduction = ?, prompt = ?, 
                    avatar = ?, is_public = ?, status = 0, update_time = NOW() 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$name, $category_id, $introduction, $prompt, $avatar_path, $is_public, $character_id, $user_id]);
        } else {
            // 用户没有上传新头像，保持原有头像
            $stmt = $db->prepare("
                UPDATE ai_character 
                SET name = ?, category_id = ?, introduction = ?, prompt = ?, 
                    is_public = ?, status = 0, update_time = NOW() 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$name, $category_id, $introduction, $prompt, $is_public, $character_id, $user_id]);
        }
        
        $message = '角色更新成功，已重新提交审核';
    } else {
        // 新增角色 - 如果没有上传头像，使用默认头像
        if ($avatar_path === null) {
            $avatar_path = '/static/ai-images/ai.png';
        }
        
        $stmt = $db->prepare("
            INSERT INTO ai_character 
            (name, user_id, category_id, introduction, prompt, avatar, is_public, status, create_time, update_time) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
        ");
        $stmt->execute([$name, $user_id, $category_id, $introduction, $prompt, $avatar_path, $is_public]);
        
        $message = '角色创建成功，等待审核';
    }
    
	json_success(['message' => $message]);
} catch (PDOException $e) {
    error_log("保存角色失败: " . $e->getMessage());
	json_error('保存失败：' . $e->getMessage());
}
?>