
<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-17
最后编辑时间：2025-10-19
文件描述：卡密生成页面，用于卡密生成，后续可以用于用户兑换vip使用
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

// 获取网站配置
$settings = [];
try {
    $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取网站配置失败: " . $e->getMessage());
}

// 处理各种操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'generate_codes':
                // 批量生成卡密
                $type = intval($_POST['type']);
                $value = intval($_POST['value']);
                $quantity = intval($_POST['quantity']);
                $prefix = trim($_POST['prefix']);
                
                if ($quantity <= 0 || $quantity > 1000) {
                    $message = ['type' => 'error', 'text' => '生成数量必须在1-1000之间'];
                    break;
                }
                
                $generated_codes = [];
                $success_count = 0;
                
                try {
                    $db->beginTransaction();
                    
                    for ($i = 0; $i < $quantity; $i++) {
                        // 生成唯一卡密
                        do {
                            $code = $prefix . strtoupper(substr(md5(uniqid() . mt_rand()), 0, 12));
                            $stmt = $db->prepare("SELECT COUNT(*) FROM redeem_code WHERE code = ?");
                            $stmt->execute([$code]);
                        } while ($stmt->fetchColumn() > 0);
                        
                        // 插入数据库
                        $stmt = $db->prepare("INSERT INTO redeem_code (code, type, value, create_by, status) VALUES (?, ?, ?, ?, 0)");
                        if ($stmt->execute([$code, $type, $value, $admin_username])) {
                            $generated_codes[] = $code;
                            $success_count++;
                        }
                    }
                    
                    $db->commit();
                    $message = [
                        'type' => 'success', 
                        'text' => "成功生成 {$success_count} 个卡密",
                        'codes' => $generated_codes
                    ];
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    $message = ['type' => 'error', 'text' => '生成卡密失败: ' . $e->getMessage()];
                }
                break;
                
            case 'edit_code':
                // 编辑卡密
                $code_id = intval($_POST['code_id']);
                $code = trim($_POST['code']);
                $type = intval($_POST['type']);
                $value = intval($_POST['value']);
                
                try {
                    // 检查卡密是否已使用
                    $stmt = $db->prepare("SELECT status FROM redeem_code WHERE id = ?");
                    $stmt->execute([$code_id]);
                    $code_status = $stmt->fetchColumn();
                    
                    if ($code_status == 1) {
                        $message = ['type' => 'error', 'text' => '已使用的卡密不能编辑'];
                        break;
                    }
                    
                    // 检查卡密是否重复
                    $stmt = $db->prepare("SELECT COUNT(*) FROM redeem_code WHERE code = ? AND id != ?");
                    $stmt->execute([$code, $code_id]);
                    if ($stmt->fetchColumn() > 0) {
                        $message = ['type' => 'error', 'text' => '卡密代码已存在'];
                        break;
                    }
                    
                    $stmt = $db->prepare("UPDATE redeem_code SET code = ?, type = ?, value = ? WHERE id = ?");
                    if ($stmt->execute([$code, $type, $value, $code_id])) {
                        $message = ['type' => 'success', 'text' => '卡密更新成功'];
                    } else {
                        $message = ['type' => 'error', 'text' => '卡密更新失败'];
                    }
                } catch (Exception $e) {
                    $message = ['type' => 'error', 'text' => '编辑卡密失败: ' . $e->getMessage()];
                }
                break;
                
            case 'delete_codes':
                // 批量删除卡密
                if (!empty($_POST['code_ids'])) {
                    $code_ids = array_map('intval', $_POST['code_ids']);
                    $placeholders = str_repeat('?,', count($code_ids) - 1) . '?';
                    
                    try {
                        // 只能删除未使用的卡密
                        $stmt = $db->prepare("DELETE FROM redeem_code WHERE id IN ($placeholders) AND status = 0");
                        $deleted = $stmt->execute($code_ids);
                        
                        $message = ['type' => 'success', 'text' => "成功删除 {$stmt->rowCount()} 个卡密"];
                    } catch (Exception $e) {
                        $message = ['type' => 'error', 'text' => '删除卡密失败: ' . $e->getMessage()];
                    }
                } else {
                    $message = ['type' => 'error', 'text' => '请选择要删除的卡密'];
                }
                break;
        }
    }
}

