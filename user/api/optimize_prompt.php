<?php
/* 
作者：殒狐FOX
文件创建时间：2025-01-26
最后编辑时间：2025-01-26
文件描述：用于AI优化角色提示词

*/
session_start();
require_once(__DIR__ . './../../config/config.php');
require_once(__DIR__ . './../../config/functions.php');

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
	json_error('未登录');
}

$user_id = $_SESSION['user_id'];
$prompt = get_str($_POST, 'prompt', '');

// 验证提示词不为空
if (empty($prompt)) {
	json_error('提示词不能为空');
}

try {
	// 获取第一个启用的AI模型
	$stmt = $db->prepare("SELECT * FROM ai_model WHERE status = 1 ORDER BY sort_order ASC, id ASC LIMIT 1");
	$stmt->execute();
	$ai_model = $stmt->fetch(PDO::FETCH_ASSOC);
	
	if (!$ai_model) {
		json_error('没有可用的AI模型');
	}
	
	// 检查原提示词中是否包含 {name} 占位符
	$has_name_placeholder = strpos($prompt, '{name}') !== false;
	
	// 构建优化提示词的请求
	$optimize_prompt = "请优化以下角色提示词，使其更加清晰、详细和有效。\n\n**重要判断规则：**\n在优化之前，请先判断原提示词是否有意义和逻辑：\n- 如果原提示词过于简单（如只有几个字、无意义的字符、乱码等）\n- 如果原提示词没有逻辑、无法理解其含义\n- 如果原提示词内容空洞、无法构成有效的角色设定\n\n如果出现以上情况，请直接返回：提示词过于简单，无法优化\n\n**如果原提示词有意义且可以优化，请按以下要求优化：**\n1. 保持原有角色的核心特征和设定\n2. 增加更多细节描述，使角色更加生动\n3. 明确角色的说话风格、行为习惯和知识范围\n4. 确保提示词结构清晰，易于AI理解\n5. 保持或改进原有的语气和风格";
	
	// 如果原提示词包含 {name} 占位符，说明其作用并要求保留
	if ($has_name_placeholder) {
		$optimize_prompt .= "\n6. **非常重要**：原提示词中包含 {name} 占位符，这是用于引用用户名字的特殊标记。在实际使用时，{name} 会被替换为用户的真实名字（例如：如果用户名是\"萝卜\"，则 {name} 会被替换为\"萝卜\"）。优化后的提示词必须完整保留这个 {name} 占位符，保持 {name} 的格式不变，不要将其替换为其他文字或删除。";
	}
	
	$optimize_prompt .= "\n\n原提示词：\n" . $prompt . "\n\n请先判断原提示词是否有意义。如果无意义或过于简单，直接返回：提示词过于简单，无法优化\n如果原提示词有意义，请直接输出优化后的提示词，不要添加任何解释或说明文字。";
	
	// 如果原提示词包含 {name} 占位符，再次强调
	if ($has_name_placeholder) {
		$optimize_prompt .= "\n\n再次提醒：如果原提示词包含 {name} 占位符，优化后的提示词也必须包含 {name} 占位符，这是用于引用用户名字的标记，格式必须保持为 {name}（包含大括号）。";
	}
	
	$messages = [
		[
			'role' => 'user',
			'content' => $optimize_prompt
		]
	];
	
	$api_url = $ai_model['api_url'];
	$api_key = $ai_model['api_key'];
	$max_tokens = min(intval($ai_model['max_tokens']), 2048); // 限制最大token数
	$temperature = floatval($ai_model['temperature']);
	
	$request_data = [
		'model' => $ai_model['model'],
		'messages' => $messages,
		'max_tokens' => $max_tokens,
		'temperature' => $temperature,
		'stream' => false
	];
	
	// 调用AI API
	$ch = curl_init();
	curl_setopt_array($ch, [
		CURLOPT_URL => $api_url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => json_encode($request_data),
		CURLOPT_HTTPHEADER => [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $api_key,
			'Accept: application/json'
		],
		CURLOPT_TIMEOUT => 60,
		CURLOPT_CONNECTTIMEOUT => 30,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => false,
		CURLOPT_USERAGENT => 'AI-Chat-System/1.0'
	]);
	
	$response = curl_exec($ch);
	$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$curl_error = curl_error($ch);
	curl_close($ch);
	
	if ($curl_error) {
		app_log("AI API调用失败", ['error' => $curl_error], 'chatapi.log');
		json_error('AI服务调用失败：' . $curl_error);
	}
	
	if ($http_code !== 200) {
		app_log("AI API返回错误", ['http_code' => $http_code, 'response' => $response], 'chatapi.log');
		json_error('AI服务返回错误，请稍后重试');
	}
	
	$response_data = json_decode($response, true);
	
	if (!isset($response_data['choices'][0]['message']['content'])) {
		app_log("AI API响应格式错误", ['response' => $response], 'chatapi.log');
		json_error('AI服务响应格式错误');
	}
	
	$optimized_prompt = trim($response_data['choices'][0]['message']['content']);
	
	// 检查AI是否判断提示词过于简单无法优化
	if (strpos($optimized_prompt, '提示词过于简单，无法优化') !== false || 
	    strpos($optimized_prompt, '提示词过于简单') !== false ||
	    strpos($optimized_prompt, '无法优化') !== false) {
		json_error('提示词过于简单，无法优化。请提供更有意义的角色描述。');
	}
	
	// 如果原提示词包含 {name} 占位符，但优化后的提示词丢失了，在末尾添加
	if ($has_name_placeholder && strpos($optimized_prompt, '{name}') === false) {
		$optimized_prompt .= ' 用户名叫 {name}';
	}
	
	// 限制优化后的提示词长度（最多600个字符，与原提示词限制一致）
	if (mb_strlen($optimized_prompt, 'UTF-8') > 600) {
		$optimized_prompt = mb_substr($optimized_prompt, 0, 600, 'UTF-8');
		// 如果被截断，确保 {name} 占位符还在
		if ($has_name_placeholder && strpos($optimized_prompt, '{name}') === false) {
			// 在末尾添加 {name} 占位符，确保不超过600字符
			$name_placeholder = ' 用户名叫 {name}';
			$max_length = 600 - mb_strlen($name_placeholder, 'UTF-8');
			if ($max_length > 0) {
				$optimized_prompt = mb_substr($optimized_prompt, 0, $max_length, 'UTF-8') . $name_placeholder;
			} else {
				// 如果连占位符都放不下，至少保留 {name}
				$optimized_prompt = mb_substr($optimized_prompt, 0, 594, 'UTF-8') . ' {name}';
			}
		}
	}
	
	json_success([
		'optimized_prompt' => $optimized_prompt,
		'message' => '提示词优化完成'
	]);
	
} catch (PDOException $e) {
	error_log("优化提示词失败: " . $e->getMessage());
	json_error('服务器错误：' . $e->getMessage());
} catch (Exception $e) {
	error_log("优化提示词失败: " . $e->getMessage());
	json_error('优化失败：' . $e->getMessage());
}
?>

