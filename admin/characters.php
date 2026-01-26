<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-17
最后编辑时间：2025-10-19
文件描述：角色列表，可以编辑角色和查看角色等
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
    $character_id = intval($_GET['id']);
    
    try {
        // 开始事务
        $db->beginTransaction();
        
        // 删除角色相关的所有数据
        // 1. 删除角色评分
        $stmt = $db->prepare("DELETE FROM character_rating WHERE character_id = ?");
        $stmt->execute([$character_id]);
        
        // 2. 删除角色订阅
        $stmt = $db->prepare("DELETE FROM character_subscription WHERE character_id = ?");
        $stmt->execute([$character_id]);
        
        // 3. 删除聊天记录
        $stmt = $db->prepare("DELETE FROM chat_record WHERE character_id = ?");
        $stmt->execute([$character_id]);
        
        // 4. 删除会话
        $stmt = $db->prepare("DELETE FROM chat_session WHERE character_id = ?");
        $stmt->execute([$character_id]);
        
        // 5. 获取角色头像路径并删除头像文件（如果不是默认头像）
        $stmt = $db->prepare("SELECT avatar FROM ai_character WHERE id = ?");
        $stmt->execute([$character_id]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($character && !empty($character['avatar'])) {
            $avatar = $character['avatar'];
            // 排除默认头像（支持多种格式）
            $default_avatars = [
                '/static/ai-images/ai.png',
                '\\static\\ai-images\\ai.png',
                'static/ai-images/ai.png',
                'static\\ai-images\\ai.png'
            ];
            
            // 标准化路径进行比较
            $normalized_avatar = str_replace(['\\', '/'], '/', strtolower(trim($avatar)));
            $is_default = false;
            foreach ($default_avatars as $default) {
                $normalized_default = str_replace(['\\', '/'], '/', strtolower(trim($default)));
                if ($normalized_avatar === $normalized_default || basename($normalized_avatar) === 'ai.png') {
                    $is_default = true;
                    break;
                }
            }
            
            if (!$is_default) {
                // 提取文件名
                $filename = basename($avatar);
                // 构建完整路径
                $full_path = __DIR__ . '/../static/ai-images/' . $filename;
                
                // 如果文件存在，删除它
                if (file_exists($full_path)) {
                    @unlink($full_path);
                }
            }
        }
        
        // 6. 最后删除角色
        $stmt = $db->prepare("DELETE FROM ai_character WHERE id = ?");
        $stmt->execute([$character_id]);
        
        $db->commit();
        
        $_SESSION['message'] = "角色删除成功！";
        $_SESSION['message_type'] = "success";
        
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['message'] = "删除角色失败: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header('Location: characters.php');
    exit;
}

// 处理状态切换 - 通过/禁用
if (isset($_GET['action']) && $_GET['action'] == 'toggle_status' && isset($_GET['id'])) {
    $character_id = intval($_GET['id']);
    
    try {
        // 获取当前状态
        $stmt = $db->prepare("SELECT status FROM ai_character WHERE id = ?");
        $stmt->execute([$character_id]);
        $current_status = $stmt->fetchColumn();
        
        // 切换状态：1(通过) <-> 2(禁用)
        $new_status = ($current_status == 1) ? 2 : 1;
        
        $stmt = $db->prepare("UPDATE ai_character SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $character_id]);
        
        $_SESSION['message'] = "角色状态更新成功！";
        $_SESSION['message_type'] = "success";
        
    } catch (PDOException $e) {
        $_SESSION['message'] = "状态更新失败: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header('Location: characters.php');
    exit;
}

// 处理公开状态切换
if (isset($_GET['action']) && $_GET['action'] == 'toggle_public' && isset($_GET['id'])) {
    $character_id = intval($_GET['id']);
    
    try {
        $stmt = $db->prepare("UPDATE ai_character SET is_public = 1 - is_public WHERE id = ?");
        $stmt->execute([$character_id]);
        
        $_SESSION['message'] = "公开状态更新成功！";
        $_SESSION['message_type'] = "success";
        
    } catch (PDOException $e) {
        $_SESSION['message'] = "状态更新失败: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header('Location: characters.php');
    exit;
}

// 处理头像上传
function handleAvatarUpload($character_id) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../static/ai-images/';
        
        // 检查目录是否存在，不存在则创建
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        
        // 只允许上传 png 和 jpg 文件
        if (!in_array($file_extension, ['png', 'jpg', 'jpeg'])) {
            return ['success' => false, 'message' => '只允许上传 PNG 或 JPG 格式的图片'];
        }
        
        // 生成文件名：角色id-ai.png
        $filename = $character_id . '-ai.png';
        $file_path = $upload_dir . $filename;
        
        // 如果是 jpg/jpeg，先转换为 png
        if (in_array($file_extension, ['jpg', 'jpeg'])) {
            $image = imagecreatefromjpeg($_FILES['avatar']['tmp_name']);
            if ($image) {
                imagepng($image, $file_path);
                imagedestroy($image);
            } else {
                return ['success' => false, 'message' => '图片处理失败'];
            }
        } else {
            // 直接移动 png 文件
            if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $file_path)) {
                return ['success' => false, 'message' => '文件上传失败'];
            }
        }
        
        return ['success' => true, 'file_path' => '/static/ai-images/' . $filename];
    }
    
    return ['success' => true, 'file_path' => null];
}

