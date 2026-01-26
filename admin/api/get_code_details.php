<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-17
最后编辑时间：2025-10-19
api文件描述：用于卡密管理
*/
session_start();
require_once(__DIR__ . '/../../config/config.php');

// 检查管理员是否登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '未授权访问']);
    exit;
}

if (isset($_GET['id'])) {
    $code_id = intval($_GET['id']);
    
    try {
        $stmt = $db->prepare("
            SELECT rc.*, u.name as used_user_name 
            FROM redeem_code rc 
            LEFT JOIN user u ON rc.used_by = u.id 
            WHERE rc.id = ?
        ");
        $stmt->execute([$code_id]);
        $code = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($code) {
            // 格式化时间
            $code['create_time'] = date('Y-m-d H:i:s', strtotime($code['create_time']));
            $code['used_time'] = $code['used_time'] ? date('Y-m-d H:i:s', strtotime($code['used_time'])) : null;
            
            echo json_encode(['success' => true, 'data' => $code]);
        } else {
            echo json_encode(['success' => false, 'message' => '卡密不存在']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '获取详情失败: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => '参数错误']);
}
?>