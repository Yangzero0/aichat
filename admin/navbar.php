<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-17
最后编辑时间：2025-10-19
文件描述：侧边导航栏，引入到后台其他页面中
*/

require_once(__DIR__ . '/../config/config.php');

// 检查管理员是否登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 获取管理员信息
$admin_id = $_SESSION['admin_id'];
$admin_username = $_SESSION['admin_username'];
$admin_level = $_SESSION['admin_level'];

// 获取网站配置 - 如果已经在 index.php 中获取了，这里就不需要重复获取
// 如果 $settings 变量不存在，则从数据库获取
if (!isset($settings)) {
    $settings = [];
    try {
        $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("获取网站配置失败: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理 - <?php echo htmlspecialchars($settings['title'] ?? 'AI角色扮演平台'); ?></title>
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
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 70px;
            --header-height: 70px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: var(--text-primary);
            line-height: 1.6;
        }
        
        /* 侧边栏样式 */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            z-index: 1000;
            overflow: visible;
        }
        
        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
            overflow: visible; 
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: var(--header-height);
            position: relative;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }
        
        .logo-image {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            object-fit: cover;
        }
        
        .logo-icon {
            font-size: 24px;
            color: var(--primary-color);
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo-text {
            font-size: 16px;
            font-weight: 700;
            color: var(--dark-color);
            white-space: nowrap;
            transition: opacity 0.3s;
        }
        
        .sidebar.collapsed .logo-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }
        
        .toggle-btn {
            background: white;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: all 0.3s;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute; /* 改为绝对定位 */
            right: -18px; /* 调整位置，让按钮一半在侧边栏外 */
            top: 50%; /* 垂直居中 */
            transform: translateY(-50%);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            z-index: 1001;
            font-size: 14px;
        }
        
        .toggle-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        }
        
        .toggle-btn:active {
            transform: translateY(-50%) scale(0.95);
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .menu-section {
            margin-bottom: 24px;
        }
        
        .menu-title {
            padding: 0 20px 12px 20px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
            transition: opacity 0.3s;
        }
        
        .sidebar.collapsed .menu-title {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--text-primary);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            white-space: nowrap;
        }
        
        .menu-item:hover {
            background: var(--light-color);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }
        
        .menu-item.active {
            background: rgba(102, 126, 234, 0.1);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }
        
        .menu-icon {
            width: 20px;
            text-align: center;
            margin-right: 12px;
            font-size: 16px;
            transition: margin-right 0.3s;
        }
        
        .sidebar.collapsed .menu-icon {
            margin-right: 0;
        }
        
        .menu-text {
            transition: opacity 0.3s;
        }
        
        .sidebar.collapsed .menu-text {
            opacity: 0;
            width: 0;
            overflow: hidden;
        }
        
        /* 主内容区域 */
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }
        
        .sidebar.collapsed ~ .main-content {
            margin-left: var(--sidebar-collapsed-width);
        }
        
        /* 顶部导航栏 */
        .top-navbar {
            background: white;
            height: var(--header-height);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 16px;
            border-radius: 8px;
            transition: background 0.3s;
            cursor: pointer;
        }
        
        .admin-info:hover {
            background: var(--light-color);
        }
        
        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .admin-details {
            display: flex;
            flex-direction: column;
        }
        
        .admin-name {
            font-weight: 600;
            font-size: 14px;
        }
        
        .admin-role {
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .logout-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .logout-btn:hover {
            background: var(--light-color);
            color: var(--danger-color);
        }
        
        /* 内容区域 */
        .content-wrapper {
            padding: 30px;
        }
        
        /* 统计卡片 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card.users {
            border-left-color: var(--success-color);
        }
        
        .stat-card.characters {
            border-left-color: var(--warning-color);
        }
        
        .stat-card.chats {
            border-left-color: var(--primary-color);
        }
        
        .stat-card.today {
            border-left-color: var(--secondary-color);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--dark-color);
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .stat-icon {
            width: 20px;
            text-align: center;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .content-wrapper {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 20px;
            color: var(--text-primary);
            cursor: pointer;
            padding: 8px;
        }
        
        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
            
            .top-navbar {
                padding: 0 20px;
            }
            
            .admin-details {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- 侧边栏 -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo" class="logo-image" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="logo-icon" style="display: none;">
                        <i class="fas fa-robot"></i>
                    </div>
                <?php else: ?>
                    <div class="logo-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                <?php endif; ?>
                <span class="logo-text"><?php echo htmlspecialchars($settings['title'] ?? 'AI平台'); ?> 管理后台</span>
            </div>
            <button class="toggle-btn" id="toggleBtn">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-section">
                <div class="menu-title">主要</div>
                <a href="index.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home menu-icon"></i>
                    <span class="menu-text">仪表盘</span>
                </a>
                <a href="users.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users menu-icon"></i>
                    <span class="menu-text">用户管理</span>
                </a>
                <a href="characters.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'characters.php' ? 'active' : ''; ?>">
                    <i class="fas fa-robot menu-icon"></i>
                    <span class="menu-text">角色管理</span>
                </a>
                <a href="character_review.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'character_review.php' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle menu-icon"></i>
                    <span class="menu-text">角色审核</span>
                </a>
            </div>
            
<div class="menu-section">
    <div class="menu-title">内容</div>
    <a href="categories.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
        <i class="fas fa-tags menu-icon"></i>
        <span class="menu-text">分类管理</span>
    </a>
</div>
            
            <div class="menu-section">
                <div class="menu-title">系统</div>
                <a href="ai_models.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'ai_models.php' ? 'active' : ''; ?>">
                    <i class="fas fa-brain menu-icon"></i>
                    <span class="menu-text">AI模型</span>
                </a>
                <a href="settings.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog menu-icon"></i>
                    <span class="menu-text">系统设置</span>
                </a>
                <?php if ($admin_level == 0): ?>
                <a href="admins.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'admins.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-shield menu-icon"></i>
                    <span class="menu-text">管理员</span>
                </a>
                <a href="redeem_codes.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'redeem_codes.php' ? 'active' : ''; ?>">
                    <i class="fas fa-gift menu-icon"></i>
                    <span class="menu-text">卡密管理</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 主内容区域 -->
    <div class="main-content">
        <!-- 顶部导航栏 -->
        <nav class="top-navbar">
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
            
            <h1 class="page-title">仪表盘</h1>
            
            <div class="navbar-actions">
                <div class="admin-info">
                    <div class="admin-avatar">
                        <?php echo strtoupper(substr($admin_username, 0, 1)); ?>
                    </div>
                    <div class="admin-details">
                        <span class="admin-name"><?php echo htmlspecialchars($admin_username); ?></span>
                        <span class="admin-role"><?php echo $admin_level == 0 ? '超级管理员' : '管理员'; ?></span>
                    </div>
                </div>
                
                <a href="logout.php" class="logout-btn" title="退出登录">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </nav>

        <!-- 内容包装器 -->
        <div class="content-wrapper">

        <script>
        // 侧边栏折叠功能
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('toggleBtn');
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const mainContent = document.querySelector('.main-content');
            
            console.log('DOM加载完成，初始化侧边栏功能');
            
            // 从本地存储读取侧边栏状态
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed) {
                sidebar.classList.add('collapsed');
                if (toggleBtn) {
                    toggleBtn.querySelector('i').className = 'fas fa-chevron-right';
                }
            }
            
            // PC端折叠按钮
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    console.log('点击折叠按钮');
                    const isCollapsed = sidebar.classList.toggle('collapsed');
                    
                    // 更新按钮图标
                    toggleBtn.querySelector('i').className = isCollapsed ? 
                        'fas fa-chevron-right' : 'fas fa-chevron-left';
                    
                    // 保存状态到本地存储
                    localStorage.setItem('sidebarCollapsed', isCollapsed);
                    console.log('侧边栏状态:', isCollapsed ? '折叠' : '展开');
                });
            } else {
                console.error('找不到折叠按钮');
            }
            
            // 移动端菜单按钮
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    sidebar.classList.toggle('mobile-open');
                });
            }
            
            // 点击内容区域关闭移动端菜单
            if (mainContent) {
                mainContent.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('mobile-open');
                    }
                });
            }
            
            // 窗口大小变化时调整
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('mobile-open');
                }
            });
        });
        </script>