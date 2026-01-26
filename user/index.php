<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-7
最后编辑时间：2025-11-15
文件描述：用户主页面，包含签到功能

*/
ob_start();
require_once(__DIR__ . './../config/config.php');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// 处理签到动作
if (isset($_GET['action']) && $_GET['action'] == 'checkin') {
    // 检查用户是否已经签到 today
    $user_id = $_SESSION['user_id'];
    $today = date('Y-m-d');
    
    // 获取用户最后签到日期
    $stmt = $db->prepare("SELECT last_checkin FROM user WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $last_checkin = $user['last_checkin'];
    
    // 如果最后签到日期不是今天，则进行签到
    if ($last_checkin != $today) {
        // 获取签到积分
        $stmt_setting = $db->query("SELECT qd_points FROM web_setting ORDER BY id DESC LIMIT 1");
        $setting = $stmt_setting->fetch(PDO::FETCH_ASSOC);
        $qd_points = $setting['qd_points'] ?? 0;
        
        if ($qd_points > 0) {
            // 更新用户积分和最后签到日期
            $stmt = $db->prepare("UPDATE user SET points = points + ?, last_checkin = ? WHERE id = ?");
            $stmt->execute([$qd_points, $today, $user_id]);
            
            // 设置签到成功的消息
            $_SESSION['checkin_message'] = "签到成功！获得{$qd_points}积分。";
        }
    } else {
        $_SESSION['checkin_message'] = "今天已经签到过了！";
    }
    
    // 重定向到当前页面，去除action参数
    header('Location: ' . str_replace('?action=checkin', '', $_SERVER['REQUEST_URI']));
    exit;
}

// 获取角色数据和分类数据
$categories = [];
$characters = [];
$search_query = $_GET['search'] ?? '';
$category_id = $_GET['category'] ?? '';
$settings = []; // 确保$settings已定义

// 获取当前用户信息
$stmt_user = $db->prepare("SELECT last_checkin, points FROM user WHERE id = ?");
$stmt_user->execute([$_SESSION['user_id']]);
$current_user = $stmt_user->fetch(PDO::FETCH_ASSOC);
$last_checkin = $current_user['last_checkin'];
$user_points = $current_user['points'];

// 检查今天是否已经签到
$today = date('Y-m-d');
$has_checked_in = ($last_checkin == $today);

try {
    // 获取网站配置（包含公告）
    $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 获取所有分类
    $stmt = $db->prepare("SELECT * FROM character_category WHERE status = 1 ORDER BY sort_order ASC");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 构建查询条件
    $where_conditions = ["ac.status = 1", "ac.is_public = 1"];
    $params = [];
    
    if (!empty($search_query)) {
        $where_conditions[] = "(ac.name LIKE ? OR ac.introduction LIKE ?)";
        $search_term = "%$search_query%";
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if (!empty($category_id) && is_numeric($category_id)) {
        $where_conditions[] = "ac.category_id = ?";
        $params[] = $category_id;
    }
    
    $where_sql = implode(" AND ", $where_conditions);
    
    // 获取角色数据
    $stmt = $db->prepare("
        SELECT ac.*, cc.name as category_name 
        FROM ai_character ac 
        LEFT JOIN character_category cc ON ac.category_id = cc.id 
        WHERE $where_sql 
        ORDER BY ac.usage_count DESC, ac.avg_rating DESC 
        LIMIT 30
    ");
    $stmt->execute($params);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("数据库查询错误: " . $e->getMessage());
}

// 获取热门角色（用于推荐）
$popular_characters = [];
try {
    $stmt = $db->prepare("
        SELECT ac.*, cc.name as category_name 
        FROM ai_character ac 
        LEFT JOIN character_category cc ON ac.category_id = cc.id 
        WHERE ac.status = 1 AND ac.is_public = 1 
        ORDER BY ac.usage_count DESC 
        LIMIT 6
    ");
    $stmt->execute();
    $popular_characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取热门角色错误: " . $e->getMessage());
}

// 调试信息 - 检查公告内容
$notice_content = '';
if (!empty($settings['notice'])) {
    $notice_content = $settings['notice'];
} elseif (!empty($settings['Notice'])) {
    $notice_content = $settings['Notice'];
}

$checkin_points_setting = (int) ($settings['qd_points'] ?? 0);
?>

<section class="section-card hero-card">
    <div>
        <h1 class="hero-card__title">
            <i class="fas fa-home"></i>
            欢迎来到<?php echo htmlspecialchars($settings['title'] ?? 'AI角色世界'); ?>
        </h1>
        <p class="hero-card__description">探索各种精心设计的AI角色，开启一段奇妙的智能对话旅程。</p>
        <div class="hero-card__meta">
            <span class="stat-pill">
                <i class="fas fa-coins"></i>
                当前积分 <?php echo number_format((int) $user_points); ?>
            </span>
            <span class="stat-pill">
                <i class="fas fa-<?php echo $has_checked_in ? 'check' : 'clock'; ?>"></i>
                今日<?php echo $has_checked_in ? '已签到' : '未签到'; ?>
            </span>
        </div>
    </div>
</section>

<?php if ($checkin_points_setting > 0): ?>
<section class="section-card checkin-card">
    <div class="checkin-card__info">
        <div class="checkin-card__icon">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div>
            <div class="checkin-card__title">每日签到</div>
            <div class="checkin-card__text">
                <?php if ($has_checked_in): ?>
                    今日已签到，明天再来吧！
                <?php else: ?>
                    签到即可获得 <?php echo $checkin_points_setting; ?> 积分
                <?php endif; ?>
            </div>
            <?php if (isset($_SESSION['checkin_message'])): ?>
                <?php $checkinAlertClass = strpos($_SESSION['checkin_message'], '成功') !== false ? 'alert-banner--success' : 'alert-banner--warning'; ?>
                <div class="alert-banner <?php echo $checkinAlertClass; ?>" id="checkinMessage">
                    <?php echo $_SESSION['checkin_message']; ?>
                </div>
                <?php unset($_SESSION['checkin_message']); ?>
            <?php endif; ?>
        </div>
    </div>
    <button class="button button--primary" id="checkinBtn" <?php echo $has_checked_in ? 'disabled' : ''; ?>>
        <i class="fas fa-<?php echo $has_checked_in ? 'check' : 'gift'; ?>"></i>
        <?php echo $has_checked_in ? '已签到' : '立即签到'; ?>
    </button>
</section>
<?php endif; ?>

<section class="section-card" id="searchPanel">
    <div class="section-card__header">
        <div>
            <h2 class="section-card__title">查找角色</h2>
            <p class="section-card__subtitle">使用关键词或分类，快速定位心仪的AI角色</p>
        </div>
    </div>
    <div class="search-panel">
        <form method="GET" action="" class="search-panel__bar">
            <input type="text" name="search" class="search-panel__input" placeholder="搜索角色名称或描述..." value="<?php echo htmlspecialchars($search_query); ?>">
            <button type="submit" class="button button--primary">
                <i class="fas fa-search"></i>
                搜索
            </button>
        </form>
        <div class="filter-chips" role="group" aria-label="角色分类筛选">
            <button type="button" class="filter-chip <?php echo empty($category_id) ? 'is-active' : ''; ?>" data-category="">全部角色</button>
            <?php foreach ($categories as $category): ?>
                <button type="button" class="filter-chip <?php echo $category_id == $category['id'] ? 'is-active' : ''; ?>" data-category="<?php echo $category['id']; ?>">
                    <?php echo htmlspecialchars($category['name']); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section-card">
    <?php if (!empty($search_query) || !empty($category_id)): ?>
        <div class="section-card__header">
            <h2 class="section-card__title">搜索结果</h2>
            <span class="section-meta">找到 <?php echo count($characters); ?> 个角色</span>
        </div>
        <?php if (!empty($characters)): ?>
            <div class="card-grid">
                <?php foreach ($characters as $character): ?>
                    <article class="character-card" onclick="startChat(<?php echo $character['id']; ?>)">
                        <div class="character-card__cover">
                            <?php if (!empty($character['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($character['avatar']); ?>" alt="<?php echo htmlspecialchars($character['name']); ?>" onerror="this.src='/static/ai-images/ai.png'">
                            <?php else: ?>
                                <div class="character-card__placeholder">
                                    <i class="fas fa-robot"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="character-card__body">
                            <h3 class="character-card__name"><?php echo htmlspecialchars($character['name']); ?></h3>
                            <?php if (!empty($character['category_name'])): ?>
                                <span class="character-card__badge"><?php echo htmlspecialchars($character['category_name']); ?></span>
                            <?php endif; ?>
                            <p class="character-card__intro"><?php echo htmlspecialchars($character['introduction'] ?? '暂无介绍'); ?></p>
                            <div class="character-card__stats">
                                <span class="character-card__stat">
                                    <i class="fas fa-star"></i>
                                    <?php echo number_format($character['avg_rating'], 1); ?>
                                </span>
                                <span class="character-card__stat">
                                    <i class="fas fa-comments"></i>
                                    <?php echo $character['usage_count']; ?> 对话
                                </span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state__icon"><i class="fas fa-search"></i></div>
                <div class="empty-state__title">没有找到相关角色</div>
                <p class="empty-state__description">请尝试调整搜索条件或浏览其他分类。</p>
                <a href="characters" class="button button--primary">
                    <i class="fas fa-robot"></i>
                    浏览全部角色
                </a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="section-card__header">
            <h2 class="section-card__title">热门角色</h2>
            <a href="characters" class="button button--ghost">
                查看全部
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
        <?php if (!empty($popular_characters)): ?>
            <div class="card-grid">
                <?php foreach ($popular_characters as $character): ?>
                    <article class="character-card" onclick="startChat(<?php echo $character['id']; ?>)">
                        <div class="character-card__cover">
                            <?php if (!empty($character['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($character['avatar']); ?>" alt="<?php echo htmlspecialchars($character['name']); ?>" onerror="this.src='/static/ai-images/ai.png'">
                            <?php else: ?>
                                <div class="character-card__placeholder">
                                    <i class="fas fa-robot"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="character-card__body">
                            <h3 class="character-card__name"><?php echo htmlspecialchars($character['name']); ?></h3>
                            <?php if (!empty($character['category_name'])): ?>
                                <span class="character-card__badge"><?php echo htmlspecialchars($character['category_name']); ?></span>
                            <?php endif; ?>
                            <p class="character-card__intro"><?php echo htmlspecialchars($character['introduction'] ?? '暂无介绍'); ?></p>
                            <div class="character-card__stats">
                                <span class="character-card__stat">
                                    <i class="fas fa-star"></i>
                                    <?php echo number_format($character['avg_rating'], 1); ?>
                                </span>
                                <span class="character-card__stat">
                                    <i class="fas fa-comments"></i>
                                    <?php echo $character['usage_count']; ?> 对话
                                </span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state__icon"><i class="fas fa-robot"></i></div>
                <div class="empty-state__title">暂无热门角色</div>
                <p class="empty-state__description">目前还没有公开的角色，请稍后再来查看。</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php if (!empty($notice_content)): ?>
<div class="notice-overlay" id="noticeOverlay">
    <div class="notice-modal" role="dialog" aria-modal="true" aria-labelledby="noticeTitle">
        <div class="notice-header">
            <h3 class="notice-title" id="noticeTitle">系统公告</h3>
            <button class="notice-close" id="noticeClose" type="button" aria-label="关闭公告">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="notice-content">
            <div class="notice-text"><?php echo nl2br(htmlspecialchars($notice_content)); ?></div>
        </div>
        <div class="notice-footer">
            <button class="button button--subtle" id="noticeCancel" type="button">暂时不再弹出</button>
            <button class="button button--primary" id="noticeConfirm" type="button">确认</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function startChat(characterId) {
    window.location.href = `Introduction?character_id=${characterId}`;
}

document.addEventListener('DOMContentLoaded', function() {
    const checkinBtn = document.getElementById('checkinBtn');
    const checkinMessage = document.getElementById('checkinMessage');

    if (checkinBtn && !checkinBtn.disabled) {
        checkinBtn.addEventListener('click', function() {
            window.location.href = '?action=checkin';
        });
    }

    if (checkinMessage) {
        checkinMessage.style.display = 'block';
        setTimeout(() => {
            checkinMessage.style.opacity = '0';
            setTimeout(() => {
                if (checkinMessage && checkinMessage.parentElement) {
                    checkinMessage.parentElement.removeChild(checkinMessage);
                }
            }, 300);
        }, 3000);
    }

    document.querySelectorAll('.filter-chip').forEach(chip => {
        chip.addEventListener('click', function() {
            const categoryId = this.getAttribute('data-category');
            const url = new URL(window.location.href);

            if (categoryId) {
                url.searchParams.set('category', categoryId);
            } else {
                url.searchParams.delete('category');
            }
            url.searchParams.delete('search');
            window.location.href = url.toString();
        });
    });

    <?php if (!empty($search_query) || !empty($category_id)): ?>
    const searchPanel = document.getElementById('searchPanel');
    if (searchPanel) {
        searchPanel.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
    <?php endif; ?>

    <?php if (!empty($notice_content)): ?>
    const noticeOverlay = document.getElementById('noticeOverlay');
    if (noticeOverlay) {
        const storedNotice = localStorage.getItem('hide_notice');
        const hideUntil = storedNotice ? parseInt(storedNotice, 10) : 0;

        if (!storedNotice || Number.isNaN(hideUntil) || hideUntil <= Date.now()) {
            if (storedNotice && hideUntil <= Date.now()) {
                localStorage.removeItem('hide_notice');
            }

            setTimeout(() => {
                noticeOverlay.classList.add('active');
            }, 500);

            const noticeClose = document.getElementById('noticeClose');
            const noticeConfirm = document.getElementById('noticeConfirm');
            const noticeCancel = document.getElementById('noticeCancel');

            const hideOverlay = () => noticeOverlay.classList.remove('active');

            if (noticeClose) {
                noticeClose.addEventListener('click', hideOverlay);
            }

            if (noticeConfirm) {
                noticeConfirm.addEventListener('click', hideOverlay);
            }

            if (noticeCancel) {
                noticeCancel.addEventListener('click', function() {
                    const expireTime = Date.now() + (24 * 60 * 60 * 1000);
                    localStorage.setItem('hide_notice', expireTime.toString());
                    hideOverlay();
                });
            }

            noticeOverlay.addEventListener('click', function(event) {
                if (event.target === noticeOverlay) {
                    hideOverlay();
                }
            });
        }
    }
    <?php endif; ?>
});

window.addEventListener('load', function() {
    const storedNotice = localStorage.getItem('hide_notice');
    if (storedNotice) {
        const hideUntil = parseInt(storedNotice, 10);
        if (Number.isNaN(hideUntil) || hideUntil <= Date.now()) {
            localStorage.removeItem('hide_notice');
        }
    }
});
</script>

<?php
$content = ob_get_clean();
include 'navbar.php';
?>

