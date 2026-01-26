<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-17
最后编辑时间：2025-10-19
文件描述：网站配置页面，用于配置网站信息
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

// 检查权限
if ($admin_level != 0) {
    die('权限不足，只有超级管理员可以访问此页面');
}

// 获取网站配置
$settings = [];
try {
    $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 如果没有配置，创建默认配置
    if (!$settings) {
        $default_settings = [
            'title' => 'AI聊天平台',
            'description' => '基于人工智能的在线角色扮演聊天平台',
            'url' => '',
            'logo' => '/static/images/logo.png',
            'register' => 1,
            'reg_mail' => 0,
            'vip' => 1,
            'qd_points' => 10,
            'reg_points' => 100,
            'smtp_host' => '',
            'smtp_port' => 25,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_from_email' => '',
            'smtp_from_name' => '',
            'notice' => ''
        ];
        
        $columns = implode(', ', array_keys($default_settings));
        $values = ':' . implode(', :', array_keys($default_settings));
        $stmt = $db->prepare("INSERT INTO web_setting ($columns) VALUES ($values)");
        $stmt->execute($default_settings);
        
        // 重新获取配置
        $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("获取网站配置失败: " . $e->getMessage());
    $error = "获取网站配置失败: " . $e->getMessage();
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // 基本设置
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $url = $_POST['url'] ?? '';
        $logo = $settings['logo'] ?? '/static/images/logo.png'; // 默认保持原logo
        
        // 处理Logo上传
        if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../static/images/';
            $targetFile = $uploadDir . 'logo.png';
            
            // 检查上传目录是否存在
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // 检查文件类型
            $imageFileType = strtolower(pathinfo($_FILES['logo_upload']['name'], PATHINFO_EXTENSION));
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
            
            if (in_array($imageFileType, $allowedTypes)) {
                // 检查文件大小 (限制为2MB)
                if ($_FILES['logo_upload']['size'] <= 2 * 1024 * 1024) {
                    // 移动上传的文件
                    if (move_uploaded_file($_FILES['logo_upload']['tmp_name'], $targetFile)) {
                        $logo = '/static/images/logo.png';
                        $upload_success = "Logo上传成功！";
                    } else {
                        $upload_error = "Logo上传失败，请检查目录权限。";
                    }
                } else {
                    $upload_error = "Logo文件太大，请选择小于2MB的图片。";
                }
            } else {
                $upload_error = "只支持 JPG, JPEG, PNG, GIF, SVG 格式的图片。";
            }
        }
        
        $register = isset($_POST['register']) ? 1 : 0;
        $reg_mail = isset($_POST['reg_mail']) ? 1 : 0;
        $vip = isset($_POST['vip']) ? 1 : 0;
        $qd_points = intval($_POST['qd_points'] ?? 10);
        $reg_points = intval($_POST['reg_points'] ?? 100);
        
        // 邮件设置
        $smtp_host = $_POST['smtp_host'] ?? '';
        $smtp_port = intval($_POST['smtp_port'] ?? 25);
        $smtp_username = $_POST['smtp_username'] ?? '';
        $smtp_password = $_POST['smtp_password'] ?? '';
        $smtp_from_email = $_POST['smtp_from_email'] ?? '';
        $smtp_from_name = $_POST['smtp_from_name'] ?? '';
        
        // 公告
        $notice = $_POST['notice'] ?? '';
        
        // 更新数据库
        $update_data = [
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'logo' => $logo,
            'register' => $register,
            'reg_mail' => $reg_mail,
            'vip' => $vip,
            'qd_points' => $qd_points,
            'reg_points' => $reg_points,
            'smtp_host' => $smtp_host,
            'smtp_port' => $smtp_port,
            'smtp_username' => $smtp_username,
            'smtp_password' => $smtp_password,
            'smtp_from_email' => $smtp_from_email,
            'smtp_from_name' => $smtp_from_name,
            'notice' => $notice,
            'update_time' => date('Y-m-d H:i:s')
        ];
        
        // 构建更新SQL
        $set_parts = [];
        foreach ($update_data as $key => $value) {
            $set_parts[] = "$key = :$key";
        }
        $set_sql = implode(', ', $set_parts);
        
        $stmt = $db->prepare("UPDATE web_setting SET $set_sql WHERE id = :id");
        $update_data['id'] = $settings['id'];
        $stmt->execute($update_data);
        
        $success = "网站配置更新成功！";
        
        // 重新获取最新配置
        $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("更新网站配置失败: " . $e->getMessage());
        $error = "更新网站配置失败: " . $e->getMessage();
    }
}

// 包含导航栏
include 'navbar.php';
?>

