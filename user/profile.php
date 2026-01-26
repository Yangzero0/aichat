<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-10
最后编辑时间：2025-11-15
文件描述：用户个人信息页面，编辑个人信息

*/
session_start();
require_once(__DIR__ . './../config/config.php');

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_info = [];
$settings = [];

try {
    // 获取用户信息
    $stmt = $db->prepare("SELECT * FROM user WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_info) {
        header('Location: login');
        exit;
    }
    
    // 获取网站配置
    $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("数据库查询错误: " . $e->getMessage());
    die("系统错误，请稍后重试");
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // 验证昵称
        if (empty($name)) {
            $response['message'] = '昵称不能为空';
            echo json_encode($response);
            exit;
        }
        
        // 昵称长度限制为10个字符
        if (mb_strlen($name) > 10) {
            $response['message'] = '昵称长度不能超过10个字符';
            echo json_encode($response);
            exit;
        }
        
        // 验证手机号
        if (!empty($phone) && !preg_match('/^1[3-9]\d{9}$/', $phone)) {
            $response['message'] = '手机号格式不正确';
            echo json_encode($response);
            exit;
        }
        
        // 处理头像上传
        $avatar_path = $user_info['avatar'];
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $upload_result = handleAvatarUpload($_FILES['avatar'], $user_id);
            if (!$upload_result['success']) {
                $response['message'] = $upload_result['message'];
                echo json_encode($response);
                exit;
            }
            $avatar_path = $upload_result['avatar_path'];
        }
        
        // 更新用户信息
        $stmt = $db->prepare("UPDATE user SET name = ?, phone = ?, avatar = ? WHERE id = ?");
        $stmt->execute([$name, $phone, $avatar_path, $user_id]);
        
        // 更新session中的用户信息
        $_SESSION['name'] = $name;
        $_SESSION['avatar'] = $avatar_path;
        
        $response['success'] = true;
        $response['message'] = '个人信息更新成功';
        $response['new_avatar'] = $avatar_path;
        $response['new_name'] = $name;
        
    } catch (PDOException $e) {
        error_log("更新用户信息错误: " . $e->getMessage());
        $response['message'] = '更新失败，请稍后重试';
    }
    
    echo json_encode($response);
    exit;
}

// 处理头像上传的函数
function handleAvatarUpload($file, $user_id) {
    $result = ['success' => false, 'message' => '', 'avatar_path' => ''];
    
    // 检查文件类型
    $allowed_extensions = ['jpg', 'jpeg', 'png'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        $result['message'] = '只支持 JPG 和 PNG 格式的图片';
        return $result;
    }
    
    // 检查文件大小 (2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        $result['message'] = '图片大小不能超过 2MB';
        return $result;
    }
    
    // 创建目录
    $upload_dir = __DIR__ . '/../static/user-images/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // 生成文件名 - 使用固定格式
    $filename = $user_id . '-user.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    // 移动文件
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $result['success'] = true;
        $result['avatar_path'] = '/static/user-images/' . $filename;
    } else {
        $result['message'] = '文件上传失败';
    }
    
    return $result;
}

// 获取用户头像URL
function getUserAvatar($user_info) {
    if (!empty($user_info['avatar'])) {
        return $user_info['avatar'];
    }
    return '/static/user-images/user.png';
}

ob_start();
?>

