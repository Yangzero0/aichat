<?php
/**
 * 获取网站配置
 */
function getWebSettings() {
    global $db;
    try {
        $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
        return $stmt->fetch();
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * 发送邮箱验证码
 */
function sendVerificationCode($email, $code) {
    $settings = getWebSettings();
    if (!$settings || !$settings['reg_mail']) {
        return false;
    }

    // 配置邮件参数
    $subject = "邮箱验证码 - " . $settings['title'];
    $message = "您的验证码是：{$code}，有效期15分钟。请勿泄露给他人。";
    $headers = "From: " . $settings['smtp_from_name'] . " <" . $settings['smtp_from_email'] . ">\r\n";
    $headers .= "Content-Type: text/html; charset=utf-8\r\n";

    // 使用SMTP发送邮件（这里简化处理，实际应该使用PHPMailer等库）
    if ($settings['smtp_host']) {
        // 实际项目中建议使用PHPMailer或SwiftMailer
        return mail($email, $subject, $message, $headers);
    }
    
    return false;
}

/**
 * 生成随机验证码
 */
function generateVerificationCode($length = 6) {
    return sprintf("%0{$length}d", mt_rand(0, pow(10, $length) - 1));
}

/**
 * 验证用户登录
 */
function verifyUserLogin($username, $password) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT * FROM user WHERE username = ? AND status = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && md5($password) === $user['password']) {
            return $user;
        }
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * 会话与请求工具
 */
function ensureSessionStarted() {
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}
}

function requireLoginOrJsonError() {
	ensureSessionStarted();
	if (!isset($_SESSION['user_id'])) {
		json_error('未登录');
	}
	return intval($_SESSION['user_id']);
}

function requirePostOrJsonError() {
	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
		json_error('请求方法不正确');
	}
}

function json_success(array $data = []) {
	header('Content-Type: application/json');
	echo json_encode(array_merge(['success' => true], $data), JSON_UNESCAPED_UNICODE);
	exit;
}

function json_error(string $message, array $extra = []) {
	header('Content-Type: application/json');
	echo json_encode(array_merge(['success' => false, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
	exit;
}

function get_int(array $src, string $key, int $default = 0) {
	return isset($src[$key]) ? intval($src[$key]) : $default;
}

function get_str(array $src, string $key, string $default = '') {
	return isset($src[$key]) ? trim((string)$src[$key]) : $default;
}

/**
 * 通用日志
 */
function app_log(string $message, $data = null, string $file = 'app.log') {
	$logDir = __DIR__ . '/../user/logs';
	if (!is_dir($logDir)) {
		@mkdir($logDir, 0755, true);
	}
	$logFile = rtrim($logDir, '/\\') . '/' . $file;
	$timestamp = date('Y-m-d H:i:s');
	$logMessage = "[{$timestamp}] {$message}";
	if ($data !== null) {
		$logMessage .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
	}
	$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
	$logMessage .= " | IP: {$ip}\n";
	@error_log($logMessage, 3, $logFile);
}
?>