// 获取卡密列表
$where_conditions = [];
$params = [];

// 筛选条件
if (!empty($_GET['type']) && $_GET['type'] !== 'all') {
    $where_conditions[] = "rc.type = ?";
    $params[] = intval($_GET['type']);
}

if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
    $where_conditions[] = "rc.status = ?";
    $params[] = intval($_GET['status']);
}

if (!empty($_GET['create_by'])) {
    $where_conditions[] = "rc.create_by LIKE ?";
    $params[] = '%' . trim($_GET['create_by']) . '%';
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_conditions);
}

// 分页
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 获取总记录数
$stmt = $db->prepare("SELECT COUNT(*) FROM redeem_code rc" . $where_sql);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// 获取卡密数据
$sql = "SELECT rc.*, u.name as used_user_name 
        FROM redeem_code rc 
        LEFT JOIN user u ON rc.used_by = u.id 
        {$where_sql} 
        ORDER BY rc.create_time DESC 
        LIMIT {$offset}, {$per_page}";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$redeem_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 包含导航栏
include 'navbar.php';
?>

<div class="content-wrapper">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <h1 style="font-size: 1.8rem; font-weight: 600; color: var(--dark-color);">卡密管理</h1>
        <button class="btn btn-primary" onclick="openGenerateModal()" style="background: var(--primary-color); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-plus"></i>
            生成卡密
        </button>
    </div>

    <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $message['type'] == 'success' ? 'success' : 'error'; ?>" 
             style="padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; 
                    background: <?php echo $message['type'] == 'success' ? 'var(--success-color)' : 'var(--danger-color)'; ?>;
                    color: white;">
            <?php echo htmlspecialchars($message['text']); ?>
        </div>
    <?php endif; ?>

    <!-- 筛选表单 -->
    <div class="filter-card" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;">
        <form method="GET" id="filterForm" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end;">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: var(--text-primary);">卡密类型</label>
                <select name="type" class="form-select" onchange="document.getElementById('filterForm').submit()" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px;">
                    <option value="all">全部类型</option>
                    <option value="0" <?php echo (isset($_GET['type']) && $_GET['type'] == '0') ? 'selected' : ''; ?>>积分卡</option>
                    <option value="1" <?php echo (isset($_GET['type']) && $_GET['type'] == '1') ? 'selected' : ''; ?>>会员卡</option>
                </select>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: var(--text-primary);">使用状态</label>
                <select name="status" class="form-select" onchange="document.getElementById('filterForm').submit()" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px;">
                    <option value="all">全部状态</option>
                    <option value="0" <?php echo (isset($_GET['status']) && $_GET['status'] == '0') ? 'selected' : ''; ?>>未使用</option>
                    <option value="1" <?php echo (isset($_GET['status']) && $_GET['status'] == '1') ? 'selected' : ''; ?>>已使用</option>
                </select>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 500; color: var(--text-primary);">生成管理员</label>
                <input type="text" name="create_by" value="<?php echo htmlspecialchars($_GET['create_by'] ?? ''); ?>" 
                       placeholder="输入管理员账号" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px;">
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="background: var(--primary-color); color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">搜索</button>
                <button type="button" onclick="window.location.href='redeem_codes.php'" class="btn btn-secondary" style="background: var(--text-secondary); color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">重置</button>
            </div>
        </form>
    </div>

    <!-- 批量操作 -->
    <div class="batch-actions" style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">
        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" style="margin-right: 5px;">
        <label for="selectAll" style="cursor: pointer;">全选</label>
        <button onclick="batchDelete()" class="btn btn-danger" style="background: var(--danger-color); color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 14px;">
            <i class="fas fa-trash"></i> 批量删除
        </button>
        <span style="color: var(--text-secondary); font-size: 14px;">
            共 <?php echo $total_records; ?> 条记录
        </span>
    </div>

    <!-- 卡密列表 -->
    <div class="card" style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div class="table-responsive">
            <table class="table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); width: 30px;"></th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color);">卡密</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color);">类型</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color);">面值/天数</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color);">状态</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); width: 120px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($redeem_codes)): ?>
                        <tr>
                            <td colspan="6" style="padding: 40px; text-align: center; color: var(--text-secondary);">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px; display: block; color: #d1d5db;"></i>
                                暂无卡密数据
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($redeem_codes as $code): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px;">
                                    <input type="checkbox" name="code_ids[]" value="<?php echo $code['id']; ?>" class="code-checkbox" 
                                           <?php echo $code['status'] == 1 ? 'disabled' : ''; ?>>
                                </td>
                                <td style="padding: 12px; font-family: monospace; font-weight: 500;"><?php echo htmlspecialchars($code['code']); ?></td>
                                <td style="padding: 12px;">
                                    <span class="badge <?php echo $code['type'] == 0 ? 'badge-info' : 'badge-warning'; ?>" 
                                          style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: <?php echo $code['type'] == 0 ? 'var(--primary-color)' : 'var(--warning-color)'; ?>; color: white;">
                                        <?php echo $code['type'] == 0 ? '积分卡' : '会员卡'; ?>
                                    </span>
                                </td>
                                <td style="padding: 12px;">
                                    <?php echo $code['type'] == 0 ? $code['value'] . ' 积分' : $code['value'] . ' 天'; ?>
                                </td>
                                <td style="padding: 12px;">
                                    <span class="badge <?php echo $code['status'] == 0 ? 'badge-success' : 'badge-secondary'; ?>" 
                                          style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: <?php echo $code['status'] == 0 ? 'var(--success-color)' : 'var(--text-secondary)'; ?>; color: white;">
                                        <?php echo $code['status'] == 0 ? '未使用' : '已使用'; ?>
                                    </span>
                                </td>
                                <td style="padding: 12px;">
                                    <div style="display: flex; gap: 8px;">
                                        <button onclick="viewCodeDetails(<?php echo $code['id']; ?>)" class="btn btn-sm btn-info" 
                                                style="background: var(--primary-color); color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 12px;">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="editCode(<?php echo $code['id']; ?>)" class="btn btn-sm btn-warning"
                                                <?php echo $code['status'] == 1 ? 'disabled' : ''; ?>
                                                style="background: <?php echo $code['status'] == 1 ? '#d1d5db' : 'var(--warning-color)'; ?>; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: <?php echo $code['status'] == 1 ? 'not-allowed' : 'pointer'; ?>; font-size: 12px;">
                                            <i class="fas fa-edit"></i>
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

    <!-- 分页 -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination" style="display: flex; justify-content: center; margin-top: 20px; gap: 5px;">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-link" style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px; text-decoration: none; color: var(--text-primary);">首页</a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link" style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px; text-decoration: none; color: var(--text-primary);">上一页</a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>" 
                   style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px; text-decoration: none; 
                          <?php echo $i == $page ? 'background: var(--primary-color); color: white;' : 'color: var(--text-primary);'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link" style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px; text-decoration: none; color: var(--text-primary);">下一页</a>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-link" style="padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px; text-decoration: none; color: var(--text-primary);">末页</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- 生成卡密模态框 -->