<div class="app-main__content">
    <!-- 消息提示 -->
    <div class="alert-banner" id="message" style="display: none;"></div>
    
    <!-- 用户头像和基本信息 -->
    <section class="section-card hero-card">
        <div class="profile-header">
            <img src="<?php echo getUserAvatar($user_info); ?>" 
                 alt="用户头像" 
                 class="profile-avatar"
                 onerror="this.src='/static/user-images/user.png'">
            <h1 class="profile-name"><?php echo htmlspecialchars($user_info['name']); ?></h1>
            <p class="profile-email"><?php echo htmlspecialchars($user_info['mail']); ?></p>
        </div>
    </section>
    
    <!-- 用户信息网格 -->
    <section class="section-card">
        <div class="info-grid">
            <!-- VIP信息 -->
            <div class="info-card">
                <div class="info-label">会员状态</div>
                <div class="info-value">
                    <?php if (($settings['vip'] ?? 1) && $user_info['expiry'] && strtotime($user_info['expiry']) > time()): ?>
                        <span class="vip-badge">
                            <i class="fas fa-crown"></i> VIP会员
                        </span>
                        <div class="info-value-detail">
                            到期时间: <?php echo date('Y-m-d H:i', strtotime($user_info['expiry'])); ?>
                        </div>
                    <?php else: ?>
                        <span class="info-value-text">普通用户</span>
                        <?php if ($user_info['expiry'] && strtotime($user_info['expiry']) <= time()): ?>
                            <div class="info-value-detail info-value-detail--error">
                                VIP已过期
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 积分信息 -->
            <div class="info-card">
                <div class="info-label">积分余额</div>
                <div class="info-value">
                    <span class="points-badge">
                        <i class="fas fa-coins"></i> <?php echo $user_info['points']; ?> 积分
                    </span>
                </div>
            </div>
            
            <!-- 手机号信息 -->
            <div class="info-card">
                <div class="info-label">手机号码</div>
                <div class="info-value">
                    <?php if ($user_info['phone']): ?>
                        <span class="info-value-text"><?php echo htmlspecialchars($user_info['phone']); ?></span>
                    <?php else: ?>
                        <span class="info-value-text info-value-text--muted">未绑定</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 注册时间 -->
            <div class="info-card">
                <div class="info-label">注册时间</div>
                <div class="info-value">
                    <span class="info-value-text"><?php echo date('Y-m-d', strtotime($user_info['create_time'])); ?></span>
                </div>
            </div>
        </div>
        
        <!-- 编辑按钮 -->
        <button class="button button--primary" id="editProfileBtn" style="width: 100%; margin-top: 1.5rem;">
            <i class="fas fa-edit"></i> 编辑个人信息
        </button>
    </section>
</div>