<div class="settings-page">
    <div class="page-header" style="margin-bottom: 30px;">
        <h1 style="font-size: 1.8rem; font-weight: 600; color: var(--dark-color);">系统设置</h1>
        <p style="color: var(--text-secondary); margin-top: 8px;">管理网站基本配置、邮件设置和系统公告</p>
    </div>

    <?php if (isset($success)): ?>
    <div class="alert alert-success" style="background: #d1fae5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($upload_success)): ?>
    <div class="alert alert-success" style="background: #d1fae5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($upload_success); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
    <div class="alert alert-error" style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <?php if (isset($upload_error)): ?>
    <div class="alert alert-error" style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px;">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($upload_error); ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="settings-form" enctype="multipart/form-data">
        <div class="settings-tabs" style="margin-bottom: 30px;">
            <div class="tabs-header" style="border-bottom: 1px solid var(--border-color);">
                <button type="button" class="tab-btn active" data-tab="basic">基本设置</button>
                <button type="button" class="tab-btn" data-tab="email">邮件配置</button>
                <button type="button" class="tab-btn" data-tab="notice">网站公告</button>
            </div>
        </div>

        <!-- 基本设置标签页 -->
        <div class="tab-content active" id="basic-tab">
            <div class="settings-section" style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 24px;">
                <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 20px; color: var(--dark-color);">网站信息</h3>
                
                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label for="title" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">网站标题</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($settings['title'] ?? ''); ?>" 
                               style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
                    </div>
                    
                    <div class="form-group">
                        <label for="url" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">网站地址</label>
                        <input type="url" id="url" name="url" value="<?php echo htmlspecialchars($settings['url'] ?? ''); ?>" 
                               style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;" 
                               placeholder="https://example.com">
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 16px;">
                    <label for="description" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">网站描述</label>
                    <textarea id="description" name="description" rows="3" 
                              style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px; resize: vertical;"><?php echo htmlspecialchars($settings['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group" style="margin-top: 16px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">网站Logo</label>
                    
                    <!-- 当前Logo预览 -->
                    <div style="margin-bottom: 16px; padding: 16px; background: var(--light-color); border-radius: 8px;">
                        <div style="font-size: 14px; color: var(--text-secondary); margin-bottom: 8px;">当前Logo预览：</div>
                        <?php if (!empty($settings['logo'])): ?>
                            <img src="<?php echo htmlspecialchars($settings['logo']); ?>" alt="网站Logo" 
                                 style="max-width: 200px; max-height: 80px; border: 1px solid var(--border-color); border-radius: 4px; padding: 4px; background: white;"
                                 onerror="this.style.display='none'; document.getElementById('no-logo-message').style.display='block';">
                            <div id="no-logo-message" style="display: none; color: var(--text-secondary); font-size: 14px;">
                                <i class="fas fa-image"></i> 当前Logo无法显示
                            </div>
                        <?php else: ?>
                            <div style="color: var(--text-secondary); font-size: 14px;">
                                <i class="fas fa-image"></i> 未设置Logo
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Logo上传 -->
                    <div>
                        <label for="logo_upload" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">上传新Logo</label>
                        <input type="file" id="logo_upload" name="logo_upload" accept="image/*" 
                               style="width: 100%; padding: 8px 0; font-size: 14px;"
                               onchange="previewLogo(this)">
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                            支持 JPG, PNG, GIF, SVG 格式，最大 2MB。上传后将覆盖 /static/images/logo.png ,记得清理缓存
                        </div>
                        
                        <!-- 上传预览 -->
                        <div id="logo-preview" style="display: none; margin-top: 12px;">
                            <div style="font-size: 14px; color: var(--text-secondary); margin-bottom: 8px;">上传预览：</div>
                            <img id="preview-image" src="#" alt="Logo预览" 
                                 style="max-width: 200px; max-height: 80px; border: 1px solid var(--border-color); border-radius: 4px; padding: 4px; background: white;">
                        </div>
                    </div>
                </div>
            </div>

            <div class="settings-section" style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 24px;">
                <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 20px; color: var(--dark-color);">功能开关</h3>
                
                <div class="switch-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <div class="switch-item">
                        <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                            <input type="checkbox" name="register" value="1" <?php echo ($settings['register'] ?? 1) ? 'checked' : ''; ?> 
                                   style="width: 18px; height: 18px;">
                            <span style="font-weight: 500;">用户注册</span>
                        </label>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">是否允许新用户注册</div>
                    </div>
                    
                    <div class="switch-item">
                        <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                            <input type="checkbox" name="reg_mail" value="1" <?php echo ($settings['reg_mail'] ?? 0) ? 'checked' : ''; ?> 
                                   style="width: 18px; height: 18px;">
                            <span style="font-weight: 500;">邮箱验证</span>
                        </label>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">注册时是否需要邮箱验证</div>
                    </div>
                    
                    <div class="switch-item">
                        <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                            <input type="checkbox" name="vip" value="1" <?php echo ($settings['vip'] ?? 1) ? 'checked' : ''; ?> 
                                   style="width: 18px; height: 18px;">
                            <span style="font-weight: 500;">VIP功能</span>
                        </label>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">是否启用VIP会员功能</div>
                    </div>
                </div>
            </div>

            <div class="settings-section" style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 24px;">
                <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 20px; color: var(--dark-color);">积分设置</h3>
                
                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label for="qd_points" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">签到积分</label>
                        <input type="number" id="qd_points" name="qd_points" value="<?php echo htmlspecialchars($settings['qd_points'] ?? 10); ?>" 
                               min="0" max="1000" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">用户每日签到获得的积分数</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="reg_points" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">注册积分</label>
                        <input type="number" id="reg_points" name="reg_points" value="<?php echo htmlspecialchars($settings['reg_points'] ?? 100); ?>" 
                               min="0" max="10000" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">新用户注册时赠送的积分数</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 邮件配置标签页 -->
        <div class="tab-content" id="email-tab">
            <div class="settings-section" style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 24px;">
                <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 20px; color: var(--dark-color);">SMTP邮件服务器配置</h3>
                <p style="color: var(--text-secondary); margin-bottom: 20px;">用于发送验证邮件、通知邮件等系统邮件</p>
                
                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label for="smtp_host" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">SMTP服务器</label>
                        <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" 
                               style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;" 
                               placeholder="smtp.example.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_port" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">SMTP端口</label>
                        <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? 25); ?>" 
                               min="1" max="65535" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
                    </div>
                </div>
                
                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 16px;">
                    <div class="form-group">
                        <label for="smtp_username" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">用户名</label>
                        <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>" 
                               style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_password" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">密码/授权码</label>
                        <input type="password" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>" 
                               style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
                    </div>
                </div>
                
                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 16px;">
                    <div class="form-group">
                        <label for="smtp_from_email" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">发件人邮箱</label>
                        <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? ''); ?>" 
                               style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
                    </div>
                    
                    <div class="form-group">
                        <label for="smtp_from_name" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">发件人名称</label>
                        <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? ''); ?>" 
                               style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
                    </div>
                </div>
            </div>
        </div>

        <!-- 网站公告标签页 -->
        <div class="tab-content" id="notice-tab">
            <div class="settings-section" style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 24px;">
                <h3 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 20px; color: var(--dark-color);">网站公告</h3>
                <p style="color: var(--text-secondary); margin-bottom: 20px;">设置网站公告内容，支持HTML格式</p>
                
                <div class="form-group">
                    <label for="notice" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary);">公告内容</label>
                    <textarea id="notice" name="notice" rows="8" 
                              style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px; resize: vertical; font-family: inherit;"><?php echo htmlspecialchars($settings['notice'] ?? ''); ?></textarea>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">支持HTML标签，用于显示在网站首页或其他位置的公告</div>
                </div>
            </div>
        </div>

        <div class="form-actions" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color);">
            <button type="submit" class="save-btn" style="background: var(--primary-color); color: white; border: none; padding: 12px 24px; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; transition: background 0.3s;">
                <i class="fas fa-save"></i> 保存设置
            </button>
            <button type="reset" class="reset-btn" style="background: var(--light-color); color: var(--text-primary); border: 1px solid var(--border-color); padding: 12px 24px; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer; margin-left: 12px; transition: all 0.3s;">
                <i class="fas fa-undo"></i> 重置
            </button>
        </div>
    </form>
