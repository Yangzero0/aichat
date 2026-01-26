<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-10
最后编辑时间：2025-10-25
api文件描述：用于保存或读取会话信息和ai终止功能
*/
session_start();
require_once(__DIR__ . './../../config/config.php');
require_once(__DIR__ . './../../config/functions.php');

// 设置JSON响应头
header('Content-Type: application/json');

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
	json_error('用户未登录');
}

$user_id = intval($_SESSION['user_id']);
$action = isset($_POST['action']) ? $_POST['action'] : '';

// 添加会话调试信息
app_log("用户会话信息", [
	'user_id' => $user_id,
	'session_user_id' => $_SESSION['user_id'] ?? '未设置',
	'action' => $action,
	'post_data' => $_POST
], 'chat_session.log');

try {
	app_log("收到请求", ['action' => $action, 'post_data' => $_POST], 'chat_session.log');
	
	switch ($action) {
		case 'get_sessions':
			$character_id = get_int($_POST, 'character_id', 0);
			$sql = "
                SELECT 
                    cs.id,
                    cs.character_id,
                    cs.model_id,
                    cs.session_title,
                    cs.start_time,
                    cs.last_active,
                    cs.message_count,
                    ac.name as character_name,
                    ac.avatar as character_avatar,
                    am.model_name
                FROM chat_session cs
                LEFT JOIN ai_character ac ON cs.character_id = ac.id
                LEFT JOIN ai_model am ON cs.model_id = am.id
                WHERE cs.user_id = ? AND cs.status = 1
            ";
			
			$params = [$user_id];
			if ($character_id > 0) {
				$sql .= " AND cs.character_id = ?";
				$params[] = $character_id;
			}
			
			$sql .= " ORDER BY cs.last_active DESC";
			
			$stmt = $db->prepare($sql);
			$stmt->execute($params);
			$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
			
			json_success(['sessions' => $sessions]);
			break;

		case 'delete_session':
			$session_id = get_int($_POST, 'session_id', 0);
			
			if ($session_id <= 0) {
				json_error('会话ID无效');
			}
			
			// 验证会话属于当前用户
			$stmt = $db->prepare("SELECT id, character_id FROM chat_session WHERE id = ? AND user_id = ?");
			$stmt->execute([$session_id, $user_id]);
			$session_info = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if (!$session_info) {
				json_error('会话不存在或无权限删除');
			}
			
			// 开始事务
			$db->beginTransaction();
			
			try {
				// 硬删除：先删除聊天记录，再删除会话
				$stmt = $db->prepare("DELETE FROM chat_record WHERE session_id = ?");
				$stmt->execute([$session_id]);
				
				$stmt = $db->prepare("DELETE FROM chat_session WHERE id = ?");
				$stmt->execute([$session_id]);
				
				$db->commit();
				json_success([
					'message' => '会话删除成功',
					'deleted_session_id' => $session_id,
					'character_id' => $session_info['character_id']
				]);
			} catch (Exception $e) {
				$db->rollBack();
				throw $e;
			}
			break;

		case 'update_title':
			$session_id = get_int($_POST, 'session_id', 0);
			$new_title = get_str($_POST, 'new_title', '');
			
			app_log("修改标题请求", ['session_id' => $session_id, 'new_title' => $new_title, 'user_id' => $user_id], 'chat_session.log');
			
			if ($session_id <= 0 || empty($new_title)) {
				json_error('参数不完整');
			}
			
			if (mb_strlen($new_title, 'UTF-8') > 255) {
				json_error('标题长度不能超过255个字符');
			}
			
			// 验证会话属于当前用户 - 使用更详细的查询来调试
			$stmt = $db->prepare("SELECT id, user_id, character_id FROM chat_session WHERE id = ?");
			$stmt->execute([$session_id]);
			$session_info = $stmt->fetch(PDO::FETCH_ASSOC);
			
			app_log("会话验证结果", ['session_info' => $session_info, 'current_user_id' => $user_id], 'chat_session.log');
			
			if (!$session_info) {
				json_error('会话不存在');
			}
			
			if ($session_info['user_id'] != $user_id) {
				json_error('无权限修改此会话');
			}
			
			// 更新标题
			$stmt = $db->prepare("UPDATE chat_session SET session_title = ? WHERE id = ?");
			$result = $stmt->execute([$new_title, $session_id]);
			
			app_log("更新标题结果", ['result' => $result, 'rowCount' => $stmt->rowCount()], 'chat_session.log');
			
			if ($result && $stmt->rowCount() > 0) {
				json_success(['message' => '标题修改成功']);
			} else {
				json_error('标题修改失败，可能标题未变化');
			}
			break;
				
		case 'update_message':
			// 更新消息并重新生成
			$message_id = get_int($_POST, 'message_id', 0);
			$new_message = get_str($_POST, 'new_message', '');
			
			app_log("更新消息请求", ['message_id' => $message_id, 'new_message_length' => strlen($new_message)], 'chat_session.log');
			
			if ($message_id <= 0 || empty($new_message)) {
				json_error('参数不完整');
			}
			
			// 获取消息信息并验证权限
			$stmt = $db->prepare("
                SELECT cr.*, cs.user_id, cs.character_id, cs.model_id 
                FROM chat_record cr 
                LEFT JOIN chat_session cs ON cr.session_id = cs.id 
                WHERE cr.id = ? AND cs.user_id = ?
            ");
			$stmt->execute([$message_id, $user_id]);
			$message_info = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if (!$message_info) {
				json_error('消息不存在或无权限修改');
			}
			
			// 开始事务
			$db->beginTransaction();
			
			try {
				// 硬删除：删除该消息之后的所有消息
				$stmt = $db->prepare("
                    DELETE FROM chat_record 
                    WHERE session_id = ? AND message_order > ?
                ");
				$stmt->execute([$message_info['session_id'], $message_info['message_order']]);
				
				// 更新用户消息
				$stmt = $db->prepare("
                    UPDATE chat_record 
                    SET user_message = ?, ai_response = NULL, tokens_used = 0, is_interrupted = 0
                    WHERE id = ?
                ");
				$stmt->execute([$new_message, $message_id]);
				
				$db->commit();
				
				// 返回需要的信息用于重新生成
				json_success([
					'message' => '消息更新成功',
					'session_id' => $message_info['session_id'],
					'character_id' => $message_info['character_id'],
					'model_id' => $message_info['model_id'],
					'message_id' => $message_id
				]);
			} catch (Exception $e) {
				$db->rollBack();
				throw $e;
			}
			break;
			
		case 'regenerate':
			// 重新生成AI回复
			$message_id = get_int($_POST, 'message_id', 0);
			
			app_log("重新生成请求", ['message_id' => $message_id], 'chat_session.log');
			
			if ($message_id <= 0) {
				json_error('消息ID无效');
			}
			
			// 获取消息信息并验证权限
			$stmt = $db->prepare("
                SELECT cr.*, cs.user_id, cs.character_id, cs.model_id 
                FROM chat_record cr 
                LEFT JOIN chat_session cs ON cr.session_id = cs.id 
                WHERE cr.id = ? AND cs.user_id = ?
            ");
			$stmt->execute([$message_id, $user_id]);
			$message_info = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if (!$message_info) {
				json_error('消息不存在或无权限操作');
			}
			
			// 开始事务
			$db->beginTransaction();
			
			try {
				// 硬删除：删除该消息之后的所有消息
				$stmt = $db->prepare("
                    DELETE FROM chat_record 
                    WHERE session_id = ? AND message_order > ?
                ");
				$stmt->execute([$message_info['session_id'], $message_info['message_order']]);
				
				// 清空AI回复，等待重新生成
				$stmt = $db->prepare("
                    UPDATE chat_record 
                    SET ai_response = NULL, tokens_used = 0, is_interrupted = 0
                    WHERE id = ?
                ");
				$stmt->execute([$message_id]);
				
				$db->commit();
				
				json_success([
					'message' => '准备重新生成AI回复',
					'session_id' => $message_info['session_id'],
					'character_id' => $message_info['character_id'],
					'model_id' => $message_info['model_id'],
					'message_id' => $message_id
				]);
			} catch (Exception $e) {
				$db->rollBack();
				throw $e;
			}
			break;
			
		case 'stop_generation':
			// 中断消息生成
			$message_id = get_int($_POST, 'message_id', 0);
			
			if ($message_id <= 0) {
				json_error('消息ID无效');
			}
			
			// 获取消息信息并验证权限
			$stmt = $db->prepare("
                SELECT cr.*, cs.user_id 
                FROM chat_record cr 
                LEFT JOIN chat_session cs ON cr.session_id = cs.id 
                WHERE cr.id = ? AND cs.user_id = ?
            ");
			$stmt->execute([$message_id, $user_id]);
			$message_info = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if (!$message_info) {
				json_error('消息不存在或无权限操作');
			}
			
			// 标记消息为中断状态
			$stmt = $db->prepare("
                UPDATE chat_record 
                SET is_interrupted = 1 
                WHERE id = ?
            ");
			$stmt->execute([$message_id]);
			
			// 返回消息内容，确保前端能获取到所有已保存的内容
			json_success([
				'message' => '消息生成已中断',
				'ai_response' => $message_info['ai_response'] ?? ''
			]);
			break;
			
		case 'continue_generation':
			// 继续生成消息
			$message_id = get_int($_POST, 'message_id', 0);
			
			if ($message_id <= 0) {
				json_error('消息ID无效');
			}
			
			// 获取消息信息并验证权限
			$stmt = $db->prepare("
                SELECT cr.*, cs.user_id, cs.character_id, cs.model_id 
                FROM chat_record cr 
                LEFT JOIN chat_session cs ON cr.session_id = cs.id 
                WHERE cr.id = ? AND cs.user_id = ? AND cr.is_interrupted = 1
            ");
			$stmt->execute([$message_id, $user_id]);
			$message_info = $stmt->fetch(PDO::FETCH_ASSOC);
			
			if (!$message_info) {
				json_error('消息不存在、无权限操作或无法继续生成');
			}
			
			// 清除中断标记
			$stmt = $db->prepare("
                UPDATE chat_record 
                SET is_interrupted = 0 
                WHERE id = ?
            ");
			$stmt->execute([$message_id]);
			
			json_success([
				'message' => '准备继续生成',
				'session_id' => $message_info['session_id'],
				'character_id' => $message_info['character_id'],
				'model_id' => $message_info['model_id'],
				'message_id' => $message_id
			]);
			break;
			
		default:
			json_error('未知操作');
			break;
	}
} catch (Exception $e) {
	app_log("chat_session.php 错误", ['error' => $e->getMessage()], 'chat_session.log');
	json_error('服务器错误: ' . $e->getMessage());
}
?>