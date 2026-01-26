<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-10
最后编辑时间：2025-11-15
文件描述：用户添加角色页面，然后后台审核

*/
session_start();
require_once(__DIR__ . './../config/config.php');

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['name'] ?? '';

// 获取用户创建的角色列表
$characters = [];
try {
    $stmt = $db->prepare("
        SELECT 
            ac.*,
            cc.name as category_name
        FROM ai_character ac 
        LEFT JOIN character_category cc ON ac.category_id = cc.id 
        WHERE ac.user_id = ? 
        ORDER BY ac.create_time DESC
    ");
    $stmt->execute([$user_id]);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取角色列表失败: " . $e->getMessage());
}

// 获取角色分类
$categories = [];
try {
    $stmt = $db->query("SELECT id, name FROM character_category WHERE status = 1 ORDER BY sort_order");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取分类失败: " . $e->getMessage());
}

// 获取网站配置
$settings = [];
try {
    $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取配置失败: " . $e->getMessage());
}

ob_start();
?>

<section class="section-card hero-card">
    <div class="section-card__header">
        <div>
            <h1 class="section-card__title"><i class="fas fa-user-plus"></i> 我的角色</h1>
            <p class="hero-card__description">管理您创建的所有AI角色</p>
        </div>
        <button class="button button--primary" id="addCharacterBtn">
            <i class="fas fa-plus"></i>
            添加新角色
        </button>
    </div>
</section>

<?php if (empty($characters)): ?>
    <section class="section-card empty-state">
        <i class="fas fa-robot"></i>
        <h3>您还没有创建任何角色</h3>
        <p>创建您的第一个AI角色，开始与更多人分享吧！</p>
        <button class="button button--primary" id="addCharacterBtnEmpty">
            <i class="fas fa-plus"></i>
            创建第一个角色
        </button>
    </section>
<?php else: ?>
    <div class="card-grid">
        <?php foreach ($characters as $character): ?>
            <div class="character-card" 
                 data-character-id="<?php echo $character['id']; ?>" 
                 data-status="<?php echo $character['status']; ?>"
                 style="cursor: pointer;">
                <div class="character-card__cover">
                    <img 
                        src="<?php echo !empty($character['avatar']) ? htmlspecialchars($character['avatar']) : '/static/ai-images/ai.png'; ?>" 
                        alt="<?php echo htmlspecialchars($character['name']); ?>" 
                        onerror="this.src='/static/ai-images/ai.png'"
                    >
                </div>
                <div class="character-card__body">
                    <h3 class="character-card__name"><?php echo htmlspecialchars($character['name']); ?></h3>
                    <?php if (!empty($character['category_name'])): ?>
                        <span class="filter-chip"><?php echo htmlspecialchars($character['category_name']); ?></span>
                    <?php endif; ?>
                    
                    <!-- 状态标签 -->
                    <?php if ($character['status'] == 0): ?>
                        <div class="status-badge status-pending">
                            <i class="fas fa-clock"></i> 等待审核
                        </div>
                        <div class="status-hint pending">
                            仅您自己可以使用，审核通过后将对其他用户开放
                        </div>
                    <?php elseif ($character['status'] == 1): ?>
                        <div class="status-badge status-approved">
                            <i class="fas fa-check"></i> 审核通过
                        </div>
                    <?php elseif ($character['status'] == 2): ?>
                        <div class="status-badge status-rejected">
                            <i class="fas fa-times"></i> 审核失败
                        </div>
                        <div class="status-hint rejected">
                            请修改后重新提交审核
                        </div>
                    <?php endif; ?>
                    
                    <p class="character-card__intro"><?php echo htmlspecialchars($character['introduction'] ?? '暂无介绍'); ?></p>
                    
                    <div class="character-meta">
                        <small>
                            <?php if ($character['is_public'] == 1): ?>
                                <i class="fas fa-globe"></i> 公开
                            <?php else: ?>
                                <i class="fas fa-lock"></i> 私密
                            <?php endif; ?>
                            • 创建于 <?php echo date('Y-m-d', strtotime($character['create_time'])); ?>
                            <?php if ($character['update_time']): ?>
                                • 更新于 <?php echo date('Y-m-d', strtotime($character['update_time'])); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                    
                    <div class="character-actions" onclick="event.stopPropagation();">
                        <button class="button button--primary button--small" onclick="startChat(<?php echo $character['id']; ?>)">
                            <i class="fas fa-comments"></i> 使用
                        </button>
                        <button class="button button--subtle button--small" onclick="editCharacter(<?php echo $character['id']; ?>)">
                            <i class="fas fa-edit"></i> 编辑
                        </button>
                        <button class="button button--danger button--small" onclick="deleteCharacter(<?php echo $character['id']; ?>, '<?php echo htmlspecialchars($character['name']); ?>')">
                            <i class="fas fa-trash"></i> 删除
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- 添加/编辑角色模态框 -->
<div class="modal" id="characterModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="modalTitle">添加新角色</h3>
            <button class="modal-close" id="modalClose">&times;</button>
        </div>
        <form id="characterForm" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" id="characterId" name="character_id">
                
                <!-- 状态提醒 -->
                <div class="auth-field" id="statusReminder" style="display: none;">
                    <div class="status-badge status-pending" style="display: inline-flex;">
                        <i class="fas fa-info-circle"></i> 编辑后需要重新审核
                    </div>
                    <div class="form-hint" style="margin-top: 0.4rem;">
                        修改角色信息后，需要管理员重新审核通过才能对其他用户开放
                    </div>
                </div>
                
                <div class="auth-field">
                    <label class="auth-label" for="characterName">角色名称 *</label>
                    <input type="text" class="auth-input" id="characterName" name="name" required maxlength="10" placeholder="最多10个字">
                    <div class="form-error" id="nameError" style="display: none; margin-top: 0.4rem;"></div>
                </div>
                
                <div class="auth-field">
                    <label class="auth-label" for="categoryId">角色分类 *</label>
                    <select class="auth-input" id="categoryId" name="category_id" required>
                        <option value="">请选择分类</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="auth-field">
                    <label class="auth-label">角色头像</label>
                    <div class="avatar-upload">
                        <div class="avatar-preview" id="avatarPreview">
                            <img src="/static/ai-images/ai.png" alt="头像预览" id="avatarPreviewImg">
                        </div>
                        <div class="avatar-upload-btn">
                            <input type="file" class="file-input" id="avatarFile" name="avatar" accept=".jpg,.jpeg,.png">
                            <label for="avatarFile" class="file-label">
                                <i class="fas fa-upload"></i> 选择图片
                            </label>
                            <div class="form-hint">支持 JPG, PNG 格式，最大 10MB，建议尺寸 1:1</div>
                            <div class="form-error" id="avatarError"></div>
                        </div>
                    </div>
                </div>
                
                <div class="auth-field">
                    <label class="auth-label" for="introduction">角色介绍</label>
                    <textarea class="auth-input" id="introduction" name="introduction" rows="3" maxlength="150" placeholder="简要描述角色的背景、性格特点等（最多150个字符）"></textarea>
                </div>
                
                <div class="auth-field">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <label class="auth-label" for="prompt">角色提示词 *</label>
                        <button type="button" class="button button--subtle button--small" id="optimizePromptBtn" style="margin: 0;">
                            <i class="fas fa-magic"></i> AI优化提示词
                        </button>
                    </div>
                    <textarea class="auth-input" id="prompt" name="prompt" rows="6" maxlength="600" required placeholder="详细描述角色的身份、性格、说话风格、知识范围等。这将决定AI如何扮演这个角色。（最多600个字符）"></textarea>
                    <div class="form-hint" id="optimizePromptHint" style="display: none; margin-top: 0.4rem;"></div>
                    <div class="form-hint" style="margin-top: 0.4rem; padding: 0.5rem; background-color: #f0f9ff; border-left: 3px solid #3b82f6; border-radius: 4px;">
                        <div style="display: flex; align-items: flex-start;">
                            <i class="fas fa-info-circle" style="color: #3b82f6; margin-right: 0.5rem; margin-top: 0.2rem;"></i>
                            <div>
                                <strong style="color: #1e40af;">{name} 占位符说明：</strong>
                                <p style="margin: 0.3rem 0 0 0; color: #1e3a8a; font-size: 0.9em;">
                                    您可以在提示词中使用 <code style="background-color: #dbeafe; padding: 0.2rem 0.4rem; border-radius: 3px; font-family: monospace;">{name}</code> 来引用用户的名字。<br>
                                    如果用户名叫"萝卜"，<br>
                                    提示词中写"你好，{name}"，AI会显示为"你好，萝卜"。<br>
                                    这样可以让角色更个性化地称呼用户。
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="auth-field">
                    <div class="switch-group">
                        <label class="auth-label">公开角色</label>
                        <label class="switch">
                            <input type="checkbox" id="isPublic" name="is_public" value="1" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="form-hint">开启后其他用户可以看到和使用这个角色，关闭后仅您自己可见</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="button button--subtle" id="cancelBtn">取消</button>
                <button type="submit" class="button button--primary" id="submitBtn">保存角色</button>
            </div>
        </form>
    </div>
</div>

<!-- 删除确认模态框 -->
<div class="modal" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">确认删除</h3>
            <button class="modal-close" id="deleteModalClose">&times;</button>
        </div>
        <div class="modal-body">
            <p>您确定要删除角色 "<span id="deleteCharacterName"></span>" 吗？此操作不可恢复！</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="button button--subtle" id="cancelDeleteBtn">取消</button>
            <button type="button" class="button button--danger" id="confirmDeleteBtn">确认删除</button>
        </div>
    </div>
</div>

<style>
/* 增加创建/编辑角色弹窗的宽度 */
#characterModal .modal-content {
    max-width: 700px;
    width: 90%;
}