<!-- 编辑模态框 -->
<div class="modal" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">编辑个人信息</h3>
            <button class="modal-close" id="closeModal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="profileForm" enctype="multipart/form-data">
            <div class="modal-body">
                <!-- 头像上传 -->
                <div class="auth-field profile-edit">
                    <label class="auth-label">头像</label>
                    <div class="avatar-upload">
                        <div class="avatar-preview" id="avatarPreview">
                            <img src="<?php echo getUserAvatar($user_info); ?>" 
                                 alt="头像预览" 
                                 id="avatarPreviewImg"
                                 onerror="this.src='/static/user-images/user.png'">
                        </div>
                        <div class="avatar-upload-btn">
                            <input type="file" name="avatar" id="avatarInput" class="file-input" accept=".jpg,.jpeg,.png">
                            <label for="avatarInput" class="file-label">
                                <i class="fas fa-upload"></i> 选择图片
                            </label>
                            <div class="form-hint">支持 JPG、PNG 格式，大小不超过 2MB</div>
                        </div>
                    </div>
                </div>
                
                <!-- 昵称 -->
                <div class="auth-field">
                    <label class="auth-label" for="name">昵称 *</label>
                    <input type="text" 
                           name="name" 
                           id="name" 
                           class="auth-input" 
                           value="<?php echo htmlspecialchars($user_info['name']); ?>" 
                           required
                           maxlength="10">
                    <div class="char-count" id="nameCharCount">0/10</div>
                </div>
                
                <!-- 邮箱（只读） -->
                <div class="auth-field">
                    <label class="auth-label">邮箱地址</label>
                    <input type="email" 
                           class="auth-input" 
                           value="<?php echo htmlspecialchars($user_info['mail']); ?>" 
                           readonly
                           style="background: var(--button-muted-bg); color: var(--text-secondary);">
                    <div class="form-hint">邮箱地址不可更改</div>
                </div>
                
                <!-- 手机号 -->
                <div class="auth-field">
                    <label class="auth-label" for="phone">手机号码</label>
                    <input type="tel" 
                           name="phone" 
                           id="phone" 
                           class="auth-input" 
                           value="<?php echo htmlspecialchars($user_info['phone'] ?? ''); ?>" 
                           placeholder="请输入手机号码"
                           pattern="[0-9]{11}">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="button button--subtle" id="cancelEdit">
                    <i class="fas fa-times"></i> 取消
                </button>
                <button type="submit" class="button button--primary" id="saveProfileBtn">
                    <i class="fas fa-save"></i> 保存更改
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const editBtn = document.getElementById('editProfileBtn');
        const closeModal = document.getElementById('closeModal');
        const cancelEdit = document.getElementById('cancelEdit');
        const editModal = document.getElementById('editModal');
        const profileForm = document.getElementById('profileForm');
        const avatarInput = document.getElementById('avatarInput');
        const saveProfileBtn = document.getElementById('saveProfileBtn');
        const messageDiv = document.getElementById('message');
        const nameInput = document.getElementById('name');
        const nameCharCount = document.getElementById('nameCharCount');
        
        // 初始化字符计数
        updateCharCount(nameInput.value.length);
        
        // 昵称字符计数
        nameInput.addEventListener('input', function() {
            updateCharCount(this.value.length);
        });
        
        function updateCharCount(length) {
            nameCharCount.textContent = length + '/10';
            if (length >= 9) {
                nameCharCount.className = 'char-count warning';
            } else if (length >= 10) {
                nameCharCount.className = 'char-count error';
            } else {
                nameCharCount.className = 'char-count';
            }
        }
        
        // 打开模态框
        editBtn.addEventListener('click', function() {
            editModal.classList.add('active');
        });
        
        // 关闭模态框
        function closeEditModal() {
            editModal.classList.remove('active');
            // 重置表单
            profileForm.reset();
            // 重置头像预览
            const avatarPreviewImg = document.getElementById('avatarPreviewImg');
            if (avatarPreviewImg) {
                avatarPreviewImg.src = '<?php echo getUserAvatar($user_info); ?>';
            }
            // 重置字符计数
            updateCharCount(nameInput.value.length);
            // 隐藏消息
            messageDiv.style.display = 'none';
            messageDiv.classList.remove('show', 'alert-banner--success', 'alert-banner--error');
        }
        
        closeModal.addEventListener('click', closeEditModal);
        cancelEdit.addEventListener('click', closeEditModal);
        
        // 点击遮罩层关闭
        editModal.addEventListener('click', function(e) {
            if (e.target === editModal) {
                closeEditModal();
            }
        });
        
        // 头像预览
        avatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // 检查文件类型
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    showMessage('只支持 JPG 和 PNG 格式的图片', 'error');
                    this.value = '';
                    return;
                }
                
                // 检查文件大小 (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    showMessage('图片大小不能超过 2MB', 'error');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const avatarPreviewImg = document.getElementById('avatarPreviewImg');
                    if (avatarPreviewImg) {
                        avatarPreviewImg.src = e.target.result;
                    }
                };
                reader.readAsDataURL(file);
            }
        });
        
        // 表单提交
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // 前端昵称长度验证
            const nameValue = nameInput.value.trim();
            if (nameValue.length > 10) {
                showMessage('昵称长度不能超过10个字符', 'error');
                return;
            }
            
            const formData = new FormData(this);
            saveProfileBtn.disabled = true;
            saveProfileBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';
            
            fetch('profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    
                    // 更新页面显示
                    if (data.new_avatar) {
                        document.querySelector('.profile-avatar').src = data.new_avatar;
                    }
                    
                    if (data.new_name) {
                        document.querySelector('.profile-name').textContent = data.new_name;
                    }
                    
                    // 立即关闭模态框
                    closeEditModal();
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('网络错误，请稍后重试', 'error');
            })
            .finally(() => {
                saveProfileBtn.disabled = false;
                saveProfileBtn.innerHTML = '<i class="fas fa-save"></i> 保存更改';
            });
        });
        
        // 显示消息
        function showMessage(text, type) {
            messageDiv.textContent = text;
            messageDiv.className = 'alert-banner';
            if (type === 'error') {
                messageDiv.classList.add('alert-banner--error');
            } else {
                messageDiv.classList.add('alert-banner--success');
            }
            messageDiv.style.display = 'block';
            messageDiv.classList.add('show');
            
            // 3秒后自动隐藏
            setTimeout(() => {
                messageDiv.classList.remove('show');
                setTimeout(() => {
                    messageDiv.style.display = 'none';
                    messageDiv.classList.remove('alert-banner--success', 'alert-banner--error');
                }, 300);
            }, 3000);
        }
        
        // 手机号输入验证
        const phoneInput = document.getElementById('phone');
        phoneInput.addEventListener('input', function(e) {
            // 只允许数字
            e.target.value = e.target.value.replace(/[^\d]/g, '');
            
            // 限制长度
            if (e.target.value.length > 11) {
                e.target.value = e.target.value.slice(0, 11);
            }
        });
    });
</script>

<?php
$content = ob_get_clean();
include 'navbar.php';
?>