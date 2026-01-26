<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-7
最后编辑时间：2025-11-15
文件描述：用户登录页面

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

// 处理登录
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        try {
            $stmt = $db->prepare("SELECT * FROM user WHERE (username = ? OR mail = ?) AND status = 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && md5($password) === $user['password']) {
                // 登录成功
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['name'] = $user['name'];
                
                // 设置成功消息，延迟跳转
                $success = '登录成功！正在跳转...';
            } else {
                $error = '用户名或密码错误';
            }
        } catch (PDOException $e) {
            $error = '登录失败: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - <?php echo htmlspecialchars($settings['title'] ?? 'AI聊天系统'); ?></title>
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
        <?php if ($success): ?>
            <div class="auth-alert auth-alert--success" role="status" style="margin-bottom: 1rem;"><?php echo htmlspecialchars($success); ?></div>
            <script>
                setTimeout(function() {
                    window.location.href = 'index';
                }, 1000);
            </script>
        <?php endif; ?>
        
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
                <h1 class="auth-title" id="authTitle"><?php echo htmlspecialchars($settings['title'] ?? 'AI聊天系统'); ?></h1>
                <p class="auth-subtitle"><?php echo htmlspecialchars($settings['description'] ?? '欢迎登录'); ?></p>
            </header>

        <?php if ($error): ?>
                <div class="auth-alert auth-alert--error" role="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

            <form method="POST" action="" class="auth-form" novalidate>
                <div class="inputGroup">
                    <input type="text" id="username" name="username" required autocomplete="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" <?php echo !empty($_POST['username']) ? 'class="has-value"' : ''; ?>>
                    <label for="username">用户名或邮箱</label>
                </div>
            
                <div class="inputGroup">
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                    <label for="password">密码</label>
                </div>
            
                <button type="submit" name="login" class="auth-button">登录</button>
        </form>

            <nav class="auth-links" aria-label="登录页面快捷链接">
            <a href="forgot_password">忘记密码？</a>
            <?php if ($settings['register'] ?? 1): ?>
                <a href="register">注册账号</a>
            <?php endif; ?>
            <a href="index">返回首页</a>
            </nav>
        </main>
    </div>

    <script src="<?php echo $assetPrefix; ?>/assets/js/auth-theme.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.auth-form');
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