@media (min-width: 768px) {
    #characterModal .modal-content {
        max-width: 750px;
    }
}

@media (min-width: 1024px) {
    #characterModal .modal-content {
        max-width: 800px;
    }
}

/* 手机端字体缩放优化 */
@media (max-width: 768px) {
    /* 基础字体缩放 */
    html {
        font-size: 14px;
    }
    
    /* 标题字体 */
    .section-card__title {
        font-size: 1.4rem !important;
    }
    
    .hero-card__description {
        font-size: 0.9rem !important;
    }
    
    /* 角色卡片字体 */
    .character-card__name {
        font-size: 1.1rem !important;
    }
    
    .character-card__intro {
        font-size: 0.85rem !important;
    }
    
    .character-meta {
        font-size: 0.7rem !important;
    }
    
    /* 按钮字体 */
    .button {
        font-size: 0.9rem !important;
    }
    
    .button--small {
        font-size: 0.85rem !important;
    }
    
    /* 提示词优化按钮 - 手机端宽度窄一些，不拉伸 */
    #optimizePromptBtn {
        width: auto !important;
        min-width: auto !important;
        max-width: none !important;
        padding: 0.5rem 0.75rem !important;
        white-space: nowrap !important;
        flex-shrink: 0 !important;
    }
    
    /* 弹窗字体 */
    #characterModal .modal-title {
        font-size: 1.2rem !important;
    }
    
    #characterModal .auth-label {
        font-size: 0.9rem !important;
    }
    
    #characterModal .auth-input {
        font-size: 0.9rem !important;
    }
    
    #characterModal .form-hint {
        font-size: 0.75rem !important;
    }
    
    /* 状态标签字体 */
    .status-badge {
        font-size: 0.75rem !important;
    }
    
    .status-hint {
        font-size: 0.7rem !important;
    }
    
    /* 空状态字体 */
    .empty-state h3 {
        font-size: 1.2rem !important;
    }
    
    .empty-state p {
        font-size: 0.85rem !important;
    }
}

