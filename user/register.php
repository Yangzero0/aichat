<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-17
最后编辑时间：2025-11-15
文件描述：用户注册页面

*/
session_start();
require_once(__DIR__ . '/../config/config.php');

// 检查是否已登录
if (isset($_SESSION['user_id'])) {
    header('Location: index');
    exit;
}

// 获取网站配置
$settings = [];
try {
    $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('获取配置失败: ' . $e->getMessage());
}

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$assetPrefix = rtrim($scriptDir, '/');
$assetPrefix = $assetPrefix === '' ? '' : $assetPrefix;

// 检查注册功能是否开启
if (!($settings['register'] ?? 1)) {
    die('注册功能已关闭');
}

$error = '';
$success = '';
$mail_verified = $settings['reg_mail'] ?? 0;

// 处理注册
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $password_confirm = trim($_POST['password_confirm']);
    $mail = trim($_POST['mail']);
    $phone = trim($_POST['phone'] ?? '');
    $verify_code = trim($_POST['verify_code'] ?? '');
    
    // 基础验证
    if (empty($name) || empty($username) || empty($password) || empty($mail)) {
        $error = '请填写所有必填字段';
    } elseif ($password !== $password_confirm) {
        $error = '两次输入的密码不一致';
    } elseif (strlen($password) < 6) {
        $error = '密码长度至少6位';
    } elseif (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        $error = '邮箱格式不正确';
    } else {
        try {
            // 检查用户名和邮箱是否已存在
            $stmt = $db->prepare("SELECT id FROM user WHERE username = ? OR mail = ?");
            $stmt->execute([$username, $mail]);
            if ($stmt->fetch()) {
                $error = '用户名或邮箱已存在';
            } else {
                // 如果需要邮箱验证
                if ($mail_verified) {
                    if (empty($verify_code)) {
                        $error = '请输入验证码';
                    } else {
                        // 验证验证码
                        $stmt = $db->prepare("SELECT * FROM mail_verify_code WHERE mail = ? AND code = ? AND type = 0 AND status = 0 AND expire_time > NOW()");
                        $stmt->execute([$mail, $verify_code]);
                        $code_record = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$code_record) {
                            $error = '验证码错误或已过期';
                        } else {
                            // 标记验证码为已使用
                            $stmt = $db->prepare("UPDATE mail_verify_code SET status = 1 WHERE id = ?");
                            $stmt->execute([$code_record['id']]);
                            
                            // 创建用户
                            createUser($db, $name, $username, $password, $mail, $phone, $settings);
                            $success = '注册成功！正在跳转...';
                        }
                    }
                } else {
                    // 不需要邮箱验证，直接创建用户
                    createUser($db, $name, $username, $password, $mail, $phone, $settings);
                    $success = '注册成功！正在跳转...';
                }
            }
        } catch (PDOException $e) {
            $error = '注册失败: ' . $e->getMessage();
        }
    }
}

// 创建用户函数
function createUser($db, $name, $username, $password, $mail, $phone, $settings) {
    $mail_verified = $settings['reg_mail'] ?? 0 ? 1 : 0;
    $reg_points = $settings['reg_points'] ?? 100;
    
    $stmt = $db->prepare("INSERT INTO user (name, username, password, mail, phone, points, mail_verified) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $name,
        $username,
        md5($password),
        $mail,
        $phone,
        $reg_points,
        $mail_verified
    ]);
    
    // 自动登录
    $user_id = $db->lastInsertId();
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['name'] = $name;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 - <?php echo htmlspecialchars($settings['title'] ?? 'AI聊天系统'); ?></title>
    <link rel="icon" type="image/x-icon" href="../static/images/favicon.ico">
    <script>
        (function() {
            try {
                const stored = window.localStorage.getItem('auth-theme');
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.setAttribute('data-theme', stored || (prefersDark ? 'dark' : 'light'));
            } catch (error) {
                document.documentElement.setAttribute('data-theme', 'light');
        }
        })();
    </script>
    <link rel="stylesheet" href="<?php echo $assetPrefix; ?>/assets/css/auth-theme.css">
