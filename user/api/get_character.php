<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-5
最后编辑时间：2025-10-13
文件描述：用于获取角色信息

*/
session_start();
require_once(__DIR__ . './../../config/config.php');
require_once(__DIR__ . './../../config/functions.php');

// 登录校验
$user_id = requireLoginOrJsonError();

if (!isset($_GET['id'])) {
	json_error('参数错误');
}

$character_id = intval($_GET['id']);

try {
    $stmt = $db->prepare("
        SELECT * FROM ai_character 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$character_id, $user_id]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($character) {
		json_success(['character' => $character]);
    } else {
		json_error('角色不存在或无权访问');
    }
} catch (PDOException $e) {
    error_log("获取角色失败: " . $e->getMessage());
	json_error('服务器错误');
}
?>