/* 超小屏幕进一步优化 */
@media (max-width: 480px) {
    html {
        font-size: 13px;
    }
    
    .section-card__title {
        font-size: 1.3rem !important;
    }
    
    .character-card__name {
        font-size: 1rem !important;
    }
    
    .button {
        font-size: 0.85rem !important;
    }
    
    /* 提示词优化按钮 - 超小屏幕进一步优化 */
    #optimizePromptBtn {
        padding: 0.45rem 0.65rem !important;
        font-size: 0.8rem !important;
    }
    
    #characterModal .modal-title {
        font-size: 1.1rem !important;
    }
}
</style>

<script>
    // 全局变量
    let currentEditingId = null;
    let deleteCharacterId = null;
    let isSubmitting = false; // 防止重复提交标志
    
    // DOM 元素
    const addCharacterBtn = document.getElementById('addCharacterBtn');
    const addCharacterBtnEmpty = document.getElementById('addCharacterBtnEmpty');
    const characterModal = document.getElementById('characterModal');
    const deleteModal = document.getElementById('deleteModal');
    const characterForm = document.getElementById('characterForm');
    const avatarFile = document.getElementById('avatarFile');
    const avatarPreviewImg = document.getElementById('avatarPreviewImg');
    const avatarError = document.getElementById('avatarError');
    const nameError = document.getElementById('nameError');
    const characterNameInput = document.getElementById('characterName');
    const modalTitle = document.getElementById('modalTitle');
    const optimizePromptBtn = document.getElementById('optimizePromptBtn');
    const optimizePromptHint = document.getElementById('optimizePromptHint');
    
    // 事件监听器
    document.addEventListener('DOMContentLoaded', function() {
        // 添加角色按钮
        if (addCharacterBtn) {
            addCharacterBtn.addEventListener('click', showAddModal);
        }
        if (addCharacterBtnEmpty) {
            addCharacterBtnEmpty.addEventListener('click', showAddModal);
        }
        
        // 关闭模态框
        document.getElementById('modalClose').addEventListener('click', hideModal);
        document.getElementById('cancelBtn').addEventListener('click', hideModal);
        document.getElementById('deleteModalClose').addEventListener('click', hideDeleteModal);
        document.getElementById('cancelDeleteBtn').addEventListener('click', hideDeleteModal);
        
        // 角色名称输入时清除错误提示
        if (characterNameInput) {
            characterNameInput.addEventListener('input', function() {
                if (nameError) {
                    nameError.style.display = 'none';
                    nameError.textContent = '';
                }
                this.style.borderColor = '';
            });
        }
        
        // 角色名称输入时清除错误提示
        if (characterNameInput) {
            characterNameInput.addEventListener('input', function() {
                if (nameError) {
                    nameError.style.display = 'none';
                    nameError.textContent = '';
                }
                this.style.borderColor = '';
            });
        }
        
        // 头像文件验证
        avatarFile.addEventListener('change', function(e) {
            const file = e.target.files[0];
            avatarError.classList.remove('show');
            
            if (file) {
                // 检查文件类型
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    avatarError.textContent = '只支持 JPG 和 PNG 格式的图片';
                    avatarError.classList.add('show');
                    avatarFile.value = '';
                    return;
                }
                
                // 检查文件大小 (10MB = 10 * 1024 * 1024 bytes)
                const maxSize = 10 * 1024 * 1024;
                if (file.size > maxSize) {
                    avatarError.textContent = '图片大小不能超过 10MB';
                    avatarError.classList.add('show');
                    avatarFile.value = '';
                    return;
                }
                
                // 预览图片
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarPreviewImg.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
        
        // 表单提交
        characterForm.addEventListener('submit', handleFormSubmit);
        
        // 确认删除
        document.getElementById('confirmDeleteBtn').addEventListener('click', confirmDelete);
        
        // AI优化提示词按钮
        if (optimizePromptBtn) {
            optimizePromptBtn.addEventListener('click', optimizePrompt);
        }
        
        // 点击模态框外部关闭（仅删除确认弹窗，创建/编辑角色弹窗不允许点击空白处关闭）
        // characterModal.addEventListener('click', function(e) {
        //     if (e.target === characterModal) {
        //         hideModal();
        //     }
        // });
        
        deleteModal.addEventListener('click', function(e) {
            if (e.target === deleteModal) {
                hideDeleteModal();
            }
        });
        
        // 角色卡点击事件
        document.querySelectorAll('.character-card').forEach(card => {
            card.addEventListener('click', function(e) {
                // 如果点击的是按钮区域，不处理
                if (e.target.closest('.character-actions')) {
                    return;
                }
                
                const characterId = this.getAttribute('data-character-id');
                const status = parseInt(this.getAttribute('data-status'));
                
                // 如果审核通过(status == 1)，跳转到详情页；否则跳转到聊天页
                if (status === 1) {
                    window.location.href = `Introduction?character_id=${characterId}`;
                } else {
                    window.location.href = `chat?character_id=${characterId}`;
                }
            });
        });
    });
    
    // 显示添加模态框
    function showAddModal() {
        currentEditingId = null;
        modalTitle.textContent = '添加新角色';
        
        // 重置提交状态
        isSubmitting = false;
        
        // 隐藏状态提醒
        document.getElementById('statusReminder').style.display = 'none';
        
        characterForm.reset();
        avatarPreviewImg.src = '/static/ai-images/ai.png';
        document.getElementById('isPublic').checked = true;
        avatarError.classList.remove('show');
        if (nameError) {
            nameError.style.display = 'none';
            nameError.textContent = '';
        }
        
        // 恢复表单状态
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = false;
        submitBtn.textContent = '保存角色';
        characterForm.style.pointerEvents = 'auto';
        
        characterModal.classList.add('active');
    }
    
    // 编辑角色
    function editCharacter(id) {
        currentEditingId = id;
        modalTitle.textContent = '编辑角色';
        
        // 重置提交状态
        isSubmitting = false;
        
        // 显示状态提醒
        document.getElementById('statusReminder').style.display = 'block';
        
        // 恢复表单状态
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = false;
        submitBtn.textContent = '保存角色';
        characterForm.style.pointerEvents = 'auto';
        
        // 清除错误提示
        if (nameError) {
            nameError.style.display = 'none';
            nameError.textContent = '';
        }
        if (characterNameInput) {
            characterNameInput.style.borderColor = '';
        }
        
        // 获取角色数据
        fetch(`./api/get_character.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const character = data.character;
                    document.getElementById('characterId').value = character.id;
                    document.getElementById('characterName').value = character.name;
                    document.getElementById('categoryId').value = character.category_id;
                    document.getElementById('introduction').value = character.introduction || '';
                    document.getElementById('prompt').value = character.prompt;
                    document.getElementById('isPublic').checked = character.is_public == 1;
                    
                    if (character.avatar) {
                        avatarPreviewImg.src = character.avatar;
                    } else {
                        avatarPreviewImg.src = '/static/ai-images/ai.png';
                    }
                    
                    characterModal.classList.add('active');
                } else {
                    showMessage('获取角色信息失败', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('网络错误', 'error');
            });
    }
    
    // 隐藏模态框
    function hideModal() {
        characterModal.classList.remove('active');
    }
    
    // 显示删除确认模态框
    function deleteCharacter(id, name) {
        deleteCharacterId = id;
        document.getElementById('deleteCharacterName').textContent = name;
        deleteModal.classList.add('active');
    }
    
    // 隐藏删除模态框
    function hideDeleteModal() {
        deleteModal.classList.remove('active');
        deleteCharacterId = null;
    }
    
    // 确认删除
    function confirmDelete() {
        if (!deleteCharacterId) return;
        
        const submitBtn = document.getElementById('confirmDeleteBtn');
        submitBtn.disabled = true;
        submitBtn.textContent = '删除中...';
        
        fetch('./api/delete_character.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `character_id=${deleteCharacterId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('角色删除成功', 'success');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                showMessage(data.message || '删除失败', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('网络错误', 'error');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = '确认删除';
            hideDeleteModal();
        });
    }
    
    // 处理表单提交
    function handleFormSubmit(e) {
        e.preventDefault();
        
        // 防止重复提交
        if (isSubmitting) {
            return false;
        }
        
        // 验证头像文件
        const fileInput = document.getElementById('avatarFile');
        if (fileInput.files.length > 0) {
            const file = fileInput.files[0];
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            const maxSize = 10 * 1024 * 1024;
            
            if (!allowedTypes.includes(file.type)) {
                avatarError.textContent = '只支持 JPG 和 PNG 格式的图片';
                avatarError.classList.add('show');
                return false;
            }
            
            if (file.size > maxSize) {
                avatarError.textContent = '图片大小不能超过 10MB';
                avatarError.classList.add('show');
                return false;
            }
        }
        
        // 设置提交标志，防止重复提交
        isSubmitting = true;
        const formData = new FormData(characterForm);
        const submitBtn = document.getElementById('submitBtn');
        const isEdit = currentEditingId !== null;
        
        // 立即禁用按钮和表单
        submitBtn.disabled = true;
        submitBtn.textContent = '保存中...';
        characterForm.style.pointerEvents = 'none'; // 禁用整个表单的交互
        
        fetch('./api/save_character.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                // 成功后禁用表单，防止在页面跳转前再次提交
                characterForm.style.pointerEvents = 'none';
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                const errorMessage = data.message || '保存失败';
                showMessage(errorMessage, 'error');
                
                // 如果错误信息包含"角色名称已存在"，在角色名称输入框下方显示错误提示
                if (errorMessage.includes('角色名称已存在') || errorMessage.includes('名称已存在')) {
                    if (nameError && characterNameInput) {
                        nameError.textContent = errorMessage;
                        nameError.style.display = 'block';
                        nameError.style.color = '#ef4444';
                        // 聚焦到角色名称输入框
                        characterNameInput.focus();
                        characterNameInput.style.borderColor = '#ef4444';
                        // 3秒后恢复边框颜色
                        setTimeout(() => {
                            characterNameInput.style.borderColor = '';
                        }, 3000);
                    }
                } else {
                    // 清除名称错误提示
                    if (nameError) {
                        nameError.style.display = 'none';
                        nameError.textContent = '';
                    }
                }
                
                // 失败后恢复表单状态
                isSubmitting = false;
                submitBtn.disabled = false;
                submitBtn.textContent = '保存角色';
                characterForm.style.pointerEvents = 'auto';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('网络错误', 'error');
            // 错误后恢复表单状态
            isSubmitting = false;
            submitBtn.disabled = false;
            submitBtn.textContent = '保存角色';
            characterForm.style.pointerEvents = 'auto';
        });
        
        return false;
    }
    
    // 开始聊天
    function startChat(characterId) {
        window.location.href = `chat?character_id=${characterId}`;
    }
    
    // AI优化提示词
    function optimizePrompt() {
        const promptTextarea = document.getElementById('prompt');
        const currentPrompt = promptTextarea.value.trim();
        
        if (!currentPrompt) {
            showMessage('请先输入提示词内容', 'error');
            return;
        }
        
        // 禁用按钮，显示加载状态
        optimizePromptBtn.disabled = true;
        const originalText = optimizePromptBtn.innerHTML;
        optimizePromptBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 优化中...';
        optimizePromptHint.style.display = 'none';
        
        // 发送请求
        const formData = new FormData();
        formData.append('prompt', currentPrompt);
        
        fetch('./api/optimize_prompt.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 更新提示词内容
                promptTextarea.value = data.optimized_prompt;
                optimizePromptHint.textContent = '提示词已优化完成，您可以继续编辑';
                optimizePromptHint.style.display = 'block';
                optimizePromptHint.style.color = '#10b981';
                showMessage('提示词优化完成', 'success');
            } else {
                showMessage(data.message || '优化失败', 'error');
                optimizePromptHint.textContent = data.message || '优化失败，请稍后重试';
                optimizePromptHint.style.display = 'block';
                optimizePromptHint.style.color = '#ef4444';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('网络错误', 'error');
            optimizePromptHint.textContent = '网络错误，请检查网络连接后重试';
            optimizePromptHint.style.display = 'block';
            optimizePromptHint.style.color = '#ef4444';
        })
        .finally(() => {
            // 恢复按钮状态
            optimizePromptBtn.disabled = false;
            optimizePromptBtn.innerHTML = originalText;
        });
    }
    
    // 显示消息提示
    function showMessage(message, type) {
        const messageEl = document.createElement('div');
        messageEl.textContent = message;
        messageEl.className = 'alert-banner';
        messageEl.classList.add(type === 'success' ? 'alert-banner--success' : 'alert-banner--error');
        messageEl.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
            max-width: 280px;
            transform: translateX(100%);
            transition: transform var(--transition-base);
        `;
        
        document.body.appendChild(messageEl);
        
        setTimeout(() => {
            messageEl.style.transform = 'translateX(0)';
        }, 100);
        
        setTimeout(() => {
            messageEl.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (document.body.contains(messageEl)) {
                    document.body.removeChild(messageEl);
                }
            }, 200);
        }, 2500);
    }
</script>

<?php
$content = ob_get_clean();
include 'navbar.php';
?>