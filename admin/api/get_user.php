<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-17
最后编辑时间：2025-10-19
api文件描述：同于上传用户头像
*/
session_start();
require_once(__DIR__ . '/../../config/config.php');

// 检查管理员是否登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '未授权访问']);
    exit;
}

if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '用户ID不能为空']);
    exit;
}

$user_id = intval($_GET['id']);

try {
    $stmt = $db->prepare("SELECT * FROM user WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // 检查头像文件是否存在
        $avatar_path = $user['avatar'];
        if ($avatar_path && $avatar_path !== '/static/user-images/user.png') {
            $full_avatar_path = __DIR__ . '/../..' . $avatar_path;
            if (!file_exists($full_avatar_path)) {
                $user['avatar'] = '/static/user-images/user.png';
            }
        } else {
            $user['avatar'] = '/static/user-images/user.png';
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'user' => $user]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => '用户不存在']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '获取用户信息失败: ' . $e->getMessage()]);
}
?>