<div id="generateModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content" style="background-color: white; margin: 5% auto; padding: 0; border-radius: 8px; width: 500px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div class="modal-header" style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.25rem;">生成卡密</h3>
            <span class="close" onclick="closeGenerateModal()" style="cursor: pointer; font-size: 1.5rem;">&times;</span>
        </div>
        <form method="POST" id="generateForm">
            <input type="hidden" name="action" value="generate_codes">
            <div class="modal-body" style="padding: 20px;">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">卡密类型</label>
                    <select name="type" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px;">
                        <option value="0">积分卡</option>
                        <option value="1">会员卡</option>
                    </select>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">
                        <span id="valueLabel">面值/天数</span>
                    </label>
                    <input type="number" name="value" min="1" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">生成数量</label>
                    <input type="number" name="quantity" min="1" max="1000" value="10" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">卡密前缀（可选）</label>
                    <input type="text" name="prefix" maxlength="10" placeholder="如：VIP" style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px;">
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeGenerateModal()" style="padding: 8px 16px; border: 1px solid var(--border-color); border-radius: 4px; background: white; cursor: pointer;">取消</button>
                <button type="submit" style="padding: 8px 16px; border: none; border-radius: 4px; background: var(--primary-color); color: white; cursor: pointer;">生成</button>
            </div>
        </form>
    </div>
