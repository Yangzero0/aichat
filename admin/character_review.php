<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-17
最后编辑时间：2025-10-19
文件描述：角色审核列表页面，可以审核用户创建的角色信息
*/
session_start();
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

// 处理审核操作 & 审核设置
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 单个角色通过 / 拒绝
    if (isset($_POST['action']) && isset($_POST['character_id'])) {
        $character_id = intval($_POST['character_id']);
        $action = $_POST['action'];
        
        try {
            if ($action === 'approve') {
                $status = 1;
                $message = "角色审核通过";
            } elseif ($action === 'reject') {
                $status = 2;
                $message = "角色审核失败";
            } else {
                throw new Exception("无效的操作");
            }
            
            $stmt = $db->prepare("UPDATE ai_character SET status = ?, update_time = NOW() WHERE id = ?");
            $stmt->execute([$status, $character_id]);
            
            $_SESSION['success_message'] = $message . "成功";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "操作失败: " . $e->getMessage();
        }
        
        header("Location: character_review.php");
        exit;
    }

    // 保存 AI 审核设置
    if (isset($_POST['save_review_settings'])) {
        try {
            $ai_review_enabled = isset($_POST['ai_review_enabled']) ? 1 : 0;
            $ai_review_rules = isset($_POST['ai_review_rules']) ? trim($_POST['ai_review_rules']) : '';

            // 获取当前配置
            $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
            $current_settings = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($current_settings) {
                $stmt = $db->prepare("UPDATE web_setting SET ai_review_enabled = ?, ai_review_rules = ?, update_time = NOW() WHERE id = ?");
                $stmt->execute([$ai_review_enabled, $ai_review_rules, $current_settings['id']]);
            } else {
                // 无配置时创建一条只包含审核字段的记录
                $stmt = $db->prepare("INSERT INTO web_setting (ai_review_enabled, ai_review_rules, update_time) VALUES (?, ?, NOW())");
                $stmt->execute([$ai_review_enabled, $ai_review_rules]);
            }

            $_SESSION['success_message'] = "审核设置保存成功";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "保存失败: " . $e->getMessage();
        }

        header("Location: character_review.php");
        exit;
    }
}

// 获取筛选条件
$filter_status = isset($_GET['status']) ? intval($_GET['status']) : 0;
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

// 构建查询条件
$where_conditions = [];
$params = [];

if ($filter_status !== '') {
    $where_conditions[] = "ac.status = ?";
    $params[] = $filter_status;
}

if (!empty($search_keyword)) {
    $where_conditions[] = "(ac.name LIKE ? OR u.username LIKE ? OR cc.name LIKE ?)";
    $params[] = "%$search_keyword%";
    $params[] = "%$search_keyword%";
    $params[] = "%$search_keyword%";
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(" AND ", $where_conditions);
}

// 获取角色列表
$characters = [];
try {
    $sql = "SELECT ac.*, u.username as creator_name, cc.name as category_name 
            FROM ai_character ac 
            LEFT JOIN user u ON ac.user_id = u.id 
            LEFT JOIN character_category cc ON ac.category_id = cc.id 
            $where_sql 
            ORDER BY 
                CASE WHEN ac.status = 0 THEN 0 ELSE 1 END,
                ac.create_time DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取角色列表失败: " . $e->getMessage());
}

// 获取网站配置
$settings = [];
try {
    $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取网站配置失败: " . $e->getMessage());
}

// 包含导航栏
include 'navbar.php';
?>

<!-- 修改页面标题 -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelector('.page-title').textContent = '角色审核';
});
</script>

