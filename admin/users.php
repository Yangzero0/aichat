<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-17
最后编辑时间：2025-10-19
文件描述：用户管理页面，用于管理用户列表信息
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

// 处理删除操作
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    
    try {
        // 开始事务
        $db->beginTransaction();
        
        // 删除用户相关的所有数据
        // 1. 删除角色评分
        $stmt = $db->prepare("DELETE FROM character_rating WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 2. 删除角色订阅
        $stmt = $db->prepare("DELETE FROM character_subscription WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 3. 删除聊天记录
        $stmt = $db->prepare("DELETE FROM chat_record WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 4. 删除会话
        $stmt = $db->prepare("DELETE FROM chat_session WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 5. 删除用户创建的角色
        $stmt = $db->prepare("DELETE FROM ai_character WHERE user_id = ?");
        $stmt->execute([$user_id]);
        
        // 6. 删除用户头像文件
        $avatar_path = __DIR__ . '/../static/user-images/' . $user_id . '-user.png';
        if (file_exists($avatar_path)) {
            unlink($avatar_path);
        }
        
        // 7. 更新卡密使用记录（设置为未使用）
        $stmt = $db->prepare("UPDATE redeem_code SET used_by = NULL, used_time = NULL, status = 0 WHERE used_by = ?");
        $stmt->execute([$user_id]);
        
        // 8. 最后删除用户
        $stmt = $db->prepare("DELETE FROM user WHERE id = ?");
        $stmt->execute([$user_id]);
        
        $db->commit();
        
        $_SESSION['message'] = "用户删除成功！";
        $_SESSION['message_type'] = "success";
        
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['message'] = "删除用户失败: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header('Location: users.php');
    exit;
}

// 处理状态切换
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status' && isset($_GET['id'])) {
    $user_id = intval($_GET['id']);
    
    try {
        $stmt = $db->prepare("UPDATE user SET status = 1 - status WHERE id = ?");
        $stmt->execute([$user_id]);
        
        $_SESSION['message'] = "用户状态更新成功！";
        $_SESSION['message_type'] = "success";
        
    } catch (PDOException $e) {
        $_SESSION['message'] = "状态更新失败: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header('Location: users.php');
    exit;
}

// 处理头像上传
function handleAvatarUpload($user_id, $file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // 检查文件类型
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $file_type = mime_content_type($file['tmp_name']);
    if (!in_array($file_type, $allowed_types)) {
        return false;
    }
    
    // 检查文件大小（限制为2MB）
    if ($file['size'] > 2 * 1024 * 1024) {
        return false;
    }
    
    // 确保目录存在
    $upload_dir = __DIR__ . '/../static/user-images/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // 生成目标文件名
    $filename = $user_id . '-user.png';
    $target_path = $upload_dir . $filename;
    
    // 处理图片
    try {
        // 根据原图类型创建图像资源
        switch ($file_type) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($file['tmp_name']);
                break;
            case 'image/png':
                $image = imagecreatefrompng($file['tmp_name']);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($file['tmp_name']);
                break;
            default:
                return false;
        }
        
        if (!$image) {
            return false;
        }
        
        // 获取原图尺寸
        $width = imagesx($image);
        $height = imagesy($image);
        
        // 创建正方形画布
        $size = min($width, $height);
        $avatar = imagecreatetruecolor(200, 200);
        
        // 保持透明背景（如果是PNG或GIF）
        if ($file_type == 'image/png' || $file_type == 'image/gif') {
            imagealphablending($avatar, false);
            imagesavealpha($avatar, true);
            $transparent = imagecolorallocatealpha($avatar, 0, 0, 0, 127);
            imagefill($avatar, 0, 0, $transparent);
        } else {
            $white = imagecolorallocate($avatar, 255, 255, 255);
            imagefill($avatar, 0, 0, $white);
        }
        
        // 计算裁剪坐标（居中裁剪）
        $src_x = ($width - $size) / 2;
        $src_y = ($height - $size) / 2;
        
        // 裁剪并调整大小
        imagecopyresampled($avatar, $image, 0, 0, $src_x, $src_y, 200, 200, $size, $size);
        
        // 保存为PNG格式
        $result = imagepng($avatar, $target_path, 9);
        
        // 释放内存
        imagedestroy($image);
        imagedestroy($avatar);
        
        return $result;
        
    } catch (Exception $e) {
        error_log("头像处理失败: " . $e->getMessage());
        return false;
    }
}

// 处理编辑用户
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_user') {
    $user_id = intval($_POST['user_id']);
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $mail = trim($_POST['mail']);
    $phone = trim($_POST['phone']);
    $points = intval($_POST['points']);
    $expiry = $_POST['expiry'] ?: null;
    $status = intval($_POST['status']);
    
    // 验证必填字段
    if (empty($name) || empty($username)) {
        $_SESSION['message'] = "昵称和账号不能为空！";
        $_SESSION['message_type'] = "error";
    } else {
        try {
            // 检查用户名是否已被其他用户使用
            $stmt = $db->prepare("SELECT id FROM user WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) {
                $_SESSION['message'] = "用户名已被其他用户使用！";
                $_SESSION['message_type'] = "error";
            } else {
                // 处理头像上传
                $avatar_updated = false;
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    if (handleAvatarUpload($user_id, $_FILES['avatar'])) {
                        $avatar_updated = true;
                    } else {
                        $_SESSION['message'] = "头像上传失败，请检查图片格式和大小！";
                        $_SESSION['message_type'] = "error";
                        header('Location: users.php');
                        exit;
                    }
                }
                
                // 更新用户信息
                if ($avatar_updated) {
                    $sql = "UPDATE user SET name = ?, username = ?, mail = ?, phone = ?, points = ?, expiry = ?, status = ?, avatar = ? WHERE id = ?";
                    $avatar_path = '/static/user-images/' . $user_id . '-user.png';
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$name, $username, $mail, $phone, $points, $expiry, $status, $avatar_path, $user_id]);
                } else {
                    $sql = "UPDATE user SET name = ?, username = ?, mail = ?, phone = ?, points = ?, expiry = ?, status = ? WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$name, $username, $mail, $phone, $points, $expiry, $status, $user_id]);
                }
                
                $_SESSION['message'] = "用户信息更新成功！";
                $_SESSION['message_type'] = "success";
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "更新用户信息失败: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    }
    
    header('Location: users.php');
    exit;
}

// 获取搜索和筛选参数
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// 构建查询条件
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR username LIKE ? OR mail LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($status_filter !== '') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// 获取用户总数（用于分页）
try {
    $count_sql = "SELECT COUNT(*) FROM user $where_sql";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_users = $stmt->fetchColumn();
} catch (PDOException $e) {
    $total_users = 0;
    error_log("获取用户总数失败: " . $e->getMessage());
}

// 分页设置
$per_page = 20;
$total_pages = ceil($total_users / $per_page);
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $per_page;

// 获取用户列表
$users = [];
try {
    $sql = "SELECT * FROM user $where_sql ORDER BY create_time DESC LIMIT $per_page OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取用户列表失败: " . $e->getMessage());
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

<div class="users-page">
    <!-- 页面标题和操作按钮 -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="font-size: 1.75rem; font-weight: 600; color: var(--dark-color);">用户管理</h1>
        <div style="display: flex; gap: 12px;">
            <button onclick="exportUsers()" style="background: var(--success-color); color: white; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 14px;">
                <i class="fas fa-download"></i>
                导出用户
            </button>
        </div>
    </div>

    <!-- 消息提示 -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message <?php echo $_SESSION['message_type']; ?>" style="padding: 12px 16px; margin-bottom: 20px; border-radius: 8px; background: <?php echo $_SESSION['message_type'] == 'success' ? 'var(--success-color)' : 'var(--danger-color)'; ?>; color: white; display: flex; justify-content: space-between; align-items: center;">
            <span><?php echo htmlspecialchars($_SESSION['message']); ?></span>
            <button onclick="this.parentElement.style.display='none'" style="background: none; border: none; color: white; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- 搜索和筛选 -->
    <div class="filters-card" style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 24px;">
        <form method="GET" style="display: grid; grid-template-columns: 1fr auto auto; gap: 12px; align-items: end;">
            <div>
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">搜索用户</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="搜索昵称、账号或邮箱..." style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">状态筛选</label>
                <select name="status" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                    <option value="">全部状态</option>
                    <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>启用</option>
                    <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>禁用</option>
                </select>
            </div>
            
            <div>
                <button type="submit" style="background: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; height: 40px;">
                    <i class="fas fa-search"></i> 搜索
                </button>
            </div>
        </form>
    </div>

    <!-- 用户统计 -->
    <div class="user-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);"><?php echo $total_users; ?></div>
            <div style="color: var(--text-secondary); font-size: 14px;">总用户数</div>
        </div>
        
        <?php
        // 获取今日新增用户
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM user WHERE DATE(create_time) = CURDATE()");
            $today_users = $stmt->fetchColumn();
        } catch (PDOException $e) {
            $today_users = 0;
        }
        ?>
        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div style="font-size: 2rem; font-weight: 700; color: var(--success-color);"><?php echo $today_users; ?></div>
            <div style="color: var(--text-secondary); font-size: 14px;">今日新增</div>
        </div>
    </div>

    <!-- 用户列表 -->
    <div class="users-table-card" style="background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden;">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--light-color);">
                        <th style="padding: 16px; text-align: left; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">用户信息</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">账号信息</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">会员状态</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">注册时间</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">状态</th>
                        <th style="padding: 16px; text-align: center; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" style="padding: 40px; text-align: center; color: var(--text-secondary);">
                                <i class="fas fa-users" style="font-size: 48px; margin-bottom: 16px; color: var(--border-color); display: block;"></i>
                                暂无用户数据
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <?php
                            // 获取用户头像路径
                            $avatar_path = $user['avatar'];
                            $avatar_exists = false;
                            
                            if ($avatar_path && $avatar_path !== '/static/user-images/user.png') {
                                $full_avatar_path = __DIR__ . '/..' . $avatar_path;
                                $avatar_exists = file_exists($full_avatar_path);
                            }
                            
                            if (!$avatar_exists) {
                                $avatar_path = '/static/user-images/user.png';
                            }
                            ?>
                            <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.3s;">
                                <td style="padding: 16px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; overflow: hidden; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));">
                                            <img src="<?php echo $avatar_path; ?>" 
                                                 alt="<?php echo htmlspecialchars($user['name']); ?>" 
                                                 style="width: 100%; height: 100%; object-fit: cover;"
                                                 onerror="this.src='/static/user-images/user.png'">
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: var(--text-primary);"><?php echo htmlspecialchars($user['name']); ?></div>
                                            <div style="font-size: 12px; color: var(--text-secondary);">ID: <?php echo $user['id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 16px;">
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($user['username']); ?></div>
                                    <?php if (!empty($user['mail'])): ?>
                                        <div style="font-size: 12px; color: var(--text-secondary);"><?php echo htmlspecialchars($user['mail']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 16px;">
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <div style="font-weight: 500; color: var(--text-primary);">
                                            <?php echo $user['points']; ?> 积分
                                        </div>
                                        <div style="font-size: 12px; color: var(--text-secondary);">
                                            <?php if ($user['expiry'] && strtotime($user['expiry']) > time()): ?>
                                                VIP 至 <?php echo date('Y-m-d', strtotime($user['expiry'])); ?>
                                            <?php else: ?>
                                                普通用户
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 16px;">
                                    <div style="color: var(--text-primary);"><?php echo date('Y-m-d', strtotime($user['create_time'])); ?></div>
                                    <div style="font-size: 12px; color: var(--text-secondary);"><?php echo date('H:i:s', strtotime($user['create_time'])); ?></div>
                                </td>
                                <td style="padding: 16px;">
                                    <span class="status-badge <?php echo $user['status'] ? 'active' : 'inactive'; ?>" 
                                          style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; display: inline-block;">
                                        <?php echo $user['status'] ? '启用' : '禁用'; ?>
                                    </span>
                                </td>
                                <td style="padding: 16px; text-align: center;">
                                    <div style="display: flex; gap: 8px; justify-content: center;">
                                        <button onclick="editUser(<?php echo $user['id']; ?>)" 
                                                class="btn-edit"
                                                style="padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; display: flex; align-items: center; gap: 4px; background: var(--primary-color); color: white;">
                                            <i class="fas fa-edit"></i>
                                            编辑
                                        </button>
                                        
                                        <button onclick="toggleStatus(<?php echo $user['id']; ?>)" 
                                                class="btn-status <?php echo $user['status'] ? 'disable' : 'enable'; ?>"
                                                style="padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; display: flex; align-items: center; gap: 4px;">
                                            <i class="fas <?php echo $user['status'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                            <?php echo $user['status'] ? '禁用' : '启用'; ?>
                                        </button>
                                        
                                        <button onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['name']); ?>')" 
                                                class="btn-delete"
                                                style="padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; display: flex; align-items: center; gap: 4px; background: var(--danger-color); color: white;">
                                            <i class="fas fa-trash"></i>
                                            删除
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 分页 -->
        <?php if ($total_pages > 1): ?>
            <div style="padding: 20px; display: flex; justify-content: center; border-top: 1px solid var(--border-color);">
                <div style="display: flex; gap: 8px;">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" 
                           style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; text-decoration: none; color: var(--text-primary);">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" 
                           style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; text-decoration: none; 
                                  <?php echo $i == $current_page ? 'background: var(--primary-color); color: white; border-color: var(--primary-color);' : 'color: var(--text-primary);'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>" 
                           style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; text-decoration: none; color: var(--text-primary);">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 编辑用户模态框 -->
<div id="editModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: white; border-radius: 12px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header" style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.25rem; font-weight: 600; color: var(--dark-color);">编辑用户信息</h3>
            <button onclick="closeEditModal()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: var(--text-secondary);">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="editForm" method="POST" enctype="multipart/form-data" class="modal-body" style="padding: 20px;">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="edit_user_id">
            
            <div style="display: grid; gap: 16px;">
                <!-- 头像上传 -->
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">用户头像</label>
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div id="avatarPreview" style="width: 80px; height: 80px; border-radius: 50%; overflow: hidden; border: 2px solid var(--border-color);">
                            <img id="currentAvatar" src="" alt="当前头像" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <div style="flex: 1;">
                            <input type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/gif" style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">
                                支持 JPG、PNG、GIF 格式，最大 2MB
                            </div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">昵称 *</label>
                    <input type="text" name="name" id="edit_name" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">用户名 *</label>
                    <input type="text" name="username" id="edit_username" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">邮箱</label>
                    <input type="email" name="mail" id="edit_mail" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">手机号</label>
                    <input type="tel" name="phone" id="edit_phone" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">积分</label>
                    <input type="number" name="points" id="edit_points" min="0" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">VIP到期时间</label>
                    <input type="datetime-local" name="expiry" id="edit_expiry" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">状态</label>
                    <select name="status" id="edit_status" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer" style="padding: 20px 0 0; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeEditModal()" style="padding: 10px 20px; border: 1px solid var(--border-color); border-radius: 8px; background: white; color: var(--text-primary); cursor: pointer; font-size: 14px;">
                    取消
                </button>
                <button type="submit" style="padding: 10px 20px; border: none; border-radius: 8px; background: var(--primary-color); color: white; cursor: pointer; font-size: 14px;">
                    保存更改
                </button>
            </div>
        </form>
    </div>
</div>

</div><!-- 关闭 content-wrapper -->
</div><!-- 关闭 main-content -->

<script>
// 状态切换
function toggleStatus(userId) {
    if (confirm('确定要切换该用户的状态吗？')) {
        window.location.href = 'users.php?action=toggle_status&id=' + userId;
    }
}

// 删除确认
function confirmDelete(userId, userName) {
    if (confirm('确定要永久删除用户 "' + userName + '" 吗？\n\n此操作将删除该用户的所有数据，包括：\n• 用户基本信息\n• 创建的角色\n• 聊天记录\n• 评分记录\n• 订阅记录\n• 用户头像\n\n此操作不可恢复！')) {
        window.location.href = 'users.php?action=delete&id=' + userId;
    }
}

// 编辑用户
function editUser(userId) {
    // 显示加载状态
    const modal = document.getElementById('editModal');
    modal.style.display = 'flex';
    
    // 清空表单和头像预览
    document.getElementById('editForm').reset();
    document.getElementById('currentAvatar').src = '/static/user-images/user.png';
    
    // 获取用户数据
    fetch('./api/get_user.php?id=' + userId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                document.getElementById('edit_user_id').value = user.id;
                document.getElementById('edit_name').value = user.name;
                document.getElementById('edit_username').value = user.username;
                document.getElementById('edit_mail').value = user.mail || '';
                document.getElementById('edit_phone').value = user.phone || '';
                document.getElementById('edit_points').value = user.points;
                document.getElementById('edit_status').value = user.status;
                
                // 设置头像
                if (user.avatar) {
                    document.getElementById('currentAvatar').src = user.avatar;
                } else {
                    document.getElementById('currentAvatar').src = '/static/user-images/user.png';
                }
                
                // 处理VIP到期时间
                if (user.expiry) {
                    const expiryDate = new Date(user.expiry);
                    document.getElementById('edit_expiry').value = expiryDate.toISOString().slice(0, 16);
                } else {
                    document.getElementById('edit_expiry').value = '';
                }
            } else {
                alert('获取用户信息失败: ' + data.message);
                closeEditModal();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('获取用户信息失败');
            closeEditModal();
        });
}

