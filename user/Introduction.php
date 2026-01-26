<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-7
最后编辑时间：2025-11-15
文件描述：角色信息页面，角色详细信息，评分评论功能

*/
session_start();
require_once(__DIR__ . './../config/config.php');

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

// 获取角色ID并验证
$character_id = isset($_GET['character_id']) ? intval($_GET['character_id']) : 0;

if ($character_id <= 0) {
    header('Location: characters');
    exit;
}

// 获取角色详细信息
$character = [];
$category_name = '';
$creator_name = '';
$is_subscribed = false;
$user_rating = 0;
$user_comment = '';

try {
    // 使用预处理语句防止SQL注入
    $stmt = $db->prepare("
        SELECT 
            ac.*, 
            cc.name as category_name,
            u.name as creator_name
        FROM ai_character ac 
        LEFT JOIN character_category cc ON ac.category_id = cc.id 
        LEFT JOIN user u ON ac.user_id = u.id
        WHERE ac.id = ? AND ac.status = 1 AND ac.is_public = 1
    ");
    $stmt->execute([$character_id]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$character) {
        // 角色不存在或无权访问
        header('Location: characters');
        exit;
    }
    
    // 检查用户是否已订阅该角色
    $user_id = $_SESSION['user_id'];
    $stmt = $db->prepare("
        SELECT id FROM character_subscription 
        WHERE user_id = ? AND character_id = ? AND status = 1
    ");
    $stmt->execute([$user_id, $character_id]);
    $is_subscribed = $stmt->fetch() ? true : false;
    
    // 获取用户对该角色的评分和评论
    $stmt = $db->prepare("
        SELECT rating, comment FROM character_rating 
        WHERE user_id = ? AND character_id = ?
    ");
    $stmt->execute([$user_id, $character_id]);
    $rating_result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rating_result) {
        $user_rating = intval($rating_result['rating']);
        $user_comment = $rating_result['comment'] ?? '';
    }
    
} catch (PDOException $e) {
    error_log("数据库查询错误: " . $e->getMessage());
    header('Location: characters');
    exit;
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

<div class="app-main__content">
    <!-- 顶部导航 -->
    <section class="section-card hero-card">
        <div class="section-card__header">
            <a href="characters" class="button button--ghost">
                <i class="fas fa-arrow-left"></i>
                返回角色广场
            </a>
            <div class="character-detail-header__actions">
                <button class="button <?php echo $is_subscribed ? 'button--subscribed' : 'button--subtle'; ?>" id="subscribeBtn" data-character-id="<?php echo $character_id; ?>">
                    <i class="fas fa-star"></i>
                    <?php echo $is_subscribed ? '已订阅' : '订阅角色'; ?>
                </button>
                <button class="button button--primary" id="startChatBtn">
                    <i class="fas fa-comments"></i>
                    开始聊天
                </button>
            </div>
        </div>
    </section>
    
    <!-- 主要内容 -->
        <div class="character-detail-layout">
            <!-- 左侧信息区域 -->
        <section class="section-card character-main-card">
                <!-- 角色头部 -->
                <div class="character-hero">
                    <?php if (!empty($character['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($character['avatar']); ?>" alt="<?php echo htmlspecialchars($character['name']); ?>" class="character-hero__img">
                    <?php else: ?>
                    <div class="character-hero__placeholder">
                        <i class="fas fa-robot"></i>
                        </div>
                    <?php endif; ?>
                    <div class="character-hero__overlay">
                        <h1 class="character-hero__title"><?php echo htmlspecialchars($character['name']); ?></h1>
                        <div class="character-hero__meta">
                            <span class="character-hero__meta-item">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($character['category_name']); ?>
                            </span>
                            <span class="character-hero__meta-item">
                                <i class="fas fa-comments"></i>
                                <?php echo $character['usage_count']; ?> 次对话
                            </span>
                            <span class="character-hero__meta-item">
                                <i class="fas fa-star"></i>
                                评分 <?php echo number_format($character['avg_rating'], 1); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- 详细信息 -->
            <div class="character-info-section">
                    <!-- 角色介绍 -->
                <div class="character-info-section__item">
                        <h3 class="character-info-section__title">
                            <i class="fas fa-info-circle"></i>
                            角色介绍
                        </h3>
                        <div class="character-description">
                            <?php echo nl2br(htmlspecialchars($character['introduction'] ?? '这个角色还没有详细的介绍信息。')); ?>
                        </div>
                    </div>
                    
                    <!-- 角色统计 -->
                <div class="character-info-section__item">
                        <h3 class="character-info-section__title">
                            <i class="fas fa-chart-bar"></i>
                            角色统计
                        </h3>
                        <div class="character-stats-grid">
                            <div class="character-stat-card">
                                <div class="character-stat-label">使用次数</div>
                                <div class="character-stat-value"><?php echo $character['usage_count']; ?></div>
                            </div>
                            <div class="character-stat-card">
                                <div class="character-stat-label">平均评分</div>
                                <div class="character-stat-value"><?php echo number_format($character['avg_rating'], 1); ?></div>
                            </div>
                            <div class="character-stat-card">
                                <div class="character-stat-label">创建时间</div>
                                <div class="character-stat-value"><?php echo date('Y-m-d', strtotime($character['create_time'])); ?></div>
                            </div>
                            <div class="character-stat-card">
                                <div class="character-stat-label">最后更新</div>
                                <div class="character-stat-value"><?php echo date('Y-m-d', strtotime($character['update_time'])); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- 右侧边栏 -->
            <div class="character-sidebar">
                <!-- 创建者信息 -->
                <section class="section-card">
                <h3 class="section-card__title">
                        <i class="fas fa-user"></i>
                        创建者信息
                    </h3>
                    <div class="creator-info">
                        <div class="creator-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="creator-details">
                            <h4><?php echo htmlspecialchars($character['creator_name']); ?></h4>
                            <p>角色创建者</p>
                        </div>
                    </div>
                </section>
                
                <!-- 评分区域 -->
                <section class="section-card">
                <h3 class="section-card__title">
                        <i class="fas fa-star"></i>
                        角色评分
                    </h3>
                    <div class="rating-section">
                        <div class="rating-display"><?php echo number_format($character['avg_rating'], 1); ?></div>
                        <div class="rating-stars" id="ratingStars">
                            <?php 
                            $avg_rating = floatval($character['avg_rating']);
                            for ($i = 1; $i <= 5; $i++): 
                                $is_active = $i <= round($avg_rating);
                            ?>
                                <span class="rating-star readonly <?php echo $is_active ? 'active' : ''; ?>">
                                    <i class="fas fa-star"></i>
                                </span>
                            <?php endfor; ?>
                        </div>
                        <div class="rating-count">基于用户评价</div>
                    </div>
                </section>
                
                <!-- 角色标签 -->
                <section class="section-card">
                <h3 class="section-card__title">
                        <i class="fas fa-tags"></i>
                        角色标签
                    </h3>
                    <div class="tags-container">
                    <span class="filter-chip"><?php echo htmlspecialchars($character['category_name']); ?></span>
                        <?php if ($character['avg_rating'] >= 4.0): ?>
                        <span class="filter-chip" style="background: #fef3c7; color: #d97706;">高评分</span>
                        <?php endif; ?>
                        <?php if ($character['usage_count'] > 100): ?>
                        <span class="filter-chip" style="background: #dcfce7; color: #16a34a;">热门</span>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
        
        <!-- 评价区域 -->
    <section class="section-card reviews-section">
        <h2 class="section-card__title">
                <i class="fas fa-comments"></i>
                用户评价
            </h2>
            
            <!-- 评价表单 -->
            <div class="review-form">
            <h3 class="section-card__subtitle">
                    <?php echo $user_rating ? '修改我的评价' : '发表评价'; ?>
                </h3>
                <form id="reviewForm">
                <div class="form-group">
                        <label class="auth-label">评分</label>
                        <div class="rating-input" id="reviewRating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="rating-input-star <?php echo $i <= $user_rating ? 'active' : ''; ?>" data-rating="<?php echo $i; ?>">
                                    <i class="fas fa-star"></i>
                                </span>
                            <?php endfor; ?>
                        </div>
                    </div>
                <div class="form-group">
                        <label class="auth-label">评论（可选）</label>
                    <textarea class="auth-input review-comment-input" id="reviewComment" placeholder="分享您对这个角色的使用体验..." rows="4"><?php echo htmlspecialchars($user_comment); ?></textarea>
                    </div>
                    <button type="submit" class="button button--primary" id="submitReviewBtn">
                        <?php echo $user_rating ? '更新评价' : '提交评价'; ?>
                    </button>
                </form>
            </div>
            
            <!-- 评价列表 -->
        <div class="reviews-list" id="reviewsList">
            <!-- 评价将通过JavaScript动态加载 -->
        </div>
            
            <!-- 加载更多按钮 -->
            <div class="load-more-reviews">
                <button class="button button--ghost" id="loadMoreBtn">加载更多评价</button>
            </div>
        </section>
</div>

<!-- 回到顶部按钮 -->
<button class="back-to-top" id="backToTop">
    <i class="fas fa-chevron-up"></i>
</button>

<script>
    // JavaScript代码保持不变...
    // 全局变量
    let currentPage = 1;
    let isLoading = false;
    let hasMoreReviews = true;
    let currentUserRating = <?php echo $user_rating; ?>;
    
    // 订阅功能
    document.getElementById('subscribeBtn').addEventListener('click', function() {
        const characterId = this.getAttribute('data-character-id');
        const isSubscribed = this.classList.contains('button--subscribed');
        const btn = this;
        
        // 发送订阅请求
        fetch('./api/subscribe.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `character_id=${characterId}&action=${isSubscribed ? 'unsubscribe' : 'subscribe'}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (isSubscribed) {
                    btn.classList.remove('button--subscribed');
                    btn.classList.add('button--subtle');
                    btn.innerHTML = '<i class="fas fa-star"></i> 订阅角色';
                    showMessage('已取消订阅', 'success');
                } else {
                    btn.classList.remove('button--subtle');
                    btn.classList.add('button--subscribed');
                    btn.innerHTML = '<i class="fas fa-star"></i> 已订阅';
                    showMessage('订阅成功', 'success');
                }
            } else {
                showMessage(data.message || '操作失败', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('网络错误，请稍后重试', 'error');
        });
    });
    
    // 初始订阅状态
    <?php if ($is_subscribed): ?>
        document.getElementById('subscribeBtn').classList.add('button--subscribed');
    <?php endif; ?>
    
    // 开始聊天
    document.getElementById('startChatBtn').addEventListener('click', function() {
        window.location.href = `chat?character_id=<?php echo $character_id; ?>`;
    });
    
    // 评价表单评分
    document.querySelectorAll('#reviewRating .rating-input-star').forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            currentUserRating = rating;
            
            // 更新UI
            document.querySelectorAll('#reviewRating .rating-input-star').forEach(s => {
                const starRating = parseInt(s.getAttribute('data-rating'));
                if (starRating <= rating) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });
        });
        
        // 悬停效果
        star.addEventListener('mouseenter', function() {
            const rating = parseInt(this.getAttribute('data-rating'));
            document.querySelectorAll('#reviewRating .rating-input-star').forEach(s => {
                const starRating = parseInt(s.getAttribute('data-rating'));
                if (starRating <= rating) {
                    s.style.color = '#fbbf24';
                }
            });
        });
        
        star.addEventListener('mouseleave', function() {
            document.querySelectorAll('#reviewRating .rating-input-star').forEach(s => {
                const starRating = parseInt(s.getAttribute('data-rating'));
                if (starRating <= currentUserRating) {
                    s.style.color = '#f59e0b';
                } else {
                    s.style.color = '#e5e7eb';
                }
            });
        });
    });
    
    // 提交评价表单
    document.getElementById('reviewForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const characterId = <?php echo $character_id; ?>;
        const rating = currentUserRating;
        const comment = document.getElementById('reviewComment').value.trim();
        
        if (rating === 0) {
            showMessage('请选择评分', 'error');
            return;
        }
        
        const submitBtn = document.getElementById('submitReviewBtn');
        submitBtn.disabled = true;
        submitBtn.textContent = '提交中...';
        
        // 发送评价请求
        fetch('./api/submit_review.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `character_id=${characterId}&rating=${rating}&comment=${encodeURIComponent(comment)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('评价提交成功', 'success');
                // 更新平均评分显示
                if (data.new_avg_rating) {
                    const avgRating = parseFloat(data.new_avg_rating).toFixed(1);
                    document.querySelector('.rating-display').textContent = avgRating;
                    // 更新角色评分星星
                    updateRatingStars(data.new_avg_rating);
                    // 更新角色头部显示的评分
                    const heroMetaItems = document.querySelectorAll('.character-hero__meta-item');
                    heroMetaItems.forEach(item => {
                        if (item.querySelector('.fa-star')) {
                            item.innerHTML = `<i class="fas fa-star"></i> 评分 ${avgRating}`;
                        }
                    });
                }
                // 更新用户评价表单中的评分显示
                if (data.user_rating) {
                    currentUserRating = parseInt(data.user_rating);
                    document.querySelectorAll('#reviewRating .rating-input-star').forEach(s => {
                        const starRating = parseInt(s.getAttribute('data-rating'));
                        if (starRating <= currentUserRating) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                }
                // 更新评价表单标题
                const reviewFormTitle = document.querySelector('.section-card__subtitle');
                if (reviewFormTitle) {
                    reviewFormTitle.textContent = '修改我的评价';
                }
                // 重新加载评价列表
                currentPage = 1;
                document.getElementById('reviewsList').innerHTML = '';
                loadReviews();
                
                // 更新按钮文本
                submitBtn.textContent = '更新评价';
            } else {
                showMessage(data.message || '评价提交失败', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('网络错误，请稍后重试', 'error');
        })
        .finally(() => {
            submitBtn.disabled = false;
        });
    });
    
    // 更新角色评分星星显示
    function updateRatingStars(avgRating) {
        const stars = document.querySelectorAll('#ratingStars .rating-star');
        const roundedRating = Math.round(parseFloat(avgRating));
        
        stars.forEach((star, index) => {
            if (index < roundedRating) {
                star.classList.add('active');
            } else {
                star.classList.remove('active');
            }
        });
    }
    
    // 加载评价
    function loadReviews() {
        if (isLoading || !hasMoreReviews) return;
        
        isLoading = true;
        const characterId = <?php echo $character_id; ?>;
        const loadMoreBtn = document.getElementById('loadMoreBtn');
        
        if (currentPage === 1) {
            loadMoreBtn.disabled = true;
            loadMoreBtn.textContent = '加载中...';
        }
        
        fetch(`./api/get_reviews.php?character_id=${characterId}&page=${currentPage}&limit=10`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const reviewsList = document.getElementById('reviewsList');
                    
                    if (data.reviews.length === 0 && currentPage === 1) {
                        // 没有评价
                        reviewsList.innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-comment-slash empty-state__icon"></i>
                                <h3 class="empty-state__title">暂无评价</h3>
                                <p class="empty-state__description">成为第一个评价这个角色的人吧！</p>
                            </div>
                        `;
                        hasMoreReviews = false;
                        loadMoreBtn.style.display = 'none';
                    } else if (data.reviews.length > 0) {
                        // 添加评价到列表
                        data.reviews.forEach(review => {
                            const reviewElement = createReviewElement(review);
                            reviewsList.appendChild(reviewElement);
                        });
                        
                        // 检查是否还有更多评价
                        hasMoreReviews = data.has_more;
                        if (!hasMoreReviews) {
                            loadMoreBtn.style.display = 'none';
                        } else {
                            currentPage++;
                        }
                    }
                } else {
                    showMessage('加载评价失败', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('网络错误，请稍后重试', 'error');
            })
            .finally(() => {
                isLoading = false;
                loadMoreBtn.disabled = false;
                loadMoreBtn.textContent = '加载更多评价';
            });
    }
    
    // 创建评价元素
    function createReviewElement(review) {
        const reviewElement = document.createElement('div');
        reviewElement.className = 'review-item';
        
        const rating = Number(review.rating);
        
        // 生成星星HTML
        let starsHtml = '';
        for (let i = 1; i <= 5; i++) {
            const isActive = i <= rating;
            starsHtml += `<i class="fas fa-star review-star ${isActive ? 'active' : ''}"></i>`;
        }
        
        // 处理评论内容，去除前导和尾随的空白字符
        let commentContent = review.comment ? review.comment.trim() : '用户没有留下评论。';
        
        // 处理用户头像
        const userAvatar = review.user_avatar || '/static/user-images/user.png';
        
        reviewElement.innerHTML = `
            <div class="review-header">
                <div class="reviewer-info">
                    <div class="reviewer-avatar">
                        <img src="${userAvatar}" alt="${review.user_name}" onerror="this.src='/static/user-images/user.png'">
                    </div>
                    <div class="reviewer-details">
                        <h4>${review.user_name}</h4>
                    </div>
                </div>
                <div class="review-rating">
                    ${starsHtml}
                </div>
            </div>
            <div class="review-content">${commentContent}</div>
            <div class="review-date">${review.create_time}</div>
        `;
        
        return reviewElement;
    }
    
    // 加载更多评价
    document.getElementById('loadMoreBtn').addEventListener('click', loadReviews);
    
    // 回到顶部功能
    const backToTopBtn = document.getElementById('backToTop');
    
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTopBtn.classList.add('visible');
        } else {
            backToTopBtn.classList.remove('visible');
        }
    });
    
    backToTopBtn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    
    // 显示消息提示
    function showMessage(message, type) {
        const messageEl = document.createElement('div');
        messageEl.textContent = message;
        messageEl.className = 'alert-banner';
        
        // 根据类型添加相应的样式类
        if (type === 'error') {
            messageEl.classList.add('alert-banner--error');
        } else if (type === 'success') {
            messageEl.classList.add('alert-banner--success');
        } else if (type === 'warning') {
            messageEl.classList.add('alert-banner--warning');
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
        // 加载初始评价
        loadReviews();
        
        // 监听滚动加载更多
        window.addEventListener('scroll', function() {
            if (isLoading || !hasMoreReviews) return;
            
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const windowHeight = window.innerHeight;
            const documentHeight = document.documentElement.scrollHeight;
            
            // 当滚动到页面底部50px内时加载更多
            if (scrollTop + windowHeight >= documentHeight - 50) {
                loadReviews();
            }
        });
    });
</script>

<?php
$content = ob_get_clean();
include 'navbar.php';
?>