</div>

<style>
.tabs-header {
    display: flex;
    gap: 0;
}

.tab-btn {
    background: none;
    border: none;
    padding: 12px 24px;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-secondary);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.3s;
}

.tab-btn:hover {
    color: var(--primary-color);
}

.tab-btn.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.save-btn:hover {
    background: var(--primary-dark);
}

.reset-btn:hover {
    background: var(--border-color);
}

.form-group input:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.switch-item input[type="checkbox"] {
    accent-color: var(--primary-color);
}

.alert i {
    margin-right: 8px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 标签页切换功能
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // 更新按钮状态
            tabBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // 更新内容显示
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === `${tabId}-tab`) {
                    content.classList.add('active');
                }
            });
        });
    });
    
    // 重置按钮功能
    document.querySelector('.reset-btn').addEventListener('click', function(e) {
        if (!confirm('确定要重置所有更改吗？')) {
            e.preventDefault();
        }
    });
});

// Logo上传预览功能
function previewLogo(input) {
    const preview = document.getElementById('logo-preview');
    const previewImage = document.getElementById('preview-image');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewImage.src = e.target.result;
            preview.style.display = 'block';
        }
        
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
    }
}

// 文件大小验证
document.getElementById('logo_upload').addEventListener('change', function() {
    const file = this.files[0];
    if (file && file.size > 2 * 1024 * 1024) {
        alert('文件大小不能超过2MB！');
        this.value = '';
        document.getElementById('logo-preview').style.display = 'none';
    }
});
</script>

</div><!-- 关闭 content-wrapper -->
</div><!-- 关闭 main-content -->

</body>
</html>