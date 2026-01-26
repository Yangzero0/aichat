<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-5
最后编辑时间：2025-10-13
文件描述：用于获取角色评价信息

*/
require_once(__DIR__ . './../../config/config.php');
require_once(__DIR__ . './../../config/functions.php');

header('Content-Type: application/json');

// 获取参数
$character_id = get_int($_GET, 'character_id', 0);
$page = max(1, get_int($_GET, 'page', 1));
$limit = min(50, max(1, get_int($_GET, 'limit', 10)));
$offset = ($page - 1) * $limit;

if ($character_id <= 0) {
	json_error('角色ID无效');
}

try {
    // 获取评价总数
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM character_rating WHERE character_id = ?");
    $stmt->execute([$character_id]);
    $total_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_reviews = $total_result['total'];
    
    // 获取评价列表
    $stmt = $db->prepare("
        SELECT 
            cr.rating, 
            cr.comment, 
            cr.create_time,
            u.name as user_name,
            u.avatar as user_avatar
        FROM character_rating cr
        LEFT JOIN user u ON cr.user_id = u.id
        WHERE cr.character_id = ?
        ORDER BY cr.create_time DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $character_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 格式化日期和处理头像
    foreach ($reviews as &$review) {
        $review['create_time'] = date('Y-m-d H:i', strtotime($review['create_time']));
        // 处理用户头像
        if (empty($review['user_avatar'])) {
            $review['user_avatar'] = '/static/user-images/user.png';
        }
    }
    
	json_success([
		'reviews' => $reviews,
		'has_more' => ($offset + count($reviews)) < $total_reviews
	]);
    
} catch (PDOException $e) {
    error_log("获取评价错误: " . $e->getMessage());
	json_error('获取评价失败，请稍后重试');
}
?>