</head>
<body class="auth-page">
    <div class="auth-shell">
        <main class="auth-card" aria-labelledby="authTitle">
            <div class="auth-card__toggle">
                <label class="switch" data-theme-toggle>
                    <input type="checkbox" id="toggle" />
                    <span class="slider">
                        <div class="moons-hole">
                            <div class="moon-hole"></div>
                            <div class="moon-hole"></div>
                            <div class="moon-hole"></div>
                        </div>
                        <div class="clouds">
                            <div class="cloud"></div>
                            <div class="cloud"></div>
                            <div class="cloud"></div>
                            <div class="cloud"></div>
                            <div class="cloud"></div>
                            <div class="cloud"></div>
                            <div class="cloud"></div>
                        </div>
                        <div class="stars">
                            <svg class="star" viewBox="0 0 20 20">
                                <path d="M 0 10 C 10 10,10 10 ,0 10 C 10 10 , 10 10 , 10 20 C 10 10 , 10 10 , 20 10 C 10 10 , 10 10 , 10 0 C 10 10,10 10 ,0 10 Z"></path>
                            </svg>
                            <svg class="star" viewBox="0 0 20 20">
                                <path d="M 0 10 C 10 10,10 10 ,0 10 C 10 10 , 10 10 , 10 20 C 10 10 , 10 10 , 20 10 C 10 10 , 10 10 , 10 0 C 10 10,10 10 ,0 10 Z"></path>
                            </svg>
                            <svg class="star" viewBox="0 0 20 20">
                                <path d="M 0 10 C 10 10,10 10 ,0 10 C 10 10 , 10 10 , 10 20 C 10 10 , 10 10 , 20 10 C 10 10 , 10 10 , 10 0 C 10 10,10 10 ,0 10 Z"></path>
                            </svg>
                            <svg class="star" viewBox="0 0 20 20">
                                <path d="M 0 10 C 10 10,10 10 ,0 10 C 10 10 , 10 10 , 10 20 C 10 10 , 10 10 , 20 10 C 10 10 , 10 10 , 10 0 C 10 10,10 10 ,0 10 Z"></path>
                            </svg>
                            <svg class="star" viewBox="0 0 20 20">
                                <path d="M 0 10 C 10 10,10 10 ,0 10 C 10 10 , 10 10 , 10 20 C 10 10 , 10 10 , 20 10 C 10 10 , 10 10 , 10 0 C 10 10,10 10 ,0 10 Z"></path>
                            </svg>
                        </div>
                    </span>
                </label>
            </div>

            <header class="auth-headline">
                <div class="auth-headline__logo">
                    <img src="../static/images/logo.png"
                        alt="<?php echo htmlspecialchars($settings['title'] ?? 'AI聊天系统'); ?> Logo"
                        loading="lazy"
                        onerror="this.style.display='none'">
                </div>
                <h1 class="auth-title" id="authTitle">注册账号</h1>
                <p class="auth-subtitle">加入<?php echo htmlspecialchars($settings['title'] ?? 'AI聊天系统'); ?>，开启智能对话体验</p>
            <?php if ($reg_points = $settings['reg_points'] ?? 0): ?>
                    <span class="auth-badge">注册即送 <?php echo (int) $reg_points; ?> 积分</span>
            <?php endif; ?>
            </header>

        <?php if ($error): ?>
                <div class="auth-alert auth-alert--error" role="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
                <div class="auth-alert auth-alert--success" role="status"><?php echo htmlspecialchars($success); ?></div>
            <script>
                setTimeout(function() {
                    window.location.href = 'index';
                }, 1500);
            </script>
        <?php endif; ?>

            <form method="POST" action="" id="registerForm" class="auth-form" novalidate>
                <div class="inputGroup">
                    <input type="text" id="name" name="name" required autocomplete="nickname" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" <?php echo !empty($_POST['name']) ? 'class="has-value"' : ''; ?>>
                    <label for="name">昵称</label>
                </div>
            
                <div class="inputGroup">
                    <input type="text" id="username" name="username" required autocomplete="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" <?php echo !empty($_POST['username']) ? 'class="has-value"' : ''; ?>>
                    <label for="username">用户名</label>
                </div>
            
                <div class="inputGroup">
                    <input type="email" id="mail" name="mail" required autocomplete="email" value="<?php echo htmlspecialchars($_POST['mail'] ?? ''); ?>" <?php echo !empty($_POST['mail']) ? 'class="has-value"' : ''; ?>>
                    <label for="mail">邮箱</label>
                </div>
            
            <?php if ($mail_verified): ?>
                    <div class="auth-field">
                        <div class="auth-inline-group auth-inline-group--compact">
                            <div class="inputGroup" style="flex: 1;">
                                <input type="text" id="verify_code" name="verify_code" required maxlength="6" inputmode="numeric" autocomplete="one-time-code" value="<?php echo htmlspecialchars($_POST['verify_code'] ?? ''); ?>" <?php echo !empty($_POST['verify_code']) ? 'class="has-value"' : ''; ?>>
                                <label for="verify_code">邮箱验证码</label>
                            </div>
                            <button type="button" id="sendCodeBtn" class="auth-button auth-button--muted">发送验证码</button>
                </div>
                        <p class="auth-helper">验证码将发送到您的邮箱，30分钟内有效</p>
            </div>
            <?php endif; ?>
            
                <div class="inputGroup">
                    <input type="tel" id="phone" name="phone" autocomplete="tel" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" <?php echo !empty($_POST['phone']) ? 'class="has-value"' : ''; ?>>
                    <label for="phone">手机号（选填）</label>
                </div>
            
                <div class="inputGroup">
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                    <label for="password">密码</label>
                </div>
            
                <div class="inputGroup">
                    <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
                    <label for="password_confirm">确认密码</label>
                </div>
            
                <button type="submit" name="register" class="auth-button">注册账号</button>
        </form>

            <nav class="auth-links" aria-label="注册页面快捷链接">
            <a href="login">已有账号？立即登录</a>
            <a href="index">返回首页</a>
            </nav>
        </main>
    </div>

    <?php if ($mail_verified): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sendCodeBtn = document.getElementById('sendCodeBtn');
            const mailInput = document.getElementById('mail');
            const verifyCodeInput = document.getElementById('verify_code');
            
            // 验证邮箱格式
            function validateEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
            
            // 发送验证码
            sendCodeBtn.addEventListener('click', function() {
                const mail = mailInput.value.trim();
                
                if (!mail) {
                    alert('请输入邮箱地址');
                    mailInput.focus();
                    return;
                }
                
                if (!validateEmail(mail)) {
                    alert('请输入有效的邮箱地址');
                    mailInput.focus();
                    return;
                }
                
                // 禁用按钮并开始倒计时
                let countdown = 60;
                sendCodeBtn.disabled = true;
                sendCodeBtn.textContent = `发送中...`;
                
                // 发送AJAX请求
                const formData = new FormData();
                formData.append('mail', mail);
                formData.append('type', '0'); // 0表示注册验证
                
                fetch('./mail/send_verify_code.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        
                        // 开始倒计时
                        const timer = setInterval(() => {
                            countdown--;
                            sendCodeBtn.textContent = `重新发送(${countdown}s)`;
                            
                            if (countdown <= 0) {
                                clearInterval(timer);
                                sendCodeBtn.disabled = false;
                                sendCodeBtn.textContent = '发送验证码';
                            }
                        }, 1000);
                    } else {
                        alert(data.message);
                        sendCodeBtn.disabled = false;
                        sendCodeBtn.textContent = '发送验证码';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('网络错误，请稍后重试');
                    sendCodeBtn.disabled = false;
                    sendCodeBtn.textContent = '发送验证码';
                });
            });
            
            // 邮箱输入框回车时触发发送验证码
            mailInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendCodeBtn.click();
                }
            });
            
            // 验证码输入框回车时触发表单提交
            verifyCodeInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    document.getElementById('registerForm').dispatchEvent(new Event('submit'));
                }
            });
        });
    </script>
    <?php endif; ?>

    <script src="<?php echo $assetPrefix; ?>/assets/js/auth-theme.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const inputs = document.querySelectorAll('.inputGroup input');
            
            // 初始化输入框标签状态
            inputs.forEach(input => {
                if (input.value) {
                    input.classList.add('has-value');
                }
                input.addEventListener('input', function() {
                    // 移除错误状态
                    this.classList.remove('error');
                    if (this.value) {
                        this.classList.add('has-value');
                    } else {
                        this.classList.remove('has-value');
                    }
                });
            });
            
            // 表单提交验证
            if (form) {
                form.addEventListener('submit', function(e) {
                    let isValid = true;
                    
                    inputs.forEach(input => {
                        // 检查必填字段
                        if (input.hasAttribute('required') && !input.value.trim()) {
                            input.classList.add('error');
                            isValid = false;
                        } else {
                            input.classList.remove('error');
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>