<div class="content-wrapper">
    <!-- 成功/错误消息提示 -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <!-- AI审核操作栏 -->
    <div class="ai-review-section" style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <button id="aiReviewBtn" type="button" 
                        style="background: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 0.875rem; transition: background 0.3s; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-robot"></i> 一键AI审核
                </button>
                <button id="reviewSettingsBtn" type="button" 
                        style="background: var(--light-color); color: var(--text-primary); border: 1px solid var(--border-color); padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 0.875rem; transition: background 0.3s; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fas fa-cog"></i> 审核设置
                    <i class="fas fa-chevron-down" id="settingsChevron" style="font-size: 0.75rem; transition: transform 0.3s;"></i>
                </button>
            </div>
            <div id="aiReviewStatus" style="display: none; padding: 8px 16px; background: var(--light-color); border-radius: 6px; font-size: 0.875rem;">
                <span id="reviewProgress">正在审核中...</span>
            </div>
        </div>

        <!-- 审核设置展开区域 -->
        <div id="reviewSettingsPanel" style="display: none; padding-top: 16px; border-top: 1px solid var(--border-color); margin-top: 8px;">
            <form method="POST" action="character_review.php">
                <div style="margin-bottom: 16px;">
                    <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">
                        <input type="checkbox" name="ai_review_enabled" value="1" 
                               <?php echo (isset($settings['ai_review_enabled']) && $settings['ai_review_enabled'] == 1) ? 'checked' : ''; ?>
                               style="width: 18px; height: 18px; cursor: pointer;">
                        <span>启用AI自动审核</span>
                    </label>
                    <p style="margin: 4px 0 0 26px; font-size: 0.875rem; color: var(--text-secondary);">
                        开启后，系统将自动审核待审核的角色内容（通过计划任务定时访问自动审核URL）。
                    </p>
                </div>

                <?php
                // 获取网站URL，用于显示自动审核URL
                $site_url = isset($settings['url']) ? rtrim($settings['url'], '/') : '';
                if (empty($site_url)) {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $script_path = $_SERVER['SCRIPT_NAME'] ?? '';
                    // 从 /admin/character_review.php 获取站点根路径
                    $base_path = str_replace('/admin/character_review.php', '', $script_path);
                    $base_path = rtrim($base_path, '/');
                    $site_url = $protocol . '://' . $host . $base_path;
                }
                $auto_review_url = $site_url . '/admin/api/ai_review.php?action=auto_review';
                ?>

                <div style="margin-bottom: 16px; padding: 12px; background: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 6px;">
                    <h4 style="margin: 0 0 8px 0; font-size: 0.9rem; font-weight: 600; color: var(--text-primary);">
                        <i class="fas fa-info-circle" style="color: #3b82f6;"></i> 如何设置AI自动审核（宝塔计划任务）
                    </h4>
                    <ol style="margin: 0; padding-left: 20px; font-size: 0.85rem; color: var(--text-primary); line-height: 1.8;">
                        <li>登录 <strong>宝塔面板</strong></li>
                        <li>进入 <strong>计划任务</strong></li>
                        <li>点击 <strong>添加任务</strong></li>
                        <li>任务类型选择 <strong>访问URL</strong></li>
                        <li>执行周期选择 <strong>每天</strong>（可根据需要调整时间）</li>
                        <li>在URL地址中输入下面的自动审核地址：</li>
                    </ol>
                    <div style="margin-top: 12px; padding: 10px; background: white; border: 1px solid #d1d5db; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 0.8rem; word-break: break-all;">
                        <strong style="color: var(--text-primary);">自动审核URL：</strong><br>
                        <span id="autoReviewUrl" style="color: #3b82f6; user-select: all;"><?php echo htmlspecialchars($auto_review_url); ?></span>
                        <button type="button" onclick="copyAutoReviewUrl()" 
                                style="margin-left: 8px; padding: 4px 8px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.75rem;">
                            <i class="fas fa-copy"></i> 复制
                        </button>
                    </div>
                    <p style="margin: 8px 0 0 0; font-size: 0.75rem; color: var(--text-secondary);">
                        开启自动审核后，系统会在计划任务触发时，自动审核当前所有<strong>待审核</strong>的角色。
                    </p>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">
                        AI审核规则 / 拒绝类型说明
                    </label>
                    <textarea name="ai_review_rules" rows="6" 
                              placeholder="请输入AI审核规则，告诉AI哪些类型的角色应该被拒绝。例如：&#10;1. 包含色情、暴力、政治敏感内容&#10;2. 涉及违法、违规内容&#10;3. 恶意攻击、歧视性内容&#10;4. 内容无意义、过于简单、缺乏实质性内容&#10;5. 测试内容、占位符、乱码等&#10;6. 其他不符合平台规范的内容"
                              style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.875rem; font-family: inherit; resize: vertical;"><?php echo htmlspecialchars($settings['ai_review_rules'] ?? ''); ?></textarea>
                    <p style="margin-top: 4px; font-size: 0.75rem; color: var(--text-secondary);">
                        这些规则将指导AI判断哪些角色应该被拒绝审核。AI会自动识别并拒绝无意义、过于简单或缺乏实质性内容的角色。
                    </p>
                </div>

                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" name="save_review_settings" 
                            style="background: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 0.875rem;">
                        <i class="fas fa-save"></i> 保存设置
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 筛选和搜索 -->
    <div class="filter-section" style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <form method="GET" action="character_review.php">
            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 16px; align-items: end;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">审核状态</label>
                    <select name="status" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; background: white;">
                        <option value="">全部状态</option>
                        <option value="0" <?php echo $filter_status === 0 ? 'selected' : ''; ?>>待审核</option>
                        <option value="1" <?php echo $filter_status === 1 ? 'selected' : ''; ?>>已通过</option>
                        <option value="2" <?php echo $filter_status === 2 ? 'selected' : ''; ?>>未通过</option>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-primary);">搜索</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search_keyword); ?>" 
                           placeholder="搜索角色名称、创建者或分类..." 
                           style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px;">
                </div>
                
                <div>
                    <button type="submit" style="background: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; transition: background 0.3s;">
                        <i class="fas fa-search"></i> 搜索
                    </button>
                    <a href="character_review.php" style="margin-left: 10px; padding: 10px 20px; background: var(--light-color); color: var(--text-primary); text-decoration: none; border-radius: 6px; display: inline-block;">
                        重置
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- 角色列表 -->
    <div class="characters-list">
        <?php if (empty($characters)): ?>
            <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                <i class="fas fa-robot" style="font-size: 48px; color: #d1d5db; margin-bottom: 16px;"></i>
                <h3 style="color: var(--text-secondary); margin-bottom: 8px;">暂无角色</h3>
                <p style="color: var(--text-secondary);">没有找到符合条件的角色</p>
            </div>
        <?php else: ?>
            <div style="display: grid; gap: 16px;">
                <?php foreach ($characters as $character): ?>
                    <div class="character-item" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-left: 4px solid <?php 
                        echo $character['status'] == 0 ? 'var(--warning-color)' : 
                             ($character['status'] == 1 ? 'var(--success-color)' : 'var(--danger-color)'); 
                    ?>;">
                        <div style="display: flex; justify-content: between; align-items: start; gap: 20px;">
                            <!-- 角色信息 -->
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                                    <?php if (!empty($character['avatar'])): ?>
                                        <img src="<?php echo htmlspecialchars($character['avatar']); ?>" 
                                             alt="<?php echo htmlspecialchars($character['name']); ?>" 
                                             style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 50px; height: 50px; border-radius: 8px; background: var(--light-color); display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-robot" style="color: var(--text-secondary);"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div>
                                        <h3 style="margin: 0 0 4px 0; font-size: 1.1rem; color: var(--text-primary);">
                                            <?php echo htmlspecialchars($character['name']); ?>
                                        </h3>
                                        <div style="display: flex; gap: 16px; font-size: 0.875rem; color: var(--text-secondary);">
                                            <span>创建者: <?php echo htmlspecialchars($character['creator_name'] ?? '未知'); ?></span>
                                            <span>分类: <?php echo htmlspecialchars($character['category_name'] ?? '未分类'); ?></span>
                                            <span>创建时间: <?php echo date('Y-m-d H:i', strtotime($character['create_time'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($character['introduction'])): ?>
                                    <p style="margin: 0 0 12px 0; color: var(--text-primary); line-height: 1.5;">
                                        <?php echo htmlspecialchars(mb_substr($character['introduction'], 0, 100) . (mb_strlen($character['introduction']) > 100 ? '...' : '')); ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <span class="status-badge" style="padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">
                                        <?php 
                                        $status_text = ['待审核', '已通过', '未通过'];
                                        $status_colors = ['var(--warning-color)', 'var(--success-color)', 'var(--danger-color)'];
                                        echo $status_text[$character['status']];
                                        ?>
                                    </span>
                                    
                                    <?php if ($character['is_public'] == 1): ?>
                                        <span style="padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: var(--light-color); color: var(--text-secondary);">
                                            公开
                                        </span>
                                    <?php else: ?>
                                        <span style="padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; background: var(--light-color); color: var(--text-secondary);">
                                            私密
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- 操作按钮 -->
                            <div style="display: flex; gap: 8px; flex-direction: column; min-width: 120px;">
                                <button class="view-detail-btn" 
                                        data-character='<?php echo htmlspecialchars(json_encode($character), ENT_QUOTES); ?>'
                                        style="background: var(--primary-color); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 0.875rem; transition: background 0.3s;">
                                    <i class="fas fa-eye"></i> 详情
                                </button>
                                
                                <?php if ($character['status'] == 0): ?>
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="character_id" value="<?php echo $character['id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" style="background: var(--success-color); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 0.875rem; width: 100%; transition: background 0.3s;">
                                            <i class="fas fa-check"></i> 通过
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="character_id" value="<?php echo $character['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" style="background: var(--danger-color); color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 0.875rem; width: 100%; transition: background 0.3s;">
                                            <i class="fas fa-times"></i> 拒绝
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 详情弹窗 -->
<div id="detailModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 24px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 20px;">
            <h3 id="modalTitle" style="margin: 0; color: var(--text-primary);">角色详情</h3>
            <button id="closeModal" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary);">&times;</button>
        </div>
        
        <div id="modalContent">
            <!-- 内容将通过JavaScript动态填充 -->
        </div>
    </div>
</div>

<style>
.character-item {
    transition: transform 0.2s, box-shadow 0.2s;
}

.character-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}

