<?php
session_start();

// 检查配置文件是否存在
$config_file = __DIR__ . '/config/config.php';
if (!file_exists($config_file)) {
    // 配置文件不存在，显示安装提示页面
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>数据库配置缺失 - AI角色扮演平台</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Microsoft YaHei', 'PingFang SC', 'Noto Sans SC', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .container {
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                max-width: 600px;
                width: 100%;
                padding: 50px 40px;
                text-align: center;
            }
            .icon {
                font-size: 80px;
                color: #ff6b6b;
                margin-bottom: 30px;
            }
            h1 {
                color: #2c3e50;
                font-size: 2rem;
                margin-bottom: 20px;
                font-weight: 600;
            }
            p {
                color: #6c757d;
                font-size: 1.1rem;
                line-height: 1.8;
                margin-bottom: 30px;
            }
            .btn {
                display: inline-block;
                background: #3498db;
                color: white;
                padding: 15px 40px;
                border-radius: 50px;
                text-decoration: none;
                font-size: 1.1rem;
                font-weight: 500;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
            }
            .btn:hover {
                background: #2980b9;
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
            }
            .info {
                background: #f8f9fa;
                border-left: 4px solid #3498db;
                padding: 20px;
                margin-top: 30px;
                text-align: left;
                border-radius: 4px;
            }
            .info h3 {
                color: #2c3e50;
                margin-bottom: 10px;
                font-size: 1.1rem;
            }
            .info ul {
                color: #6c757d;
                padding-left: 20px;
                line-height: 1.8;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">⚠️</div>
            <h1>数据库配置缺失</h1>
            <p>系统检测到数据库配置文件不存在，请先完成安装配置。</p>
            <a href="./install/" class="btn">前往安装页面</a>
            <div class="info">
                <h3>安装说明：</h3>
                <ul>
                    <li>点击上方按钮进入安装向导</li>
                    <li>按照提示完成数据库配置</li>
                    <li>填写网站基本信息和管理员账户</li>
                    <li>安装完成后即可正常使用系统</li>
                </ul>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

require_once($config_file);

/**
 * 辅助函数：安全输出HTML
 * @param mixed $value 要输出的值
 * @param string $default 默认值
 * @return string 转义后的HTML字符串
 */
function safe_output($value, $default = '') {
    return htmlspecialchars($value ?? $default, ENT_QUOTES, 'UTF-8');
}

/**
 * 辅助函数：格式化数字
 * @param mixed $value 要格式化的数值
 * @param int $decimals 小数位数
 * @return string 格式化后的数字字符串
 */
function format_number($value, $decimals = 0) {
    return number_format((float)($value ?? 0), $decimals);
}

// 初始化变量（使用默认值确保页面正常显示）
$settings = [
    'title' => 'AI角色扮演平台',
    'description' => '与各种精心设计的AI角色互动，体验前所未有的对话乐趣。开启您的次元之旅，与AI角色建立深度连接。',
    'logo' => '',
    'register' => 1,
    'smtp_from_email' => 'contact@example.com'
];
$popular_characters = [];
$top_rated_characters = [];
$stats = [
    'total_characters' => 0,
    'total_conversations' => 0,
    'avg_rating' => 5.0  // 默认评分，避免显示0
];

/**
 * 获取角色卡片数据（标准化数据格式）
 * @param array $character 角色数据数组
 * @return array 标准化后的角色数据
 */
function get_character_card_data($character) {
    return [
        'id' => (int)($character['id'] ?? 0),
        'name' => $character['name'] ?? '',
        'avatar' => $character['avatar'] ?? '',
        'category_name' => $character['category_name'] ?? '',
        'introduction' => $character['introduction'] ?? '暂无介绍',
        'avg_rating' => (float)($character['avg_rating'] ?? 0),
        'usage_count' => (int)($character['usage_count'] ?? 0)
    ];
}

/**
 * 渲染角色卡片HTML（减少代码重复）
 * @param array $character 角色数据
 * @param int $index 索引（用于动画延迟）
 * @return string 角色卡片的HTML字符串
 */
function render_character_card($character, $index = 0) {
    $char = get_character_card_data($character);
    $delay = $index * 0.15;
    $character_id = $char['id'];
    $character_name = safe_output($char['name']);
    $character_avatar = safe_output($char['avatar']);
    $category_name = safe_output($char['category_name']);
    $introduction = safe_output($char['introduction']);
    $avg_rating = format_number($char['avg_rating'], 1);
    $usage_count = $char['usage_count'];
    
    ob_start();
    ?>
    <div class="home-character-card" style="transition-delay: <?php echo $delay; ?>s;" onclick="window.location.href='./user/chat?character_id=<?php echo $character_id; ?>'">
        <div class="home-character-avatar-wrapper">
            <?php if (!empty($char['avatar'])): ?>
                <img src="<?php echo $character_avatar; ?>" alt="<?php echo $character_name; ?>" class="home-character-avatar" loading="lazy">
            <?php else: ?>
                <div style="display: flex; align-items: center; justify-content: center; height: 100%; background: var(--dark-gray);">
                    <i class="fas fa-user" style="font-size: 5rem; color: var(--text-muted);"></i>
                </div>
            <?php endif; ?>
        </div>
        <div class="home-character-info">
            <h3 class="home-character-name"><?php echo $character_name; ?></h3>
            <?php if (!empty($category_name)): ?>
                <span class="home-character-category"><?php echo $category_name; ?></span>
            <?php endif; ?>
            <p class="home-character-intro"><?php echo $introduction; ?></p>
            <div class="home-character-stats">
                <div class="home-stat-item home-character-rating">
                    <i class="fas fa-star"></i>
                    <span><?php echo $avg_rating; ?></span>
                </div>
                <div class="home-stat-item">
                    <i class="fas fa-comments"></i>
                    <span><?php echo $usage_count; ?> 次对话</span>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

try {
    // 获取网站配置
    $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // 优化：使用UNION合并查询，减少数据库访问次数
    // 获取最热门的6个角色和评分最高的6个角色
    $stmt = $db->prepare("
        (
            SELECT ac.*, cc.name as category_name, 'popular' as list_type
            FROM ai_character ac 
            LEFT JOIN character_category cc ON ac.category_id = cc.id 
            WHERE ac.status = 1 AND ac.is_public = 1 
            ORDER BY ac.usage_count DESC 
            LIMIT 6
        )
        UNION ALL
        (
            SELECT ac.*, cc.name as category_name, 'rated' as list_type
            FROM ai_character ac 
            LEFT JOIN character_category cc ON ac.category_id = cc.id 
            WHERE ac.status = 1 AND ac.is_public = 1 AND ac.avg_rating > 0 
            ORDER BY ac.avg_rating DESC, ac.usage_count DESC 
            LIMIT 6
        )
    ");
    $stmt->execute();
    $all_characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 分离热门角色和评分角色（优化：使用更高效的方式）
    $popular_characters = [];
    $top_rated_characters = [];
    $character_ids_seen = [];
    
    foreach ($all_characters as $character) {
        $char_id = (int)$character['id'];
        
        // 避免重复添加相同角色
        if (isset($character_ids_seen[$char_id])) {
            continue;
        }
        $character_ids_seen[$char_id] = true;
        
        if ($character['list_type'] === 'popular' && count($popular_characters) < 6) {
            unset($character['list_type']);
            $popular_characters[] = $character;
        } elseif ($character['list_type'] === 'rated' && count($top_rated_characters) < 6) {
            unset($character['list_type']);
            $top_rated_characters[] = $character;
        }
    }
    
    // 优化：使用SQL聚合函数计算统计数据，减少PHP计算
    $stats_stmt = $db->query("
        SELECT 
            COUNT(DISTINCT ac.id) as total_characters,
            COALESCE(SUM(ac.usage_count), 0) as total_conversations,
            COALESCE(AVG(CASE WHEN ac.avg_rating > 0 THEN ac.avg_rating END), 0) as avg_rating
        FROM ai_character ac
        WHERE ac.status = 1 AND ac.is_public = 1
    ");
    $stats_result = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats_result) {
        $stats['total_characters'] = (int)($stats_result['total_characters'] ?? 0);
        $stats['total_conversations'] = (int)($stats_result['total_conversations'] ?? 0);
        $stats['avg_rating'] = (float)($stats_result['avg_rating'] ?? 0);
    }
    
} catch (PDOException $e) {
    // 错误处理：记录错误但不影响页面显示，使用默认值
    error_log("数据库查询错误 [index.php]: " . $e->getMessage());
    // 确保关键变量有默认值
    if (empty($settings)) {
        $settings = [
            'title' => 'AI角色扮演平台',
            'description' => '与各种精心设计的AI角色互动，体验前所未有的对话乐趣。',
            'logo' => '',
            'register' => 1,
            'smtp_from_email' => 'contact@example.com'
        ];
    }
}

// 检查用户登录状态
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['name'] ?? '';

// 计算静态资源前缀（优化：缓存计算结果，避免重复计算）
static $assetPrefix = null;
if ($assetPrefix === null) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $assetPrefix = rtrim($scriptDir, '/') ?: '';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo safe_output($settings['title'] ?? 'AI角色扮演平台'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="static/images/favicon.ico">
    <style>
        /* 引入自定义字体 */
        @font-face {
            font-family: 'AnimeHug';
            src: url('static/ttf/AnimeHug-2.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }

        /* 电影级黑白主题 - 重置和基础 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --black: #000000;
            --dark-gray: #0a0a0a;
            --gray: #1a1a1a;
            --light-gray: #2a2a2a;
            --white: #ffffff;
            --off-white: #f5f5f5;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --text-muted: #707070;
            --border: rgba(255, 255, 255, 0.1);
            --border-light: rgba(255, 255, 255, 0.05);
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.5);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.6);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.7);
            --shadow-xl: 0 16px 64px rgba(0, 0, 0, 0.8);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Microsoft YaHei', 'PingFang SC', 'Noto Sans SC', sans-serif;
            background: var(--black);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
            position: relative;
            max-width: 100vw;
        }

        /* 胶片颗粒效果 */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(255, 255, 255, 0.01) 2px, rgba(255, 255, 255, 0.01) 4px),
                repeating-linear-gradient(90deg, transparent, transparent 2px, rgba(255, 255, 255, 0.01) 2px, rgba(255, 255, 255, 0.01) 4px);
            pointer-events: none;
            z-index: 9999;
            opacity: 0.3;
            animation: grain 0.5s steps(6) infinite;
        }

        @keyframes grain {
            0%, 100% { transform: translate(0, 0); }
            10% { transform: translate(-5%, -10%); }
            20% { transform: translate(-15%, 5%); }
            30% { transform: translate(7%, -25%); }
            40% { transform: translate(-5%, 25%); }
            50% { transform: translate(-15%, 10%); }
            60% { transform: translate(15%, 0%); }
            70% { transform: translate(0%, 15%); }
            80% { transform: translate(3%, 35%); }
            90% { transform: translate(-10%, 10%); }
        }

        /* 毛玻璃导航栏 - 电影级 */
        .home-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: all 0.6s cubic-bezier(0.23, 1, 0.32, 1);
            backdrop-filter: blur(40px) saturate(200%);
            -webkit-backdrop-filter: blur(40px) saturate(200%);
            background: rgba(0, 0, 0, 0.4);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: var(--shadow-md);
        }

        .home-header.scrolled {
            backdrop-filter: blur(50px) saturate(200%);
            -webkit-backdrop-filter: blur(50px) saturate(200%);
            background: rgba(0, 0, 0, 0.7);
            box-shadow: var(--shadow-lg);
            border-bottom-color: rgba(255, 255, 255, 0.15);
        }

        .home-navbar {
            max-width: 1600px;
            margin: 0 auto;
            padding: 1.5rem 3rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .home-logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            position: relative;
        }

        .home-logo::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 1px;
            background: var(--white);
            transition: width 0.6s cubic-bezier(0.23, 1, 0.32, 1);
        }

        .home-logo:hover::after {
            width: 100%;
        }

        .home-logo:hover {
            transform: translateY(-2px);
        }

        .home-logo img {
            height: 48px;
            width: 48px;
            object-fit: contain;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.8));
        }

        .home-logo-text {
            font-size: 1.75rem;
            font-weight: 300;
            color: var(--text-primary);
            letter-spacing: 0.1em;
            text-transform: uppercase;
            font-family: 'Arial', sans-serif;
        }

        .home-nav-menu {
            display: flex;
            list-style: none;
            gap: 3.5rem;
            align-items: center;
            margin: 0;
            padding: 0;
        }

        .home-nav-menu a {
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 300;
            font-size: 0.875rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            position: relative;
            padding: 0.5rem 0;
        }

        .home-nav-menu a::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%) scaleX(0);
            width: 100%;
            height: 1px;
            background: var(--white);
            transition: transform 0.6s cubic-bezier(0.23, 1, 0.32, 1);
            transform-origin: center;
        }

        .home-nav-menu a:hover {
            color: var(--white);
            letter-spacing: 0.2em;
        }

        .home-nav-menu a:hover::before {
            transform: translateX(-50%) scaleX(1);
        }

        .home-auth-buttons {
            display: flex;
            gap: 1.25rem;
            align-items: center;
        }

        .home-btn {
            padding: 0.875rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 300;
            transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.8125rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            border: 1px solid var(--white);
            background: transparent;
            color: var(--white);
            position: relative;
            overflow: hidden;
        }

        .home-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--white);
            transition: left 0.5s cubic-bezier(0.23, 1, 0.32, 1);
            z-index: 0;
        }

        .home-btn span {
            position: relative;
            z-index: 1;
        }

        .home-btn i {
            position: relative;
            z-index: 1;
        }

        .home-btn:hover {
            color: var(--black);
            border-color: var(--white);
        }

        .home-btn:hover::before {
            left: 0;
        }

        .home-btn-outline {
            border-color: var(--border);
            color: var(--text-secondary);
        }

        .home-btn-outline:hover {
            border-color: var(--white);
            color: var(--white);
        }

        .home-user-greeting {
            color: var(--text-secondary);
            font-size: 0.8125rem;
            letter-spacing: 0.1em;
            margin-right: 0.75rem;
        }

        /* 英雄区域 - 电影级 */
        .home-hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 160px 3rem 120px;
            background: radial-gradient(ellipse at center, rgba(255, 255, 255, 0.03) 0%, transparent 70%),
                        linear-gradient(180deg, var(--black) 0%, var(--dark-gray) 100%);
            overflow: hidden;
            /* 等比例缩放 - 以1920px为基准 */
            --base-width: 1920px;
            width: 100%;
        }

        /* 等比例缩放由JavaScript控制 */
        .home-hero.scaled {
            transform-origin: top center;
        }

        /* 电影级光效 */
        .home-hero::before {
            content: '';
            position: absolute;
            top: 20%;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            filter: blur(80px);
            animation: lightPulse 8s ease-in-out infinite;
            opacity: 0.5;
        }

        @keyframes lightPulse {
            0%, 100% { transform: translateX(-50%) scale(1); opacity: 0.3; }
            50% { transform: translateX(-50%) scale(1.2); opacity: 0.6; }
        }

        .home-hero::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                repeating-linear-gradient(0deg, transparent, transparent 1px, rgba(255, 255, 255, 0.02) 1px, rgba(255, 255, 255, 0.02) 2px);
            pointer-events: none;
            opacity: 0.3;
        }

        .home-hero-title-wrapper {
            margin-bottom: 3rem;
            text-align: left;
            position: relative;
        }

        .home-hero-title-wrapper::before {
            content: '';
            position: absolute;
            top: -4.4rem;
            left: 0;
            width: 100%;
            height: 1px;
            background-color: var(--white);
            opacity: 0.8;
            animation: lineSlideIn 7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes lineSlideIn {
            from {
                opacity: 0;
                transform: translateX(30vw);
            }
            to {
                opacity: 0.8;
                transform: translateX(0);
            }
        }

        .home-hero-logo {
            position: absolute;
            left: clamp(450px, 48vw, 600px);
            margin-left: -15rem;
            top: 50%;
            transform: translateY(-50%);
            width: clamp(200px, 20vw, 360px);
            height: clamp(200px, 20vw, 360px);
            filter: invert(1);
            object-fit: contain;
            z-index: 10;
            opacity: 0;
            animation: logoScaleIn 6.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes logoScaleIn {
            from {
                opacity: 0;
                transform: translateY(-50%) scale(0.2);
            }
            to {
                opacity: 1;
                transform: translateY(-50%) scale(1);
            }
        }

        .home-hero-content {
            max-width: 1200px;
            margin: 0;
            margin-left: clamp(380px, 40vw, 580px);
            text-align: left;
            position: relative;
            z-index: 1;
            animation: heroSlideIn 7s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes heroSlideIn {
            from {
                opacity: 0;
                transform: translateX(100vw);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }


        .home-hero h1 {
            font-family: 'AnimeHug', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Microsoft YaHei', 'PingFang SC', 'Noto Sans SC', sans-serif;
            font-size: clamp(2rem, 5vw, 4rem);
            font-weight: normal;
            color: var(--white);
            line-height: 1.1;
            letter-spacing: 0.05em;
            text-transform: none;
            text-shadow: 0 0 30px rgba(255, 255, 255, 0.3),
                         0 4px 20px rgba(0, 0, 0, 0.8);
            margin: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .home-hero-subtitle {
            text-align: right;
            margin: 1.5rem 0;
            padding-right: 2rem;
            z-index: 1;
            position: relative;
        }

        .home-hero-subtitle h2 {
            font-family: 'AnimeHug', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Microsoft YaHei', 'PingFang SC', 'Noto Sans SC', sans-serif;
            font-size: clamp(1.2rem, 2.5vw, 2rem);
            font-weight: 100;
            color: var(--text-secondary);
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin: 0;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--white);
            display: inline-block;
        }

        .home-hero-tagline {
            max-width: 900px;
            padding: 2rem 3rem;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(10px);
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: 220px;
            width: 100%;
            overflow: hidden;
            z-index: 1;
            text-align: center;
            animation: buttonsFadeIn 2.5s cubic-bezier(0.16, 1, 0.3, 1) 2s both;
        }

        .home-hero-tagline::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: taglineShine 3s ease-in-out infinite;
        }

        @keyframes taglineShine {
            0% {
                left: -100%;
            }
            50% {
                left: 100%;
            }
            100% {
                left: 100%;
            }
        }

        .home-tagline-text {
            font-size: clamp(0.95rem, 2vw, 1.25rem);
            color: var(--text-secondary);
            line-height: 2;
            font-weight: 300;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            position: relative;
            z-index: 1;
            display: block;
        }

        .home-hero-buttons {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: 120px;
            width: 100%;
            max-width: 1200px;
            animation: buttonsFadeIn 2.5s cubic-bezier(0.16, 1, 0.3, 1) 2s both;
        }

        @keyframes buttonsFadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .home-hero-buttons .home-btn {
            min-width: 220px;
            justify-content: center;
            padding: 1.25rem 2.5rem;
            font-size: 0.875rem;
        }

        /* 主要内容区域 */
        .home-main {
            background: var(--black);
            position: relative;
        }

        .home-section {
            padding: 120px 3rem;
            max-width: 1600px;
            margin: 0 auto;
            position: relative;
        }

        .home-section-header {
            text-align: center;
            margin-bottom: 6rem;
        }

        .home-section-title {
            font-size: clamp(2.5rem, 6vw, 4.5rem);
            font-weight: 100;
            color: var(--white);
            margin-bottom: 1.5rem;
            position: relative;
            display: inline-block;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .home-section-title::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--white), transparent);
            animation: lineExpand 1.5s cubic-bezier(0.23, 1, 0.32, 1) 0.5s both;
        }

        @keyframes lineExpand {
            from {
                width: 0;
                opacity: 0;
            }
            to {
                width: 100px;
                opacity: 1;
            }
        }

        .home-section-description {
            font-size: 1.125rem;
            color: var(--text-secondary);
            max-width: 700px;
            margin: 2.5rem auto 0;
            line-height: 1.9;
            font-weight: 300;
            letter-spacing: 0.05em;
        }

        /* 角色卡片网格 - 电影级 */
        .home-characters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 3rem;
            margin-top: 5rem;
        }

        .home-character-card {
            background: var(--dark-gray);
            border: 1px solid var(--border-light);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: all 0.8s cubic-bezier(0.23, 1, 0.32, 1);
            cursor: pointer;
            position: relative;
            opacity: 0;
            transform: translateY(50px);
        }

        .home-character-card.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .home-character-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, transparent 0%, rgba(255, 255, 255, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.8s cubic-bezier(0.23, 1, 0.32, 1);
            z-index: 1;
            pointer-events: none;
        }

        .home-character-card:hover {
            transform: translateY(-15px);
            box-shadow: var(--shadow-xl);
            border-color: var(--white);
        }

        .home-character-card:hover::before {
            opacity: 1;
        }

        .home-character-avatar-wrapper {
            position: relative;
            width: 100%;
            height: 400px;
            overflow: hidden;
            background: var(--white);
            border-radius: 20px 20px 0 0;
        }

        .home-character-avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 1.2s cubic-bezier(0.23, 1, 0.32, 1);
            filter: contrast(1.1);
        }

        .home-character-card:hover .home-character-avatar {
            transform: scale(1.1);
            filter: contrast(1.2);
        }

        .home-character-info {
            padding: 2.5rem;
            position: relative;
            z-index: 2;
        }

        .home-character-name {
            font-size: 1.5rem;
            font-weight: 300;
            color: var(--white);
            margin: 0 0 1.25rem 0;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .home-character-category {
            display: inline-block;
            background: transparent;
            color: var(--white);
            padding: 0.5rem 1rem;
            border: 1px solid var(--white);
            font-size: 0.75rem;
            font-weight: 300;
            margin-bottom: 1.25rem;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
        }

        .home-character-card:hover .home-character-category {
            background: var(--white);
            color: var(--black);
        }

        .home-character-intro {
            color: var(--text-secondary);
            font-size: 0.9375rem;
            line-height: 1.8;
            margin-bottom: 1.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-weight: 300;
        }

        .home-character-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-light);
        }

        .home-stat-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 300;
            letter-spacing: 0.05em;
        }

        .home-stat-item i {
            color: var(--white);
            transition: transform 0.4s cubic-bezier(0.23, 1, 0.32, 1);
        }

        .home-character-card:hover .home-stat-item {
            color: var(--white);
        }

        .home-character-card:hover .home-stat-item i {
            transform: scale(1.3);
        }

        .home-character-rating {
            color: var(--white);
        }

        .home-character-rating i {
            color: var(--white);
        }

        /* 特性展示 - 电影级 */
        .home-features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 3rem;
            margin-top: 5rem;
        }

        .home-feature-card {
            background: var(--dark-gray);
            border: 1px solid var(--border-light);
            padding: 4rem 3rem;
            border-radius: 0;
            box-shadow: var(--shadow-lg);
            transition: all 0.8s cubic-bezier(0.23, 1, 0.32, 1);
            text-align: center;
            position: relative;
            overflow: hidden;
            opacity: 0;
            transform: translateY(50px);
        }

        .home-feature-card.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .home-feature-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.8s cubic-bezier(0.23, 1, 0.32, 1);
        }

        .home-feature-card:hover {
            transform: translateY(-12px);
            box-shadow: var(--shadow-xl);
            border-color: var(--white);
        }

        .home-feature-card:hover::before {
            opacity: 1;
        }

        .home-feature-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 2.5rem;
            background: transparent;
            border: 1px solid var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--white);
            transition: all 0.8s cubic-bezier(0.23, 1, 0.32, 1);
            position: relative;
            z-index: 1;
        }

        .home-feature-card:hover .home-feature-icon {
            background: var(--white);
            color: var(--black);
            transform: rotate(5deg) scale(1.1);
        }

        .home-feature-card h3 {
            font-size: 1.75rem;
            font-weight: 300;
            color: var(--white);
            margin-bottom: 1.5rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            position: relative;
            z-index: 1;
        }

        .home-feature-card p {
            color: var(--text-secondary);
            line-height: 1.9;
            font-weight: 300;
            letter-spacing: 0.05em;
            position: relative;
            z-index: 1;
        }

        /* 统计数据 - 电影级 */
        .home-stats-section {
            background: var(--dark-gray);
            color: var(--white);
            padding: 120px 3rem;
            position: relative;
            overflow: hidden;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .home-stats-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                repeating-linear-gradient(0deg, transparent, transparent 1px, rgba(255, 255, 255, 0.02) 1px, rgba(255, 255, 255, 0.02) 2px);
            opacity: 0.3;
            pointer-events: none;
        }

        .home-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 4rem;
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .home-stat-card {
            text-align: center;
            opacity: 0;
            transform: translateY(50px);
            transition: all 0.8s cubic-bezier(0.23, 1, 0.32, 1);
        }

        .home-stat-card.visible {
            opacity: 1;
            transform: translateY(0);
        }

        .home-stat-number {
            font-size: 4.5rem;
            font-weight: 100;
            margin-bottom: 1rem;
            color: var(--white);
            letter-spacing: 0.05em;
            animation: numberReveal 1.5s cubic-bezier(0.23, 1, 0.32, 1);
        }

        @keyframes numberReveal {
            from {
                opacity: 0;
                transform: scale(0.5);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .home-stat-label {
            font-size: 1rem;
            font-weight: 300;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--text-secondary);
        }

        /* 页脚 - 电影级 */
        .home-footer {
            background: var(--black);
            border-top: 1px solid var(--border);
            color: var(--text-primary);
            padding: 100px 3rem 50px;
        }

        .home-footer-content {
            max-width: 1600px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 5rem;
            margin-bottom: 4rem;
        }

        .home-footer-column h3 {
            font-size: 1.25rem;
            font-weight: 300;
            margin-bottom: 2rem;
            color: var(--white);
            letter-spacing: 0.15em;
            text-transform: uppercase;
        }

        .home-footer-column p,
        .home-footer-column a {
            color: var(--text-secondary);
            text-decoration: none;
            display: block;
            margin-bottom: 1rem;
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            font-weight: 300;
            letter-spacing: 0.05em;
        }

        .home-footer-column a:hover {
            color: var(--white);
            transform: translateX(8px);
        }

        .home-footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .home-footer-links li {
            margin-bottom: 1rem;
        }

        .home-copyright {
            text-align: center;
            padding-top: 3rem;
            border-top: 1px solid var(--border-light);
            color: var(--text-muted);
            font-size: 0.8125rem;
            letter-spacing: 0.1em;
            font-weight: 300;
        }

        /* 空状态 */
        .home-empty-state {
            text-align: center;
            padding: 120px 3rem;
            color: var(--text-secondary);
        }

        .home-empty-state i {
            font-size: 6rem;
            margin-bottom: 2.5rem;
            color: var(--text-muted);
            animation: emptyPulse 3s ease-in-out infinite;
            filter: grayscale(100%);
        }

        @keyframes emptyPulse {
            0%, 100% { transform: scale(1); opacity: 0.4; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .home-empty-state h3 {
            font-size: 2rem;
            margin-bottom: 1.25rem;
            color: var(--white);
            font-weight: 300;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        /* 响应式设计 */
        /* 大屏适配 (1440px - 1920px) - 保持桌面端效果，适度缩放 */
        @media (max-width: 1919px) and (min-width: 1440px) {
            .home-hero-logo {
                left: clamp(400px, 45vw, 580px);
                width: clamp(200px, 18vw, 320px);
                height: clamp(200px, 18vw, 320px);
            }

            .home-hero-content {
                margin-left: clamp(350px, 38vw, 550px);
            }
        }

        /* 中等屏幕适配 (1024px - 1439px) - 平板横屏 */
        @media (max-width: 1439px) and (min-width: 1024px) {
            .home-hero-logo {
                left: clamp(320px, 40vw, 480px);
                width: clamp(180px, 16vw, 280px);
                height: clamp(180px, 16vw, 280px);
            }

            .home-hero-content {
                margin-left: clamp(280px, 35vw, 480px);
            }

            .home-hero h1 {
                font-size: clamp(1.8rem, 4.5vw, 3.5rem);
            }

            .home-hero-subtitle h2 {
                font-size: clamp(1rem, 2vw, 1.5rem);
            }

            .home-characters-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 2.5rem;
            }

            .home-features-grid {
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                gap: 2.5rem;
            }
        }

        /* 中等屏幕适配 (769px - 1023px) - 平板竖屏 */
        @media (max-width: 1023px) and (min-width: 769px) {
            .home-hero-logo {
                left: clamp(250px, 32vw, 400px);
                width: clamp(150px, 14vw, 240px);
                height: clamp(150px, 14vw, 240px);
            }

            .home-hero-content {
                margin-left: clamp(220px, 30vw, 380px);
            }

            .home-hero h1 {
                font-size: clamp(1.6rem, 4vw, 3rem);
            }

            .home-hero-subtitle h2 {
                font-size: clamp(0.95rem, 1.8vw, 1.4rem);
            }

            .home-characters-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 2rem;
            }

            .home-features-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 2rem;
            }

            .home-navbar {
                padding: 1.25rem 2rem;
            }

            .home-nav-menu {
                gap: 2rem;
            }

            .home-section {
                padding: 100px 2rem;
            }
        }

        /* 小屏幕适配 (手机) - 768px 及以下 */
        @media (max-width: 768px) {
            /* 整个页面等比例缩小，基于桌面端1920px设计 */
            html {
                zoom: 0.35;
                -webkit-text-size-adjust: 100%;
            }

            body {
                width: 285.714vw; /* 100 / 0.35 = 285.714 */
                transform-origin: top left;
                overflow-x: hidden;
                max-width: none;
            }

            .home-navbar {
                padding: 1.5rem 3rem;
            }

            .home-nav-menu {
                display: flex;
            }

            .home-auth-buttons {
                gap: 1.25rem;
            }

            .home-btn {
                padding: 0.875rem 2rem;
                font-size: 0.8125rem;
            }

            .home-user-greeting {
                display: inline;
            }

            .home-hero {
                padding: 160px 3rem 120px;
                min-height: 100vh;
            }

            .home-hero-content {
                margin-left: clamp(380px, 40vw, 580px);
                text-align: left;
            }

            .home-hero-title-wrapper {
                text-align: left;
            }

            .home-hero-title-wrapper::before {
                left: 0;
                transform: none;
                width: 100%;
                animation: lineSlideIn 7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }

            .home-hero h1 {
                font-size: clamp(2rem, 5vw, 4rem);
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .home-hero-logo {
                position: absolute;
                left: clamp(450px, 48vw, 600px);
                margin-left: -15rem;
                top: 50%;
                transform: translateY(-50%);
                width: clamp(200px, 20vw, 360px);
                height: clamp(200px, 20vw, 360px);
                display: block;
                animation: logoScaleIn 6.5s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }

            .home-hero-subtitle {
                text-align: right;
                padding-right: 2rem;
            }

            .home-hero-subtitle h2 {
                font-size: clamp(1.2rem, 2.5vw, 2rem);
                padding-bottom: 1rem;
            }

            .home-hero-tagline {
                position: absolute;
                left: 50%;
                transform: translateX(-50%);
                bottom: 220px;
                width: 100%;
                padding: 2rem 3rem;
            }

            .home-hero-buttons {
                position: absolute;
                left: 50%;
                transform: translateX(-50%);
                bottom: 120px;
                width: 100%;
                flex-direction: row;
                justify-content: center;
            }

            .home-hero-buttons .home-btn {
                min-width: 220px;
                width: auto;
                max-width: none;
            }

            .home-section {
                padding: 120px 3rem;
            }

            .home-section-header {
                margin-bottom: 6rem;
            }

            .home-section-title {
                font-size: clamp(2.5rem, 6vw, 4.5rem);
            }

            .home-section-description {
                font-size: 1.125rem;
            }

            /* 角色卡片改为三列 */
            .home-characters-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 3rem;
                margin-top: 5rem;
            }

            .home-character-card {
                min-width: 0;
            }

            .home-character-avatar-wrapper {
                height: 400px;
            }

            .home-character-info {
                padding: 2.5rem;
            }

            .home-character-name {
                font-size: 1.5rem;
            }

            .home-character-intro {
                font-size: 0.9375rem;
                -webkit-line-clamp: 2;
            }

            .home-features-grid {
                grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
                gap: 3rem;
                margin-top: 5rem;
            }

            .home-feature-card {
                padding: 4rem 3rem;
            }

            .home-stats-section {
                padding: 120px 3rem;
            }

            .home-stats-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 4rem;
            }

            .home-stat-number {
                font-size: 4.5rem;
            }

            .home-footer {
                padding: 100px 3rem 50px;
            }

            .home-footer-content {
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 5rem;
            }
        }

        /* 超小屏幕适配 - 480px 及以下，进一步缩小 */
        @media (max-width: 480px) {
            html {
                zoom: 0.28; /* 更小的缩放比例以适应小屏手机 */
            }

            body {
                width: 357.143vw; /* 100 / 0.28 = 357.143 */
            }

            .home-characters-grid {
                gap: 2rem; /* 稍微减小间距 */
            }

            .home-character-avatar-wrapper {
                height: 350px; /* 稍微减小高度 */
            }

            .home-stats-grid {
                grid-template-columns: repeat(2, 1fr); /* 小屏手机改为2列 */
                gap: 3rem;
            }

            .home-footer-content {
                grid-template-columns: 1fr; /* 小屏手机页脚单列 */
            }
        }
    </style>