</div>

<!-- 卡密详情模态框 -->
<div id="detailModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content" style="background-color: white; margin: 5% auto; padding: 0; border-radius: 8px; width: 500px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div class="modal-header" style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.25rem;">卡密详情</h3>
            <span class="close" onclick="closeDetailModal()" style="cursor: pointer; font-size: 1.5rem;">&times;</span>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <div id="detailContent">
                <!-- 详情内容将通过JavaScript填充 -->
            </div>
        </div>
        <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
            <button type="button" onclick="closeDetailModal()" style="padding: 8px 16px; border: 1px solid var(--border-color); border-radius: 4px; background: white; cursor: pointer;">关闭</button>
        </div>
    </div>
</div>

<!-- 编辑卡密模态框 -->
<div id="editModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content" style="background-color: white; margin: 5% auto; padding: 0; border-radius: 8px; width: 500px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div class="modal-header" style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.25rem;">编辑卡密</h3>
            <span class="close" onclick="closeEditModal()" style="cursor: pointer; font-size: 1.5rem;">&times;</span>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit_code">
            <input type="hidden" name="code_id" id="editCodeId">
            <div class="modal-body" style="padding: 20px;">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">卡密代码</label>
                    <input type="text" name="code" id="editCode" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px; font-family: monospace;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">卡密类型</label>
                    <select name="type" id="editType" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px;">
                        <option value="0">积分卡</option>
                        <option value="1">会员卡</option>
                    </select>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;" id="editValueLabel">面值/天数</label>
                    <input type="number" name="value" id="editValue" min="1" required style="width: 100%; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 4px;">
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" onclick="closeEditModal()" style="padding: 8px 16px; border: 1px solid var(--border-color); border-radius: 4px; background: white; cursor: pointer;">取消</button>
                <button type="submit" style="padding: 8px 16px; border: none; border-radius: 4px; background: var(--primary-color); color: white; cursor: pointer;">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 生成结果模态框 -->
<?php if (isset($message) && isset($message['codes'])): ?>
<div id="resultModal" class="modal" style="display: block; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content" style="background-color: white; margin: 5% auto; padding: 0; border-radius: 8px; width: 600px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <div class="modal-header" style="padding: 16px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.25rem;">生成结果</h3>
            <span class="close" onclick="closeResultModal()" style="cursor: pointer; font-size: 1.5rem;">&times;</span>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <p style="margin-bottom: 15px;">成功生成 <?php echo count($message['codes']); ?> 个卡密：</p>
            <textarea id="generatedCodes" readonly style="width: 100%; height: 200px; padding: 12px; border: 1px solid var(--border-color); border-radius: 4px; font-family: monospace; resize: none;"><?php echo implode("\n", $message['codes']); ?></textarea>
        </div>
        <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <button type="button" onclick="copyCodes()" style="padding: 8px 16px; border: none; border-radius: 4px; background: var(--success-color); color: white; cursor: pointer;">
                <i class="fas fa-copy"></i> 一键复制
            </button>
            <button type="button" onclick="closeResultModal()" style="padding: 8px 16px; border: 1px solid var(--border-color); border-radius: 4px; background: white; cursor: pointer;">关闭</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// 模态框控制函数
function openGenerateModal() {
    document.getElementById('generateModal').style.display = 'block';
}

function closeGenerateModal() {
    document.getElementById('generateModal').style.display = 'none';
}

