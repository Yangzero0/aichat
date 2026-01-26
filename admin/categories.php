<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-17
最后编辑时间：2025-10-19
文件描述：分类标签管理页面，可以管理分类和添加分类
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

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        // 添加分类
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $sort_order = intval($_POST['sort_order']);
        $status = isset($_POST['status']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("INSERT INTO character_category (name, description, sort_order, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $description, $sort_order, $status]);
            $success_message = "分类添加成功！";
        } catch (PDOException $e) {
            $error_message = "添加分类失败: " . $e->getMessage();
        }
    } elseif (isset($_POST['edit_category'])) {
        // 编辑分类
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $sort_order = intval($_POST['sort_order']);
        $status = isset($_POST['status']) ? 1 : 0;
        
        try {
            $stmt = $db->prepare("UPDATE character_category SET name = ?, description = ?, sort_order = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $description, $sort_order, $status, $id]);
            $success_message = "分类更新成功！";
        } catch (PDOException $e) {
            $error_message = "更新分类失败: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_category'])) {
        // 删除分类
        $id = intval($_POST['id']);
        
        try {
            // 检查该分类下是否有角色
            $stmt = $db->prepare("SELECT COUNT(*) FROM ai_character WHERE category_id = ?");
            $stmt->execute([$id]);
            $character_count = $stmt->fetchColumn();
            
            if ($character_count > 0) {
                $error_message = "无法删除该分类，因为该分类下还有 {$character_count} 个角色。请先删除或转移这些角色。";
            } else {
                $stmt = $db->prepare("DELETE FROM character_category WHERE id = ?");
                $stmt->execute([$id]);
                $success_message = "分类删除成功！";
            }
        } catch (PDOException $e) {
            $error_message = "删除分类失败: " . $e->getMessage();
        }
    }
}

// 获取分类列表
$categories = [];
try {
    $stmt = $db->query("SELECT * FROM character_category ORDER BY sort_order ASC, id DESC");
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

// 直接包含 navbar.php
include 'navbar.php';
?>

<!-- 内容包装器中的内容 -->
<div class="categories-page">
    <!-- 页面标题和操作按钮 -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="font-size: 1.75rem; font-weight: 600; color: var(--dark-color);">分类管理</h1>
        <button class="add-category-btn" style="background: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 500; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i>
            添加分类
        </button>
    </div>

    <!-- 消息提示 -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success" style="background: #d1fae5; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #10b981;">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error" style="background: #fee2e2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #ef4444;">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>

    <!-- 分类列表 -->
    <div class="categories-table-container" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <table class="categories-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8fafc;">
                    <th style="padding: 16px; text-align: left; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">ID</th>
                    <th style="padding: 16px; text-align: left; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">分类名称</th>
                    <th style="padding: 16px; text-align: left; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">描述</th>
                    <th style="padding: 16px; text-align: center; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">排序</th>
                    <th style="padding: 16px; text-align: center; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">状态</th>
                    <th style="padding: 16px; text-align: center; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">创建时间</th>
                    <th style="padding: 16px; text-align: center; font-weight: 600; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="7" style="padding: 40px; text-align: center; color: var(--text-secondary);">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; color: #d1d5db;"></i>
                            <p>暂无分类数据</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 16px; color: var(--text-secondary);"><?php echo $category['id']; ?></td>
                            <td style="padding: 16px; font-weight: 500;"><?php echo htmlspecialchars($category['name']); ?></td>
                            <td style="padding: 16px; color: var(--text-secondary); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?php echo htmlspecialchars($category['description'] ?? '无描述'); ?>
                            </td>
                            <td style="padding: 16px; text-align: center; color: var(--text-secondary);"><?php echo $category['sort_order']; ?></td>
                            <td style="padding: 16px; text-align: center;">
                                <span class="status-badge <?php echo $category['status'] ? 'status-active' : 'status-inactive'; ?>" 
                                      style="padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; display: inline-block;">
                                    <?php echo $category['status'] ? '启用' : '禁用'; ?>
                                </span>
                            </td>
                            <td style="padding: 16px; text-align: center; color: var(--text-secondary);">
                                <?php echo date('Y-m-d H:i', strtotime($category['create_time'])); ?>
                            </td>
                            <td style="padding: 16px; text-align: center;">
                                <div style="display: flex; gap: 8px; justify-content: center;">
                                    <button class="edit-btn" data-category='<?php echo json_encode($category); ?>' 
                                            style="background: var(--warning-color); color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px;">
                                        <i class="fas fa-edit"></i> 编辑
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这个分类吗？此操作不可撤销！');">
                                        <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                        <button type="submit" name="delete_category" 
                                                style="background: var(--danger-color); color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px;">
                                            <i class="fas fa-trash"></i> 删除
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 添加/编辑分类模态框 -->
<div id="categoryModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: white; border-radius: 12px; padding: 24px; width: 90%; max-width: 500px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 id="modalTitle" style="font-size: 1.25rem; font-weight: 600;">添加分类</h3>
            <button class="close-modal" style="background: none; border: none; font-size: 18px; cursor: pointer; color: var(--text-secondary);">&times;</button>
        </div>
        
        <form id="categoryForm" method="POST">
            <input type="hidden" name="id" id="categoryId">
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">分类名称 *</label>
                <input type="text" name="name" id="categoryName" required 
                       style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
            </div>
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">分类描述</label>
                <textarea name="description" id="categoryDescription" rows="3"
                          style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px; resize: vertical;"></textarea>
            </div>
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500; color: var(--text-primary);">排序权重</label>
                <input type="number" name="sort_order" id="categorySortOrder" value="0" min="0"
                       style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 14px;">
                <small style="color: var(--text-secondary); font-size: 12px;">数值越小排序越靠前</small>
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="status" id="categoryStatus" checked 
                           style="width: 16px; height: 16px;">
                    <span style="font-weight: 500; color: var(--text-primary);">启用分类</span>
                </label>
            </div>
            
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="cancel-btn" 
                        style="background: var(--light-color); color: var(--text-primary); border: 1px solid var(--border-color); padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;">
                    取消
                </button>
                <button type="submit" name="add_category" id="submitBtn" 
                        style="background: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-weight: 500;">
                    添加分类
                </button>
            </div>
        </form>
    </div>
</div>

</div><!-- 关闭 content-wrapper -->
</div><!-- 关闭 main-content -->

<style>
.status-active {
    background: rgba(16, 185, 129, 0.1);
    color: #065f46;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.status-inactive {
    background: rgba(107, 114, 128, 0.1);
    color: #374151;
    border: 1px solid rgba(107, 114, 128, 0.2);
}

.add-category-btn:hover {
    background: var(--primary-dark) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.edit-btn:hover {
    background: #eab308 !important;
    transform: translateY(-1px);
}

form button[type="submit"][name="delete_category"]:hover {
    background: #dc2626 !important;
    transform: translateY(-1px);
}

.modal-content {
    animation: modalSlideIn 0.3s ease-out;
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

.categories-table tbody tr:hover {
    background: #f8fafc;
}

input:focus, textarea:focus {
    outline: none;
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('categoryModal');
    const addBtn = document.querySelector('.add-category-btn');
    const closeBtn = document.querySelector('.close-modal');
    const cancelBtn = document.querySelector('.cancel-btn');
    const form = document.getElementById('categoryForm');
    const modalTitle = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');
    
    // 打开添加分类模态框
    addBtn.addEventListener('click', function() {
        modalTitle.textContent = '添加分类';
        submitBtn.textContent = '添加分类';
        submitBtn.name = 'add_category';
        
        // 重置表单
        form.reset();
        document.getElementById('categoryId').value = '';
        document.getElementById('categoryStatus').checked = true;
        
        modal.style.display = 'flex';
    });
    
    // 编辑分类
    document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const category = JSON.parse(this.dataset.category);
            
            modalTitle.textContent = '编辑分类';
            submitBtn.textContent = '更新分类';
            submitBtn.name = 'edit_category';
            
            // 填充表单数据
            document.getElementById('categoryId').value = category.id;
            document.getElementById('categoryName').value = category.name;
            document.getElementById('categoryDescription').value = category.description || '';
            document.getElementById('categorySortOrder').value = category.sort_order;
            document.getElementById('categoryStatus').checked = category.status == 1;
            
            modal.style.display = 'flex';
        });
    });
    
    // 关闭模态框
    function closeModal() {
        modal.style.display = 'none';
    }
    
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    
    // 点击模态框背景关闭
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });
    
    // 表单提交验证
    form.addEventListener('submit', function(e) {
        const name = document.getElementById('categoryName').value.trim();
        if (!name) {
            e.preventDefault();
            alert('请填写分类名称');
            document.getElementById('categoryName').focus();
        }
    });
    
    // 添加键盘事件支持
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            closeModal();
        }
    });
});
</script>

</body>
</html>