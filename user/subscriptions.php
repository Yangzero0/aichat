<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-17
最后编辑时间：2025-10-18
文件描述：用户订阅管理页面

*/
session_start();
require_once(__DIR__ . './../config/config.php');

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$user_id = $_SESSION['user_id'];

// 获取用户的订阅角色
$subscribed_characters = [];
try {
    $stmt = $db->prepare("
        SELECT 
            cs.subscribe_time,
            ac.id,
            ac.name,
            ac.avatar,
            ac.introduction,
            ac.usage_count,
            ac.avg_rating,
            cc.name as category_name,
            u.name as creator_name
        FROM character_subscription cs
        INNER JOIN ai_character ac ON cs.character_id = ac.id
        LEFT JOIN character_category cc ON ac.category_id = cc.id
        LEFT JOIN user u ON ac.user_id = u.id
        WHERE cs.user_id = ? AND cs.status = 1 AND ac.status = 1
        ORDER BY cs.subscribe_time DESC
    ");
    $stmt->execute([$user_id]);
    $subscribed_characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取订阅角色失败: " . $e->getMessage());
}

// 获取网站配置
$settings = [];
try {
    $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取配置失败: " . $e->getMessage());
}

ob_start();
?>

            <section class="section-card hero-card">
                <div class="section-card__header">
                    <div>
                        <h1 class="section-card__title">
                            <i class="fas fa-star"></i>
                            我的订阅
                        </h1>
                        <p class="section-card__description">您订阅的所有AI角色都在这里</p>
                    </div>
                    <a href="characters" class="button button--primary">
                        <i class="fas fa-robot"></i>
                        浏览角色广场
                    </a>
                </div>
            </section>
            
<div class="app-main__content">
            <?php if (empty($subscribed_characters)): ?>
                <!-- 空状态 -->
                <section class="section-card empty-state">
                    <i class="fas fa-star empty-state__icon"></i>
                    <h3 class="empty-state__title">暂无订阅</h3>
                    <p class="empty-state__description">您还没有订阅任何AI角色，快去角色广场发现有趣的AI角色吧！</p>
                    <a href="characters" class="button button--primary">
                        <i class="fas fa-robot"></i>
                        浏览角色广场
                    </a>
                </section>
            <?php else: ?>
                <!-- 订阅角色网格 -->
                <div class="card-grid">
                    <?php foreach ($subscribed_characters as $character): ?>
                <div class="character-card" onclick="window.location.href='Introduction?character_id=<?php echo $character['id']; ?>'">
                    <!-- 收藏时间 -->
                            <div class="subscribe-time">
                                <i class="fas fa-clock"></i>
                                <?php echo date('Y-m-d', strtotime($character['subscribe_time'])); ?>
                            </div>
                            
                    <!-- 角色头像 -->
                            <div class="character-card__cover">
                        <?php if (!empty($character['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($character['avatar']); ?>" alt="<?php echo htmlspecialchars($character['name']); ?>" onerror="this.src='/static/ai-images/ai.png'">
                        <?php else: ?>
                            <div class="character-card__placeholder">
                                <i class="fas fa-robot"></i>
                            </div>
                        <?php endif; ?>
                            </div>
                            
                            <!-- 角色信息 -->
                            <div class="character-card__body">
                                    <h3 class="character-card__name"><?php echo htmlspecialchars($character['name']); ?></h3>
                                
                                <?php if (!empty($character['category_name'])): ?>
                                    <span class="filter-chip"><?php echo htmlspecialchars($character['category_name']); ?></span>
                                <?php endif; ?>
                                
                                <p class="character-card__intro">
                                    <?php echo htmlspecialchars($character['introduction'] ?? '这个角色还没有详细的介绍信息。'); ?>
                                </p>
                                
                        <div class="character-card__stats">
                            <div class="character-card__stats-left">
                                    <span class="character-rating">
                                        <i class="fas fa-star"></i> 
                                        <?php echo number_format($character['avg_rating'], 1); ?>
                                    </span>

                                </div>
                            <button class="button button--danger button--small" onclick="event.stopPropagation(); unsubscribeCharacter(<?php echo $character['id']; ?>, this)">
                                <i class="fas fa-times"></i>
                                取消订阅
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
</div>

<script>
    // 取消订阅功能
    function unsubscribeCharacter(characterId, button) {
        if (!confirm('确定要取消订阅这个角色吗？')) {
            return;
        }
        
        // 禁用按钮防止重复点击
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 取消中...';
        
        // 发送取消订阅请求
        fetch('./api/subscribe.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `character_id=${characterId}&action=unsubscribe`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 移除角色卡片
                const card = button.closest('.character-card');
                card.style.opacity = '0';
                card.style.transform = 'scale(0.8)';
                
                setTimeout(() => {
                    card.remove();
                    
                    // 检查是否还有订阅角色
                    const remainingCards = document.querySelectorAll('.character-card');
                    if (remainingCards.length === 0) {
                        // 显示空状态
                        showEmptyState();
                    }
                    
                    showMessage('取消订阅成功', 'success');
                }, 300);
            } else {
                showMessage(data.message || '取消订阅失败', 'error');
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-times"></i> 取消订阅';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('网络错误，请稍后重试', 'error');
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-times"></i> 取消订阅';
        });
    }
    
    // 显示空状态
    function showEmptyState() {
        const container = document.querySelector('.app-main__content');
        container.innerHTML = `
            <section class="section-card empty-state">
            <i class="fas fa-star empty-state__icon"></i>
            <h3 class="empty-state__title">暂无订阅</h3>
            <p class="empty-state__description">您还没有订阅任何AI角色，快去角色广场发现有趣的AI角色吧！</p>
            <a href="characters" class="button button--primary">
                <i class="fas fa-robot"></i>
                浏览角色广场
            </a>
            </section>
        `;
    }
    
    // 显示消息提示
    function showMessage(message, type) {
        const messageEl = document.createElement('div');
        messageEl.textContent = message;
        messageEl.className = 'alert-banner';
        if (type === 'error') {
            messageEl.classList.add('alert-banner--error');
        }
        
        document.body.appendChild(messageEl);
        
        // 显示动画
        setTimeout(() => {
            messageEl.classList.add('show');
        }, 100);
        
        // 自动隐藏
        setTimeout(() => {
            messageEl.classList.remove('show');
            setTimeout(() => {
                if (document.body.contains(messageEl)) {
                    document.body.removeChild(messageEl);
                }
            }, 300);
        }, 2500);
    }
    
    // 页面加载完成后的初始化
    document.addEventListener('DOMContentLoaded', function() {
        // 添加卡片点击事件（委托）
        document.addEventListener('click', function(e) {
            const card = e.target.closest('.character-card');
            const button = e.target.closest('button');
            if (card && button && button.classList.contains('button--danger')) {
                return; // 点击取消订阅按钮时不跳转
            }
            if (card) {
                const onclickAttr = card.getAttribute('onclick');
                if (onclickAttr) {
                    const match = onclickAttr.match(/window\.location\.href='([^']+)'/);
                    if (match && match[1]) {
                        window.location.href = match[1];
                    }
                }
            }
        });
    });
</script>

<?php
$content = ob_get_clean();
include 'navbar.php';
?>