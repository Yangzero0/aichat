<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-7
最后编辑时间：2025-11-15
文件描述：重置密码页面

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

$error = '';
$success = '';
$step = 1; // 1: 输入邮箱和验证码, 2: 设置新密码

// 处理第一步：验证邮箱和验证码
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_email'])) {
    $mail = trim($_POST['mail']);
    $verify_code = trim($_POST['verify_code']);
    
    if (empty($mail) || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
        $error = '请输入有效的邮箱地址';
    } elseif (empty($verify_code)) {
        $error = '请输入验证码';
    } else {
        try {
            // 验证验证码
            $stmt = $db->prepare("SELECT * FROM mail_verify_code WHERE mail = ? AND code = ? AND type = 1 AND status = 0 AND expire_time > NOW()");
            $stmt->execute([$mail, $verify_code]);
            $code_record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$code_record) {
                $error = '验证码错误或已过期';
            } else {
                // 验证邮箱是否存在且用户状态正常
                $stmt = $db->prepare("SELECT id FROM user WHERE mail = ? AND status = 1");
                $stmt->execute([$mail]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    $error = '该邮箱未注册或账号已被禁用';
                } else {
                    // 验证成功，进入第二步
                    $step = 2;
                    $_SESSION['reset_mail'] = $mail;
                    $_SESSION['reset_code_id'] = $code_record['id'];
                    $_SESSION['reset_user_id'] = $user['id'];
                    $success = '验证成功，请设置新密码';
                }
            }
        } catch (PDOException $e) {
            $error = '验证失败: ' . $e->getMessage();
        }
    }
}

// 处理第二步：设置新密码
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $password = trim($_POST['password']);
    $password_confirm = trim($_POST['password_confirm']);
    
    if (empty($password) || empty($password_confirm)) {
        $error = '请输入新密码和确认密码';
    } elseif ($password !== $password_confirm) {
        $error = '两次输入的密码不一致';
    } elseif (strlen($password) < 6) {
        $error = '密码长度至少6位';
    } elseif (!isset($_SESSION['reset_mail']) || !isset($_SESSION['reset_code_id']) || !isset($_SESSION['reset_user_id'])) {
        $error = '会话已过期，请重新验证邮箱';
        $step = 1;
    } else {
        try {
            // 更新用户密码
            $stmt = $db->prepare("UPDATE user SET password = ? WHERE id = ? AND mail = ? AND status = 1");
            $stmt->execute([md5($password), $_SESSION['reset_user_id'], $_SESSION['reset_mail']]);
            
            if ($stmt->rowCount() > 0) {
                // 标记验证码为已使用
                $stmt = $db->prepare("UPDATE mail_verify_code SET status = 1 WHERE id = ?");
                $stmt->execute([$_SESSION['reset_code_id']]);
                
                // 清除会话
                unset($_SESSION['reset_mail']);
                unset($_SESSION['reset_code_id']);
                unset($_SESSION['reset_user_id']);
                
                $success = '密码重置成功！正在跳转到登录页面...';
                
                // 延迟跳转
                echo '<script>setTimeout(function() { window.location.href = "login"; }, 2000);</script>';
            } else {
                $error = '密码重置失败，请检查邮箱是否正确或联系管理员';
            }
        } catch (PDOException $e) {
            $error = '密码重置失败: ' . $e->getMessage();
        }
    }
}

