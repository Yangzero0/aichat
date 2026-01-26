<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-13
最后编辑时间：2025-10-13
文件描述：用于会话管理，废弃方案，并没有实际引入到现有功能中

*/
session_start();
require_once(__DIR__ . './../config/config.php');

// 检查用户是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$user_id = $_SESSION['user_id'];

// 获取用户的所有会话（按最后活动时间排序）
$sessions = [];
try {
    $stmt = $db->prepare("
        SELECT 
            cs.*,
            ac.name as character_name,
            ac.avatar as character_avatar,
            am.model_name,
            COUNT(cr.id) as message_count
        FROM chat_session cs
        LEFT JOIN ai_character ac ON cs.character_id = ac.id
        LEFT JOIN ai_model am ON cs.model_id = am.id
        LEFT JOIN chat_record cr ON cs.id = cr.session_id AND cr.is_deleted = 0
        WHERE cs.user_id = ? AND cs.status = 1
        GROUP BY cs.id
        ORDER BY cs.last_active DESC
    ");
    $stmt->execute([$user_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取会话列表失败: " . $e->getMessage());
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

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>会话管理 - <?php echo $settings['title'] ?? 'AI聊天平台'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .session-manager {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }
        
        .header p {
            color: #666;
            font-size: 16px;
        }
        
        .sessions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .session-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #e8ecef;
            cursor: pointer;
        }
        
        .session-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
            border-color: #007AFF;
        }
        
        .session-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        
        .character-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: cover;
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        .session-info {
            flex: 1;
        }
        
        .session-title {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        
        .session-meta {
            display: flex;
            gap: 12px;
            font-size: 13px;
            color: #666;
            flex-wrap: wrap;
        }
        
        .session-content {
            margin-bottom: 16px;
        }
        
        .last-message {
            font-size: 14px;
            color: #555;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .session-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: 1px solid #e8ecef;
            background: white;
            border-radius: 6px;
            font-size: 12px;
            color: #555;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            background: #f8f9fa;
            border-color: #007AFF;
            color: #007AFF;
        }
        
        .action-btn.delete {
            color: #ff3b30;
        }
        
        .action-btn.delete:hover {
            background: #ffe6e6;
            border-color: #ff3b30;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 16px;
            color: #ddd;
        }
        
        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 8px;
            color: #666;
        }
        
        .empty-state p {
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .new-chat-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #007AFF;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .new-chat-btn:hover {
            background: #0056CC;
            transform: translateY(-1px);
        }
        
        /* 自定义弹窗样式 */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal {
            background: white;
            border-radius: 12px;
            padding: 24px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            animation: modalSlideIn 0.2s ease;
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
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a1a1a;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 18px;
            color: #666;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
        }
        
        .modal-close:hover {
            background: #f5f5f5;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        
        .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #007AFF;
        }
        
        .char-count {
            text-align: right;
            font-size: 12px;
            color: #666;
            margin-top: 4px;
        }
        
        .char-count.warning {
            color: #ff9500;
        }
        
        .char-count.error {
            color: #ff3b30;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #007AFF;
            color: white;
        }
        
        .btn-primary:hover:not(:disabled) {
            background: #0056CC;
        }
        
        .btn-secondary {
            background: #f5f5f5;
            color: #333;
        }
        
        .btn-secondary:hover {
            background: #e5e5e5;
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .sessions-grid {
                grid-template-columns: 1fr;
            }
            
            .session-manager {
                padding: 16px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .modal {
                width: 95%;
                margin: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .session-card {
                padding: 16px;
            }
            
            .session-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .character-avatar {
                width: 40px;
                height: 40px;
            }
            
            .session-meta {
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="session-manager">
        <div class="header">
            <h1><i class="fas fa-comments"></i> 我的会话</h1>
            <p>管理您与AI角色的所有对话记录</p>
        </div>
        
        <?php if (empty($sessions)): ?>
            <div class="empty-state">
                <i class="fas fa-comment-slash"></i>
                <h3>暂无会话记录</h3>
                <p>开始与AI角色进行第一次对话吧！</p>
                <a href="characters" class="new-chat-btn">
                    <i class="fas fa-plus"></i>
                    开始新对话
                </a>
            </div>
        <?php else: ?>
            <div class="sessions-grid">
                <?php foreach ($sessions as $session): ?>
                    <div class="session-card" onclick="window.location.href='chat?session_id=<?php echo $session['id']; ?>'">
                        <div class="session-header">
                            <?php if (!empty($session['character_avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($session['character_avatar']); ?>" 
                                     alt="<?php echo htmlspecialchars($session['character_name']); ?>" 
                                     class="character-avatar">
                            <?php else: ?>
                                <div class="character-avatar" style="display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-robot" style="color: white; font-size: 20px;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="session-info">
                                <div class="session-title">
                                    <?php 
                                    $title = $session['session_title'] ?: '新对话';
                                    if (mb_strlen($title) > 14) {
                                        echo htmlspecialchars(mb_substr($title, 0, 14) . '...');
                                    } else {
                                        echo htmlspecialchars($title);
                                    }
                                    ?>
                                </div>
                                <div class="session-meta">
                                    <span><?php echo htmlspecialchars($session['character_name']); ?></span>
                                    <span>•</span>
                                    <span><?php echo htmlspecialchars($session['model_name']); ?></span>
                                    <span>•</span>
                                    <span><?php echo $session['message_count']; ?> 条消息</span>
                                    <span>•</span>
                                    <span><?php 
                                        $lastActive = strtotime($session['last_active']);
                                        $now = time();
                                        $diff = $now - $lastActive;
                                        
                                        if ($diff < 60) {
                                            echo '刚刚';
                                        } elseif ($diff < 3600) {
                                            echo floor($diff / 60) . '分钟前';
                                        } elseif ($diff < 86400) {
                                            echo floor($diff / 3600) . '小时前';
                                        } elseif ($diff < 2592000) {
                                            echo floor($diff / 86400) . '天前';
                                        } else {
                                            echo date('Y-m-d', $lastActive);
                                        }
                                    ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="session-content">
                            <?php
                            // 获取最后一条消息作为预览
                            try {
                                $lastMsgStmt = $db->prepare("
                                    SELECT user_message, ai_response 
                                    FROM chat_record 
                                    WHERE session_id = ? AND is_deleted = 0 
                                    ORDER BY message_order DESC 
                                    LIMIT 1
                                ");
                                $lastMsgStmt->execute([$session['id']]);
                                $lastMessage = $lastMsgStmt->fetch(PDO::FETCH_ASSOC);
                                
                                $preview = '';
                                if ($lastMessage) {
                                    if (!empty($lastMessage['ai_response'])) {
                                        $preview = $lastMessage['ai_response'];
                                    } else if (!empty($lastMessage['user_message'])) {
                                        $preview = $lastMessage['user_message'];
                                    }
                                }
                                
                                // 截断预览文本
                                if (mb_strlen($preview) > 80) {
                                    $preview = mb_substr($preview, 0, 80) . '...';
                                }
                            } catch (PDOException $e) {
                                $preview = '加载消息失败';
                            }
                            ?>
                            <div class="last-message"><?php echo htmlspecialchars($preview ?: '暂无消息'); ?></div>
                        </div>
                        
                        <div class="session-actions">
                            <button class="action-btn" onclick="event.stopPropagation(); openEditModal(<?php echo $session['id']; ?>, '<?php echo htmlspecialchars(addslashes($session['session_title'] ?: '新对话')); ?>')">
                                <i class="fas fa-edit"></i> 编辑
                            </button>
                            <button class="action-btn delete" onclick="event.stopPropagation(); deleteSession(<?php echo $session['id']; ?>)">
                                <i class="fas fa-trash"></i> 删除
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 32px;">
                <a href="characters" class="new-chat-btn">
                    <i class="fas fa-plus"></i>
                    开始新对话
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- 编辑会话标题弹窗 -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">编辑会话标题</h3>
                <button class="modal-close" onclick="closeEditModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editForm">
                <input type="hidden" id="editSessionId">
                <div class="form-group">
                    <label class="form-label" for="editSessionTitle">会话标题</label>
                    <input type="text" class="form-input" id="editSessionTitle" maxlength="30" placeholder="请输入会话标题">
                    <div class="char-count" id="charCount">0/30</div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">取消</button>
                    <button type="submit" class="btn btn-primary" id="saveEditBtn">保存</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentSessionId = null;
        
        // 打开编辑弹窗
        function openEditModal(sessionId, currentTitle) {
            currentSessionId = sessionId;
            document.getElementById('editSessionId').value = sessionId;
            document.getElementById('editSessionTitle').value = currentTitle;
            updateCharCount();
            document.getElementById('editModal').classList.add('active');
            document.getElementById('editSessionTitle').focus();
        }
        
        // 关闭编辑弹窗
        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
            currentSessionId = null;
        }
        
        // 更新字符计数
        function updateCharCount() {
            const input = document.getElementById('editSessionTitle');
            const charCount = document.getElementById('charCount');
            const length = input.value.length;
            
            charCount.textContent = `${length}/30`;
            
            if (length > 25) {
                charCount.classList.add('warning');
                charCount.classList.remove('error');
            } else if (length > 30) {
                charCount.classList.add('error');
                charCount.classList.remove('warning');
            } else {
                charCount.classList.remove('warning', 'error');
            }
        }
        
        // 监听标题输入
        document.getElementById('editSessionTitle').addEventListener('input', updateCharCount);
        
        // 提交编辑表单
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const sessionId = document.getElementById('editSessionId').value;
            const newTitle = document.getElementById('editSessionTitle').value.trim();
            
            if (!newTitle) {
                alert('请输入会话标题');
                return;
            }
            
            const saveBtn = document.getElementById('saveEditBtn');
            saveBtn.disabled = true;
            saveBtn.textContent = '保存中...';
            
            // 发送编辑请求
            fetch('./api/chat_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=edit_title&session_id=${sessionId}&title=${encodeURIComponent(newTitle)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeEditModal();
                    location.reload();
                } else {
                    alert(data.message || '更新失败');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('网络错误，请稍后重试');
            })
            .finally(() => {
                saveBtn.disabled = false;
                saveBtn.textContent = '保存';
            });
        });
        
        // 删除会话
        function deleteSession(sessionId) {
            if (confirm('确定要删除这个会话吗？此操作将删除所有相关的聊天记录，且不可恢复。')) {
                fetch('./api/chat_session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_session&session_id=${sessionId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('会话删除成功');
                        location.reload();
                    } else {
                        alert(data.message || '删除失败');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('网络错误，请稍后重试');
                });
            }
        }
        
        // 点击弹窗外部关闭
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
        
        // ESC键关闭弹窗
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('editModal').classList.contains('active')) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>

<?php
$content = ob_get_clean();
include 'navbar.php';
?>