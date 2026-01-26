<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-17
最后编辑时间：2025-10-19
文件描述：后台登录页面，没啥讲的
*/
session_start();
require_once(__DIR__ . '/../config/config.php');

// 如果已经登录，直接跳转到后台首页
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

// 获取网站配置
$settings = [];
try {
    $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取网站配置失败: " . $e->getMessage());
}

$error = '';

// 检查登录失败次数
$login_attempts = $_SESSION['login_attempts'] ?? 0;
$last_attempt_time = $_SESSION['last_attempt_time'] ?? 0;
$lockout_time = 300; // 5分钟锁定

// 检查是否在锁定期内
if ($login_attempts >= 5 && (time() - $last_attempt_time) < $lockout_time) {
    $remaining_time = $lockout_time - (time() - $last_attempt_time);
    $error = "登录失败次数过多，请 " . ceil($remaining_time / 60) . " 分钟后再试";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        try {
            // 查询管理员表
            $stmt = $db->prepare("SELECT * FROM admin WHERE user = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin && md5($password) === $admin['password']) {
                // 登录成功，重置失败计数
                $_SESSION['login_attempts'] = 0;
                $_SESSION['last_attempt_time'] = 0;
                
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['user'];
                $_SESSION['admin_level'] = $admin['level'];
                
                header('Location: index.php');
                exit;
            } else {
                // 登录失败，增加失败计数
                $_SESSION['login_attempts'] = $login_attempts + 1;
                $_SESSION['last_attempt_time'] = time();
                
                $remaining_attempts = 5 - ($login_attempts + 1);
                if ($remaining_attempts > 0) {
                    $error = "管理员账号或密码错误，还剩 {$remaining_attempts} 次尝试机会";
                } else {
                    $error = "登录失败次数过多，请 5 分钟后再试";
                }
            }
        } catch (PDOException $e) {
            $error = '登录失败，请稍后重试';
        }
    } else {
        $error = '请输入管理员账号和密码';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - <?php echo htmlspecialchars($settings['title'] ?? 'AI角色扮演平台'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #667eea;
            --primary-dark: #5a6fd8;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1f2937;
            --light-color: #f8fafc;
            --border-color: #e5e7eb;
            --text-primary: #374151;
            --text-secondary: #6b7280;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--text-primary);
            line-height: 1.6;
        }
        
        .login-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .login-logo {
            font-size: 2.5rem;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        
        .login-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 8px;
            position: relative;
            z-index: 1;
        }
        
        .login-subtitle {
            opacity: 0.9;
            font-size: 0.95rem;
            position: relative;
            z-index: 1;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
            font-size: 14px;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            background: var(--light-color);
            color: var(--text-primary);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-input.has-error {
            border-color: var(--danger-color);
        }
        
        .form-input:disabled {
            background-color: #f3f4f6;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .error-message {
            color: var(--danger-color);
            font-size: 14px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 12px;
            background: rgba(239, 68, 68, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 8px;
        }
        
        .warning-message {
            color: var(--warning-color);
            font-size: 14px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 12px;
            background: rgba(245, 158, 11, 0.05);
            border: 1px solid rgba(245, 158, 11, 0.2);
            border-radius: 8px;
        }
        
        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }
        
        .login-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .login-btn:active:not(:disabled) {
            transform: translateY(0);
        }
        
        .login-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .login-btn:hover::before {
            left: 100%;
        }
        
        .login-footer {
            text-align: center;
            padding: 20px;
            border-top: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .back-to-home {
            color: var(--primary-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
            padding: 8px 16px;
            border-radius: 6px;
        }
        
        .back-to-home:hover {
            color: var(--primary-dark);
            background: var(--light-color);
        }
        
        .attempts-info {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background: var(--light-color);
            border-radius: 8px;
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .lockout-info {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(239, 68, 68, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 8px;
            color: var(--danger-color);
            font-size: 14px;
        }
        
        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
            }
            
            .login-header {
                padding: 30px 20px;
            }
            
            .login-body {
                padding: 30px 20px;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">
                <i class="fas fa-user-shield"></i>
            </div>
            <h1 class="login-title">管理员登录</h1>
            <p class="login-subtitle"><?php echo htmlspecialchars($settings['title'] ?? 'AI角色扮演平台'); ?> 后台管理系统</p>
        </div>
        
        <div class="login-body">
            <?php if (!empty($error)): ?>
                <div class="<?php echo (strpos($error, '分钟后再试') !== false) ? 'lockout-info' : 'error-message'; ?>">
                    <i class="fas <?php echo (strpos($error, '分钟后再试') !== false) ? 'fa-lock' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($login_attempts > 0 && $login_attempts < 5): ?>
                <div class="attempts-info">
                    <i class="fas fa-shield-alt"></i>
                    已尝试 <?php echo $login_attempts; ?> 次，剩余 <?php echo 5 - $login_attempts; ?> 次机会
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">管理员账号</label>
                    <input type="text" name="username" class="form-input <?php echo !empty($error) ? 'has-error' : ''; ?>" 
                           placeholder="请输入管理员账号" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                           <?php echo ($login_attempts >= 5 && (time() - $last_attempt_time) < $lockout_time) ? 'disabled' : ''; ?> required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">密码</label>
                    <input type="password" name="password" class="form-input <?php echo !empty($error) ? 'has-error' : ''; ?>" 
                           placeholder="请输入密码" 
                           <?php echo ($login_attempts >= 5 && (time() - $last_attempt_time) < $lockout_time) ? 'disabled' : ''; ?> required>
                </div>
                
                <button type="submit" class="login-btn" 
                    <?php echo ($login_attempts >= 5 && (time() - $last_attempt_time) < $lockout_time) ? 'disabled' : ''; ?>>
                    <i class="fas fa-sign-in-alt"></i>
                    <?php echo ($login_attempts >= 5 && (time() - $last_attempt_time) < $lockout_time) ? '账号已锁定' : '登录后台'; ?>
                </button>
            </form>
        </div>
        
        <div class="login-footer">
            <a href="../index.php" class="back-to-home">
                <i class="fas fa-arrow-left"></i>
                返回网站首页
            </a>
        </div>
    </div>

    <script>
        // 自动刷新锁定倒计时
        <?php if ($login_attempts >= 5 && (time() - $last_attempt_time) < $lockout_time): ?>
        let remainingTime = <?php echo $lockout_time - (time() - $last_attempt_time); ?>;
        
        function updateLockoutTimer() {
            if (remainingTime <= 0) {
                location.reload();
                return;
            }
            
            const minutes = Math.floor(remainingTime / 60);
            const seconds = remainingTime % 60;
            
            const lockoutElement = document.querySelector('.lockout-info');
            if (lockoutElement) {
                lockoutElement.innerHTML = `<i class="fas fa-lock"></i> 登录失败次数过多，请 ${minutes} 分 ${seconds} 秒后再试`;
            }
            
            remainingTime--;
            setTimeout(updateLockoutTimer, 1000);
        }
        
        // 页面加载后开始计时
        document.addEventListener('DOMContentLoaded', function() {
            updateLockoutTimer();
        });
        <?php endif; ?>

        // 输入框焦点效果
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        this.parentElement.classList.remove('focused');
                    }
                });
            });
        });
    </script>
</body>