// 如果直接访问第二步但会话不存在，则返回第一步
if ($step === 2 && (!isset($_SESSION['reset_mail']) || !isset($_SESSION['reset_code_id']) || !isset($_SESSION['reset_user_id']))) {
    $step = 1;
    $error = '会话已过期，请重新验证邮箱';
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>重置密码 - <?php echo htmlspecialchars($settings['title'] ?? 'AI聊天系统'); ?></title>
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
                <h1 class="auth-title" id="authTitle">重置密码</h1>
                <p class="auth-subtitle">找回您的<?php echo htmlspecialchars($settings['title'] ?? 'AI聊天系统'); ?>账号密码</p>
            </header>

            <div class="auth-stepper" aria-hidden="true">
                <div class="auth-step <?php echo $step == 1 ? 'is-active' : 'is-complete'; ?>">
                    <span class="auth-step__index">1</span>
                    <span class="auth-step__label">验证身份</span>
                </div>
                <div class="auth-step <?php echo $step == 2 ? 'is-active' : ''; ?>">
                    <span class="auth-step__index">2</span>
                    <span class="auth-step__label">设置新密码</span>
            </div>
        </div>

        <?php if ($error): ?>
                <div class="auth-alert auth-alert--error" role="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
                <div class="auth-alert auth-alert--success" role="status"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
                <form method="POST" action="" id="verifyEmailForm" class="auth-form" novalidate>
                    <div class="inputGroup">
                        <input type="email" id="mail" name="mail" required autocomplete="email" value="<?php echo htmlspecialchars($_POST['mail'] ?? ''); ?>" <?php echo !empty($_POST['mail']) ? 'class="has-value"' : ''; ?>>
                        <label for="mail">邮箱地址</label>
                    </div>
            
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
            
                    <button type="submit" name="verify_email" class="auth-button">验证并继续</button>
        </form>
        <?php endif; ?>

        <?php if ($step == 2): ?>
                <form method="POST" action="" id="resetPasswordForm" class="auth-form" novalidate>
                    <div class="inputGroup">
                        <input type="password" id="password" name="password" required autocomplete="new-password">
                        <label for="password">新密码</label>
                    </div>
            
                    <div class="inputGroup">
                        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
                        <label for="password_confirm">确认新密码</label>
                    </div>
            
                    <div class="auth-inline-actions">
                        <button type="button" onclick="window.location.href='forgot_password'" class="auth-button auth-button--muted">上一步</button>
                        <button type="submit" name="reset_password" class="auth-button">重置密码</button>
            </div>
        </form>
        <?php endif; ?>

            <nav class="auth-links" aria-label="找回密码页面快捷链接">
            <a href="login">返回登录</a>
            <?php if ($settings['register'] ?? 1): ?>
                <a href="register">注册账号</a>
            <?php endif; ?>
            <a href="index">返回首页</a>
            </nav>
        </main>
    </div>

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
            if (sendCodeBtn) {
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
                    formData.append('type', '1'); // 1表示密码重置
                    
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
                if (verifyCodeInput) {
                    verifyCodeInput.addEventListener('keypress', function(e) {
                        if (e.key === 'Enter') {
                            document.getElementById('verifyEmailForm').dispatchEvent(new Event('submit'));
                        }
                    });
                }
            }
            
            // 密码确认实时验证
            const passwordInput = document.getElementById('password');
            const passwordConfirmInput = document.getElementById('password_confirm');
            
            if (passwordInput && passwordConfirmInput) {
                function validatePasswordMatch() {
                    if (passwordInput.value && passwordConfirmInput.value) {
                        if (passwordInput.value !== passwordConfirmInput.value) {
                            passwordConfirmInput.style.borderColor = '#dc3545';
                        } else {
                            passwordConfirmInput.style.borderColor = '#28a745';
                        }
                    } else {
                        passwordConfirmInput.style.borderColor = '#e1e5e9';
                    }
                }
                
                passwordInput.addEventListener('input', validatePasswordMatch);
                passwordConfirmInput.addEventListener('input', validatePasswordMatch);
            }
        });
    </script>
    <script src="<?php echo $assetPrefix; ?>/assets/js/auth-theme.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const verifyForm = document.getElementById('verifyEmailForm');
            const resetForm = document.getElementById('resetPasswordForm');
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
            
            // 验证邮箱表单提交
            if (verifyForm) {
                verifyForm.addEventListener('submit', function(e) {
                    let isValid = true;
                    const formInputs = verifyForm.querySelectorAll('.inputGroup input');
                    
                    formInputs.forEach(input => {
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
            
            // 重置密码表单提交
            if (resetForm) {
                resetForm.addEventListener('submit', function(e) {
                    let isValid = true;
                    const formInputs = resetForm.querySelectorAll('.inputGroup input');
                    
                    formInputs.forEach(input => {
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