// 处理添加角色
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_character') {
    $name = trim($_POST['name']);
    $category_id = intval($_POST['category_id']);
    $prompt = trim($_POST['prompt']);
    $introduction = trim($_POST['introduction']);
    $is_public = intval($_POST['is_public']);
    $status = intval($_POST['status']);
    
    // 验证必填字段
    if (empty($name) || empty($prompt)) {
        $_SESSION['message'] = "角色名称和提示词不能为空！";
        $_SESSION['message_type'] = "error";
    } else {
        try {
            // 检查角色名称是否已存在
            $stmt = $db->prepare("SELECT id FROM ai_character WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetch()) {
                $_SESSION['message'] = "角色名称已存在！";
                $_SESSION['message_type'] = "error";
            } else {
                // 插入新角色
                $sql = "INSERT INTO ai_character (name, user_id, category_id, prompt, introduction, is_public, status, create_time) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $db->prepare($sql);
                $stmt->execute([$name, $admin_id, $category_id, $prompt, $introduction, $is_public, $status]);
                
                $character_id = $db->lastInsertId();
                
                // 处理头像上传
                $avatar_result = handleAvatarUpload($character_id);
                if (!$avatar_result['success']) {
                    $_SESSION['message'] = "角色添加成功，但头像上传失败: " . $avatar_result['message'];
                    $_SESSION['message_type'] = "warning";
                } else {
                    // 如果有上传头像，更新数据库中的头像路径
                    if ($avatar_result['file_path']) {
                        $stmt = $db->prepare("UPDATE ai_character SET avatar = ? WHERE id = ?");
                        $stmt->execute([$avatar_result['file_path'], $character_id]);
                    }
                    $_SESSION['message'] = "角色添加成功！";
                    $_SESSION['message_type'] = "success";
                }
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "添加角色失败: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    }
    
    header('Location: characters.php');
    exit;
}

// 处理编辑角色
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit_character') {
    $character_id = intval($_POST['character_id']);
    $name = trim($_POST['name']);
    $category_id = intval($_POST['category_id']);
    $prompt = trim($_POST['prompt']);
    $introduction = trim($_POST['introduction']);
    $is_public = intval($_POST['is_public']);
    $status = intval($_POST['status']);
    
    // 验证必填字段
    if (empty($name) || empty($prompt)) {
        $_SESSION['message'] = "角色名称和提示词不能为空！";
        $_SESSION['message_type'] = "error";
    } else {
        try {
            // 检查角色名称是否被其他角色使用
            $stmt = $db->prepare("SELECT id FROM ai_character WHERE name = ? AND id != ?");
            $stmt->execute([$name, $character_id]);
            if ($stmt->fetch()) {
                $_SESSION['message'] = "角色名称已被其他角色使用！";
                $_SESSION['message_type'] = "error";
            } else {
                // 处理头像上传
                $avatar_result = handleAvatarUpload($character_id);
                if (!$avatar_result['success']) {
                    $_SESSION['message'] = "头像上传失败: " . $avatar_result['message'];
                    $_SESSION['message_type'] = "error";
                    header('Location: characters.php');
                    exit;
                }
                
                // 如果有上传头像，更新头像路径
                $avatar_update = "";
                $params = [$name, $category_id, $prompt, $introduction, $is_public, $status, $character_id];
                
                if ($avatar_result['file_path']) {
                    $avatar_update = ", avatar = ?";
                    array_splice($params, -1, 0, [$avatar_result['file_path']]);
                }
                
                // 更新角色信息
                $sql = "UPDATE ai_character SET name = ?, category_id = ?, prompt = ?, introduction = ?, is_public = ?, status = ?, update_time = NOW() $avatar_update WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                $_SESSION['message'] = "角色信息更新成功！";
                $_SESSION['message_type'] = "success";
            }
        } catch (PDOException $e) {
            $_SESSION['message'] = "更新角色信息失败: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    }
    
    header('Location: characters.php');
    exit;
}

// 获取搜索和筛选参数
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$public_filter = isset($_GET['public']) ? $_GET['public'] : '';

// 构建查询条件
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(ac.name LIKE ? OR ac.introduction LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($category_filter)) {
    $where_conditions[] = "ac.category_id = ?";
    $params[] = $category_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "ac.status = ?";
    $params[] = $status_filter;
}

if ($public_filter !== '') {
    $where_conditions[] = "ac.is_public = ?";
    $params[] = $public_filter;
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// 获取角色总数（用于分页）
try {
    $count_sql = "SELECT COUNT(*) FROM ai_character ac $where_sql";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_characters = $stmt->fetchColumn();
} catch (PDOException $e) {
    $total_characters = 0;
    error_log("获取角色总数失败: " . $e->getMessage());
}

// 分页设置
$per_page = 20;
$total_pages = ceil($total_characters / $per_page);
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $per_page;

// 获取角色列表（包含分类信息和用户信息）
$characters = [];
try {
    $sql = "SELECT ac.*, cc.name as category_name, u.name as creator_name 
            FROM ai_character ac 
            LEFT JOIN character_category cc ON ac.category_id = cc.id 
            LEFT JOIN user u ON ac.user_id = u.id 
            $where_sql 
            ORDER BY ac.create_time DESC 
            LIMIT $per_page OFFSET $offset";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取角色列表失败: " . $e->getMessage());
}

// 获取分类列表
$categories = [];
try {
    $stmt = $db->query("SELECT * FROM character_category WHERE status = 1 ORDER BY sort_order ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取分类列表失败: " . $e->getMessage());
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

<div class="characters-page">
    <!-- 页面标题和操作按钮 -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="font-size: 1.75rem; font-weight: 600; color: var(--dark-color);">角色管理</h1>
        <div style="display: flex; gap: 12px;">
            <button onclick="showAddModal()" style="background: var(--success-color); color: white; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 14px;">
                <i class="fas fa-plus"></i>
                添加角色
            </button>
            <button onclick="exportCharacters()" style="background: var(--primary-color); color: white; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 14px;">
                <i class="fas fa-download"></i>
                导出角色
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
        <form method="GET" style="display: grid; grid-template-columns: 1fr auto auto auto auto; gap: 12px; align-items: end;">
            <div>
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">搜索角色</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="搜索角色名称或介绍..." style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">分类筛选</label>
                <select name="category" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                    <option value="">全部分类</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">状态筛选</label>
                <select name="status" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                    <option value="">全部状态</option>
                    <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>通过</option>
                    <option value="2" <?php echo $status_filter === '2' ? 'selected' : ''; ?>>禁用</option>
                </select>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">公开状态</label>
                <select name="public" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                    <option value="">全部</option>
                    <option value="1" <?php echo $public_filter === '1' ? 'selected' : ''; ?>>公开</option>
                    <option value="0" <?php echo $public_filter === '0' ? 'selected' : ''; ?>>私密</option>
                </select>
            </div>
            
            <div>
                <button type="submit" style="background: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; height: 40px;">
                    <i class="fas fa-search"></i> 搜索
                </button>
            </div>
        </form>
    </div>

    <!-- 角色统计 -->
    <div class="character-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);"><?php echo $total_characters; ?></div>
            <div style="color: var(--text-secondary); font-size: 14px;">总角色数</div>
        </div>
        
        <?php
        // 获取今日新增角色
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM ai_character WHERE DATE(create_time) = CURDATE()");
            $today_characters = $stmt->fetchColumn();
        } catch (PDOException $e) {
            $today_characters = 0;
        }
        ?>
        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div style="font-size: 2rem; font-weight: 700; color: var(--success-color);"><?php echo $today_characters; ?></div>
            <div style="color: var(--text-secondary); font-size: 14px;">今日新增</div>
        </div>
        
        <?php
        // 获取通过角色数
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM ai_character WHERE status = 1");
            $enabled_characters = $stmt->fetchColumn();
        } catch (PDOException $e) {
            $enabled_characters = 0;
        }
        ?>
        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div style="font-size: 2rem; font-weight: 700; color: var(--success-color);"><?php echo $enabled_characters; ?></div>
            <div style="color: var(--text-secondary); font-size: 14px;">通过角色</div>
        </div>
        
        <?php
        // 获取公开角色数
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM ai_character WHERE is_public = 1 AND status = 1");
            $public_characters = $stmt->fetchColumn();
        } catch (PDOException $e) {
            $public_characters = 0;
        }
        ?>
        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <div style="font-size: 2rem; font-weight: 700; color: var(--warning-color);"><?php echo $public_characters; ?></div>
            <div style="color: var(--text-secondary); font-size: 14px;">公开角色</div>
        </div>
    </div>

    <!-- 角色列表 -->
    <div class="characters-table-card" style="background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden;">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--light-color);">
                        <th style="padding: 16px; text-align: left; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">角色信息</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">分类信息</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">创建信息</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">使用统计</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">状态</th>
                        <th style="padding: 16px; text-align: center; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($characters)): ?>
                        <tr>
                            <td colspan="6" style="padding: 40px; text-align: center; color: var(--text-secondary);">
                                <i class="fas fa-robot" style="font-size: 48px; margin-bottom: 16px; color: var(--border-color); display: block;"></i>
                                暂无角色数据
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($characters as $character): ?>
                            <tr style="border-bottom: 1px solid var(--border-color); transition: background 0.3s;">
                                <td style="padding: 16px;">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <?php
                                        // 显示头像
                                        $custom_avatar_path = '/static/ai-images/' . $character['id'] . '-ai.png';
                                        $default_avatar_path = '/static/ai-images/ai.png';
                                        $db_avatar_path = $character['avatar'] ?? '';
                                        
                                        // 优先检查自定义头像文件是否存在
                                        $avatar_to_show = $custom_avatar_path;
                                        if (!file_exists(__DIR__ . '/..' . $custom_avatar_path)) {
                                            // 如果自定义头像不存在，检查数据库中的头像
                                            if (!empty($db_avatar_path) && $db_avatar_path != '/static/images/default_character.png') {
                                                $avatar_to_show = $db_avatar_path;
                                            } else {
                                                // 使用默认头像
                                                $avatar_to_show = $default_avatar_path;
                                            }
                                        }
                                        ?>
                                        <div style="width: 50px; height: 50px; border-radius: 8px; background: #ffffff; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; overflow: hidden;">
                                            <img src="<?php echo $avatar_to_show; ?>?t=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($character['name']); ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">

                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: var(--text-primary); margin-bottom: 4px;"><?php echo htmlspecialchars($character['name']); ?></div>
                                            <div style="font-size: 12px; color: var(--text-secondary);">ID: <?php echo $character['id']; ?></div>
                                            <?php if (!empty($character['introduction'])): ?>
                                                <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                    <?php echo htmlspecialchars($character['introduction']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 16px;">
                                    <div style="font-weight: 500; color: var(--text-primary);"><?php echo htmlspecialchars($character['category_name'] ?? '未分类'); ?></div>
                                </td>
                                <td style="padding: 16px;">
                                    <div style="color: var(--text-primary);"><?php echo htmlspecialchars($character['creator_name'] ?? '未知用户'); ?></div>
                                    <div style="font-size: 12px; color: var(--text-secondary);"><?php echo date('Y-m-d', strtotime($character['create_time'])); ?></div>
                                </td>
                                <td style="padding: 16px;">
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <div style="font-weight: 500; color: var(--text-primary);">
                                            使用: <?php echo $character['usage_count']; ?> 次
                                        </div>
                                        <div style="font-size: 12px; color: var(--text-secondary);">
                                            评分: <?php echo number_format($character['avg_rating'], 1); ?> 分
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 16px;">
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <span class="status-badge <?php echo $character['status'] == 1 ? 'active' : 'inactive'; ?>" 
                                              style="padding: 4px 8px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; width: fit-content;">
                                            <?php echo $character['status'] == 1 ? '通过' : '禁用'; ?>
                                        </span>
                                        <span class="public-badge <?php echo $character['is_public'] ? 'public' : 'private'; ?>" 
                                              style="padding: 4px 8px; border-radius: 20px; font-size: 11px; font-weight: 500; display: inline-block; width: fit-content;">
                                            <?php echo $character['is_public'] ? '公开' : '私密'; ?>
                                        </span>
                                    </div>
                                </td>
                                <td style="padding: 16px; text-align: center;">
                                    <div style="display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">
                                        <button onclick="editCharacter(<?php echo $character['id']; ?>)" 
                                                class="btn-edit"
                                                style="padding: 6px 10px; border: none; border-radius: 6px; cursor: pointer; font-size: 11px; display: flex; align-items: center; gap: 4px; background: var(--primary-color); color: white;">
                                            <i class="fas fa-edit"></i>
                                            编辑
                                        </button>
                                        
                                        <button onclick="togglePublic(<?php echo $character['id']; ?>)" 
                                                class="btn-public <?php echo $character['is_public'] ? 'private' : 'public'; ?>"
                                                style="padding: 6px 10px; border: none; border-radius: 6px; cursor: pointer; font-size: 11px; display: flex; align-items: center; gap: 4px;">
                                            <i class="fas <?php echo $character['is_public'] ? 'fa-lock' : 'fa-globe'; ?>"></i>
                                            <?php echo $character['is_public'] ? '私密' : '公开'; ?>
                                        </button>
                                        
                                        <button onclick="toggleStatus(<?php echo $character['id']; ?>)" 
                                                class="btn-status <?php echo $character['status'] == 1 ? 'disable' : 'enable'; ?>"
                                                style="padding: 6px 10px; border: none; border-radius: 6px; cursor: pointer; font-size: 11px; display: flex; align-items: center; gap: 4px;">
                                            <i class="fas <?php echo $character['status'] == 1 ? 'fa-ban' : 'fa-check'; ?>"></i>
                                            <?php echo $character['status'] == 1 ? '禁用' : '通过'; ?>
                                        </button>
                                        
                                        <button onclick="confirmDelete(<?php echo $character['id']; ?>, '<?php echo htmlspecialchars($character['name']); ?>')" 
                                                class="btn-delete"
                                                style="padding: 6px 10px; border: none; border-radius: 6px; cursor: pointer; font-size: 11px; display: flex; align-items: center; gap: 4px; background: var(--danger-color); color: white;">
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
                        <a href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>&public=<?php echo $public_filter; ?>" 
                           style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; text-decoration: none; color: var(--text-primary);">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>&public=<?php echo $public_filter; ?>" 
                           style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; text-decoration: none; 
                                  <?php echo $i == $current_page ? 'background: var(--primary-color); color: white; border-color: var(--primary-color);' : 'color: var(--text-primary);'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>&public=<?php echo $public_filter; ?>" 
                           style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; text-decoration: none; color: var(--text-primary);">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 添加角色模态框 -->
<div id="addModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: white; border-radius: 12px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header" style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.25rem; font-weight: 600; color: var(--dark-color);">添加新角色</h3>
            <button onclick="closeAddModal()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: var(--text-secondary);">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="addForm" method="POST" enctype="multipart/form-data" class="modal-body" style="padding: 20px;">
            <input type="hidden" name="action" value="add_character">
            
            <div style="display: grid; gap: 16px;">
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">角色头像</label>
                    <div style="display: flex; gap: 16px; align-items: center;">
                        <div id="addAvatarPreview" style="width: 80px; height: 80px; border-radius: 8px; background: var(--light-color); display: flex; align-items: center; justify-content: center; overflow: hidden; border: 2px dashed var(--border-color);">
                            <i class="fas fa-camera" style="font-size: 24px; color: var(--text-secondary);"></i>
                        </div>
                        <div style="flex: 1;">
                            <input type="file" name="avatar" id="addAvatar" accept=".png,.jpg,.jpeg" style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">支持 PNG、JPG 格式，建议尺寸 1:1</div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">角色名称 *</label>
                    <input type="text" name="name" id="add_name" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;" placeholder="请输入角色名称">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">分类 *</label>
                    <select name="category_id" id="add_category_id" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                        <option value="">选择分类</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">角色介绍</label>
                    <textarea name="introduction" id="add_introduction" rows="3" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; resize: vertical;" placeholder="请输入角色介绍（可选）"></textarea>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">提示词 *</label>
                    <textarea name="prompt" id="add_prompt" rows="6" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; resize: vertical; font-family: monospace;" placeholder="请输入角色提示词，定义角色的性格、背景和行为"></textarea>
                    <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">提示词将决定AI角色的行为和回应方式</div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">公开状态</label>
                        <select name="is_public" id="add_is_public" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                            <option value="1">公开</option>
                            <option value="0">私密</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">状态</label>
                        <select name="status" id="add_status" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                            <option value="1">通过</option>
                            <option value="2">禁用</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer" style="padding: 20px 0 0; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="closeAddModal()" style="padding: 10px 20px; border: 1px solid var(--border-color); border-radius: 8px; background: white; color: var(--text-primary); cursor: pointer; font-size: 14px;">
                    取消
                </button>
                <button type="submit" style="padding: 10px 20px; border: none; border-radius: 8px; background: var(--success-color); color: white; cursor: pointer; font-size: 14px;">
                    添加角色
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑角色模态框 -->
<div id="editModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: white; border-radius: 12px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header" style="padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.25rem; font-weight: 600; color: var(--dark-color);">编辑角色信息</h3>
            <button onclick="closeEditModal()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: var(--text-secondary);">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="editForm" method="POST" enctype="multipart/form-data" class="modal-body" style="padding: 20px;">
            <input type="hidden" name="action" value="edit_character">
            <input type="hidden" name="character_id" id="edit_character_id">
            
            <div style="display: grid; gap: 16px;">
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">角色头像</label>
                    <div style="display: flex; gap: 16px; align-items: center;">
                        <div id="editAvatarPreview" style="width: 80px; height: 80px; border-radius: 8px; background: var(--light-color); display: flex; align-items: center; justify-content: center; overflow: hidden; border: 2px dashed var(--border-color);">
                            <i class="fas fa-camera" style="font-size: 24px; color: var(--text-secondary);"></i>
                        </div>
                        <div style="flex: 1;">
                            <input type="file" name="avatar" id="editAvatar" accept=".png,.jpg,.jpeg" style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
                            <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">支持 PNG、JPG 格式，建议尺寸 1:1</div>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">角色名称 *</label>
                    <input type="text" name="name" id="edit_name" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">分类 *</label>
                    <select name="category_id" id="edit_category_id" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                        <option value="">选择分类</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">角色介绍</label>
                    <textarea name="introduction" id="edit_introduction" rows="3" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; resize: vertical;"></textarea>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">提示词 *</label>
                    <textarea name="prompt" id="edit_prompt" rows="6" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px; resize: vertical; font-family: monospace;"></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">公开状态</label>
                        <select name="is_public" id="edit_is_public" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                            <option value="1">公开</option>
                            <option value="0">私密</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">状态</label>
                        <select name="status" id="edit_status" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 14px;">
                            <option value="1">通过</option>
                            <option value="2">禁用</option>
                        </select>
                    </div>
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
// 显示添加模态框
function showAddModal() {
    const modal = document.getElementById('addModal');
    modal.style.display = 'flex';
    document.getElementById('addForm').reset();
    document.getElementById('addAvatarPreview').innerHTML = '<i class="fas fa-camera" style="font-size: 24px; color: var(--text-secondary);"></i>';
}

// 关闭添加模态框
function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

// 状态切换 - 通过/禁用
function toggleStatus(characterId) {
    if (confirm('确定要切换该角色的状态吗？')) {
        window.location.href = 'characters.php?action=toggle_status&id=' + characterId;
    }
}

// 公开状态切换
function togglePublic(characterId) {
    if (confirm('确定要切换该角色的公开状态吗？')) {
        window.location.href = 'characters.php?action=toggle_public&id=' + characterId;
    }
}

// 删除确认
function confirmDelete(characterId, characterName) {
    if (confirm('确定要永久删除角色 "' + characterName + '" 吗？\n\n此操作将删除该角色的所有数据，包括：\n• 角色基本信息\n• 聊天记录\n• 评分记录\n• 订阅记录\n• 头像文件\n\n此操作不可恢复！')) {
        window.location.href = 'characters.php?action=delete&id=' + characterId;
    }
}

// 编辑角色
function editCharacter(characterId) {
    // 显示加载状态
    const modal = document.getElementById('editModal');
    modal.style.display = 'flex';
    
    // 清空表单
    document.getElementById('editForm').reset();
    
    // 获取角色数据
    fetch('./api/get_character.php?id=' + characterId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const character = data.character;
                document.getElementById('edit_character_id').value = character.id;
                document.getElementById('edit_name').value = character.name;
                document.getElementById('edit_category_id').value = character.category_id;
                document.getElementById('edit_introduction').value = character.introduction || '';
                document.getElementById('edit_prompt').value = character.prompt;
                document.getElementById('edit_is_public').value = character.is_public;
                document.getElementById('edit_status').value = character.status;
                
                // 显示当前头像
                const customAvatarPath = '/static/ai-images/' + character.id + '-ai.png';
                const defaultAvatarPath = '/static/ai-images/ai.png';
                const avatarPreview = document.getElementById('editAvatarPreview');
                
                // 检查自定义头像是否存在
                fetch(customAvatarPath + '?t=' + new Date().getTime(), { method: 'HEAD' })
                    .then(response => {
                        if (response.ok) {
                            avatarPreview.innerHTML = '<img src="' + customAvatarPath + '?t=' + new Date().getTime() + '" style="width: 100%; height: 100%; object-fit: cover;">';
                        } else {
                            // 检查数据库中的头像路径
                            if (character.avatar && character.avatar !== '/static/images/default_character.png') {
                                avatarPreview.innerHTML = '<img src="' + character.avatar + '?t=' + new Date().getTime() + '" style="width: 100%; height: 100%; object-fit: cover;">';
                            } else {
                                // 显示默认头像
                                avatarPreview.innerHTML = '<img src="' + defaultAvatarPath + '?t=' + new Date().getTime() + '" style="width: 100%; height: 100%; object-fit: cover;">';
                            }
                        }
                    })
                    .catch(() => {
                        // 如果检查失败，回退到数据库中的头像
                        if (character.avatar && character.avatar !== '/static/images/default_character.png') {
                            avatarPreview.innerHTML = '<img src="' + character.avatar + '?t=' + new Date().getTime() + '" style="width: 100%; height: 100%; object-fit: cover;">';
                        } else {
                            avatarPreview.innerHTML = '<img src="' + defaultAvatarPath + '?t=' + new Date().getTime() + '" style="width: 100%; height: 100%; object-fit: cover;">';
                        }
                    });
            } else {
                alert('获取角色信息失败: ' + data.message);
                closeEditModal();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('获取角色信息失败');
            closeEditModal();
        });
}