// 关闭编辑模态框
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// 头像预览
document.getElementById('avatarInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('currentAvatar').src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
});

// 导出用户（示例功能）
function exportUsers() {
    alert('导出功能开发中...');
}

// 状态标签样式
document.addEventListener('DOMContentLoaded', function() {
    const statusBadges = document.querySelectorAll('.status-badge');
    statusBadges.forEach(badge => {
        if (badge.classList.contains('active')) {
            badge.style.background = 'rgba(16, 185, 129, 0.1)';
            badge.style.color = 'var(--success-color)';
        } else {
            badge.style.background = 'rgba(239, 68, 68, 0.1)';
            badge.style.color = 'var(--danger-color)';
        }
    });

    // 状态按钮样式
    const statusButtons = document.querySelectorAll('.btn-status');
    statusButtons.forEach(btn => {
        if (btn.classList.contains('disable')) {
            btn.style.background = 'rgba(239, 68, 68, 0.1)';
            btn.style.color = 'var(--danger-color)';
        } else {
            btn.style.background = 'rgba(16, 185, 129, 0.1)';
            btn.style.color = 'var(--success-color)';
        }
    });
});

// 点击模态框外部关闭
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>

<style>
.btn-edit:hover {
    background: var(--primary-dark) !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

.btn-status:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.btn-delete:hover {
    background: #dc2626 !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.message {
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.users-table-card table tbody tr:hover {
    background: var(--light-color);
}

.modal-content {
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* 头像预览样式 */
#avatarPreview {
    transition: all 0.3s ease;
}

#avatarPreview:hover {
    border-color: var(--primary-color);
    transform: scale(1.05);
}
</style>

</body>
</html>