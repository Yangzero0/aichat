<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-7
最后编辑时间：2025-10-16
文件描述：发送验证码邮件功能

*/
require_once(__DIR__ . './../../config/config.php');

// 引入PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// 自动加载PHPMailer（根据您的安装方式调整路径）
require_once __DIR__ . './vendor/autoload.php'; // Composer安装
// 或者手动引入：
// require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
// require_once __DIR__ . '/../phpmailer/src/SMTP.php';
// require_once __DIR__ . '/../phpmailer/src/Exception.php';

header('Content-Type: application/json');

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方法不正确']);
    exit;
}

// 获取参数
$mail = trim($_POST['mail'] ?? '');
$type = intval($_POST['type'] ?? 0); // 0-注册验证 1-密码重置

if (empty($mail) || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => '请输入有效的邮箱地址']);
    exit;
}

try {
    // 获取网站配置
    $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$settings) {
        throw new Exception('系统配置错误');
    }
    
    // 检查SMTP配置
    if (empty($settings['smtp_host']) || empty($settings['smtp_username'])) {
        throw new Exception('邮件服务未配置，请联系管理员');
    }
    
    // 根据类型进行不同检查
    if ($type === 0) {
        // 注册验证：检查邮箱是否已注册
        $stmt = $db->prepare("SELECT id FROM user WHERE mail = ?");
        $stmt->execute([$mail]);
        if ($stmt->fetch()) {
            throw new Exception('该邮箱已被注册');
        }
    } else {
        // 密码重置：检查邮箱是否存在且用户状态正常
        $stmt = $db->prepare("SELECT id FROM user WHERE mail = ? AND status = 1");
        $stmt->execute([$mail]);
        if (!$stmt->fetch()) {
            throw new Exception('该邮箱未注册或账号已被禁用');
        }
    }
    
    // 检查发送频率（同一邮箱1分钟内只能发送一次）
    $stmt = $db->prepare("SELECT create_time FROM mail_verify_code WHERE mail = ? AND type = ? AND create_time > DATE_SUB(NOW(), INTERVAL 1 MINUTE) ORDER BY create_time DESC LIMIT 1");
    $stmt->execute([$mail, $type]);
    if ($stmt->fetch()) {
        throw new Exception('验证码发送过于频繁，请1分钟后再试');
    }
    
    // 生成6位数字验证码
    $code = sprintf("%06d", mt_rand(1, 999999));
    
    // 保存验证码到数据库
    $stmt = $db->prepare("INSERT INTO mail_verify_code (mail, code, type, expire_time) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
    $stmt->execute([$mail, $code, $type]);
    
    // 发送验证邮件
    if (sendVerificationEmail($mail, $code, $type, $settings)) {
        echo json_encode(['success' => true, 'message' => '验证码已发送到您的邮箱，30分钟内有效']);
    } else {
        // 如果发送失败，删除刚刚插入的验证码记录
        $stmt = $db->prepare("DELETE FROM mail_verify_code WHERE mail = ? AND code = ? AND type = ?");
        $stmt->execute([$mail, $code, $type]);
        throw new Exception('验证码发送失败，请稍后重试');
    }
    
} catch (PDOException $e) {
    error_log("数据库错误: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '系统错误，请稍后重试']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * 使用PHPMailer发送验证邮件
 */
function sendVerificationEmail($mail, $code, $type, $settings) {
    $site_title = $settings['title'] ?? 'AI聊天系统';
    
    if ($type === 0) {
        $subject = "【{$site_title}】注册验证码";
        $content = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #667eea; color: white; padding: 15px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                    .code { font-size: 32px; color: #667eea; text-align: center; margin: 20px 0; font-weight: bold; }
                    .footer { margin-top: 20px; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>【{$site_title}】注册验证码</h2>
                    </div>
                    <div class='content'>
                        <p>您正在注册{$site_title}账号，验证码为：</p>
                        <div class='code'>{$code}</div>
                        <p>该验证码30分钟内有效，请勿泄露给他人。</p>
                        <p>如果不是您本人操作，请忽略此邮件。</p>
                    </div>
                    <div class='footer'>
                        <p>此邮件由系统自动发送，请勿回复。</p>
                    </div>
                </div>
            </body>
            </html>
        ";
    } else {
        $subject = "【{$site_title}】密码重置验证码";
        $content = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #667eea; color: white; padding: 15px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                    .code { font-size: 32px; color: #667eea; text-align: center; margin: 20px 0; font-weight: bold; }
                    .footer { margin-top: 20px; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>【{$site_title}】密码重置验证码</h2>
                    </div>
                    <div class='content'>
                        <p>您正在重置{$site_title}账号密码，验证码为：</p>
                        <div class='code'>{$code}</div>
                        <p>该验证码30分钟内有效，请勿泄露给他人。</p>
                        <p>如果不是您本人操作，请立即修改账号密码。</p>
                    </div>
                    <div class='footer'>
                        <p>此邮件由系统自动发送，请勿回复。</p>
                    </div>
                </div>
            </body>
            </html>
        ";
    }
    
    try {
        // 创建PHPMailer实例
        $phpmailer = new PHPMailer(true);
        
        // 服务器设置
        $phpmailer->isSMTP();
        $phpmailer->Host = $settings['smtp_host'];
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = $settings['smtp_username'];
        $phpmailer->Password = $settings['smtp_password'];
        
        // 根据端口选择加密方式
        $port = $settings['smtp_port'] ?? 587;
        if ($port == 465) {
            $phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        
        $phpmailer->Port = $port;
        
        // 调试模式（生产环境应关闭）
        // $phpmailer->SMTPDebug = SMTP::DEBUG_SERVER;
        
        // 编码设置
        $phpmailer->CharSet = 'UTF-8';
        
        // 发件人
        $phpmailer->setFrom(
            $settings['smtp_from_email'] ?? $settings['smtp_username'],
            $settings['smtp_from_name'] ?? $site_title
        );
        
        // 收件人
        $phpmailer->addAddress($mail);
        
        // 内容
        $phpmailer->isHTML(true);
        $phpmailer->Subject = $subject;
        $phpmailer->Body = $content;
        
        // 纯文本备用内容
        $phpmailer->AltBody = strip_tags($content);
        
        // 发送邮件
        return $phpmailer->send();
        
    } catch (Exception $e) {
        error_log("邮件发送失败: " . $phpmailer->ErrorInfo);
        return false;
    }
}
?>