</head>
<body class="home-page">
    <!-- 毛玻璃导航栏 -->
    <header class="home-header" id="header">
        <nav class="home-navbar">
            <a href="#home" class="home-logo">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="<?php echo safe_output($settings['logo']); ?>" alt="<?php echo safe_output($settings['title'] ?? 'AI角色扮演平台'); ?>">
                <?php else: ?>
                    <i class="fas fa-robot" style="font-size: 2rem; color: var(--white);"></i>
                <?php endif; ?>
                <span class="home-logo-text"><?php echo safe_output($settings['title'] ?? 'AI角色扮演平台'); ?></span>
            </a>
            <ul class="home-nav-menu">
                <li><a href="#home">首页</a></li>
                <li><a href="#characters">角色</a></li>
                <li><a href="#features">功能</a></li>
                <li><a href="#about">关于</a></li>
            </ul>
            <div class="home-auth-buttons">
                <?php if ($is_logged_in): ?>
                    <span class="home-user-greeting">欢迎，<?php echo safe_output($user_name); ?></span>
                    <a href="./user/chat" class="home-btn">
                        <i class="fas fa-comments"></i>
                        <span>开始聊天</span>
                    </a>
                    <a href="./user/logout" class="home-btn home-btn-outline">
                        <span>退出</span>
                    </a>
                <?php else: ?>
                    <a href="./user/login" class="home-btn home-btn-outline">
                        <span>登录</span>
                    </a>
                    <?php if ($settings['register'] ?? 1): ?>
                        <a href="./user/register" class="home-btn">
                            <span>注册</span>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <!-- 英雄区域 -->
    <section class="home-hero" id="home">
        <img src="static/images/logo.png" alt="Logo" class="home-hero-logo">
        <div class="home-hero-content">
            <div class="home-hero-title-wrapper">
                <h1>hello World, Hello Ai！</h1>
            </div>
            <div class="home-hero-subtitle">
                <h2>You can start now！</h2>
            </div>
        </div>
        <div class="home-hero-tagline">
            <span class="home-tagline-text"><?php echo safe_output($settings['description'] ?? '与各种精心设计的AI角色互动，体验前所未有的对话乐趣。开启您的次元之旅，与AI角色建立深度连接。'); ?></span>
        </div>
        <div class="home-hero-buttons">
            <?php if ($is_logged_in): ?>
                <a href="./user/chat" class="home-btn">
                    <i class="fas fa-rocket"></i>
                    <span>开始聊天</span>
                </a>
            <?php else: ?>
                <a href="./user/register" class="home-btn">
                    <i class="fas fa-user-plus"></i>
                    <span>立即体验</span>
                </a>
                <a href="./user/login" class="home-btn home-btn-outline">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>登录账号</span>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- 主要内容 -->
    <div class="home-main">
        <!-- 热门角色区域 -->
        <section class="home-section" id="characters">
            <div class="home-section-header">
                <h2 class="home-section-title">热门角色</h2>
                <p class="home-section-description">探索最受欢迎的角色，开始一段奇妙的对话旅程</p>
            </div>
            
            <?php if (!empty($popular_characters)): ?>
                <div class="home-characters-grid">
                    <?php foreach ($popular_characters as $index => $character): ?>
                        <?php echo render_character_card($character, $index); ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="home-empty-state">
                    <i class="fas fa-robot"></i>
                    <h3>暂无热门角色</h3>
                    <p>目前还没有公开的角色，请稍后再来查看</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- 高评分角色区域 -->
        <?php if (!empty($top_rated_characters)): ?>
        <section class="home-section">
            <div class="home-section-header">
                <h2 class="home-section-title">高评分角色</h2>
                <p class="home-section-description">用户评价最高的角色，品质保证的对话体验</p>
            </div>
            
            <div class="home-characters-grid">
                <?php foreach ($top_rated_characters as $index => $character): ?>
                    <?php echo render_character_card($character, $index); ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- 统计数据 -->
        <section class="home-stats-section">
            <div class="home-stats-grid">
                <div class="home-stat-card">
                    <div class="home-stat-number"><?php echo $stats['total_characters']; ?>+</div>
                    <div class="home-stat-label">精选角色</div>
                </div>
                <div class="home-stat-card" style="transition-delay: 0.2s;">
                    <div class="home-stat-number"><?php echo $stats['total_conversations']; ?>+</div>
                    <div class="home-stat-label">对话次数</div>
                </div>
                <div class="home-stat-card" style="transition-delay: 0.4s;">
                    <div class="home-stat-number"><?php echo format_number($stats['avg_rating'], 1); ?></div>
                    <div class="home-stat-label">平均评分</div>
                </div>
                <div class="home-stat-card" style="transition-delay: 0.6s;">
                    <div class="home-stat-number">24/7</div>
                    <div class="home-stat-label">在线服务</div>
                </div>
            </div>
        </section>

        <!-- 特性介绍区域 -->
        <section class="home-section" id="features">
            <div class="home-section-header">
                <h2 class="home-section-title">平台特色</h2>
                <p class="home-section-description">探索我们平台的独特功能，享受优质的AI对话体验</p>
            </div>
            <div class="home-features-grid">
                <div class="home-feature-card" style="transition-delay: 0s;">
                    <div class="home-feature-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h3>多样角色</h3>
                    <p>从历史人物到虚拟角色，各种精心设计的AI角色任您选择，每个角色都有独特的性格和背景故事</p>
                </div>
                <div class="home-feature-card" style="transition-delay: 0.2s;">
                    <div class="home-feature-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3>智能对话</h3>
                    <p>基于先进AI技术，提供流畅、自然、富有情感的对话体验，让每一次交流都充满惊喜</p>
                </div>
                <div class="home-feature-card" style="transition-delay: 0.4s;">
                    <div class="home-feature-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3>个性化定制</h3>
                    <p>创建您自己的专属角色，定制独特的对话风格和背景故事，打造独一无二的AI伙伴</p>
                </div>
            </div>
        </section>
    </div>

    <!-- 页脚 -->
    <footer class="home-footer" id="about">
        <div class="home-footer-content">
            <div class="home-footer-column">
                <h3>关于我们</h3>
                <p><?php echo safe_output($settings['description'] ?? 'AI角色扮演平台，为您提供优质的AI对话体验。探索无限可能的AI角色世界，开启您的次元之旅。'); ?></p>
            </div>
            <div class="home-footer-column">
                <h3>快速链接</h3>
                <ul class="home-footer-links">
                    <li><a href="#home">首页</a></li>
                    <li><a href="#characters">角色</a></li>
                    <li><a href="#features">功能</a></li>
                    <?php if ($is_logged_in): ?>
                        <li><a href="./user/chat">开始聊天</a></li>
                        <li><a href="./user/profile">个人中心</a></li>
                    <?php else: ?>
                        <li><a href="./user/login">登录</a></li>
                        <li><a href="./user/register">注册</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="home-footer-column">
                <h3>联系我们</h3>
                <p><i class="fas fa-envelope"></i> <?php echo safe_output($settings['smtp_from_email'] ?? 'contact@example.com'); ?></p>
                <p><i class="fas fa-map-marker-alt"></i> 虚拟AI世界</p>
            </div>
        </div>
        <div class="home-copyright">
            <p>&copy; <?php echo date('Y'); ?> <?php echo safe_output($settings['title'] ?? 'AI角色扮演平台'); ?> 版权所有</p>
        </div>
    </footer>

    <script>
        // 手机端页面等比例缩放处理
        (function() {
            const MOBILE_BREAKPOINT = 768;
            const MOBILE_SMALL_BREAKPOINT = 480;
            
            function updateMobileScale() {
                const viewportWidth = window.innerWidth;
                
                // 手机端使用 CSS zoom，由媒体查询处理，这里只做兼容性检查
                if (viewportWidth <= MOBILE_BREAKPOINT) {
                    // 确保 body 宽度正确
                    const htmlZoom = window.getComputedStyle(document.documentElement).zoom || 
                                    (window.getComputedStyle(document.documentElement).transform === 'none' ? 1 : parseFloat(window.getComputedStyle(document.documentElement).transform.match(/scale\(([^)]+)\)/)?.[1] || '1'));
                    
                    if (!htmlZoom || htmlZoom === 1) {
                        // 如果不支持 zoom，使用 transform scale 作为后备方案
                        const scale = viewportWidth <= MOBILE_SMALL_BREAKPOINT ? 0.28 : 0.35;
                        document.body.style.width = `${100 / scale}vw`;
                        document.body.style.transform = `scale(${scale})`;
                        document.body.style.transformOrigin = 'top left';
                    }
                } else {
                    // 桌面端重置
                    document.body.style.width = '100%';
                    document.body.style.transform = 'none';
                    document.body.style.transformOrigin = 'top left';
                }
            }
            
            // 初始执行
            updateMobileScale();
            
            // 窗口大小改变时更新
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(updateMobileScale, 100);
            });
        })();

        // 导航栏滚动效果
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // 平滑滚动（优化：简化逻辑，移除重复计算）
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if(targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if(targetElement) {
                    const headerHeight = document.getElementById('header').offsetHeight;
                    const scrollOffset = targetElement.offsetTop - headerHeight;
                    
                    window.scrollTo({
                        top: Math.max(0, scrollOffset),
                        behavior: 'smooth'
                    });
                }
            });
        });

        // 滚动动画 - 使用 IntersectionObserver（优化：使用现代API，提升性能）
        const observerOptions = {
            threshold: 0.2,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    // 优化：元素显示后停止观察，减少性能开销
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // 观察所有需要动画的元素
        const animatedElements = document.querySelectorAll('.home-character-card, .home-feature-card, .home-stat-card');
        animatedElements.forEach(el => observer.observe(el));
    </script>
</body>
</html>
