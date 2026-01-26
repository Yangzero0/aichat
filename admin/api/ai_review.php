<?php
/* 
作者：殒狐FOX
文件创建时间：2025-01-26
最后编辑时间：2025-01-26
文件描述：AI审核API，用于自动审核角色内容
*/
session_start();
require_once(__DIR__ . '/../../config/config.php');

header('Content-Type: application/json; charset=utf-8');

// 获取审核设置
$settings = [];
try {
    $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '获取审核设置失败: ' . $e->getMessage()]);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'batch_review') {
    // 手动批量审核（需要管理员登录，不需要检查是否开启自动审核）
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        echo json_encode(['success' => false, 'message' => '未授权访问']);
        exit;
    }
    // 批量审核所有待审核的角色
    try {
        // 获取所有待审核的角色
        $stmt = $db->prepare("
            SELECT ac.*, u.username as creator_name, cc.name as category_name 
            FROM ai_character ac 
            LEFT JOIN user u ON ac.user_id = u.id 
            LEFT JOIN character_category cc ON ac.category_id = cc.id 
            WHERE ac.status = 0 
            ORDER BY ac.create_time ASC
        ");
        $stmt->execute();
        $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($characters)) {
            echo json_encode(['success' => true, 'message' => '没有待审核的角色', 'approved' => 0, 'rejected' => 0, 'failed' => 0]);
            exit;
        }
        
        // 获取AI模型配置（使用第一个启用的模型）
        $stmt = $db->query("SELECT * FROM ai_model WHERE status = 1 AND ai = 1 ORDER BY sort_order ASC, id ASC LIMIT 1");
        $ai_model = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ai_model) {
            echo json_encode(['success' => false, 'message' => '没有可用的AI模型']);
            exit;
        }
        
        $approved = 0;
        $rejected = 0;
        $failed = 0;
        
        // 获取审核规则
        $review_rules = isset($settings['ai_review_rules']) ? trim($settings['ai_review_rules']) : '';
        if (empty($review_rules)) {
            $review_rules = "请审核以下角色内容，判断是否符合平台规范。如果包含色情、暴力、政治敏感、违法违规、恶意攻击、歧视性等不当内容，应该拒绝。同时，如果内容无意义、过于简单、缺乏实质性内容、仅为测试内容或乱码等，也应该拒绝。";
        }
        
        // 对每个角色进行审核
        foreach ($characters as $character) {
            $result = reviewCharacter($character, $ai_model, $review_rules, $db);
            if ($result === 'approve') {
                $approved++;
            } elseif ($result === 'reject') {
                $rejected++;
            } else {
                $failed++;
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => '批量审核完成',
            'approved' => $approved,
            'rejected' => $rejected,
            'failed' => $failed,
            'total' => count($characters)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '批量审核失败: ' . $e->getMessage()]);
    }
    
} elseif ($action === 'auto_review') {
    // 自动审核（由计划任务调用，需要检查是否开启自动审核）
    
    // 检查是否启用AI自动审核
    if (!isset($settings['ai_review_enabled']) || $settings['ai_review_enabled'] != 1) {
        echo json_encode(['success' => false, 'message' => 'AI自动审核功能未启用']);
        exit;
    }
    
    // 简单的安全验证：检查User-Agent是否为计划任务（可选，也可以添加token验证）
    // 这里只做基本验证，实际生产环境可以添加token验证
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    
    try {
        // 获取所有待审核的角色
        $stmt = $db->prepare("
            SELECT ac.*, u.username as creator_name, cc.name as category_name 
            FROM ai_character ac 
            LEFT JOIN user u ON ac.user_id = u.id 
            LEFT JOIN character_category cc ON ac.category_id = cc.id 
            WHERE ac.status = 0 
            ORDER BY ac.create_time ASC
            LIMIT 10
        ");
        $stmt->execute();
        $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($characters)) {
            echo json_encode(['success' => true, 'message' => '没有待审核的角色', 'approved' => 0, 'rejected' => 0, 'failed' => 0]);
            exit;
        }
        
        // 获取AI模型配置（使用第一个启用的模型）
        $stmt = $db->query("SELECT * FROM ai_model WHERE status = 1 AND ai = 1 ORDER BY sort_order ASC, id ASC LIMIT 1");
        $ai_model = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ai_model) {
            echo json_encode(['success' => false, 'message' => '没有可用的AI模型']);
            exit;
        }
        
        $approved = 0;
        $rejected = 0;
        $failed = 0;
        
        // 获取审核规则
        $review_rules = isset($settings['ai_review_rules']) ? trim($settings['ai_review_rules']) : '';
        if (empty($review_rules)) {
            $review_rules = "请审核以下角色内容，判断是否符合平台规范。如果包含色情、暴力、政治敏感、违法违规、恶意攻击、歧视性等不当内容，应该拒绝。同时，如果内容无意义、过于简单、缺乏实质性内容、仅为测试内容或乱码等，也应该拒绝。";
        }
        
        // 对每个角色进行审核
        foreach ($characters as $character) {
            $result = reviewCharacter($character, $ai_model, $review_rules, $db);
            if ($result === 'approve') {
                $approved++;
            } elseif ($result === 'reject') {
                $rejected++;
            } else {
                $failed++;
            }
            
            // 每次审核后稍作延迟，避免API请求过快
            usleep(500000); // 0.5秒延迟
        }
        
        echo json_encode([
            'success' => true, 
            'message' => '自动审核完成',
            'approved' => $approved,
            'rejected' => $rejected,
            'failed' => $failed,
            'total' => count($characters)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '自动审核失败: ' . $e->getMessage()]);
    }
    
} elseif ($action === 'review_single') {
    // 审核单个角色（需要管理员登录）
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        echo json_encode(['success' => false, 'message' => '未授权访问']);
        exit;
    }
    
    $character_id = isset($_GET['character_id']) ? intval($_GET['character_id']) : 0;
    
    if ($character_id <= 0) {
        echo json_encode(['success' => false, 'message' => '角色ID无效']);
        exit;
    }
    
    try {
        // 获取角色信息
        $stmt = $db->prepare("
            SELECT ac.*, u.username as creator_name, cc.name as category_name 
            FROM ai_character ac 
            LEFT JOIN user u ON ac.user_id = u.id 
            LEFT JOIN character_category cc ON ac.category_id = cc.id 
            WHERE ac.id = ?
        ");
        $stmt->execute([$character_id]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$character) {
            echo json_encode(['success' => false, 'message' => '角色不存在']);
            exit;
        }
        
        // 获取AI模型配置
        $stmt = $db->query("SELECT * FROM ai_model WHERE status = 1 AND ai = 1 ORDER BY sort_order ASC, id ASC LIMIT 1");
        $ai_model = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$ai_model) {
            echo json_encode(['success' => false, 'message' => '没有可用的AI模型']);
            exit;
        }
        
        // 获取审核规则
        $review_rules = isset($settings['ai_review_rules']) ? trim($settings['ai_review_rules']) : '';
        if (empty($review_rules)) {
            $review_rules = "请审核以下角色内容，判断是否符合平台规范。如果包含色情、暴力、政治敏感、违法违规、恶意攻击、歧视性等不当内容，应该拒绝。同时，如果内容无意义、过于简单、缺乏实质性内容、仅为测试内容或乱码等，也应该拒绝。";
        }
        
        $result = reviewCharacter($character, $ai_model, $review_rules, $db);
        
        if ($result === 'approve') {
            echo json_encode(['success' => true, 'message' => '审核通过', 'status' => 'approve']);
        } elseif ($result === 'reject') {
            echo json_encode(['success' => true, 'message' => '审核拒绝', 'status' => 'reject']);
        } else {
            echo json_encode(['success' => false, 'message' => '审核失败: ' . $result]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '审核失败: ' . $e->getMessage()]);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => '无效的操作']);
}

