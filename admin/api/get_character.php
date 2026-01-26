<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-17
最后编辑时间：2025-10-18
api文件描述：用于获取角色信息
*/
session_start();
require_once(__DIR__ . './../../config/config.php');

// 检查管理员是否登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '未授权访问']);
    exit;
}

if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '角色ID不能为空']);
    exit;
}

$character_id = intval($_GET['id']);

try {
    $stmt = $db->prepare("SELECT * FROM ai_character WHERE id = ?");
    $stmt->execute([$character_id]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($character) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'character' => $character]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => '角色不存在']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '获取角色信息失败: ' . $e->getMessage()]);
}
?>