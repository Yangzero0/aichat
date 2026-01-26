<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-10
最后编辑时间：2025-11-15
文件描述：侧边导航栏

*/
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . './../config/config.php');

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

// 获取用户信息和网站配置
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? '';
$username = $_SESSION['username'] ?? '';
$settings = [];

try {
    $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // 错误处理
    error_log("获取配置失败: " . $e->getMessage());
}

// 计算静态资源前缀
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$assetPrefix = rtrim($scriptDir, '/');
$assetPrefix = $assetPrefix === '' ? '' : $assetPrefix;

// 检查是否有保存的导航栏状态 - 修复逻辑
$sidebarCollapsed = false; // 默认展开
if (isset($_COOKIE['sidebarCollapsed'])) {
    $sidebarCollapsed = $_COOKIE['sidebarCollapsed'] === 'true';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['title'] ?? 'AI角色扮演平台'); ?></title>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="../static/images/favicon.ico">
    <link rel="stylesheet" href="<?php echo $assetPrefix; ?>/assets/css/auth-theme.css">
</head>
<body class="app-page<?php echo (!empty($contentClass) && $contentClass === 'chat-layout') ? ' chat-page' : ''; ?>">
    <div class="app-layout">
        <aside class="app-sidebar <?php echo $sidebarCollapsed ? 'is-collapsed' : ''; ?>" id="sidebar">
            <div class="app-sidebar__brand">
                <div class="app-sidebar__logo">
                <?php if (!empty($settings['logo'])): ?>
                    <img src="<?php echo htmlspecialchars($settings['logo']); ?>" alt="Logo">
                <?php else: ?>
                    <i class="fas fa-robot"></i>
                <?php endif; ?>
            </div>
                <span class="app-sidebar__title"><?php echo htmlspecialchars($settings['title'] ?? 'AI聊天'); ?></span>
                <button
                    type="button"
                    class="app-sidebar__collapse<?php echo $sidebarCollapsed ? ' is-collapsed' : ''; ?>"
                    id="collapseBtn"
                    aria-label="<?php echo $sidebarCollapsed ? '展开导航栏' : '收起导航栏'; ?>"
                    aria-expanded="<?php echo $sidebarCollapsed ? 'false' : 'true'; ?>"
                    title="<?php echo $sidebarCollapsed ? '展开导航栏' : '收起导航栏'; ?>"
                >
                    <span class="app-sidebar__collapse-icon" aria-hidden="true">
                <i class="fas fa-chevron-<?php echo $sidebarCollapsed ? 'right' : 'left'; ?>"></i>
                    </span>
                    <span class="app-sidebar__collapse-label">
                        <?php echo $sidebarCollapsed ? '展开' : '收起'; ?>
                    </span>
            </button>
            </div>
            <nav class="app-sidebar__nav" aria-label="侧边导航">
                <a href="index" class="app-sidebar__link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'is-active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>首页</span>
            </a>
                <a href="characters" class="app-sidebar__link <?php echo basename($_SERVER['PHP_SELF']) == 'characters.php' ? 'is-active' : ''; ?>">
                <i class="fas fa-robot"></i>
                <span>角色广场</span>
            </a>
                <a href="chat" class="app-sidebar__link <?php echo basename($_SERVER['PHP_SELF']) == 'chat.php' ? 'is-active' : ''; ?>">
                <i class="fas fa-comments"></i>
                <span>开始聊天</span>
            </a>
                <a href="my_characters" class="app-sidebar__link <?php echo basename($_SERVER['PHP_SELF']) == 'my_characters.php' ? 'is-active' : ''; ?>">
                <i class="fas fa-user-plus"></i>
                <span>我的角色</span>
            </a>
                <a href="subscriptions" class="app-sidebar__link <?php echo basename($_SERVER['PHP_SELF']) == 'subscriptions.php' ? 'is-active' : ''; ?>">
                <i class="fas fa-star"></i>
                <span>我的订阅</span>
            </a>
                <a href="profile" class="app-sidebar__link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'is-active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>个人中心</span>
            </a>
                <a href="logout" class="app-sidebar__link">
                <i class="fas fa-sign-out-alt"></i>
                <span>退出登录</span>
            </a>
            </nav>
            <div class="app-sidebar__profile">
                <div class="app-sidebar__avatar">
                <i class="fas fa-user"></i>
            </div>
                <div class="app-sidebar__user">
                    <span class="app-sidebar__user-name"><?php echo htmlspecialchars($user_name ?: $username); ?></span>
                    <span class="app-sidebar__user-role">普通用户</span>
                </div>
            </div>
        </aside>

        <div class="app-main">
            <header class="app-header">
                <div class="app-header__left">
                    <button type="button" class="app-header__menu" id="mobileMenuBtn" aria-label="展开导航">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="app-header__title"><?php echo htmlspecialchars($settings['title'] ?? 'AI角色扮演平台'); ?></span>
                </div>
                <div class="app-header__actions">
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
            </header>

            <?php
                $mainContentClass = 'app-main__content';
                if (!empty($contentClass)) {
                    $mainContentClass .= ' ' . $contentClass;
                }
            ?>
            <main class="<?php echo $mainContentClass; ?>">
                <?php if (isset($content)): ?>
                    <?php echo $content; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <div class="app-overlay" id="appOverlay"></div>

    <script src="<?php echo $assetPrefix; ?>/assets/js/auth-theme.js" defer></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const sidebar = document.getElementById('sidebar');
            const collapseBtn = document.getElementById('collapseBtn');
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const overlay = document.getElementById('appOverlay');
            const links = sidebar.querySelectorAll('.app-sidebar__link');
            const collapseCookie = 'sidebarCollapsed';

            const prefersDesktop = () => window.innerWidth > 1080;

            function setBodyScroll(lock) {
                document.body.style.overflow = lock ? 'hidden' : '';
            }

            function openSidebar() {
                sidebar.classList.add('is-open');
                overlay.classList.add('is-active');
                setBodyScroll(true);
            }

            function closeSidebar() {
                sidebar.classList.remove('is-open');
                overlay.classList.remove('is-active');
                setBodyScroll(false);
                    }

            function applyCollapsedState(isCollapsed) {
                sidebar.classList.toggle('is-collapsed', isCollapsed);
                if (collapseBtn) {
                    const iconEl = collapseBtn.querySelector('.app-sidebar__collapse-icon i');
                    const labelEl = collapseBtn.querySelector('.app-sidebar__collapse-label');
                    collapseBtn.classList.toggle('is-collapsed', isCollapsed);
                    collapseBtn.setAttribute('aria-label', isCollapsed ? '展开导航栏' : '收起导航栏');
                    collapseBtn.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
                    collapseBtn.setAttribute('title', isCollapsed ? '展开导航栏' : '收起导航栏');
                    if (iconEl) {
                        iconEl.className = 'fas fa-chevron-' + (isCollapsed ? 'right' : 'left');
                    }
                    if (labelEl) {
                        labelEl.textContent = isCollapsed ? '展开' : '收起';
                    }
                }
            }

            function saveCollapsedState(isCollapsed) {
                document.cookie = collapseCookie + '=' + isCollapsed + '; max-age=' + (30 * 24 * 60 * 60) + '; path=/';
                }
                
            if (collapseBtn) {
                collapseBtn.addEventListener('click', function() {
                    const isCollapsed = !sidebar.classList.contains('is-collapsed');
                    applyCollapsedState(isCollapsed);
                    saveCollapsedState(isCollapsed);
                    });
                }
                
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function() {
                    openSidebar();
                    });
                }
                
            if (overlay) {
                overlay.addEventListener('click', function() {
                    closeSidebar();
                    });
                }
                
            links.forEach(link => {
                link.addEventListener('click', function() {
                    if (!prefersDesktop()) {
                        closeSidebar();
                    }
                    });
                });
                
                window.addEventListener('resize', function() {
                if (prefersDesktop()) {
                    closeSidebar();
                    }
                });
                
            applyCollapsedState(sidebar.classList.contains('is-collapsed'));
            closeSidebar();
            });
        </script>
</body>
</html>