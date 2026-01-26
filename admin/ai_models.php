<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-17
最后编辑时间：2025-10-19
文件描述：ai模型对接管理页面，可以添加对接ai模型信息，
使用openai和deepseek通用api规范，可以对接本地模型
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
    if (isset($_POST['add_model'])) {
        // 添加AI模型
        $model_name = trim($_POST['model_name']);
        $model = trim($_POST['model']);
        $ai = intval($_POST['ai']);
        $api_url = trim($_POST['api_url']);
        $api_key = trim($_POST['api_key']);
        $status = intval($_POST['status']);
        $sort_order = intval($_POST['sort_order']);
        $max_tokens = intval($_POST['max_tokens']);
        $temperature = floatval($_POST['temperature']);
        
        try {
            $stmt = $db->prepare("INSERT INTO ai_model (model_name, model, ai, api_url, api_key, status, sort_order, max_tokens, temperature) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$model_name, $model, $ai, $api_url, $api_key, $status, $sort_order, $max_tokens, $temperature]);
            
            $_SESSION['message'] = "AI模型添加成功！";
            $_SESSION['message_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['message'] = "添加失败: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
        
    } elseif (isset($_POST['edit_model'])) {
        // 编辑AI模型
        $id = intval($_POST['id']);
        $model_name = trim($_POST['model_name']);
        $model = trim($_POST['model']);
        $ai = intval($_POST['ai']);
        $api_url = trim($_POST['api_url']);
        $api_key = trim($_POST['api_key']);
        $status = intval($_POST['status']);
        $sort_order = intval($_POST['sort_order']);
        $max_tokens = intval($_POST['max_tokens']);
        $temperature = floatval($_POST['temperature']);
        
        try {
            $stmt = $db->prepare("UPDATE ai_model SET model_name = ?, model = ?, ai = ?, api_url = ?, api_key = ?, status = ?, sort_order = ?, max_tokens = ?, temperature = ? WHERE id = ?");
            $stmt->execute([$model_name, $model, $ai, $api_url, $api_key, $status, $sort_order, $max_tokens, $temperature, $id]);
            
            $_SESSION['message'] = "AI模型更新成功！";
            $_SESSION['message_type'] = "success";
        } catch (PDOException $e) {
            $_SESSION['message'] = "更新失败: " . $e->getMessage();
            $_SESSION['message_type'] = "error";
        }
    }
    
    header("Location: ai_models.php");
    exit;
}

// 处理删除操作
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    try {
        $stmt = $db->prepare("DELETE FROM ai_model WHERE id = ?");
        $stmt->execute([$id]);
        
        $_SESSION['message'] = "AI模型删除成功！";
        $_SESSION['message_type'] = "success";
    } catch (PDOException $e) {
        $_SESSION['message'] = "删除失败: " . $e->getMessage();
        $_SESSION['message_type'] = "error";
    }
    
    header("Location: ai_models.php");
    exit;
}

// 获取所有AI模型
$models = [];
try {
    $stmt = $db->query("SELECT * FROM ai_model ORDER BY sort_order ASC, id DESC");
    $models = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取AI模型失败: " . $e->getMessage());
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
    <!-- 页面标题和操作按钮 -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="font-size: 1.75rem; font-weight: 600;">AI模型管理</h1>
        <button class="add-model-btn" onclick="openAddModal()" style="background: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i>
            添加模型
        </button>
    </div>

    <!-- 消息提示 -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message <?php echo $_SESSION['message_type']; ?>" style="padding: 12px 16px; margin-bottom: 20px; border-radius: 8px; background: <?php echo $_SESSION['message_type'] == 'success' ? '#d1fae5' : '#fee2e2'; ?>; color: <?php echo $_SESSION['message_type'] == 'success' ? '#065f46' : '#991b1b'; ?>; border: 1px solid <?php echo $_SESSION['message_type'] == 'success' ? '#a7f3d0' : '#fecaca'; ?>;">
            <?php echo $_SESSION['message']; ?>
        </div>
        <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- AI模型表格 -->
    <div class="card" style="background: white; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow: hidden;">
        <div class="card-body" style="padding: 0;">
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e5e7eb;">
                            <th style="padding: 16px; text-align: left; font-weight: 600; font-size: 14px;">ID</th>
                            <th style="padding: 16px; text-align: left; font-weight: 600; font-size: 14px;">模型名称</th>
                            <th style="padding: 16px; text-align: left; font-weight: 600; font-size: 14px;">模型标识</th>
                            <th style="padding: 16px; text-align: left; font-weight: 600; font-size: 14px;">AI类型</th>
                            <th style="padding: 16px; text-align: left; font-weight: 600; font-size: 14px;">排序</th>
                            <th style="padding: 16px; text-align: left; font-weight: 600; font-size: 14px;">状态</th>
                            <th style="padding: 16px; text-align: left; font-weight: 600; font-size: 14px;">创建时间</th>
                            <th style="padding: 16px; text-align: center; font-weight: 600; font-size: 14px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($models)): ?>
                            <tr>
                                <td colspan="12" style="padding: 40px; text-align: center; color: #6b7280;">
                                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; color: #d1d5db;"></i>
                                    <p>暂无AI模型数据</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($models as $model): ?>
                                <tr style="border-bottom: 1px solid #f3f4f6;">
                                    <td style="padding: 16px; font-size: 14px;"><?php echo $model['id']; ?></td>
                                    <td style="padding: 16px; font-size: 14px; font-weight: 500;"><?php echo htmlspecialchars($model['model_name']); ?></td>
                                    <td style="padding: 16px; font-size: 14px;"><?php echo htmlspecialchars($model['model']); ?></td>
                                    <td style="padding: 16px; font-size: 14px;">
                                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: <?php echo $model['ai'] == 1 ? '#d1fae5' : '#fef3c7'; ?>; color: <?php echo $model['ai'] == 1 ? '#065f46' : '#92400e'; ?>;">
                                            <?php echo $model['ai'] == 1 ? '文本AI' : '其他类型'; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 16px; font-size: 14px;"><?php echo $model['sort_order']; ?></td>
                                    <td style="padding: 16px; font-size: 14px;">
                                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: <?php echo $model['status'] == 1 ? '#d1fae5' : '#fee2e2'; ?>; color: <?php echo $model['status'] == 1 ? '#065f46' : '#991b1b'; ?>;">
                                            <?php echo $model['status'] == 1 ? '启用' : '禁用'; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 16px; font-size: 14px;"><?php echo date('Y-m-d H:i', strtotime($model['create_time'])); ?></td>
                                    <td style="padding: 16px; text-align: center;">
                                        <div style="display: flex; gap: 8px; justify-content: center;">
                                            <button onclick="openEditModal(<?php echo $model['id']; ?>, '<?php echo htmlspecialchars($model['model_name']); ?>', '<?php echo htmlspecialchars($model['model']); ?>', <?php echo $model['ai']; ?>, '<?php echo htmlspecialchars($model['api_url']); ?>', '<?php echo htmlspecialchars($model['api_key']); ?>', <?php echo $model['status']; ?>, <?php echo $model['sort_order']; ?>, <?php echo $model['max_tokens']; ?>, <?php echo $model['temperature']; ?>)" style="background: #3b82f6; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; display: flex; align-items: center; gap: 4px;">
                                                <i class="fas fa-edit" style="font-size: 10px;"></i>
                                                编辑
                                            </button>
                                            <button onclick="confirmDelete(<?php echo $model['id']; ?>)" style="background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; display: flex; align-items: center; gap: 4px;">
                                                <i class="fas fa-trash" style="font-size: 10px;"></i>
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
        </div>
    </div>
</div>

<!-- 添加/编辑模态框 -->
<div id="modelModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: white; border-radius: 12px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <div class="modal-header" style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center;">
            <h3 id="modalTitle" style="font-size: 1.25rem; font-weight: 600;">添加AI模型</h3>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #6b7280;">&times;</button>
        </div>
        <form id="modelForm" method="POST" class="modal-body" style="padding: 24px;">
            <input type="hidden" id="modelId" name="id">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px;">模型名称 *</label>
                    <input type="text" id="modelName" name="model_name" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px;">模型标识 *</label>
                    <input type="text" id="model" name="model" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px;">AI类型</label>
                    <select id="ai" name="ai" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                        <option value="1">文本AI</option>
                        <option value="0">其他类型</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px;">状态</label>
                    <select id="status" name="status" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
                    </select>
                </div>
            </div>
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px;">API地址 *</label>
                <input type="url" id="apiUrl" name="api_url" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
            </div>
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px;">API密钥 *</label>
                <input type="text" id="apiKey" name="api_key" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px;">最大Token</label>
                    <input type="number" id="maxTokens" name="max_tokens" value="2048" min="1" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px;">温度 (0-1)</label>
                    <input type="number" id="temperature" name="temperature" value="0.70" min="0" max="1" step="0.01" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px;">排序权重</label>
                    <input type="number" id="sortOrder" name="sort_order" value="0" style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px;">
                </div>
            </div>
            
            <div class="modal-footer" style="padding-top: 20px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" onclick="closeModal()" style="padding: 10px 20px; border: 1px solid #d1d5db; background: white; border-radius: 6px; cursor: pointer; font-size: 14px;">取消</button>
                <button type="submit" name="add_model" id="submitBtn" style="padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">添加模型</button>
            </div>
        </form>
    </div>
</div>

</div><!-- 关闭 content-wrapper -->
</div><!-- 关闭 main-content -->

<script>
// 模态框功能
function openAddModal() {
    document.getElementById('modalTitle').textContent = '添加AI模型';
    document.getElementById('modelForm').reset();
    document.getElementById('modelId').value = '';
    document.getElementById('submitBtn').name = 'add_model';
    document.getElementById('submitBtn').textContent = '添加模型';
    document.getElementById('modelModal').style.display = 'flex';
}

function openEditModal(id, modelName, model, ai, apiUrl, apiKey, status, sortOrder, maxTokens, temperature) {
    document.getElementById('modalTitle').textContent = '编辑AI模型';
    document.getElementById('modelId').value = id;
    document.getElementById('modelName').value = modelName;
    document.getElementById('model').value = model;
    document.getElementById('ai').value = ai;
    document.getElementById('apiUrl').value = apiUrl;
    document.getElementById('apiKey').value = apiKey;
    document.getElementById('status').value = status;
    document.getElementById('sortOrder').value = sortOrder;
    document.getElementById('maxTokens').value = maxTokens;
    document.getElementById('temperature').value = temperature;
    document.getElementById('submitBtn').name = 'edit_model';
    document.getElementById('submitBtn').textContent = '更新模型';
    document.getElementById('modelModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('modelModal').style.display = 'none';
}

// 点击模态框外部关闭
document.getElementById('modelModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// 删除确认
function confirmDelete(id) {
    if (confirm('确定要删除这个AI模型吗？此操作不可恢复！')) {
        window.location.href = 'ai_models.php?delete=' + id;
    }
}

// 页面加载完成后设置活动菜单项
document.addEventListener('DOMContentLoaded', function() {
    // 设置页面标题
    document.querySelector('.page-title').textContent = 'AI模型管理';
});
</script>

</body>
</html>