<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-12
最后编辑时间：2025-10-12
文件描述：用于添加用户对角色评价

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
$rating = get_int($_POST, 'rating', 0);
$comment = get_str($_POST, 'comment', '');

if ($character_id <= 0) {
	json_error('角色ID无效');
}

if ($rating < 1 || $rating > 5) {
	json_error('评分必须在1-5之间');
}

try {
    // 开始事务
    $db->beginTransaction();
    
    // 检查是否已评价
    $stmt = $db->prepare("SELECT id FROM character_rating WHERE user_id = ? AND character_id = ?");
    $stmt->execute([$user_id, $character_id]);
    $existing_review = $stmt->fetch();
    
    if ($existing_review) {
        // 更新评价
        $stmt = $db->prepare("UPDATE character_rating SET rating = ?, comment = ?, create_time = NOW() WHERE user_id = ? AND character_id = ?");
        $stmt->execute([$rating, $comment, $user_id, $character_id]);
    } else {
        // 新增评价
        $stmt = $db->prepare("INSERT INTO character_rating (user_id, character_id, rating, comment, create_time) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $character_id, $rating, $comment]);
    }
    
    // 重新计算平均评分
    $stmt = $db->prepare("SELECT AVG(rating) as avg_rating FROM character_rating WHERE character_id = ?");
    $stmt->execute([$character_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $new_avg_rating = $result['avg_rating'] ? round($result['avg_rating'], 1) : 0;
    
    // 更新角色表的平均评分
    $stmt = $db->prepare("UPDATE ai_character SET avg_rating = ? WHERE id = ?");
    $stmt->execute([$new_avg_rating, $character_id]);
    
    // 提交事务
    $db->commit();
    
	json_success(['new_avg_rating' => $new_avg_rating]);
    
} catch (PDOException $e) {
    // 回滚事务
    $db->rollBack();
    error_log("评价提交错误: " . $e->getMessage());
	json_error('评价提交失败，请稍后重试');
}
?>