<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-4
最后编辑时间：2025-10-28
api文件描述：用于傻瓜式搭建数据库和对接数据库
*/
// 错误报告设置
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 定义常量
define('INSTALL_DIR', dirname(__FILE__));
define('CONFIG_DIR', INSTALL_DIR . '/../config');
define('SQL_FILE', INSTALL_DIR . '/install.sql');

// 安装状态
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        // 验证数据库连接
        $db_host = $_POST['db_host'] ?? 'localhost';
        $db_port = $_POST['db_port'] ?? '3306';
        $db_name = $_POST['db_name'] ?? 'ai_chat_system';
        $db_user = $_POST['db_user'] ?? 'root';
        $db_pass = $_POST['db_pass'] ?? '';
        
        try {
            // 首先尝试连接MySQL服务器（不指定数据库）
            $dsn = "mysql:host=$db_host;port=$db_port;charset=utf8";
            $pdo = new PDO($dsn, $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 简单测试查询，不查询系统表
            $pdo->query("SELECT 1")->fetch(PDO::FETCH_ASSOC);
            
            // 尝试创建数据库（如果不存在）
            try {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci");
                $success = "数据库连接成功！";
            } catch (PDOException $e) {
                // 如果创建数据库失败，检查数据库是否已存在
                try {
                    $dsn_with_db = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8";
                    $pdo_with_db = new PDO($dsn_with_db, $db_user, $db_pass);
                    $pdo_with_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo_with_db->query("SELECT 1")->fetch(PDO::FETCH_ASSOC);
                    $success = "数据库连接成功！（使用现有数据库）";
                } catch (PDOException $e2) {
                    $error = "无法创建数据库且无法连接指定数据库。请手动创建数据库 '$db_name' 并确保用户 '$db_user' 有访问权限。错误：" . $e2->getMessage();
                    $pdo_with_db = null;
                }
            }
            
            // 如果连接成功，保存配置
            if (empty($error)) {
                session_start();
                $_SESSION['db_config'] = [
                    'host' => $db_host,
                    'port' => $db_port,
                    'name' => $db_name,
                    'user' => $db_user,
                    'pass' => $db_pass
                ];
                
                // 跳转到下一步
                header('Location: ?step=2');
                exit;
            }
            
        } catch (PDOException $e) {
            $error = "数据库连接失败: " . $e->getMessage() . "<br>请检查数据库主机、端口、用户名和密码是否正确。";
        }
        
    } elseif ($step === 2) {
        // 验证网站配置和管理员信息
        session_start();
        $db_config = $_SESSION['db_config'] ?? null;
        
        if (!$db_config) {
            $error = "数据库配置丢失，请返回第一步重新配置";
        } else {
            $site_title = $_POST['site_title'] ?? 'AI角色扮演平台';
            $site_description = $_POST['site_description'] ?? '基于人工智能的在线角色扮演聊天平台';
            $site_url = $_POST['site_url'] ?? get_current_url();
            $admin_user = $_POST['admin_user'] ?? 'admin';
            $admin_pass = $_POST['admin_pass'] ?? '';
            $admin_email = $_POST['admin_email'] ?? '';
            
            if (empty($admin_user) || empty($admin_pass) || empty($admin_email)) {
                $error = "请填写完整的管理员信息";
            } else {
                try {
                    // 连接数据库
                    $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['name']};charset=utf8";
                    $pdo = new PDO($dsn, $db_config['user'], $db_config['pass']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // 执行SQL文件
                    if (!file_exists(SQL_FILE)) {
                        $error = "SQL文件不存在: " . SQL_FILE;
                    } else {
                        $sql = file_get_contents(SQL_FILE);
                        
                        // 分割SQL语句并逐条执行
                        $sql_commands = array_filter(array_map('trim', explode(';', $sql)));
                        
                        foreach ($sql_commands as $command) {
                            if (!empty($command) && strlen($command) > 10) { // 过滤空命令和短命令
                                try {
                                    $pdo->exec($command);
                                } catch (PDOException $e) {
                                    // 忽略表已存在的错误，继续执行
                                    if (strpos($e->getMessage(), 'already exists') === false) {
                                        // 如果是其他错误，记录但继续执行（可能是注释或其他非SQL语句）
                                        error_log("SQL执行警告: " . $e->getMessage());
                                    }
                                }
                            }
                        }
                        
                        // 更新网站配置
                        try {
                            $stmt = $pdo->prepare("UPDATE web_setting SET title = ?, description = ?, url = ? WHERE id = 1");
                            $stmt->execute([$site_title, $site_description, $site_url]);
                        } catch (PDOException $e) {
                            // 如果web_setting表不存在或更新失败，忽略
                            error_log("更新网站配置失败: " . $e->getMessage());
                        }
                        
                        // 更新或插入管理员账户
                        $admin_pass_md5 = md5($admin_pass);
                        try {
                            // 先尝试更新
                            $stmt = $pdo->prepare("UPDATE admin SET password = ?, mail = ? WHERE user = ?");
                            $stmt->execute([$admin_pass_md5, $admin_email, $admin_user]);
                            
                            // 如果admin用户不存在，则插入
                            if ($stmt->rowCount() === 0) {
                                $stmt = $pdo->prepare("INSERT INTO admin (user, password, level, mail) VALUES (?, ?, 0, ?)");
                                $stmt->execute([$admin_user, $admin_pass_md5, $admin_email]);
                            }
                        } catch (PDOException $e) {
                            // 如果admin表不存在，先创建表
                            error_log("管理员操作失败，尝试创建表: " . $e->getMessage());
                        }
                        
                        // 创建配置文件目录
                        if (!is_dir(CONFIG_DIR)) {
                            if (!mkdir(CONFIG_DIR, 0755, true)) {
                                throw new Exception("无法创建配置目录: " . CONFIG_DIR);
                            }
                        }
                        
                        // 生成配置文件
                        $config_content = generate_config_file($db_config);
                        $config_file = CONFIG_DIR . '/config.php';
                        
                        if (file_put_contents($config_file, $config_content)) {
                            // 创建安装锁文件
                            file_put_contents(INSTALL_DIR . '/install.lock', '安装完成于: ' . date('Y-m-d H:i:s'));
                            
                            // 清除session
                            session_destroy();
                            
                            // 跳转到完成页面
                            header('Location: ?step=3');
                            exit;
                        } else {
                            $error = "配置文件创建失败，请检查config目录权限";
                        }
                    }
                    
                } catch (PDOException $e) {
                    $error = "数据库操作失败: " . $e->getMessage() . "<br>请确保数据库用户有足够的权限执行SQL语句。";
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }
    }
}

// 获取当前URL
function get_current_url() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    // 移除install目录
    $script_dir = str_replace('/install', '', $script_dir);
    $script_dir = rtrim($script_dir, '/');
    return $protocol . '://' . $host . $script_dir;
}
// 生成配置文件内容
function generate_config_file($db_config) {
    return "<?php
/**
 * AI角色扮演平台 - 数据库配置文件
 * 自动生成于: " . date('Y-m-d H:i:s') . "
 */
 
// 显示所有错误（仅用于开发环境）
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// 设置时区为北京时间
date_default_timezone_set('Asia/Shanghai');

// 数据库配置
define('DB_HOST', '{$db_config['host']}');
define('DB_PORT', '{$db_config['port']}');
define('DB_NAME', '{$db_config['name']}');
define('DB_USER', '{$db_config['user']}');
define('DB_PASS', '{$db_config['pass']}');
define('DB_CHARSET', 'utf8mb4'); // 修改为 utf8mb4 支持emoji

// 创建数据库连接
function getDBConnection() {
    try {
        \$dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        \$pdo = new PDO(\$dsn, DB_USER, DB_PASS);
        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // 设置MySQL会话时区为北京时间
        \$pdo->exec(\"SET time_zone = '+08:00'\");
        
        // 设置连接字符集为utf8mb4
        \$pdo->exec(\"SET NAMES 'utf8mb4'\");
        
        return \$pdo;
    } catch (PDOException \$e) {
        die('数据库连接失败: ' . \$e->getMessage());
    }
}

// 全局数据库连接
\$db = getDBConnection();
?>";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI角色扮演平台 - 安装向导</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
        }
        .header p {
            opacity: 0.8;
        }
        .content {
            padding: 40px;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 40px;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            width: 120px;
        }
        .step:not(:last-child):after {
            content: '';
            position: absolute;
            top: 20px;
            left: 60px;
            width: 120px;
            height: 2px;
            background: #e0e0e0;
            z-index: 1;
        }
        .step.active:not(:last-child):after {
            background: #3498db;
        }
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #777;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 10px;
            z-index: 2;
        }
        .step.active .step-number {
            background: #3498db;
            color: white;
        }
        .step.completed .step-number {
            background: #2ecc71;
            color: white;
        }
        .step-label {
            font-size: 0.9rem;
            color: #777;
        }
        .step.active .step-label {
            color: #3498db;
            font-weight: bold;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border 0.3s;
        }
        .form-control:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        .btn {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
        }
        .btn:hover {
            background: #2980b9;
        }
        .btn-next {
            float: right;
        }
        .btn-prev {
            background: #95a5a6;
        }
        .btn-prev:hover {
            background: #7f8c8d;
        }
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #ffeaea;
            color: #c0392b;
            border: 1px solid #ffcdd2;
        }
        .alert-success {
            background: #e8f5e9;
            color: #27ae60;
            border: 1px solid #c8e6c9;
        }
        .alert-info {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }
        .alert-warning {
            background: #fff8e1;
            color: #ff8f00;
            border: 1px solid #ffecb3;
        }
        .footer {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 0.9rem;
        }
        .requirements {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .requirements h3 {
            margin-bottom: 15px;
            color: #2c3e50;
        }
        .requirement-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .requirement-status {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            color: white;
        }
        .status-ok {
            background: #2ecc71;
        }
        .status-error {
            background: #e74c3c;
        }
        .completion-message {
            text-align: center;
            padding: 30px 0;
        }
        .completion-message h2 {
            color: #2ecc71;
            margin-bottom: 20px;
        }
        .completion-message p {
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        .help-text {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }
        .manual-setup {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
        }
        .manual-setup h4 {
            color: #856404;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>AI角色扮演平台</h1>
            <p>数据库安装向导</p>
        </div>
        
        <div class="content">
            <!-- 步骤指示器 -->
            <div class="step-indicator">
                <div class="step <?php echo $step >= 1 ? 'active' : ''; ?> <?php echo $step > 1 ? 'completed' : ''; ?>">
                    <div class="step-number">1</div>
                    <div class="step-label">数据库配置</div>
                </div>
                <div class="step <?php echo $step >= 2 ? 'active' : ''; ?> <?php echo $step > 2 ? 'completed' : ''; ?>">
                    <div class="step-number">2</div>
                    <div class="step-label">网站配置</div>
                </div>
                <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">
                    <div class="step-number">3</div>
                    <div class="step-label">完成安装</div>
                </div>
            </div>
            
            <!-- 错误/成功消息 -->
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <!-- 步骤1: 数据库配置 -->
            <?php if ($step === 1): ?>
                <div class="requirements">
                    <h3>系统环境检查</h3>
                    <?php
                    // 检查PHP版本
                    $php_version = phpversion();
                    $php_ok = version_compare($php_version, '7.2.0', '>=');
                    ?>
                    <div class="requirement-item">
                        <div class="requirement-status <?php echo $php_ok ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $php_ok ? '✓' : '✗'; ?>
                        </div>
                        <div>PHP版本 ≥ 7.2.0 (当前: <?php echo $php_version; ?>)</div>
                    </div>
                    
                    <?php
                    // 检查PDO扩展
                    $pdo_ok = extension_loaded('pdo_mysql');
                    ?>
                    <div class="requirement-item">
                        <div class="requirement-status <?php echo $pdo_ok ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $pdo_ok ? '✓' : '✗'; ?>
                        </div>
                        <div>PDO MySQL扩展</div>
                    </div>
                    
                    <?php
                    // 检查SQL文件
                    $sql_file_ok = file_exists(SQL_FILE);
                    ?>
                    <div class="requirement-item">
                        <div class="requirement-status <?php echo $sql_file_ok ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $sql_file_ok ? '✓' : '✗'; ?>
                        </div>
                        <div>SQL安装文件 (<?php echo SQL_FILE; ?>)</div>
                    </div>
                    
                    <?php
                    // 检查config目录可写
                    $config_dir_writable = is_writable(dirname(CONFIG_DIR)) || (!is_dir(CONFIG_DIR) && is_writable(dirname(CONFIG_DIR)));
                    ?>
                    <div class="requirement-item">
                        <div class="requirement-status <?php echo $config_dir_writable ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $config_dir_writable ? '✓' : '✗'; ?>
                        </div>
                        <div>Config目录可写 (<?php echo CONFIG_DIR; ?>)</div>
                    </div>
                </div>
                
                <?php if ($php_ok && $pdo_ok && $sql_file_ok && $config_dir_writable): ?>
                    <div class="alert alert-info">
                        <strong>数据库配置说明：</strong><br>
                        1. 系统会尝试自动创建数据库，如果失败请手动创建<br>
                        2. 确保数据库用户有创建数据库和表的权限<br>
                        3. 如果遇到权限问题，请参考下方的手动设置指南
                    </div>
                    
                    <form method="post" action="?step=1">
                        <h3>数据库配置</h3>
                        <div class="form-group">
                            <label for="db_host">数据库主机</label>
                            <input type="text" id="db_host" name="db_host" class="form-control" value="localhost" required>
                            <div class="help-text">通常是 localhost 或 127.0.0.1</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_port">数据库端口</label>
                            <input type="text" id="db_port" name="db_port" class="form-control" value="3306" required>
                            <div class="help-text">MySQL默认端口是3306</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_name">数据库名称</label>
                            <input type="text" id="db_name" name="db_name" class="form-control" value="ai_chat_system" required>
                            <div class="help-text">如果数据库不存在，系统会尝试创建</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_user">数据库用户名</label>
                            <input type="text" id="db_user" name="db_user" class="form-control" value="root" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="db_pass">数据库密码</label>
                            <input type="password" id="db_pass" name="db_pass" class="form-control">
                            <div class="help-text">留空表示无密码</div>
                        </div>
                        
                        <div class="clearfix">
                            <button type="submit" class="btn btn-next">测试连接并继续</button>
                        </div>
                    </form>
                    
                    <div class="manual-setup">
                        <h4>手动设置数据库指南</h4>
                        <p>如果自动安装失败，请按以下步骤手动设置：</p>
                        <ol>
                            <li>使用phpMyAdmin或MySQL命令行登录</li>
                            <li>创建数据库: <code>CREATE DATABASE ai_chat_system DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;</code></li>
                            <li>创建用户并授权: 
                                <code>GRANT ALL PRIVILEGES ON ai_chat_system.* TO 'username'@'localhost' IDENTIFIED BY 'password';</code>
                            </li>
                            <li>刷新权限: <code>FLUSH PRIVILEGES;</code></li>
                            <li>然后返回此页面继续安装</li>
                        </ol>
                    </div>
                <?php else: ?>
                    <div class="alert alert-error">请解决以上环境问题后再继续安装</div>
                <?php endif; ?>
                
            <!-- 步骤2: 网站配置和管理员设置 -->
            <?php elseif ($step === 2): ?>
                <form method="post" action="?step=2">
                    <h3>网站配置</h3>
                    <div class="form-group">
                        <label for="site_title">网站标题</label>
                        <input type="text" id="site_title" name="site_title" class="form-control" value="AI角色扮演平台" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="site_description">网站描述</label>
                        <textarea id="site_description" name="site_description" class="form-control" rows="3">基于人工智能的在线角色扮演聊天平台</textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="site_url">网站地址</label>
                        <input type="url" id="site_url" name="site_url" class="form-control" value="<?php echo get_current_url(); ?>" required>
                        <div class="help-text">系统自动检测，如有错误请手动修改</div>
                    </div>
                    
                    <h3>管理员账户</h3>
                    <div class="form-group">
                        <label for="admin_user">管理员账号</label>
                        <input type="text" id="admin_user" name="admin_user" class="form-control" value="admin" required>
                        <div class="help-text">用于登录管理后台的账号</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_pass">管理员密码</label>
                        <input type="password" id="admin_pass" name="admin_pass" class="form-control" required>
                        <div class="help-text">请设置强密码以确保安全</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_email">管理员邮箱</label>
                        <input type="email" id="admin_email" name="admin_email" class="form-control" required>
                        <div class="help-text">用于接收系统通知和重置密码</div>
                    </div>
                    
                    <div class="clearfix">
                        <a href="?step=1" class="btn btn-prev">上一步</a>
                        <button type="submit" class="btn btn-next">开始安装</button>
                    </div>
                </form>
                
            <!-- 步骤3: 安装完成 -->
            <?php elseif ($step === 3): ?>
                <div class="completion-message">
                    <h2>安装完成！</h2>
                    <p>AI角色扮演平台已经成功安装并配置完成。</p>
                    <p>您现在可以开始使用系统了。</p>
                    
                    <div style="margin-top: 30px;">
                        <a href="../" class="btn">访问首页</a>
                        <a href="../admin/" class="btn" style="margin-left: 10px;">管理后台</a>
                    </div>
                    
                    <div style="margin-top: 40px; padding: 20px; background: #f8f9fa; border-radius: 5px; text-align: left;">
                        <h3>安全提醒：</h3>
                        <p>1. 请立即删除install目录以确保系统安全</p>
                        <p>2. 请修改默认管理员密码</p>
                        <p>3. 请定期备份数据库</p>
                        <p>4. 建议修改config/config.php文件的权限为644</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="footer">
            AI角色扮演平台 &copy; <?php echo date('Y'); ?> - 安装向导
        </div>
    </div>
</body>
</html>