// 关闭编辑模态框
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// 导出角色（示例功能）
function exportCharacters() {
    alert('导出功能开发中...');
}

// 头像预览功能
document.addEventListener('DOMContentLoaded', function() {
    // 添加角色头像预览
    const addAvatarInput = document.getElementById('addAvatar');
    const addAvatarPreview = document.getElementById('addAvatarPreview');
    
    if (addAvatarInput) {
        addAvatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    addAvatarPreview.innerHTML = '<img src="' + e.target.result + '" style="width: 100%; height: 100%; object-fit: cover;">';
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // 编辑角色头像预览
    const editAvatarInput = document.getElementById('editAvatar');
    const editAvatarPreview = document.getElementById('editAvatarPreview');
    
    if (editAvatarInput) {
        editAvatarInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    editAvatarPreview.innerHTML = '<img src="' + e.target.result + '" style="width: 100%; height: 100%; object-fit: cover;">';
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    // 状态标签样式
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

    // 公开状态标签
    const publicBadges = document.querySelectorAll('.public-badge');
    publicBadges.forEach(badge => {
        if (badge.classList.contains('public')) {
            badge.style.background = 'rgba(102, 126, 234, 0.1)';
            badge.style.color = 'var(--primary-color)';
        } else {
            badge.style.background = 'rgba(107, 114, 128, 0.1)';
            badge.style.color = 'var(--text-secondary)';
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

    // 公开按钮样式
    const publicButtons = document.querySelectorAll('.btn-public');
    publicButtons.forEach(btn => {
        if (btn.classList.contains('private')) {
            btn.style.background = 'rgba(107, 114, 128, 0.1)';
            btn.style.color = 'var(--text-secondary)';
        } else {
            btn.style.background = 'rgba(102, 126, 234, 0.1)';
            btn.style.color = 'var(--primary-color)';
        }
    });
});

// 点击模态框外部关闭
document.getElementById('addModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAddModal();
    }
});

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

.btn-public:hover, .btn-status:hover {
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

.characters-table-card table tbody tr:hover {
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
</style>

</body>
</html>