.status-badge {
    background: var(--light-color);
    color: var(--text-primary);
}

button:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('detailModal');
    const closeModal = document.getElementById('closeModal');
    const modalContent = document.getElementById('modalContent');
    const viewButtons = document.querySelectorAll('.view-detail-btn');

    // 审核设置折叠面板
    const reviewSettingsBtn = document.getElementById('reviewSettingsBtn');
    const reviewSettingsPanel = document.getElementById('reviewSettingsPanel');
    const settingsChevron = document.getElementById('settingsChevron');

    if (reviewSettingsBtn && reviewSettingsPanel) {
        reviewSettingsBtn.addEventListener('click', function() {
            const isVisible = reviewSettingsPanel.style.display !== 'none';
            reviewSettingsPanel.style.display = isVisible ? 'none' : 'block';
            if (settingsChevron) {
                settingsChevron.style.transform = isVisible ? 'rotate(0deg)' : 'rotate(180deg)';
            }
        });
    }

    // 一键AI审核（手动批量审核）
    const aiReviewBtn = document.getElementById('aiReviewBtn');
    const aiReviewStatus = document.getElementById('aiReviewStatus');
    const reviewProgress = document.getElementById('reviewProgress');

    if (aiReviewBtn && aiReviewStatus && reviewProgress) {
        aiReviewBtn.addEventListener('click', function() {
            if (!confirm('确定要对所有待审核角色进行AI审核吗？')) {
                return;
            }

            aiReviewBtn.disabled = true;
            aiReviewStatus.style.display = 'block';
            reviewProgress.textContent = '正在审核中...';
            reviewProgress.style.color = 'var(--text-primary)';

            fetch('api/ai_review.php?action=batch_review', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    reviewProgress.textContent = '审核完成！通过: ' + (data.approved || 0) + '，拒绝: ' + (data.rejected || 0) + '，失败: ' + (data.failed || 0);
                    reviewProgress.style.color = 'var(--success-color)';
                    setTimeout(function() {
                        window.location.reload();
                    }, 3000);
                } else {
                    reviewProgress.textContent = '审核失败: ' + (data.message || '未知错误');
                    reviewProgress.style.color = 'var(--danger-color)';
                    aiReviewBtn.disabled = false;
                }
            })
            .catch(function(error) {
                reviewProgress.textContent = '审核出错: ' + error.message;
                reviewProgress.style.color = 'var(--danger-color)';
                aiReviewBtn.disabled = false;
            });
        });
    }

    // 复制自动审核URL
    window.copyAutoReviewUrl = function() {
        var urlElement = document.getElementById('autoReviewUrl');
        if (!urlElement) return;
        var text = urlElement.textContent || urlElement.innerText;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                alert('URL已复制到剪贴板');
            }).catch(function() {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    };

    function fallbackCopy(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            alert('URL已复制到剪贴板');
        } catch (err) {
            alert('复制失败，请手动复制');
        }
        document.body.removeChild(textarea);
    }

    // 打开详情弹窗
    viewButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const characterData = JSON.parse(this.getAttribute('data-character'));
            showCharacterDetail(characterData);
        });
    });
    
    // 关闭弹窗
    closeModal.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    // 点击背景关闭
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    function showCharacterDetail(character) {
        const statusText = ['待审核', '已通过', '未通过'];
        const statusColors = ['#f59e0b', '#10b981', '#ef4444'];
        
        const content = `
            <div style="display: grid; gap: 16px;">
                <div style="display: flex; gap: 16px; align-items: start;">
                    ${character.avatar ? `
                        <img src="${character.avatar}" alt="${character.name}" 
                             style="width: 80px; height: 80px; border-radius: 8px; object-fit: cover;">
                    ` : `
                        <div style="width: 80px; height: 80px; border-radius: 8px; background: var(--light-color); 
                                  display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-robot" style="font-size: 2rem; color: var(--text-secondary);"></i>
                        </div>
                    `}
                    
                    <div style="flex: 1;">
                        <h4 style="margin: 0 0 8px 0; color: var(--text-primary);">${character.name}</h4>
                        <div style="display: flex; gap: 12px; flex-wrap: wrap; font-size: 0.875rem;">
                            <span style="padding: 4px 12px; border-radius: 20px; background: ${statusColors[character.status]}; color: white;">
                                ${statusText[character.status]}
                            </span>
                            <span style="color: var(--text-secondary);">创建者: ${character.creator_name || '未知'}</span>
                            <span style="color: var(--text-secondary);">分类: ${character.category_name || '未分类'}</span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h5 style="margin: 0 0 8px 0; color: var(--text-primary);">角色介绍</h5>
                    <p style="margin: 0; color: var(--text-primary); line-height: 1.5; background: var(--light-color); padding: 12px; border-radius: 6px;">
                        ${character.introduction || '暂无介绍'}
                    </p>
                </div>
                
                <div>
                    <h5 style="margin: 0 0 8px 0; color: var(--text-primary);">提示词</h5>
                    <pre style="margin: 0; color: var(--text-primary); line-height: 1.5; background: var(--light-color); padding: 12px; border-radius: 6px; white-space: pre-wrap; font-family: inherit; font-size: 0.875rem;">
${character.prompt || '暂无提示词'}
                    </pre>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; font-size: 0.875rem;">
                    <div>
                        <strong>创建时间:</strong> ${new Date(character.create_time).toLocaleString()}
                    </div>
                    <div>
                        <strong>更新时间:</strong> ${character.update_time ? new Date(character.update_time).toLocaleString() : '暂无'}
                    </div>
                    <div>
                        <strong>使用次数:</strong> ${character.usage_count || 0}
                    </div>
                    <div>
                        <strong>公开状态:</strong> ${character.is_public == 1 ? '公开' : '私密'}
                    </div>
                </div>
            </div>
        `;
        
        modalContent.innerHTML = content;
        modal.style.display = 'flex';
    }
});
</script>

</body>
</html>