function closeDetailModal() {
    document.getElementById('detailModal').style.display = 'none';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function closeResultModal() {
    document.getElementById('resultModal').style.display = 'none';
}

// 类型切换时更新标签
document.querySelector('select[name="type"]').addEventListener('change', function() {
    document.getElementById('valueLabel').textContent = this.value == '0' ? '积分数量' : '会员天数';
});

document.getElementById('editType').addEventListener('change', function() {
    document.getElementById('editValueLabel').textContent = this.value == '0' ? '积分数量' : '会员天数';
});

// 查看卡密详情
function viewCodeDetails(codeId) {
    // 直接使用当前页面数据，避免额外请求
    const codes = <?php echo json_encode($redeem_codes); ?>;
    const code = codes.find(c => c.id == codeId);
    
    if (code) {
        const detailContent = document.getElementById('detailContent');
        detailContent.innerHTML = `
            <div style="display: grid; gap: 12px;">
                <div>
                    <strong>卡密代码:</strong>
                    <div style="font-family: monospace; background: #f8f9fa; padding: 8px; border-radius: 4px; margin-top: 4px;">${code.code}</div>
                </div>
                <div>
                    <strong>卡密类型:</strong>
                    <span class="badge ${code.type == 0 ? 'badge-info' : 'badge-warning'}" style="padding: 4px 8px; border-radius: 4px; background: ${code.type == 0 ? 'var(--primary-color)' : 'var(--warning-color)'}; color: white; margin-left: 8px;">
                        ${code.type == 0 ? '积分卡' : '会员卡'}
                    </span>
                </div>
                <div>
                    <strong>面值/天数:</strong>
                    <span>${code.type == 0 ? code.value + ' 积分' : code.value + ' 天'}</span>
                </div>
                <div>
                    <strong>生成管理员:</strong>
                    <span>${code.create_by}</span>
                </div>
                <div>
                    <strong>创建时间:</strong>
                    <span>${new Date(code.create_time).toLocaleString()}</span>
                </div>
                <div>
                    <strong>使用状态:</strong>
                    <span class="badge ${code.status == 0 ? 'badge-success' : 'badge-secondary'}" style="padding: 4px 8px; border-radius: 4px; background: ${code.status == 0 ? 'var(--success-color)' : 'var(--text-secondary)'}; color: white; margin-left: 8px;">
                        ${code.status == 0 ? '未使用' : '已使用'}
                    </span>
                </div>
                ${code.status == 1 ? `
                    <div>
                        <strong>使用用户:</strong>
                        <span>${code.used_user_name || '未知用户'}</span>
                    </div>
                    <div>
                        <strong>使用时间:</strong>
                        <span>${code.used_time ? new Date(code.used_time).toLocaleString() : '未知'}</span>
                    </div>
                ` : ''}
            </div>
        `;
        document.getElementById('detailModal').style.display = 'block';
    } else {
        alert('获取详情失败');
    }
}

// 编辑卡密
function editCode(codeId) {
    const codes = <?php echo json_encode($redeem_codes); ?>;
    const code = codes.find(c => c.id == codeId);
    
    if (code) {
        document.getElementById('editCodeId').value = code.id;
        document.getElementById('editCode').value = code.code;
        document.getElementById('editType').value = code.type;
        document.getElementById('editValue').value = code.value;
        document.getElementById('editValueLabel').textContent = code.type == 0 ? '积分数量' : '会员天数';
        document.getElementById('editModal').style.display = 'block';
    } else {
        alert('获取卡密信息失败');
    }
}

// 全选/取消全选
function toggleSelectAll(checkbox) {
    const codeCheckboxes = document.querySelectorAll('.code-checkbox:not(:disabled)');
    codeCheckboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
}

// 批量删除
function batchDelete() {
    const selectedCodes = Array.from(document.querySelectorAll('.code-checkbox:checked'))
        .map(cb => cb.value);
    
    if (selectedCodes.length === 0) {
        alert('请选择要删除的卡密');
        return;
    }
    
    if (!confirm(`确定要删除选中的 ${selectedCodes.length} 个卡密吗？`)) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="delete_codes">
        ${selectedCodes.map(id => `<input type="hidden" name="code_ids[]" value="${id}">`).join('')}
    `;
    document.body.appendChild(form);
    form.submit();
}

// 复制生成的卡密
function copyCodes() {
    const textarea = document.getElementById('generatedCodes');
    textarea.select();
    document.execCommand('copy');
    alert('卡密已复制到剪贴板');
}

// 点击模态框外部关闭
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}
</script>

<style>

.btn {
    transition: all 0.3s ease;
}

.btn:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.badge {
    font-size: 12px;
    font-weight: 500;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

.modal-content {
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>