/**
 * 审核单个角色
 * @param array $character 角色信息
 * @param array $ai_model AI模型配置
 * @param string $review_rules 审核规则
 * @param PDO $db 数据库连接
 * @return string 'approve'|'reject'|错误信息
 */
function reviewCharacter($character, $ai_model, $review_rules, $db) {
    try {
        // 检查提示词中是否包含 {name} 占位符
        $has_name_placeholder = strpos($character['prompt'] ?? '', '{name}') !== false;
        
        // 构建审核提示词
        $prompt = "你是一个内容审核助手。请仔细审核以下角色信息，判断是否符合平台规范。\n\n";
        $prompt .= "审核规则：\n" . $review_rules . "\n\n";
        $prompt .= "特别注意：\n";
        $prompt .= "1. 如果角色名称、介绍或提示词内容无意义、过于简单（如只有几个字、乱码、重复字符、测试内容等），应该拒绝。\n";
        $prompt .= "2. 如果内容缺乏实质性信息，无法构成一个完整的角色设定，应该拒绝。\n";
        $prompt .= "3. 如果内容明显是测试、占位符或临时内容，应该拒绝。\n";
        $prompt .= "4. **重要说明**：如果提示词中包含 {name} 占位符，这是正常的系统功能标记，用于引用用户名字。在实际使用时，{name} 会被替换为带引号的用户名（例如：如果用户名是\"萝卜\"，则 {name} 会被替换为\"萝卜\"，包含引号）。{name} 占位符是合法的功能标记，不应该因为提示词中包含 {name} 而拒绝审核。\n\n";
        $prompt .= "角色信息：\n";
        $prompt .= "角色名称：" . $character['name'] . "\n";
        $prompt .= "角色介绍：" . ($character['introduction'] ?? '无') . "\n";
        $prompt .= "角色提示词：" . ($character['prompt'] ?? '无') . "\n";
        if ($has_name_placeholder) {
            $prompt .= "（提示：此提示词包含 {name} 占位符，这是用于引用用户名字的正常功能标记）\n";
        }
        $prompt .= "创建者：" . ($character['creator_name'] ?? '未知') . "\n";
        $prompt .= "分类：" . ($character['category_name'] ?? '未分类') . "\n\n";
        $prompt .= "请根据审核规则判断该角色是否应该通过审核。\n";
        $prompt .= "如果角色内容符合规范且有意义，回复：APPROVE\n";
        $prompt .= "如果角色内容不符合规范、无意义或缺乏实质性内容，回复：REJECT\n";
        $prompt .= "只回复APPROVE或REJECT，不要回复其他内容。";
        
        // 调用AI API
        $api_url = $ai_model['api_url'];
        $api_key = $ai_model['api_key'];
        $max_tokens = isset($ai_model['max_tokens']) ? intval($ai_model['max_tokens']) : 2048;
        $temperature = isset($ai_model['temperature']) ? floatval($ai_model['temperature']) : 0.7;
        
        $request_data = [
            'model' => $ai_model['model'],
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => min($max_tokens, 100), // 审核只需要简短回复
            'temperature' => 0.3 // 降低温度以获得更一致的审核结果
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request_data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        curl_close($ch);
        
        if ($curl_errno) {
            return "cURL错误: " . $curl_error;
        }
        
        if ($http_code !== 200) {
            return "API请求失败: HTTP " . $http_code;
        }
        
        $response_data = json_decode($response, true);
        
        if (!isset($response_data['choices'][0]['message']['content'])) {
            return "AI响应格式错误";
        }
        
        $ai_response = trim($response_data['choices'][0]['message']['content']);
        $ai_response = strtoupper($ai_response);
        
        // 判断审核结果
        $status = 0; // 默认保持待审核状态
        if (strpos($ai_response, 'APPROVE') !== false) {
            $status = 1; // 通过
        } elseif (strpos($ai_response, 'REJECT') !== false) {
            $status = 2; // 拒绝
        } else {
            // 如果AI回复不明确，记录日志但不更新状态
            error_log("AI审核结果不明确 - 角色ID: {$character['id']}, AI回复: {$ai_response}");
            return "AI回复不明确: " . $ai_response;
        }
        
        // 更新角色状态
        $stmt = $db->prepare("UPDATE ai_character SET status = ?, update_time = NOW() WHERE id = ?");
        $stmt->execute([$status, $character['id']]);
        
        return $status == 1 ? 'approve' : 'reject';
        
    } catch (Exception $e) {
        error_log("AI审核异常 - 角色ID: {$character['id']}, 错误: " . $e->getMessage());
        return "审核异常: " . $e->getMessage();
    }
}
?>

