<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-17
最后编辑时间：2025-10-19
文件描述：后台主页页面，可以直观查看后台信息
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

// 获取网站统计信息
$stats = [];
try {
    // 用户统计
    $stmt = $db->query("SELECT COUNT(*) as total_users FROM user WHERE status = 1");
    $stats['total_users'] = $stmt->fetchColumn();
    
    // 角色统计
    $stmt = $db->query("SELECT COUNT(*) as total_characters FROM ai_character WHERE status = 1");
    $stats['total_characters'] = $stmt->fetchColumn();
    
    // 对话统计
    $stmt = $db->query("SELECT COUNT(*) as total_chats FROM chat_record WHERE is_deleted = 0");
    $stats['total_chats'] = $stmt->fetchColumn();
    
    // 今日新增用户
    $stmt = $db->query("SELECT COUNT(*) as today_users FROM user WHERE DATE(create_time) = CURDATE()");
    $stats['today_users'] = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    error_log("获取统计信息失败: " . $e->getMessage());
}

// 获取网站配置
$settings = [];
try {
    $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取网站配置失败: " . $e->getMessage());
}

// 直接包含 navbar.php
include 'navbar.php';
?>

<!-- 内容包装器中的内容 -->
<div class="dashboard-page">
    <!-- 统计卡片 -->
    <div class="stats-grid">
        <div class="stat-card users">
            <div class="stat-value"><?php echo $stats['total_users']; ?></div>
            <div class="stat-label">
                <i class="fas fa-users stat-icon"></i>
                总用户数
            </div>
        </div>
        
        <div class="stat-card characters">
            <div class="stat-value"><?php echo $stats['total_characters']; ?></div>
            <div class="stat-label">
                <i class="fas fa-robot stat-icon"></i>
                角色数量
            </div>
        </div>
        
        <div class="stat-card chats">
            <div class="stat-value"><?php echo $stats['total_chats']; ?></div>
            <div class="stat-label">
                <i class="fas fa-comments stat-icon"></i>
                对话总数
            </div>
        </div>
        
        <div class="stat-card today">
            <div class="stat-value"><?php echo $stats['today_users']; ?></div>
            <div class="stat-label">
                <i class="fas fa-user-plus stat-icon"></i>
                今日新增
            </div>
        </div>
    </div>

    <!-- 快速操作 -->
    <div class="quick-actions" style="margin-bottom: 30px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <a href="users.php" style="display: block; background: white; padding: 20px; border-radius: 12px; text-decoration: none; color: inherit; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: all 0.3s;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; background: rgba(16, 185, 129, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-users" style="color: #10b981;"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">用户管理</div>
                        <div style="font-size: 12px; color: #6b7280;">管理平台用户</div>
                    </div>
                </div>
            </a>
            
            <a href="characters.php" style="display: block; background: white; padding: 20px; border-radius: 12px; text-decoration: none; color: inherit; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: all 0.3s;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-robot" style="color: #f59e0b;"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">角色管理</div>
                        <div style="font-size: 12px; color: #6b7280;">管理AI角色</div>
                    </div>
                </div>
            </a>
            
            <a href="settings.php" style="display: block; background: white; padding: 20px; border-radius: 12px; text-decoration: none; color: inherit; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: all 0.3s;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; background: rgba(102, 126, 234, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-cog" style="color: #667eea;"></i>
                    </div>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">系统设置</div>
                        <div style="font-size: 12px; color: #6b7280;">配置平台参数</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <!-- 最近活动 -->
    <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <h2 style="margin-bottom: 20px; font-size: 1.25rem; font-weight: 600;">最近活动</h2>
        <div style="color: #6b7280; text-align: center; padding: 40px;">
            <i class="fas fa-chart-line" style="font-size: 48px; margin-bottom: 16px; color: #d1d5db;"></i>
            <p>暂无最近活动数据</p>
        </div>
    </div>
</div>

</div>
</div>

</body>
</html>