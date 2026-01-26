<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-17
最后编辑时间：2025-10-19
文件描述：管理员页面，可以添加下级管理员，帮助审核用户和管理后台，
下级管理员权限和超级管理员权限一样(懒，没有单独写权限分类，我预想是下级管理员只能审核角色和生成卡密），下级管理员不能再添加管理员
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

// 如果不是超级管理员，重定向到首页
if ($admin_level != 0) {
    header('Location: index.php');
    exit;
}

// 处理表单提交
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_admin'])) {
        // 添加管理员
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $email = trim($_POST['email']);
        $level = intval($_POST['level']);
        
        // 验证输入
        if (empty($username) || empty($password)) {
            $message = '用户名和密码不能为空';
            $message_type = 'error';
        } else {
            try {
                // 检查用户名是否已存在
                $stmt = $db->prepare("SELECT id FROM admin WHERE user = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $message = '用户名已存在';
                    $message_type = 'error';
                } else {
                    // 插入新管理员
                    $hashed_password = md5($password);
                    $stmt = $db->prepare("INSERT INTO admin (user, password, level, mail) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $level, $email]);
                    
                    $message = '管理员添加成功';
                    $message_type = 'success';
                }
            } catch (PDOException $e) {
                $message = '添加管理员失败: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['delete_admin'])) {
        // 删除管理员
        $delete_id = intval($_POST['delete_id']);
        
        // 不能删除自己
        if ($delete_id == $admin_id) {
            $message = '不能删除自己的账号';
            $message_type = 'error';
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM admin WHERE id = ?");
                $stmt->execute([$delete_id]);
                
                $message = '管理员删除成功';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = '删除管理员失败: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['reset_password'])) {
        // 重置密码
        $reset_id = intval($_POST['reset_id']);
        $new_password = $_POST['new_password'];
        
        if (empty($new_password)) {
            $message = '新密码不能为空';
            $message_type = 'error';
        } else {
            try {
                $hashed_password = md5($new_password);
                $stmt = $db->prepare("UPDATE admin SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $reset_id]);
                
                $message = '密码重置成功';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = '密码重置失败: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// 获取管理员列表
$admins = [];
try {
    $stmt = $db->query("SELECT id, user, level, mail, create_time FROM admin ORDER BY level ASC, id ASC");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取管理员列表失败: " . $e->getMessage());
}

// 包含导航栏
include 'navbar.php';
?>

<div class="admins-page">
    <!-- 页面标题和操作按钮 -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="font-size: 1.75rem; font-weight: 600; color: var(--dark-color);">管理员管理</h1>
        <button id="addAdminBtn" class="btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i>
            添加管理员
        </button>
    </div>

    <!-- 温馨提示 -->
    <div style="background: #e3f2fd; border: 1px solid #bbdefb; border-radius: 8px; padding: 16px; margin-bottom: 24px;">
        <div style="display: flex; align-items: flex-start; gap: 12px;">
            <i class="fas fa-info-circle" style="color: #1976d2; font-size: 18px; margin-top: 2px;"></i>
            <div>
                <h4 style="margin: 0 0 8px 0; color: #1565c0; font-size: 14px; font-weight: 600;">重要提示</h4>
                <p style="margin: 0; color: #424242; font-size: 13px; line-height: 1.5;">
                    管理员账号拥有系统管理权限，请谨慎操作。请务必将管理员账号交给信任的人员，避免泄露给不相关的人员。
                </p>
            </div>
        </div>
    </div>

    <!-- 消息提示 -->
    <?php if ($message): ?>
    <div class="message <?php echo $message_type; ?>" style="margin-bottom: 24px;">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- 添加管理员表单 -->
    <div id="addAdminForm" class="form-container" style="display: none; background: white; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <h3 style="margin-bottom: 20px; font-size: 1.25rem; font-weight: 600;">添加新管理员</h3>
        <form method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label for="username">用户名 *</label>
                    <input type="text" id="username" name="username" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
                </div>
                
                <div class="form-group">
                    <label for="password">密码 *</label>
                    <input type="password" id="password" name="password" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
                </div>
                
                <div class="form-group">
                    <label for="email">邮箱</label>
                    <input type="email" id="email" name="email" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
                </div>
                
                <div class="form-group">
                    <label for="level">管理员级别</label>
                    <select id="level" name="level" style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
                        <option value="1">普通管理员</option>
                    </select>
                    <small style="color: var(--text-secondary); font-size: 12px;">只能创建普通管理员账号</small>
                </div>
            </div>
            
            <div style="display: flex; gap: 12px; margin-top: 20px;">
                <button type="submit" name="add_admin" class="btn-primary" style="padding: 10px 20px;">
                    添加管理员
                </button>
                <button type="button" id="cancelAdd" class="btn-secondary" style="padding: 10px 20px;">
                    取消
                </button>
            </div>
        </form>
    </div>

    <!-- 管理员列表 -->
    <div class="admins-list" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <div style="padding: 24px; border-bottom: 1px solid var(--border-color);">
            <h3 style="margin: 0; font-size: 1.25rem; font-weight: 600;">管理员列表</h3>
        </div>
        
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--light-color);">
                        <th style="padding: 16px; text-align: left; font-weight: 600; font-size: 14px;">ID</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; font-size: 14px;">用户名</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; font-size: 14px;">级别</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; font-size: 14px;">邮箱</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; font-size: 14px;">创建时间</th>
                        <th style="padding: 16px; text-align: left; font-weight: 600; font-size: 14px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins as $admin): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 16px; font-size: 14px;"><?php echo $admin['id']; ?></td>
                        <td style="padding: 16px; font-size: 14px;">
                            <?php echo htmlspecialchars($admin['user']); ?>
                            <?php if ($admin['id'] == $admin_id): ?>
                                <span style="background: var(--primary-color); color: white; padding: 2px 6px; border-radius: 4px; font-size: 12px; margin-left: 8px;">当前账号</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px; font-size: 14px;">
                            <?php if ($admin['level'] == 0): ?>
                                <span style="background: var(--danger-color); color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">超级管理员</span>
                            <?php else: ?>
                                <span style="background: var(--success-color); color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px;">普通管理员</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px; font-size: 14px;"><?php echo htmlspecialchars($admin['mail'] ?? '-'); ?></td>
                        <td style="padding: 16px; font-size: 14px;"><?php echo date('Y-m-d H:i', strtotime($admin['create_time'])); ?></td>
                        <td style="padding: 16px; font-size: 14px;">
                            <div style="display: flex; gap: 8px;">
                                <?php if ($admin['id'] != $admin_id): ?>
                                <button type="button" class="reset-password-btn" data-id="<?php echo $admin['id']; ?>" data-username="<?php echo htmlspecialchars($admin['user']); ?>" style="background: var(--warning-color); color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                    重置密码
                                </button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除管理员 <?php echo htmlspecialchars($admin['user']); ?> 吗？此操作不可恢复！')">
                                    <input type="hidden" name="delete_id" value="<?php echo $admin['id']; ?>">
                                    <button type="submit" name="delete_admin" class="btn-danger" style="padding: 6px 12px; border-radius: 4px; font-size: 12px;">
                                        删除
                                    </button>
                                </form>
                                <?php else: ?>
                                <span style="color: var(--text-secondary); font-size: 12px;">不可操作</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 重置密码模态框 -->
<div id="resetPasswordModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 400px;">
        <h3 style="margin-bottom: 16px; font-size: 1.25rem; font-weight: 600;">重置密码</h3>
        <p style="margin-bottom: 20px; color: var(--text-secondary); font-size: 14px;">
            为管理员 <span id="resetUsername" style="font-weight: 600;"></span> 重置密码
        </p>
        <form method="POST" id="resetPasswordForm">
            <input type="hidden" name="reset_id" id="resetId">
            <div class="form-group">
                <label for="new_password">新密码 *</label>
                <input type="password" id="new_password" name="new_password" required style="width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
            </div>
            <div style="display: flex; gap: 12px; margin-top: 20px;">
                <button type="submit" name="reset_password" class="btn-primary" style="padding: 10px 20px;">
                    确认重置
                </button>
                <button type="button" id="cancelReset" class="btn-secondary" style="padding: 10px 20px;">
                    取消
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.btn-primary {
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.3s;
}

.btn-primary:hover {
    background: var(--primary-dark);
}

.btn-secondary {
    background: var(--border-color);
    color: var(--text-primary);
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.3s;
}

.btn-secondary:hover {
    background: #d1d5db;
}

.btn-danger {
    background: var(--danger-color);
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    transition: background 0.3s;
}

.btn-danger:hover {
    background: #dc2626;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    font-size: 14px;
    color: var(--text-primary);
}

.message {
    padding: 12px 16px;
    border-radius: 6px;
    font-size: 14px;
}

.message.success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.message.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

table tbody tr:hover {
    background: var(--light-color);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const addAdminBtn = document.getElementById('addAdminBtn');
    const addAdminForm = document.getElementById('addAdminForm');
    const cancelAdd = document.getElementById('cancelAdd');
    const resetPasswordModal = document.getElementById('resetPasswordModal');
    const resetPasswordBtns = document.querySelectorAll('.reset-password-btn');
    const cancelReset = document.getElementById('cancelReset');
    const resetUsername = document.getElementById('resetUsername');
    const resetId = document.getElementById('resetId');

    // 显示/隐藏添加管理员表单
    if (addAdminBtn && addAdminForm) {
        addAdminBtn.addEventListener('click', function() {
            addAdminForm.style.display = 'block';
            addAdminBtn.style.display = 'none';
        });
        
        cancelAdd.addEventListener('click', function() {
            addAdminForm.style.display = 'none';
            addAdminBtn.style.display = 'inline-flex';
        });
    }

    // 重置密码模态框
    resetPasswordBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const adminId = this.getAttribute('data-id');
            const username = this.getAttribute('data-username');
            
            resetId.value = adminId;
            resetUsername.textContent = username;
            resetPasswordModal.style.display = 'flex';
        });
    });

    if (cancelReset) {
        cancelReset.addEventListener('click', function() {
            resetPasswordModal.style.display = 'none';
        });
    }

    // 点击模态框外部关闭
    resetPasswordModal.addEventListener('click', function(e) {
        if (e.target === resetPasswordModal) {
            resetPasswordModal.style.display = 'none';
        }
    });
});
</script>

</div><!-- 关闭 content-wrapper -->
</div><!-- 关闭 main-content -->

</body>
</html>