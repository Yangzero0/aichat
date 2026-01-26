<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-7
最后编辑时间：2025-11-15
文件描述：AI聊天页面，会话管理、角色切换及Markdown解析等功能

*/
session_start();
require_once(__DIR__ . './../config/config.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}

$user_id = $_SESSION['user_id'];
$character_id = isset($_GET['character_id']) ? intval($_GET['character_id']) : 1;
$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;

$user_info = [];
try {
    $stmt = $db->prepare("SELECT * FROM user WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user_info || empty($user_info['avatar'])) {
        $user_info['avatar'] = '/static/user-images/user.png';
    }
} catch (PDOException $e) {
    error_log("获取用户信息失败: " . $e->getMessage());
    $user_info['avatar'] = '/static/user-images/user.png';
}

$current_character = [];
try {
    $stmt = $db->prepare("
        SELECT ac.*, cc.name AS category_name
        FROM ai_character ac 
        LEFT JOIN character_category cc ON ac.category_id = cc.id 
        WHERE ac.id = ? AND (ac.status = 1 OR ac.user_id = ?)
    ");
    $stmt->execute([$character_id, $user_id]);
    $current_character = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current_character) {
        $character_id = 1;
        $stmt->execute([1, $user_id]);
        $current_character = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($current_character && empty($current_character['avatar'])) {
        $current_character['avatar'] = '/static/ai-images/ai.png';
    }
} catch (PDOException $e) {
    error_log("获取角色信息失败: " . $e->getMessage());
}

$subscribed_characters = [];
try {
    $stmt = $db->prepare("
        SELECT ac.*, cc.name AS category_name
        FROM ai_character ac 
        LEFT JOIN character_category cc ON ac.category_id = cc.id 
        LEFT JOIN character_subscription cs ON ac.id = cs.character_id 
        WHERE cs.user_id = ? AND cs.status = 1 AND ac.status = 1 
        ORDER BY ac.usage_count DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $subscribed_characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($subscribed_characters as &$character) {
        if (empty($character['avatar'])) {
            $character['avatar'] = '/static/ai-images/ai.png';
        }
    }
    unset($character);
} catch (PDOException $e) {
    error_log("获取订阅角色失败: " . $e->getMessage());
}

$chat_sessions = [];
try {
    $sql = "
        SELECT cs.*, ac.name AS character_name, ac.avatar AS character_avatar, COUNT(cr.id) AS message_count
        FROM chat_session cs 
        LEFT JOIN ai_character ac ON cs.character_id = ac.id 
        LEFT JOIN chat_record cr ON cs.id = cr.session_id AND cr.is_deleted = 0 
        WHERE cs.user_id = ? AND cs.status = 1 
    ";
    $params = [$user_id];
    
    if ($character_id > 0) {
        $sql .= " AND cs.character_id = ?";
        $params[] = $character_id;
    }
    
    $sql .= " GROUP BY cs.id ORDER BY cs.last_active DESC LIMIT 20";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $chat_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取会话列表失败: " . $e->getMessage());
}

$ai_models = [];
try {
    $stmt = $db->query("SELECT * FROM ai_model WHERE status = 1 ORDER BY sort_order DESC");
    $ai_models = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取AI模型失败: " . $e->getMessage());
}

$chat_messages = [];
$current_session_info = null;
if ($session_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM chat_session WHERE id = ? AND user_id = ?");
        $stmt->execute([$session_id, $user_id]);
        $current_session_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($current_session_info) {
            $stmt = $db->prepare("
                SELECT 
                    cr.id,
                    cr.user_message,
                    cr.ai_response,
                    cr.message_order,
                    cr.chat_time,
                    cr.is_interrupted
                FROM chat_record cr 
                WHERE cr.session_id = ? AND cr.is_deleted = 0 
                ORDER BY cr.message_order ASC
            ");
            $stmt->execute([$session_id]);
            $chat_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $character_id = $current_session_info['character_id'];
        }
    } catch (PDOException $e) {
        error_log("获取聊天消息失败: " . $e->getMessage());
    }
}

$settings = [];
try {
    $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("获取配置失败: " . $e->getMessage());
}

ob_start();
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/showdown/2.1.0/showdown.min.js"></script>

<div class="chat-shell">
<div class="ai-chat-container">
        <button class="ai-hamburger-menu hidden" id="aiHamburgerMenu">
        <i class="fas fa-bars"></i>
    </button>

        <div class="ai-sidebar-overlay" id="aiSidebarOverlay"></div>
        <aside class="ai-sidebar" id="aiSidebar">
        <div class="ai-sidebar-header">
            <h2><i class="fas fa-comments"></i> 会话信息</h2>
                <button class="ai-close-sidebar" id="aiCloseSidebar" aria-label="关闭侧边栏">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="ai-sidebar-content">
            <div class="ai-sidebar-section">
                <div class="ai-section-header">
                    <span>当前角色</span>
                </div>
                <div class="ai-character-list">
                    <?php if ($current_character): ?>
                        <div class="ai-character-item active">
                                <div class="ai-character-avatar">
                            <?php if (!empty($current_character['avatar'])): ?>
                                        <img src="<?php echo htmlspecialchars($current_character['avatar']); ?>" alt="<?php echo htmlspecialchars($current_character['name']); ?>">
                            <?php else: ?>
                                    <i class="fas fa-robot"></i>
                            <?php endif; ?>
                                </div>
                            <div class="ai-character-info">
                                <div class="ai-character-name"><?php echo htmlspecialchars($current_character['name']); ?></div>
                                <div class="ai-character-category"><?php echo htmlspecialchars($current_character['category_name'] ?? 'AI助手'); ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                            <div class="ai-character-item ai-character-item--empty">
                            <div>请选择角色</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="ai-sidebar-section">
                <div class="ai-section-header">
                    <span>已订阅角色</span>
                    <a href="characters" class="ai-view-more">查看更多</a>
                </div>
                <div class="ai-character-list">
                    <?php if (count($subscribed_characters) > 0): ?>
                        <?php foreach ($subscribed_characters as $character): ?>
                            <div class="ai-character-item <?php echo $character['id'] == $character_id ? 'active' : ''; ?>" 
                                 data-character-id="<?php echo $character['id']; ?>">
                                    <div class="ai-character-avatar">
                                <?php if (!empty($character['avatar'])): ?>
                                            <img src="<?php echo htmlspecialchars($character['avatar']); ?>" alt="<?php echo htmlspecialchars($character['name']); ?>">
                                <?php else: ?>
                                        <i class="fas fa-robot"></i>
                                <?php endif; ?>
                                    </div>
                                <div class="ai-character-info">
                                    <div class="ai-character-name"><?php echo htmlspecialchars($character['name']); ?></div>
                                    <div class="ai-character-category"><?php echo htmlspecialchars($character['category_name']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                            <div class="ai-character-item ai-character-item--empty">
                            <div>暂无订阅角色</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="ai-sidebar-section">
                <div class="ai-section-header">
                    <span>历史对话</span>
                </div>
                <div class="ai-new-chat-btn" id="aiNewChatBtn">
                    <i class="fas fa-plus"></i>
                    <span>开始新对话</span>
                </div>
                <div class="ai-session-list">
                    <?php if (count($chat_sessions) > 0): ?>
                        <?php foreach ($chat_sessions as $session): ?>
                            <div class="ai-session-item <?php echo $session['id'] == $session_id ? 'active' : ''; ?>" 
                                 data-session-id="<?php echo $session['id']; ?>"
                                 data-character-id="<?php echo $session['character_id']; ?>">
                                <div class="ai-session-header">
                                    <div class="ai-session-title-container">
                                        <div class="ai-session-title-text"><?php echo htmlspecialchars($session['session_title'] ?: '新对话'); ?></div>
                                        <div class="ai-session-actions">
                                            <button class="ai-session-edit-btn" data-session-id="<?php echo $session['id']; ?>" title="编辑标题">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="ai-session-delete-btn" data-session-id="<?php echo $session['id']; ?>" title="删除会话">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                        <div class="ai-session-meta">
                                            <span><?php echo (int)$session['message_count']; ?> 条消息 • <span class="relative-time" data-time="<?php echo htmlspecialchars($session['last_active']); ?>"><?php echo date('m-d H:i', strtotime($session['last_active'])); ?></span></span>
                                        </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                            <div class="ai-session-item ai-session-item--empty">
                            <div>暂无历史对话</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        </aside>
    
        <main class="ai-chat-main" id="aiChatMain">
            <header class="ai-chat-header">
            <div class="ai-current-character">
                    <button class="ai-toggle-sidebar-btn" id="aiToggleSidebarBtn" aria-label="切换侧边栏" title="切换侧边栏">
                        <i class="fas fa-comments"></i>
                    </button>
                    <div class="ai-character-avatar">
                <?php if (!empty($current_character['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($current_character['avatar']); ?>" alt="<?php echo htmlspecialchars($current_character['name']); ?>">
                <?php else: ?>
                        <i class="fas fa-robot"></i>
                <?php endif; ?>
                    </div>
                <div class="ai-character-details">
                    <h2><?php echo htmlspecialchars($current_character['name'] ?? '请选择角色'); ?></h2>
                    <p><?php echo htmlspecialchars($current_character['introduction'] ?? '请从侧边栏选择角色开始聊天'); ?></p>
                </div>
            </div>
            <div class="ai-header-actions">
                <div class="ai-model-selector">
                    <label for="aiModelSelect">模型:</label>
                    <select id="aiModelSelect" class="ai-model-select">
                        <?php foreach ($ai_models as $model): ?>
                            <option value="<?php echo $model['id']; ?>" 
                                    <?php echo (isset($current_session_info['model_id']) && $current_session_info['model_id'] == $model['id']) ? 'selected' : ''; ?>
                                    data-max-tokens="<?php echo $model['max_tokens']; ?>"
                                    data-temperature="<?php echo $model['temperature']; ?>">
                                <?php echo htmlspecialchars($model['model_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            </header>
        
        <div class="ai-messages-container" id="aiMessagesContainer">
            <?php if (count($chat_messages) > 0): ?>
                <?php foreach ($chat_messages as $msg): ?>
                    <?php if (!empty($msg['user_message'])): ?>
                            <div class="ai-message user"
                                 data-message-id="<?php echo $msg['id']; ?>"
                                 data-message-order="<?php echo $msg['message_order']; ?>">
                                <div class="ai-character-avatar">
                                <?php if (!empty($user_info['avatar'])): ?>
                                    <img src="<?php echo htmlspecialchars($user_info['avatar']); ?>" alt="用户头像">
                                <?php else: ?>
                                    <i class="fas fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div class="ai-message-content">
                                <div class="ai-message-bubble">
                                    <div class="ai-message-text"><?php echo htmlspecialchars($msg['user_message']); ?></div>
                                </div>
                                <div class="ai-message-time"><?php echo date('H:i', strtotime($msg['chat_time'])); ?></div>
                                <div class="ai-message-actions">
                                    <a href="javascript:void(0)" class="ai-message-action-link ai-edit-message-btn">
                                        <i class="fas fa-edit"></i> 编辑
                                    </a>
                                    <a href="javascript:void(0)" class="ai-message-action-link ai-copy-message-btn">
                                        <i class="fas fa-copy"></i> 复制
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($msg['ai_response'])): ?>
                        <div class="ai-message ai <?php echo $msg['is_interrupted'] ? 'interrupted' : ''; ?>" 
                             data-message-id="<?php echo $msg['id']; ?>" 
                             data-message-order="<?php echo $msg['message_order']; ?>"
                             data-raw-content="<?php echo htmlspecialchars($msg['ai_response']); ?>">
                                <div class="ai-character-avatar">
                                <?php if (!empty($current_character['avatar'])): ?>
                                    <img src="<?php echo htmlspecialchars($current_character['avatar']); ?>" alt="<?php echo htmlspecialchars($current_character['name']); ?>">
                                <?php else: ?>
                                    <i class="fas fa-robot"></i>
                                <?php endif; ?>
                            </div>
                            <div class="ai-message-content">
                                <div class="ai-message-bubble">
                                    <div class="ai-message-text" data-needs-render="true"><?php echo htmlspecialchars($msg['ai_response']); ?></div>
                                </div>
                                <div class="ai-message-time"><?php echo date('H:i', strtotime($msg['chat_time'])); ?></div>
                                <div class="ai-message-actions">
                                    <a href="javascript:void(0)" class="ai-message-action-link ai-regenerate-message-btn">
                                        <i class="fas fa-redo"></i> 重新生成
                                    </a>
                                    <a href="javascript:void(0)" class="ai-message-action-link ai-continue-generate-btn">
                                        <i class="fas fa-play"></i> 继续生成
                                    </a>
                                    <a href="javascript:void(0)" class="ai-message-action-link ai-copy-message-btn">
                                        <i class="fas fa-copy"></i> 复制
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="ai-empty-state">
                    <i class="fas fa-comments"></i>
                    <h3><?php echo $current_character ? '开始与 ' . htmlspecialchars($current_character['name']) . ' 对话' : '欢迎使用AI聊天'; ?></h3>
                        <p><?php echo $current_character ? '输入消息开始聊天，AI将根据角色设定回复您。' : '请从侧边栏选择角色开始聊天。'; ?></p>
                </div>
            <?php endif; ?>
        </div>
        
            <div class="ai-loading-indicator" id="aiLoadingIndicator">
    <div class="ai-typing-dots">
        <div class="ai-typing-dot"></div>
        <div class="ai-typing-dot"></div>
        <div class="ai-typing-dot"></div>
    </div>
    <span><?php echo htmlspecialchars($current_character['name'] ?? 'AI'); ?> 正在思考...</span>
                <button id="aiStopButton" class="ai-send-button stop ai-stop-button">
        <i class="fas fa-stop"></i>
        停止生成
    </button>
</div>

        <div class="ai-input-container">
            <div class="ai-input-area">
                <textarea 
                    id="aiMessageInput" 
                    class="ai-message-input" 
                    placeholder="<?php echo $current_character ? '输入您想说的话...' : '请先选择角色...'; ?>" 
                    rows="1"
                    <?php echo !$current_character ? 'disabled' : ''; ?>
                ></textarea>
                <button id="aiSendButton" class="ai-send-button" <?php echo !$current_character ? 'disabled' : ''; ?>>
                    <i class="fas fa-paper-plane"></i>
                    发送
                </button>
            </div>
        </div>
        </main>
    </div>
</div>

<script>
let showdownConverter = null;

function initializeShowdown() {
    showdownConverter = new showdown.Converter({
        tables: true,
        simplifiedAutoLink: true,
        strikethrough: true,
        tasklists: true,
        simpleLineBreaks: true,
        openLinksInNewWindow: true,
        emoji: true,
        underline: true,
        smoothLivePreview: true,
        smartIndentationFix: true,
        disableForced4SpacesIndentedSublists: true,
        requireSpaceBeforeHeadingText: true,
        ghCompatibleHeaderId: true,
        ghCodeBlocks: true,
        parseImgDimensions: true,
        headerLevelStart: 3
    });
}

let currentSessionId = <?php echo $session_id; ?>;
let currentCharacterId = <?php echo $character_id; ?>;
let currentModelId = document.getElementById('aiModelSelect').value;
let isGenerating = false;
let currentEventSource = null;
let currentGeneratingMessageId = null;

const userAvatar = '<?php echo !empty($user_info['avatar']) ? htmlspecialchars($user_info['avatar']) : "/static/user-images/user.png"; ?>';
// AI头像改为变量，可以从DOM中动态获取
let aiAvatar = '<?php echo !empty($current_character['avatar']) ? htmlspecialchars($current_character['avatar']) : "/static/ai-images/ai.png"; ?>';

// 从页面头部获取当前角色的头像（如果存在）
function updateAIAvatar() {
    const headerAvatar = document.querySelector('.ai-chat-header .ai-character-avatar img');
    if (headerAvatar) {
        aiAvatar = headerAvatar.src;
    } else {
        // 如果没有找到，使用默认头像
        aiAvatar = '/static/ai-images/ai.png';
    }
}

// 页面加载时更新AI头像
updateAIAvatar();

const aiSidebar = document.getElementById('aiSidebar');
const aiChatMain = document.getElementById('aiChatMain');
const aiHamburgerMenu = document.getElementById('aiHamburgerMenu');
const aiCloseSidebar = document.getElementById('aiCloseSidebar');
const aiToggleSidebarBtn = document.getElementById('aiToggleSidebarBtn');
const aiSidebarOverlay = document.getElementById('aiSidebarOverlay');

function toggleSidebar() {
    const isCollapsed = aiSidebar.classList.contains('collapsed');
    aiSidebar.classList.toggle('collapsed');
    
    // 移动端：控制遮罩层显示/隐藏
    if (window.innerWidth <= 900 && aiSidebarOverlay) {
        if (isCollapsed) {
            // 展开侧边栏，显示遮罩
            aiSidebarOverlay.classList.add('active');
        } else {
            // 折叠侧边栏，隐藏遮罩
            aiSidebarOverlay.classList.remove('active');
        }
    }
    
    // 更新折叠按钮图标
    if (aiToggleSidebarBtn) {
        const icon = aiToggleSidebarBtn.querySelector('i');
        if (icon) {
            icon.className = 'fas fa-comments';
        }
    }
}

if (aiToggleSidebarBtn) {
    aiToggleSidebarBtn.addEventListener('click', toggleSidebar);
}

if (aiHamburgerMenu) {
aiHamburgerMenu.addEventListener('click', toggleSidebar);
}

if (aiCloseSidebar) {
aiCloseSidebar.addEventListener('click', toggleSidebar);
}

// 点击遮罩层关闭侧边栏（移动端）
if (aiSidebarOverlay) {
    aiSidebarOverlay.addEventListener('click', function() {
        if (window.innerWidth <= 900 && !aiSidebar.classList.contains('collapsed')) {
        toggleSidebar();
    }
});
}

// 移动端默认折叠侧边栏
if (window.innerWidth <= 900 && aiSidebar) {
    aiSidebar.classList.add('collapsed');
}

function scrollToBottom() {
    const aiMessagesContainer = document.getElementById('aiMessagesContainer');
    aiMessagesContainer.scrollTop = aiMessagesContainer.scrollHeight;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function enhanceCodeBlocks(html, codeBlockLanguages = []) {
    let codeBlockIndex = 0;
    
    // 先处理完整的代码块（有闭合标签的）
    // 需要检查代码块是否在卡片内，而不是检查整个 HTML
    html = html.replace(/<pre><code([^>]*)>([\s\S]*?)<\/code><\/pre>/g, function(match, attributes, codeContent) {
        // 检查这个代码块是否已经在卡片内
        // 通过检查前后是否有 code-block-card 标签来判断
        const beforeMatch = html.substring(0, html.indexOf(match));
        const afterMatch = html.substring(html.indexOf(match) + match.length);
        
        // 检查前面是否有 code-block-card 开始标签，后面是否有对应的结束标签
        const cardStartBefore = beforeMatch.lastIndexOf('<div class="code-block-card"');
        if (cardStartBefore !== -1) {
            // 检查从卡片开始到当前代码块之间是否有完整的卡片结构
            const betweenCardAndCode = html.substring(cardStartBefore, html.indexOf(match));
            const cardEndAfter = afterMatch.indexOf('</div></div></div>');
            if (cardEndAfter !== -1 && betweenCardAndCode.includes('<div class="card__content">')) {
                // 这个代码块已经在卡片内，跳过
                return match;
            }
        }
        
        return processCodeBlock(match, attributes, codeContent, codeBlockLanguages, codeBlockIndex++);
    });
    
    // 再处理未完成的代码块（没有闭合标签的，用于实时显示）
    html = html.replace(/<pre><code([^>]*)>([\s\S]*?)(?=<\/pre>|$)/g, function(match, attributes, codeContent) {
        // 检查这个代码块是否已经在卡片内
        const matchIndex = html.indexOf(match);
        if (matchIndex === -1) {
            return match;
        }
        
        const beforeMatch = html.substring(0, matchIndex);
        const afterMatch = html.substring(matchIndex + match.length);
        
        // 检查前面是否有 code-block-card 开始标签
        const cardStartBefore = beforeMatch.lastIndexOf('<div class="code-block-card"');
        if (cardStartBefore !== -1) {
            const betweenCardAndCode = html.substring(cardStartBefore, matchIndex);
            if (betweenCardAndCode.includes('<div class="card__content">')) {
                // 这个代码块已经在卡片内，跳过
                return match;
            }
        }
        
        // 检查是否已经有闭合标签（如果已经处理过，跳过）
        if (match.includes('</code></pre>')) {
            return match;
        }
        
        return processCodeBlock(match, attributes, codeContent, codeBlockLanguages, codeBlockIndex++);
    });
    
    return html;
}

function processCodeBlock(match, attributes, codeContent, codeBlockLanguages, codeBlockIndex) {
    // 如果这个代码块已经在卡片内，跳过
    if (match.includes('code-block-card')) {
        return match;
    }
    
    // 从属性中提取语言
    let language = null;
    
    // 尝试匹配 class="language-xxx" 或 class="xxx"
    const classMatch = attributes.match(/class=["'](?:language-)?([\w-]+)["']/);
    if (classMatch) {
        language = classMatch[1];
    }
    
    // 如果没有找到，尝试匹配 lang="xxx"
    if (!language) {
        const langMatch = attributes.match(/lang=["']([\w-]+)["']/);
        if (langMatch) {
            language = langMatch[1];
        }
    }
    
    // 如果还是没有找到，尝试从预提取的语言列表中获取
    if ((!language || language === 'text' || language === '') && codeBlockLanguages.length > 0 && codeBlockLanguages[codeBlockIndex]) {
        language = codeBlockLanguages[codeBlockIndex].language;
    }
    
    // 如果还是没有找到语言，尝试从代码内容推断
    if (!language || language === 'text' || language === '') {
        language = detectLanguageFromContent(codeContent);
    }
    
    // 确保 language 不为空
    if (!language) {
        language = 'text';
    }
    
    const languageLabel = getLanguageLabel(language);
    
    // 清理代码内容：去除开头和结尾的空白行，但保留代码内部的格式
    let cleanedContent = codeContent;
    
    // 去除开头的空白行（换行符、空格、制表符等）
    cleanedContent = cleanedContent.replace(/^[\s\n\r\t]+/, '');
    
    // 去除结尾的空白行（换行符、空格、制表符等）
    cleanedContent = cleanedContent.replace(/[\s\n\r\t]+$/, '');
    
    // 如果内容为空或只有空白，直接返回空字符串
    if (!cleanedContent.trim()) {
        cleanedContent = '';
    }
    
    // 提取纯文本代码内容（用于复制）
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = cleanedContent;
    let textContent = tempDiv.textContent || tempDiv.innerText || '';
    
    // 如果 textContent 为空，尝试直接解码 HTML 实体
    if (!textContent || textContent.trim() === '') {
        textContent = cleanedContent
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"')
            .replace(/&#39;/g, "'")
            .replace(/&nbsp;/g, ' ');
    }
    
    // 清理文本内容：去除开头和结尾的空白行
    textContent = textContent.replace(/^[\s\n\r\t]+/, '').replace(/[\s\n\r\t]+$/, '');
    
    // 如果文本内容为空，使用清理后的代码内容
    if (!textContent.trim() && cleanedContent.trim()) {
        textContent = cleanedContent
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"')
            .replace(/&#39;/g, "'")
            .replace(/&nbsp;/g, ' ');
    }
    
    // 转义 textContent 以便存储在 data 属性中
    const escapedTextContent = escapeHtml(textContent)
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    
    // 生成唯一 ID
    const codeBlockId = 'code-block-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    
    // 创建带 macOS 窗口样式的代码块
    return `<div class="code-block-card" data-code-id="${codeBlockId}">
        <div class="tools">
            <div class="circle">
                <span class="red box"></span>
            </div>
            <div class="circle">
                <span class="yellow box"></span>
            </div>
            <div class="circle">
                <span class="green box"></span>
            </div>
            <div class="code-block-language">${escapeHtml(languageLabel)}</div>
            <div class="code-block-actions">
                <button class="code-copy-btn" data-code-content="${escapedTextContent}" title="复制代码">
                    <i class="fas fa-copy"></i>
                    <span>复制</span>
                </button>
            </div>
        </div>
        <div class="card__content">
            <pre><code${language ? ` class="language-${language}"` : ''}>${cleanedContent}</code></pre>
        </div>
    </div>`;
}

function detectLanguageFromContent(codeContent) {
    // 从代码内容推断语言
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = codeContent;
    let text = (tempDiv.textContent || tempDiv.innerText || '').trim();
    
    // 如果 text 为空，尝试直接解码 HTML 实体
    if (!text) {
        text = codeContent
            .replace(/&amp;/g, '&')
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&quot;/g, '"')
            .replace(/&#39;/g, "'")
            .replace(/&nbsp;/g, ' ')
            .trim();
    }
    
    if (!text) {
        return 'text';
    }
    
    // Python 特征（更全面的检测）
    if (text.match(/^(print\(|def\s+\w+|import\s+\w+|from\s+\w+\s+import|if\s+__name__|#!\/usr\/bin\/env\s+python|#!\/usr\/bin\/python)/m) ||
        text.match(/(lambda\s+\w+:|yield\s+|async\s+def|@\w+\.|self\.|__init__|__str__)/m)) {
        return 'python';
    }
    // JavaScript 特征
    if (text.match(/(console\.log|function\s+\w+|const\s+\w+|let\s+\w+|var\s+\w+|=>|require\(|module\.exports|export\s+default|\.then\(|\.catch\(|async\s+function)/m)) {
        return 'javascript';
    }
    // HTML 特征
    if (text.match(/^<(!DOCTYPE|html|head|body|div|span|p|a|img|script|style|meta|link)/m)) {
        return 'html';
    }
    // CSS 特征
    if (text.match(/(@media|@keyframes|@import|:\s*\{|margin:\s*|padding:\s*|background:\s*|color:\s*|font-size:)/m)) {
        return 'css';
    }
    // SQL 特征
    if (text.match(/(SELECT|INSERT|UPDATE|DELETE|CREATE\s+TABLE|FROM|WHERE|JOIN|GROUP\s+BY)/i)) {
        return 'sql';
    }
    // JSON 特征
    if (text.match(/^[\s]*[\{\[]/m) && text.match(/[\}\]]/m) && text.match(/["']\w+["']\s*:/)) {
        return 'json';
    }
    
    return 'text';
}

function getLanguageLabel(language) {
    if (!language) {
        language = 'text';
    }
    
    const languageMap = {
        'javascript': 'JavaScript',
        'js': 'JavaScript',
        'typescript': 'TypeScript',
        'ts': 'TypeScript',
        'python': 'Python',
        'py': 'Python',
        'java': 'Java',
        'cpp': 'C++',
        'c++': 'C++',
        'c': 'C',
        'csharp': 'C#',
        'c#': 'C#',
        'php': 'PHP',
        'ruby': 'Ruby',
        'rb': 'Ruby',
        'go': 'Go',
        'golang': 'Go',
        'rust': 'Rust',
        'rs': 'Rust',
        'swift': 'Swift',
        'kotlin': 'Kotlin',
        'kt': 'Kotlin',
        'html': 'HTML',
        'css': 'CSS',
        'scss': 'SCSS',
        'sass': 'SASS',
        'json': 'JSON',
        'xml': 'XML',
        'yaml': 'YAML',
        'yml': 'YAML',
        'markdown': 'Markdown',
        'md': 'Markdown',
        'bash': 'Bash',
        'sh': 'Shell',
        'shell': 'Shell',
        'sql': 'SQL',
        'text': '文本',
        'plain': '文本',
        'plaintext': '文本'
    };
    
    const langLower = language.toLowerCase().trim();
    return languageMap[langLower] || language.charAt(0).toUpperCase() + language.slice(1).toLowerCase();
}

function initCodeBlockButtons(container) {
    const scope = container || document;
    
    // 复制按钮
    scope.querySelectorAll('.code-copy-btn').forEach(btn => {
        // 如果已经绑定过事件，跳过
        if (btn.dataset.initialized === 'true') {
            return;
        }
        btn.dataset.initialized = 'true';
        
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const codeContent = this.getAttribute('data-code-content');
            if (codeContent) {
                // 解码 HTML 实体
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = codeContent;
                let textContent = tempDiv.textContent || tempDiv.innerText || '';
                
                // 如果 textContent 为空，尝试直接解码
                if (!textContent) {
                    textContent = codeContent
                        .replace(/&amp;/g, '&')
                        .replace(/&lt;/g, '<')
                        .replace(/&gt;/g, '>')
                        .replace(/&quot;/g, '"')
                        .replace(/&#39;/g, "'")
                        .replace(/&nbsp;/g, ' ');
                }
                
                navigator.clipboard.writeText(textContent).then(() => {
                    // 显示复制成功提示
                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-check"></i><span>已复制</span>';
                    this.classList.add('copied');
                    
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                        this.classList.remove('copied');
                    }, 2000);
                }).catch(err => {
                    console.error('复制失败:', err);
                    // 降级方案：使用 textarea
                    const textarea = document.createElement('textarea');
                    textarea.value = textContent;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand('copy');
                        const originalHTML = this.innerHTML;
                        this.innerHTML = '<i class="fas fa-check"></i><span>已复制</span>';
                        this.classList.add('copied');
                        setTimeout(() => {
                            this.innerHTML = originalHTML;
                            this.classList.remove('copied');
                        }, 2000);
                    } catch (e) {
                        alert('复制失败，请手动选择文本复制');
                    }
                    document.body.removeChild(textarea);
                });
            }
        });
    });
}

function extractCodeBlockLanguages(content) {
    // 从原始 Markdown 文本中提取代码块语言
    const languages = [];
    // 匹配 ```language 或 ```language\n 或 ```\n
    const codeBlockRegex = /```(\w+)?\s*\n?([\s\S]*?)(?:```|$)/g;
    let match;
    let index = 0;
    
    while ((match = codeBlockRegex.exec(content)) !== null) {
        const lang = match[1] ? match[1].trim().toLowerCase() : null;
        languages.push({
            index: index++,
            language: lang || 'text'
        });
    }
    
    return languages;
}

function renderMarkdown(content) {
    try {
        if (!showdownConverter) {
            initializeShowdown();
        }
        // 在解析前提取代码块语言信息
        const codeBlockLanguages = extractCodeBlockLanguages(content);
        let html = showdownConverter.makeHtml(content);
        // 为代码块添加 macOS 窗口样式，并传入语言信息
        html = enhanceCodeBlocks(html, codeBlockLanguages);
        // 只移除 <br> 标签（因为使用 white-space: pre-wrap 处理换行）
        html = html.replace(/<br\s*\/?>/gi, '\n');
        
        // 清理HTML标签之间的多余换行符（保留内容中的换行）
        // 清理标签开始和结束之间的换行：<tag>\n 和 \n</tag>
        html = html.replace(/(<[^>]+>)\s*\n+/g, '$1');
        html = html.replace(/\n+\s*(<\/[^>]+>)/g, '$1');
        
        // 清理标签之间的换行：</tag>\n<tag>
        html = html.replace(/(<\/[^>]+>)\s*\n+\s*(<[^>]+>)/g, '$1$2');
        
        // 清理段落和列表之间的换行
        html = html.replace(/(<\/p>)\s*\n+\s*(<o[lu]>)/g, '$1$2');
        html = html.replace(/(<\/o[lu]>)\s*\n+\s*(<p>)/g, '$1$2');
        
        // 清理列表项之间的换行
        html = html.replace(/(<\/li>)\s*\n+\s*(<li>)/g, '$1$2');
        
        // 清理列表标签和列表项之间的换行
        html = html.replace(/(<o[lu]>)\s*\n+\s*(<li>)/g, '$1$2');
        html = html.replace(/(<\/li>)\s*\n+\s*(<\/o[lu]>)/g, '$1$2');
        
        // 清理嵌套列表标签之间的换行
        html = html.replace(/(<li>)\s*\n+\s*(<o[lu]>)/g, '$1$2');
        html = html.replace(/(<\/o[lu]>)\s*\n+\s*(<\/li>)/g, '$1$2');
        
        // 清理段落和列表项内的多余连续空格（只压缩连续空格，保留单个空格和换行）
        html = html.replace(/<p>([\s\S]*?)<\/p>/g, function(match, content) {
            // 只清理连续的空格（2个或更多），保留换行符
            let cleanedContent = content.replace(/[ \t]{2,}/g, ' ');
            // 清理开头和结尾的空白字符
            cleanedContent = cleanedContent.replace(/^[\s\t]+|[\s\t]+$/g, '');
            return '<p>' + cleanedContent + '</p>';
        });
        
        // 清理列表项内的多余连续空格
        html = html.replace(/<li>([\s\S]*?)<\/li>/g, function(match, content) {
            // 只清理连续的空格（2个或更多），保留换行符
            let cleanedContent = content.replace(/[ \t]{2,}/g, ' ');
            // 清理开头和结尾的空白字符
            cleanedContent = cleanedContent.replace(/^[\s\t]+|[\s\t]+$/g, '');
            return '<li>' + cleanedContent + '</li>';
        });
        
        return html;
    } catch (e) {
        console.error('Markdown解析错误:', e);
        return escapeHtml(content).replace(/\n/g, '<br>');
    }
}

function renderMarkdownRealtime(content) {
    try {
        if (!showdownConverter) {
            initializeShowdown();
        }
        
        // 处理未完成的代码块
        // 检查是否有未闭合的代码块标记（```）
        const codeBlockMatches = content.match(/```/g);
        const codeBlockCount = codeBlockMatches ? codeBlockMatches.length : 0;
        const isCodeBlockOpen = codeBlockCount % 2 !== 0;
        
        let processedContent = content;
        let needsCleanup = false;
        
        // 如果有未闭合的代码块，需要特殊处理
        if (isCodeBlockOpen) {
            // 找到最后一个代码块标记的位置
            const lastCodeBlockIndex = content.lastIndexOf('```');
            const afterLastCodeBlock = content.substring(lastCodeBlockIndex + 3);
            
            // 检查代码块的状态
            // 1. 如果```后没有内容或只有空白，可能是刚输入了```标记
            // 2. 如果```后有内容但没有换行，可能是语言标识符（如```python）
            // 3. 如果```后有换行和内容，说明代码块内容正在生成
            
            if (afterLastCodeBlock.trim() === '') {
                // 只有```标记，可能还在输入语言标识符，暂时不处理
                processedContent = content;
            } else if (!afterLastCodeBlock.includes('\n')) {
                // 可能是语言标识符（如```python），暂时不处理
                processedContent = content;
            } else {
                // 代码块内容正在生成，临时添加闭合标记以便正确解析
                processedContent = content + '\n```';
                needsCleanup = true;
            }
        }
        
        // 在解析前提取代码块语言信息
        const codeBlockLanguages = extractCodeBlockLanguages(processedContent);
        
        // 使用 showdown 解析
        let html = showdownConverter.makeHtml(processedContent);
        
        // 如果代码块已经完成（不需要清理），先移除所有代码块卡片，重新应用样式
        // 这样可以确保代码块完成时不会有重复的卡片
        if (!needsCleanup) {
            // 移除所有代码块卡片，提取其中的代码内容
            html = html.replace(/<div class="code-block-card"[^>]*>[\s\S]*?<div class="card__content">\s*<pre><code([^>]*)>([\s\S]*?)<\/code><\/pre>\s*<\/div>\s*<\/div>\s*<\/div>/g, function(match, attrs, codeContent) {
                // 提取代码内容，移除卡片包装，恢复为原始的 <pre><code> 格式
                return `<pre><code${attrs}>${codeContent}</code></pre>`;
            });
        }
        
        // 先应用代码块样式（在清理之前，这样即使未完成也能显示样式）
        html = enhanceCodeBlocks(html, codeBlockLanguages);
        
        // 只移除 <br> 标签（因为使用 white-space: pre-wrap 处理换行）
        html = html.replace(/<br\s*\/?>/gi, '\n');
        
        // 清理HTML标签之间的多余换行符（保留内容中的换行）
        // 清理标签开始和结束之间的换行：<tag>\n 和 \n</tag>
        html = html.replace(/(<[^>]+>)\s*\n+/g, '$1');
        html = html.replace(/\n+\s*(<\/[^>]+>)/g, '$1');
        
        // 清理标签之间的换行：</tag>\n<tag>
        html = html.replace(/(<\/[^>]+>)\s*\n+\s*(<[^>]+>)/g, '$1$2');
        
        // 清理段落和列表之间的换行
        html = html.replace(/(<\/p>)\s*\n+\s*(<o[lu]>)/g, '$1$2');
        html = html.replace(/(<\/o[lu]>)\s*\n+\s*(<p>)/g, '$1$2');
        
        // 清理列表项之间的换行
        html = html.replace(/(<\/li>)\s*\n+\s*(<li>)/g, '$1$2');
        
        // 清理列表标签和列表项之间的换行
        html = html.replace(/(<o[lu]>)\s*\n+\s*(<li>)/g, '$1$2');
        html = html.replace(/(<\/li>)\s*\n+\s*(<\/o[lu]>)/g, '$1$2');
        
        // 清理嵌套列表标签之间的换行
        html = html.replace(/(<li>)\s*\n+\s*(<o[lu]>)/g, '$1$2');
        html = html.replace(/(<\/o[lu]>)\s*\n+\s*(<\/li>)/g, '$1$2');
        
        // 清理段落和列表项内的多余连续空格（只压缩连续空格，保留单个空格和换行）
        html = html.replace(/<p>([\s\S]*?)<\/p>/g, function(match, content) {
            // 只清理连续的空格（2个或更多），保留换行符
            let cleanedContent = content.replace(/[ \t]{2,}/g, ' ');
            // 清理开头和结尾的空白字符
            cleanedContent = cleanedContent.replace(/^[\s\t]+|[\s\t]+$/g, '');
            return '<p>' + cleanedContent + '</p>';
        });
        
        // 清理列表项内的多余连续空格
        html = html.replace(/<li>([\s\S]*?)<\/li>/g, function(match, content) {
            // 只清理连续的空格（2个或更多），保留换行符
            let cleanedContent = content.replace(/[ \t]{2,}/g, ' ');
            // 清理开头和结尾的空白字符
            cleanedContent = cleanedContent.replace(/^[\s\t]+|[\s\t]+$/g, '');
            return '<li>' + cleanedContent + '</li>';
        });
        
        // 如果临时添加了闭合标记，需要清理未完成的代码块部分
        if (needsCleanup) {
            // 找到最后一个代码块卡片
            const lastCardIndex = html.lastIndexOf('<div class="code-block-card"');
            if (lastCardIndex !== -1) {
                // 找到代码块卡片的结束位置（三个 </div>）
                const afterCard = html.substring(lastCardIndex);
                const cardEndMatch = afterCard.match(/<\/div>\s*<\/div>\s*<\/div>/);
                
                if (cardEndMatch) {
                    // 找到最后一个完整的代码块闭合标签位置
                    const cardEndIndex = lastCardIndex + afterCard.indexOf(cardEndMatch[0]) + cardEndMatch[0].length;
                    
                    // 在代码块内容中找到 </code></pre> 的位置
                    const beforeCardEnd = html.substring(0, cardEndIndex);
                    const lastPreCodeIndex = beforeCardEnd.lastIndexOf('</code></pre>');
                    
                    if (lastPreCodeIndex !== -1) {
                        // 移除最后一个完整的代码块闭合标签和卡片结束标签，保留未完成的部分
                        // 找到卡片开始到 </code></pre> 之间的内容
                        const cardStartToPreCode = html.substring(lastCardIndex, lastPreCodeIndex);
                        const preCodeMatch = cardStartToPreCode.match(/<pre><code([^>]*)>([\s\S]*)$/);
                        
                        if (preCodeMatch) {
                            // 提取语言和内容
                            const attrs = preCodeMatch[1];
                            const codeContent = preCodeMatch[2];
                            
                            // 从属性中提取语言
                            let language = null;
                            const classMatch = attrs.match(/class=["'](?:language-)?([\w-]+)["']/);
                            if (classMatch) {
                                language = classMatch[1];
                            }
                            
                            // 如果没有找到，尝试从预提取的语言列表中获取
                            if ((!language || language === 'text') && codeBlockLanguages.length > 0) {
                                const lastLangIndex = codeBlockLanguages.length - 1;
                                if (codeBlockLanguages[lastLangIndex]) {
                                    language = codeBlockLanguages[lastLangIndex].language;
                                }
                            }
                            
                            // 如果还是没有找到语言，尝试从代码内容推断
                            if (!language || language === 'text') {
                                language = detectLanguageFromContent(codeContent);
                            }
                            
                            if (!language) {
                                language = 'text';
                            }
                            
                            const languageLabel = getLanguageLabel(language);
                            
                            // 清理代码内容
                            let cleanedContent = codeContent.replace(/^[\s\n\r\t]+/, '').replace(/[\s\n\r\t]+$/, '');
                            
                            // 提取纯文本用于复制
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = cleanedContent;
                            let textContent = (tempDiv.textContent || tempDiv.innerText || '').trim();
                            
                            if (!textContent) {
                                textContent = cleanedContent
                                    .replace(/&amp;/g, '&')
                                    .replace(/&lt;/g, '<')
                                    .replace(/&gt;/g, '>')
                                    .replace(/&quot;/g, '"')
                                    .replace(/&#39;/g, "'")
                                    .replace(/&nbsp;/g, ' ')
                                    .trim();
                            }
                            
                            const escapedTextContent = escapeHtml(textContent)
                                .replace(/"/g, '&quot;')
                                .replace(/'/g, '&#39;');
                            
                            const codeBlockId = 'code-block-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
                            
                            // 创建未完成的代码块样式
                            const incompleteCodeBlock = `<div class="code-block-card" data-code-id="${codeBlockId}">
                                <div class="tools">
                                    <div class="circle">
                                        <span class="red box"></span>
                                    </div>
                                    <div class="circle">
                                        <span class="yellow box"></span>
                                    </div>
                                    <div class="circle">
                                        <span class="green box"></span>
                                    </div>
                                    <div class="code-block-language">${escapeHtml(languageLabel)}</div>
                                    <div class="code-block-actions">
                                        <button class="code-copy-btn" data-code-content="${escapedTextContent}" title="复制代码">
                                            <i class="fas fa-copy"></i>
                                            <span>复制</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="card__content">
                                    <pre><code${language ? ` class="language-${language}"` : ''}>${cleanedContent}</code></pre>
                                </div>
                            </div>`;
                            
                            // 替换未完成的代码块（移除完整的卡片，添加未完成的卡片）
                            html = html.substring(0, lastCardIndex) + incompleteCodeBlock;
                        }
                    }
                }
            }
        }
        
        return html;
    } catch (e) {
        console.error('实时Markdown解析错误:', e);
        // 如果解析失败，回退到简单处理
        return escapeHtml(content).replace(/\n/g, '<br>');
    }
}

function renderAllAIMessages() {
    // 确保 Showdown 已初始化
    if (!showdownConverter) {
        initializeShowdown();
    }
    
    // 渲染所有需要渲染的AI消息（包括中断的消息）
    document.querySelectorAll('.ai-message.ai').forEach(message => {
        const rawContent = message.getAttribute('data-raw-content');
        const messageText = message.querySelector('.ai-message-text');
        const isInterrupted = message.classList.contains('interrupted');
        
        // 如果消息文本标记为需要渲染，或者有 raw-content 属性，则进行渲染
        if (messageText && (messageText.hasAttribute('data-needs-render') || rawContent)) {
            // 优先使用 data-raw-content，因为它包含原始内容
            // data-raw-content 通过 htmlspecialchars 转义，但浏览器读取属性时会自动解码
            let contentToRender = '';
            if (rawContent) {
                // 直接使用属性值，浏览器已经自动解码了 HTML 实体
                contentToRender = rawContent;
            } else if (messageText.textContent) {
                contentToRender = messageText.textContent;
            }
            
            if (contentToRender.trim()) {
                console.log('渲染消息，内容预览:', contentToRender.substring(0, 100));
                console.log('是否中断:', isInterrupted);
                
                // 如果是中断的消息，使用 renderMarkdownRealtime 来正确处理未完成的代码块
                // 否则使用 renderMarkdown
                const renderedHTML = isInterrupted ? renderMarkdownRealtime(contentToRender) : renderMarkdown(contentToRender);
                
                console.log('渲染后HTML预览:', renderedHTML.substring(0, 200));
                console.log('是否包含代码块:', renderedHTML.includes('code-block-card'));
                messageText.innerHTML = renderedHTML;
                // 初始化代码块按钮
                initCodeBlockButtons(message);
                // 移除标记
                messageText.removeAttribute('data-needs-render');
            }
        }
    });
}

// 先初始化 Showdown
initializeShowdown();

// 页面加载完成后渲染所有消息
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        // 延迟一点确保所有元素都已加载
        setTimeout(renderAllAIMessages, 50);
    });
} else {
    // 如果 DOM 已经加载完成，延迟一点执行以确保所有元素都已渲染
    setTimeout(renderAllAIMessages, 50);
}
aiHamburgerMenu.classList.add('hidden');

// 格式化相对时间
function formatRelativeTime(timeString) {
    if (!timeString) return '刚刚';
    
    const time = new Date(timeString);
    const now = new Date();
    const diff = Math.floor((now - time) / 1000); // 秒数差
    
    if (diff < 60) {
        return '刚刚';
    } else if (diff < 3600) {
        const minutes = Math.floor(diff / 60);
        return `${minutes}分钟前`;
    } else if (diff < 86400) {
        const hours = Math.floor(diff / 3600);
        return `${hours}小时前`;
    } else if (diff < 604800) {
        const days = Math.floor(diff / 86400);
        return `${days}天前`;
    } else if (diff < 2592000) {
        const weeks = Math.floor(diff / 604800);
        return `${weeks}周前`;
    } else if (diff < 31536000) {
        const months = Math.floor(diff / 2592000);
        return `${months}个月前`;
    } else {
        const years = Math.floor(diff / 31536000);
        return `${years}年前`;
    }
}

// 更新所有相对时间显示
function updateRelativeTimes() {
    document.querySelectorAll('.relative-time').forEach(element => {
        const timeString = element.getAttribute('data-time');
        if (timeString) {
            element.textContent = formatRelativeTime(timeString);
        }
    });
}

window.addEventListener('load', function() {
    // 确保AI头像已更新
    updateAIAvatar();
    // 更新所有相对时间显示
    updateRelativeTimes();
    // 定期更新相对时间（每分钟更新一次）
    setInterval(updateRelativeTimes, 60000);
    scrollToBottom();
    renderAllAIMessages();
    // 初始化代码块按钮
    initCodeBlockButtons();
});

// 监听 DOM 变化，为新添加的代码块初始化按钮
const codeBlockObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        mutation.addedNodes.forEach(function(node) {
            if (node.nodeType === 1) { // Element node
                // 检查新添加的节点是否包含代码块
                if (node.querySelector && node.querySelector('.code-block-card')) {
                    initCodeBlockButtons(node);
                } else if (node.classList && node.classList.contains('code-block-card')) {
                    initCodeBlockButtons(node.parentElement);
                }
            }
        });
    });
});

// 开始观察消息容器的变化
const messagesContainer = document.getElementById('aiMessagesContainer');
if (messagesContainer) {
    codeBlockObserver.observe(messagesContainer, {
        childList: true,
        subtree: true
    });
}

document.querySelectorAll('.ai-character-item[data-character-id]').forEach(item => {
    item.addEventListener('click', function() {
        const characterId = this.getAttribute('data-character-id');
        if (characterId && characterId != currentCharacterId) {
            window.location.href = `chat?character_id=${characterId}`;
        }
    });
});

document.querySelectorAll('.ai-session-item[data-session-id]').forEach(item => {
    item.addEventListener('click', function(e) {
        if (e.target.closest('.ai-session-actions')) {
            return;
        }
        const sessionId = this.getAttribute('data-session-id');
        const characterId = this.getAttribute('data-character-id');
        if (sessionId && sessionId != currentSessionId) {
            window.location.href = `chat?session_id=${sessionId}&character_id=${characterId}`;
        }
    });
});

document.getElementById('aiModelSelect').addEventListener('change', function() {
    currentModelId = this.value;
});

document.getElementById('aiSendButton').addEventListener('click', sendMessage);
document.getElementById('aiMessageInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

document.getElementById('aiStopButton').addEventListener('click', function() {
    if (currentEventSource) {
        // 先关闭 EventSource
        currentEventSource.close();
        isGenerating = false;
        document.getElementById('aiLoadingIndicator').style.display = 'none';
        
        // 延迟一小段时间，确保最后一次的 data-raw-content 更新已经完成
        setTimeout(function() {
            // 如果已经有 message_id，直接标记中断
            if (currentGeneratingMessageId) {
                markMessageAsInterrupted(currentGeneratingMessageId);
            } else {
                // 如果还没有 message_id，尝试找到当前正在生成的AI消息元素
                // 查找最后一个AI消息，且没有 data-message-id 或正在显示思考状态
                const allAIMessages = document.querySelectorAll('.ai-message.ai');
                if (allAIMessages.length > 0) {
                    const lastAIMessage = allAIMessages[allAIMessages.length - 1];
                    const hasTypingDots = lastAIMessage.querySelector('.ai-typing-dots');
                    const hasMessageId = lastAIMessage.getAttribute('data-message-id');
                    
                    // 如果消息正在显示思考状态或没有 message_id，说明可能是刚创建的消息
                    if (hasTypingDots || !hasMessageId) {
                        // 先标记为中断，等收到 message_id 后再更新
                        lastAIMessage.classList.add('interrupted');
                        console.log('消息已标记为中断（临时），等待 message_id');
                        
                        // 刷新消息内容（如果有内容）
                        const messageText = lastAIMessage.querySelector('.ai-message-text');
                        // 优先从 data-raw-content 获取，如果没有则尝试从当前HTML中提取
                        let rawContent = lastAIMessage.getAttribute('data-raw-content');
                        
                        // 如果 data-raw-content 为空，尝试从当前渲染的HTML中提取原始文本
                        if (!rawContent || rawContent.trim() === '') {
                            // 从 textContent 获取（可能不准确，但总比没有好）
                            rawContent = messageText ? messageText.textContent : '';
                        }
                        
                        if (rawContent && rawContent.trim() && messageText) {
                            // 确保保存到 data-raw-content
                            lastAIMessage.setAttribute('data-raw-content', rawContent);
                            // 使用 renderMarkdownRealtime 来处理可能未完成的代码块
                            messageText.innerHTML = renderMarkdownRealtime(rawContent);
                            // 初始化代码块按钮
                            initCodeBlockButtons(lastAIMessage);
                            console.log('已刷新被中断的消息（临时），内容长度:', rawContent.length);
                        }
                        
                        // 监听消息ID的设置，一旦设置就调用 markMessageAsInterrupted
                        const observer = new MutationObserver(function(mutations) {
                            const messageId = lastAIMessage.getAttribute('data-message-id');
                            if (messageId) {
                                currentGeneratingMessageId = messageId;
                                markMessageAsInterrupted(messageId);
                                observer.disconnect();
                            }
                        });
                        observer.observe(lastAIMessage, {
                            attributes: true,
                            attributeFilter: ['data-message-id']
                        });
                    }
                }
            }
        }, 100); // 延迟100ms，确保最后一次更新完成
    }
});

function sendMessage() {
    const aiMessageInput = document.getElementById('aiMessageInput');
    const message = aiMessageInput.value.trim();
    
    if (!message || isGenerating) return;
    
    if (!currentCharacterId) {
        alert('请先选择角色');
        return;
    }

    isGenerating = true;
    const aiLoadingIndicator = document.getElementById('aiLoadingIndicator');
    aiLoadingIndicator.style.display = 'flex';

    const userMessageElement = createUserMessageElement(message);
    document.getElementById('aiMessagesContainer').appendChild(userMessageElement);
    
    const aiMessageElement = createAIMessageElement();
    document.getElementById('aiMessagesContainer').appendChild(aiMessageElement);
    
    aiMessageInput.value = '';
    scrollToBottom();

    currentEventSource = new EventSource(`./api/chatapi.php?${new URLSearchParams({
        message: message,
        character_id: currentCharacterId,
        model_id: currentModelId,
        session_id: currentSessionId
    })}`);

    let aiResponse = '';

    currentEventSource.onmessage = function(event) {
        if (event.data === '[DONE]') {
            currentEventSource.close();
            isGenerating = false;
            aiLoadingIndicator.style.display = 'none';
            currentGeneratingMessageId = null;
            if (aiMessageElement && aiResponse) {
                aiMessageElement.setAttribute('data-raw-content', aiResponse);
                aiMessageElement.querySelector('.ai-message-text').innerHTML = renderMarkdown(aiResponse);
                // 初始化代码块按钮
                initCodeBlockButtons(aiMessageElement);
            }
            updateSessionInfo();
            return;
        }

        try {
            const data = JSON.parse(event.data);
            
            // 处理新会话创建
            if (data.is_new_session && data.session_info) {
                currentSessionId = data.session_id;
                const newUrl = `chat?session_id=${currentSessionId}&character_id=${currentCharacterId}`;
                window.history.replaceState({}, '', newUrl);
                
                // 更新会话列表
                addNewSessionToList(data.session_info);
                
                // 移除当前会话的 active 状态
                document.querySelectorAll('.ai-session-item.active').forEach(item => {
                    item.classList.remove('active');
                });
                
                // 为新会话添加 active 状态
                const newSessionItem = document.querySelector(`.ai-session-item[data-session-id="${currentSessionId}"]`);
                if (newSessionItem) {
                    newSessionItem.classList.add('active');
                }
            } else if (data.session_id && data.session_id != currentSessionId) {
                currentSessionId = data.session_id;
                const newUrl = `chat?session_id=${currentSessionId}&character_id=${currentCharacterId}`;
                window.history.replaceState({}, '', newUrl);
            }
            
            if (data.content) {
                // 如果是第一次收到内容，移除思考状态（三个点）
                if (aiResponse === '') {
                    const messageTextEl = aiMessageElement.querySelector('.ai-message-text');
                    if (messageTextEl && messageTextEl.querySelector('.ai-typing-dots')) {
                        messageTextEl.innerHTML = '';
                    }
                }
                aiResponse += data.content;
                // 实时更新 data-raw-content，确保中断时能获取到完整内容
                aiMessageElement.setAttribute('data-raw-content', aiResponse);
                // 实时解析 Markdown
                aiMessageElement.querySelector('.ai-message-text').innerHTML = renderMarkdownRealtime(aiResponse);
                // 初始化新添加的代码块按钮（MutationObserver 也会处理，但这里确保及时初始化）
                initCodeBlockButtons(aiMessageElement);
                scrollToBottom();
                if (!currentGeneratingMessageId && data.message_id) {
                    currentGeneratingMessageId = data.message_id;
                    aiMessageElement.setAttribute('data-message-id', data.message_id);
                    userMessageElement.setAttribute('data-message-id', data.message_id);
                }
            } else if (data.message_id) {
                currentGeneratingMessageId = data.message_id;
                aiMessageElement.setAttribute('data-message-id', data.message_id);
                userMessageElement.setAttribute('data-message-id', data.message_id);
                // 如果有已生成的内容，也要更新 data-raw-content
                if (aiResponse) {
                    aiMessageElement.setAttribute('data-raw-content', aiResponse);
                }
                if (data.session_id && data.session_id != currentSessionId) {
                    currentSessionId = data.session_id;
                    const newUrl = `chat?session_id=${currentSessionId}&character_id=${currentCharacterId}`;
                    window.history.replaceState({}, '', newUrl);
                }
            }
        } catch (e) {
            console.error('解析错误:', e);
        }
    };

    currentEventSource.onerror = function(event) {
        console.error('EventSource错误:', event);
        currentEventSource.close();
        isGenerating = false;
        aiLoadingIndicator.style.display = 'none';
        currentGeneratingMessageId = null;
        if (!aiResponse && aiMessageElement) {
            const messageTextEl = aiMessageElement.querySelector('.ai-message-text');
            // 移除思考状态
            if (messageTextEl && messageTextEl.querySelector('.ai-typing-dots')) {
                messageTextEl.innerHTML = '';
            }
            messageTextEl.textContent = '抱歉，AI回复生成失败，请重试。';
        }
    };
}

function createUserMessageElement(message, messageOrder = null) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'ai-message user';
    
    if (messageOrder === null) {
        const allMessages = document.querySelectorAll('.ai-message');
        messageOrder = allMessages.length > 0 ? 
            Math.max(...Array.from(allMessages).map(msg => parseInt(msg.getAttribute('data-message-order')) || 0)) + 1 : 1;
    }
    
    messageDiv.setAttribute('data-message-order', messageOrder);
    
    const avatarHtml = userAvatar
        ? `<img src="${userAvatar}" alt="用户头像">`
        : `<i class="fas fa-user"></i>`;
    
    messageDiv.innerHTML = `
        <div class="ai-character-avatar">${avatarHtml}</div>
        <div class="ai-message-content">
            <div class="ai-message-bubble">
                <div class="ai-message-text">${escapeHtml(message)}</div>
            </div>
            <div class="ai-message-time">${new Date().toLocaleTimeString('zh-CN', {hour: '2-digit', minute:'2-digit'})}</div>
            <div class="ai-message-actions">
                <a href="javascript:void(0)" class="ai-message-action-link ai-edit-message-btn">
                    <i class="fas fa-edit"></i> 编辑
                </a>
                <a href="javascript:void(0)" class="ai-message-action-link ai-copy-message-btn">
                    <i class="fas fa-copy"></i> 复制
                </a>
            </div>
        </div>
    `;
    return messageDiv;
}

function createAIMessageElement(messageOrder = null) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'ai-message ai';
    
    if (messageOrder === null) {
        const allMessages = document.querySelectorAll('.ai-message');
        messageOrder = allMessages.length > 0 ? 
            Math.max(...Array.from(allMessages).map(msg => parseInt(msg.getAttribute('data-message-order')) || 0)) + 1 : 1;
    }
    
    messageDiv.setAttribute('data-message-order', messageOrder);
    messageDiv.setAttribute('data-raw-content', '');

    // 确保使用最新的AI头像
    updateAIAvatar();
    const avatarHtml = aiAvatar
        ? `<img src="${aiAvatar}" alt="AI头像" onerror="this.onerror=null; this.src='/static/ai-images/ai.png'; this.outerHTML='<i class=\\'fas fa-robot\\'></i>';">`
        : `<i class="fas fa-robot"></i>`;
    
    messageDiv.innerHTML = `
        <div class="ai-character-avatar">${avatarHtml}</div>
        <div class="ai-message-content">
            <div class="ai-message-bubble">
                <div class="ai-message-text">
                    <div class="ai-typing-dots">
                        <div class="ai-typing-dot"></div>
                        <div class="ai-typing-dot"></div>
                        <div class="ai-typing-dot"></div>
                    </div>
                </div>
            </div>
            <div class="ai-message-time">${new Date().toLocaleTimeString('zh-CN', {hour: '2-digit', minute:'2-digit'})}</div>
            <div class="ai-message-actions">
                <a href="javascript:void(0)" class="ai-message-action-link ai-regenerate-message-btn">
                    <i class="fas fa-redo"></i> 重新生成
                </a>
                <a href="javascript:void(0)" class="ai-message-action-link ai-continue-generate-btn">
                    <i class="fas fa-play"></i> 继续生成
                </a>
                <a href="javascript:void(0)" class="ai-message-action-link ai-copy-message-btn">
                    <i class="fas fa-copy"></i> 复制
                </a>
            </div>
        </div>
    `;
    return messageDiv;
}

function updateSessionInfo() {
    console.log('会话信息已更新');
}

document.addEventListener('click', function(e) {
    if (e.target.closest('.ai-copy-message-btn')) {
        const messageElement = e.target.closest('.ai-message');
        let messageText = '';
        
        if (messageElement.classList.contains('ai')) {
            messageText = messageElement.getAttribute('data-raw-content') || 
                         messageElement.querySelector('.ai-message-text').textContent;
        } else {
            messageText = messageElement.querySelector('.ai-message-text').textContent;
        }
        
        navigator.clipboard.writeText(messageText).then(() => {
            alert('消息已复制到剪贴板');
        });
    }
});

document.addEventListener('click', function(e) {
    const deleteBtn = e.target.closest('.ai-session-delete-btn');
    if (deleteBtn) {
        e.preventDefault();
        e.stopPropagation();
        const sessionId = deleteBtn.getAttribute('data-session-id');
        deleteSession(sessionId);
        return;
    }
    
    const editBtn = e.target.closest('.ai-session-edit-btn');
    if (editBtn) {
        e.preventDefault();
        e.stopPropagation();
        const sessionId = editBtn.getAttribute('data-session-id');
        const sessionItem = editBtn.closest('.ai-session-item');
        const currentTitle = sessionItem.querySelector('.ai-session-title-text').textContent;
        editSessionTitle(sessionId, currentTitle, sessionItem);
        return;
    }
});

function deleteSession(sessionId) {
    if (!confirm('确定要删除这个会话吗？此操作不可恢复。')) {
        return;
    }
    
    const xhr = new XMLHttpRequest();
    const formData = new FormData();
    formData.append('action', 'delete_session');
    formData.append('session_id', sessionId);
    
    xhr.open('POST', './api/chat_session.php', true);
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const data = JSON.parse(xhr.responseText);
                if (data.success) {
                    alert('会话删除成功');
                    const deletedSessionItem = document.querySelector(`.ai-session-item[data-session-id="${sessionId}"]`);
                    if (deletedSessionItem) {
                        deletedSessionItem.remove();
                    }
                    if (parseInt(sessionId, 10) === currentSessionId) {
                        const characterId = data.character_id || currentCharacterId;
                        window.location.href = `chat?character_id=${characterId}`;
                    } else {
                        checkAndShowEmptyState();
                    }
                } else {
                    alert('删除失败: ' + data.message);
                }
            } catch (e) {
                console.error('解析响应失败:', e);
                alert('服务器响应格式错误');
            }
        } else {
            alert('请求失败，状态码: ' + xhr.status);
        }
    };
    
    xhr.onerror = function() {
        console.error('XHR请求失败');
        alert('网络请求失败，请检查API路径和网络连接');
    };
    
    xhr.send(formData);
}

function checkAndShowEmptyState() {
    const sessionList = document.querySelector('.ai-session-list');
    const sessionItems = Array.from(sessionList.querySelectorAll('.ai-session-item'));
    const hasValidSessions = sessionItems.some(item => item.getAttribute('data-session-id'));
    
    if (!hasValidSessions) {
        const emptyState = document.createElement('div');
        emptyState.className = 'ai-session-item ai-session-item--empty';
        emptyState.innerHTML = '<div>暂无历史对话</div>';

        sessionItems.forEach(item => item.remove());
        sessionList.appendChild(emptyState);
    }
}

function editSessionTitle(sessionId, currentTitle, sessionItem) {
    const newTitle = prompt('请输入新的会话标题(最多10个字符):', currentTitle);
    if (newTitle !== null && newTitle.trim() !== '') {
        if (newTitle.trim().length > 10) {
            alert('会话标题不能超过10个字符！');
            return;
        }
        
        const xhr = new XMLHttpRequest();
        const formData = new FormData();
        formData.append('action', 'update_title');
        formData.append('session_id', sessionId);
        formData.append('new_title', newTitle.trim());
        
        xhr.open('POST', './api/chat_session.php', true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        sessionItem.querySelector('.ai-session-title-text').textContent = newTitle.trim();
                        alert('标题修改成功');
                    } else {
                        alert('修改失败: ' + data.message);
                    }
                } catch (e) {
                    console.error('解析响应失败:', e);
                    alert('服务器响应格式错误');
                }
            } else {
                alert('请求失败，状态码: ' + xhr.status);
            }
        };
        
        xhr.onerror = function() {
            console.error('XHR请求失败');
            alert('网络请求失败，请检查API路径和网络连接');
        };
        
        xhr.send(formData);
    }
}

document.addEventListener('click', function(e) {
    if (e.target.closest('.ai-edit-message-btn')) {
        const messageElement = e.target.closest('.ai-message');
        const messageId = messageElement.getAttribute('data-message-id');
        let messageText = '';
        
        if (messageElement.classList.contains('ai')) {
            messageText = messageElement.getAttribute('data-raw-content') || 
                         messageElement.querySelector('.ai-message-text').textContent;
        } else {
            messageText = messageElement.querySelector('.ai-message-text').textContent;
        }
        
        const editContainer = document.createElement('div');
        editContainer.className = 'ai-message-edit-container';
        
        editContainer.innerHTML = `
            <textarea class="ai-edit-message-textarea" placeholder="编辑您的消息...">${messageText}</textarea>
            <div class="ai-edit-actions">
                <div class="ai-edit-character-count">${messageText.length} 字符</div>
                <button class="ai-cancel-edit-btn">
                    <i class="fas fa-times"></i> 取消
                </button>
                <button class="ai-save-edit-btn">
                    <i class="fas fa-check"></i> 保存
                </button>
            </div>
        `;
        
        const messageBubble = messageElement.querySelector('.ai-message-bubble');
        const originalContent = messageBubble.innerHTML;
        messageBubble.innerHTML = '';
        messageBubble.appendChild(editContainer);
        
        const textarea = editContainer.querySelector('.ai-edit-message-textarea');
        const charCountElement = editContainer.querySelector('.ai-edit-character-count');
        
        function updateCharCount(text) {
            charCountElement.textContent = `${text.length} 字符`;
            charCountElement.classList.toggle('warning', text.length > 1000);
        }

        updateCharCount(messageText);
        textarea.focus();
        textarea.select();
        
        textarea.addEventListener('input', function() {
            updateCharCount(this.value);
        });
        
        editContainer.querySelector('.ai-save-edit-btn').addEventListener('click', function() {
            const newMessage = textarea.value.trim();
            if (newMessage) {
                if (newMessage.length > 2000) {
                    alert('消息长度不能超过2000个字符');
                    return;
                }
                updateMessage(messageId, newMessage, messageElement);
            } else {
                alert('消息不能为空');
            }
        });
        
        editContainer.querySelector('.ai-cancel-edit-btn').addEventListener('click', function() {
            messageBubble.innerHTML = originalContent;
        });
        
        textarea.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                messageBubble.innerHTML = originalContent;
            }
        });
    }
});

function updateMessage(messageId, newMessage, messageElement) {
    const formData = new FormData();
    formData.append('action', 'update_message');
    formData.append('message_id', messageId);
    formData.append('new_message', newMessage);
    
    fetch('./api/chat_session.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const messageBubble = messageElement.querySelector('.ai-message-bubble');
            if (messageElement.classList.contains('ai')) {
                messageElement.setAttribute('data-raw-content', newMessage);
                messageBubble.innerHTML = `<div class="ai-message-text">${renderMarkdown(newMessage)}</div>`;
            } else {
                messageBubble.innerHTML = `<div class="ai-message-text">${escapeHtml(newMessage)}</div>`;
            }
            
            currentSessionId = data.session_id;
            currentCharacterId = data.character_id;
            currentModelId = data.model_id;
            
            removeMessagesAfter(messageId);
            
            const aiMessageElement = document.querySelector(`.ai-message.ai[data-message-id="${messageId}"]`);
            if (aiMessageElement) {
                aiMessageElement.querySelector('.ai-message-text').innerHTML = `
                    <div class="ai-typing-dots">
                        <div class="ai-typing-dot"></div>
                        <div class="ai-typing-dot"></div>
                        <div class="ai-typing-dot"></div>
                    </div>
                `;
                aiMessageElement.classList.remove('interrupted');
                aiMessageElement.setAttribute('data-raw-content', '');
                regenerateMessageAfterEdit(messageId, newMessage, aiMessageElement);
            } else {
                const currentOrder = parseInt(messageElement.getAttribute('data-message-order'), 10);
                const newAiMessageElement = createAIMessageElement(currentOrder + 1);
                messageElement.parentNode.insertBefore(newAiMessageElement, messageElement.nextSibling);
                regenerateMessageAfterEdit(messageId, newMessage, newAiMessageElement);
            }
        } else {
            alert('更新失败: ' + data.message);
        }
    })
    .catch(error => {
        console.error('更新消息错误:', error);
        alert('更新失败: ' + error.message);
    });
}

function regenerateMessageAfterEdit(messageId, userMessage, aiMessageElement) {
    if (isGenerating) {
        alert('请等待当前生成完成');
        return;
    }
    
    isGenerating = true;
    const aiLoadingIndicator = document.getElementById('aiLoadingIndicator');
    aiLoadingIndicator.style.display = 'flex';

    scrollToBottom();

    currentEventSource = new EventSource(`./api/chatapi.php?${new URLSearchParams({
        message: userMessage,
        character_id: currentCharacterId,
        model_id: currentModelId,
        session_id: currentSessionId,
        regenerate_message_id: messageId
    })}`);

    let aiResponse = '';

    currentEventSource.onmessage = function(event) {
        if (event.data === '[DONE]') {
            currentEventSource.close();
            isGenerating = false;
            aiLoadingIndicator.style.display = 'none';
            currentGeneratingMessageId = null;
            if (aiMessageElement && aiResponse) {
                aiMessageElement.setAttribute('data-raw-content', aiResponse);
                aiMessageElement.querySelector('.ai-message-text').innerHTML = renderMarkdown(aiResponse);
                // 初始化代码块按钮
                initCodeBlockButtons(aiMessageElement);
            }
            updateSessionInfo();
            return;
        }

        try {
            const data = JSON.parse(event.data);
            if (data.content) {
                // 如果是第一次收到内容，移除思考状态（三个点）
                if (aiResponse === '') {
                    const messageTextEl = aiMessageElement.querySelector('.ai-message-text');
                    if (messageTextEl && messageTextEl.querySelector('.ai-typing-dots')) {
                        messageTextEl.innerHTML = '';
                    }
                }
                aiResponse += data.content;
                // 实时更新 data-raw-content，确保中断时能获取到完整内容
                aiMessageElement.setAttribute('data-raw-content', aiResponse);
                // 实时解析 Markdown
                aiMessageElement.querySelector('.ai-message-text').innerHTML = renderMarkdownRealtime(aiResponse);
                // 初始化新添加的代码块按钮
                initCodeBlockButtons(aiMessageElement);
                scrollToBottom();
                if (!currentGeneratingMessageId && data.message_id) {
                    currentGeneratingMessageId = data.message_id;
                    aiMessageElement.setAttribute('data-message-id', data.message_id);
                }
            }
        } catch (e) {
            console.error('解析错误:', e);
        }
    };

    currentEventSource.onerror = function(event) {
        console.error('EventSource错误:', event);
        currentEventSource.close();
        isGenerating = false;
        aiLoadingIndicator.style.display = 'none';
        currentGeneratingMessageId = null;
        if (!aiResponse && aiMessageElement) {
            const messageTextEl = aiMessageElement.querySelector('.ai-message-text');
            // 移除思考状态
            if (messageTextEl && messageTextEl.querySelector('.ai-typing-dots')) {
                messageTextEl.innerHTML = '';
            }
            messageTextEl.textContent = '抱歉，AI回复生成失败，请重试。';
        }
    };
}
    
function removeMessagesAfter(messageId) {
    const messageElement = document.querySelector(`.ai-message[data-message-id="${messageId}"]`);
    if (!messageElement) {
        return;
    }
    
    const messageOrder = parseInt(messageElement.getAttribute('data-message-order'), 10);
    const allMessages = Array.from(document.querySelectorAll('.ai-message')).sort((a, b) => {
        return parseInt(a.getAttribute('data-message-order'), 10) - parseInt(b.getAttribute('data-message-order'), 10);
    });
    
    const startIndex = allMessages.findIndex(msg => 
        parseInt(msg.getAttribute('data-message-order'), 10) === messageOrder
    );
    
    if (startIndex === -1) {
        return;
    }
    
    for (let i = startIndex + 2; i < allMessages.length; i++) {
        allMessages[i].remove();
    }
    
    checkAndShowMessagesEmptyState();
}

function checkAndShowMessagesEmptyState() {
    const aiMessagesContainer = document.getElementById('aiMessagesContainer');
    const existingMessages = aiMessagesContainer.querySelectorAll('.ai-message');
    const existingEmptyState = aiMessagesContainer.querySelector('.ai-empty-state');
    
    if (existingMessages.length === 0 && !existingEmptyState) {
        const emptyState = document.createElement('div');
        emptyState.className = 'ai-empty-state';
        emptyState.innerHTML = `
            <i class="fas fa-comments"></i>
            <h3>开始与 AI 对话</h3>
            <p>输入消息开始聊天，AI将根据角色设定回复您</p>
        `;
        aiMessagesContainer.appendChild(emptyState);
    } else if (existingMessages.length > 0 && existingEmptyState) {
        existingEmptyState.remove();
    }
}

document.addEventListener('click', function(e) {
    if (e.target.closest('.ai-regenerate-message-btn')) {
        const messageElement = e.target.closest('.ai-message');
        const messageId = messageElement.getAttribute('data-message-id');
        regenerateMessage(messageId);
    }
});

function regenerateMessage(messageId) {
    if (isGenerating) {
        alert('请等待当前生成完成');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'regenerate');
    formData.append('message_id', messageId);
    
    fetch('./api/chat_session.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentSessionId = data.session_id;
            currentCharacterId = data.character_id;
            currentModelId = data.model_id;
            removeMessagesAfter(data.message_id);
            
            const userMessageElement = document.querySelector(`.ai-message.user[data-message-id="${data.message_id}"]`);
            if (userMessageElement) {
                const userMessage = userMessageElement.querySelector('.ai-message-text').textContent;
                resendMessageForRegeneration(userMessage, data.message_id);
            } else {
                alert('找不到对应的用户消息');
            }
        } else {
            alert('重新生成失败: ' + data.message);
        }
    })
    .catch(error => {
        console.error('重新生成错误:', error);
        alert('重新生成失败: ' + error.message);
    });
}

function continueMessageGeneration(message, messageId, existingAiResponse) {
    if (isGenerating) return;
    
    isGenerating = true;
    const aiLoadingIndicator = document.getElementById('aiLoadingIndicator');
    aiLoadingIndicator.style.display = 'flex';

    const aiMessageElement = document.querySelector(`.ai-message.ai[data-message-id="${messageId}"]`);
    if (aiMessageElement) {
        // 保留现有的AI回复内容，不清空
        aiMessageElement.classList.remove('interrupted');
        // 如果现有内容为空，显示思考状态
        if (!existingAiResponse || existingAiResponse.trim() === '') {
            aiMessageElement.querySelector('.ai-message-text').innerHTML = `
                <div class="ai-typing-dots">
                    <div class="ai-typing-dot"></div>
                    <div class="ai-typing-dot"></div>
                    <div class="ai-typing-dot"></div>
                </div>
            `;
        }
    }

    scrollToBottom();

    // 传递 continue_message_id 参数，而不是 regenerate_message_id
    currentEventSource = new EventSource(`./api/chatapi.php?${new URLSearchParams({
        message: message,
        character_id: currentCharacterId,
        model_id: currentModelId,
        session_id: currentSessionId,
        continue_message_id: messageId
    })}`);

    // 从现有回复开始，而不是从空开始
    let aiResponse = existingAiResponse || '';

    currentEventSource.onmessage = function(event) {
        if (event.data === '[DONE]') {
            currentEventSource.close();
            isGenerating = false;
            aiLoadingIndicator.style.display = 'none';
            currentGeneratingMessageId = null;
            if (aiMessageElement && aiResponse) {
                aiMessageElement.setAttribute('data-raw-content', aiResponse);
                aiMessageElement.querySelector('.ai-message-text').innerHTML = renderMarkdown(aiResponse);
                // 初始化代码块按钮
                initCodeBlockButtons(aiMessageElement);
            }
            updateSessionInfo();
            return;
        }

        try {
            const data = JSON.parse(event.data);
            if (data.content) {
                // 追加新内容到现有回复
                aiResponse += data.content;
                if (aiMessageElement) {
                    // 实时更新 data-raw-content，确保中断时能获取到完整内容
                    aiMessageElement.setAttribute('data-raw-content', aiResponse);
                    // 移除思考状态（如果有）
                    const messageTextEl = aiMessageElement.querySelector('.ai-message-text');
                    if (messageTextEl && messageTextEl.querySelector('.ai-typing-dots')) {
                        messageTextEl.innerHTML = '';
                    }
                    // 实时解析 Markdown
                    messageTextEl.innerHTML = renderMarkdownRealtime(aiResponse);
                    // 初始化新添加的代码块按钮
                    initCodeBlockButtons(aiMessageElement);
                }
                scrollToBottom();
                if (!currentGeneratingMessageId && aiMessageElement) {
                    currentGeneratingMessageId = aiMessageElement.getAttribute('data-message-id');
                }
            }
        } catch (e) {
            console.error('解析错误:', e);
        }
    };

    currentEventSource.onerror = function(event) {
        console.error('EventSource错误:', event);
        currentEventSource.close();
        isGenerating = false;
        aiLoadingIndicator.style.display = 'none';
        currentGeneratingMessageId = null;
        if (!aiResponse && aiMessageElement) {
            const messageTextEl = aiMessageElement.querySelector('.ai-message-text');
            // 移除思考状态
            if (messageTextEl && messageTextEl.querySelector('.ai-typing-dots')) {
                messageTextEl.innerHTML = '';
            }
            // 如果继续生成失败，保留原有内容
            if (existingAiResponse) {
                messageTextEl.innerHTML = renderMarkdown(existingAiResponse);
            } else {
                messageTextEl.textContent = '抱歉，AI回复生成失败，请重试。';
            }
        }
    };
}

function resendMessageForRegeneration(message, messageId) {
    if (isGenerating) return;
    
    isGenerating = true;
    const aiLoadingIndicator = document.getElementById('aiLoadingIndicator');
    aiLoadingIndicator.style.display = 'flex';

    const aiMessageElement = document.querySelector(`.ai-message.ai[data-message-id="${messageId}"]`);
    if (aiMessageElement) {
        aiMessageElement.querySelector('.ai-message-text').innerHTML = `
            <div class="ai-typing-dots">
                <div class="ai-typing-dot"></div>
                <div class="ai-typing-dot"></div>
                <div class="ai-typing-dot"></div>
            </div>
        `;
        aiMessageElement.classList.remove('interrupted');
        aiMessageElement.setAttribute('data-raw-content', '');
    }

    scrollToBottom();

    currentEventSource = new EventSource(`./api/chatapi.php?${new URLSearchParams({
        message: message,
        character_id: currentCharacterId,
        model_id: currentModelId,
        session_id: currentSessionId,
        regenerate_message_id: messageId
    })}`);

    let aiResponse = '';

    currentEventSource.onmessage = function(event) {
        if (event.data === '[DONE]') {
            currentEventSource.close();
            isGenerating = false;
            aiLoadingIndicator.style.display = 'none';
            currentGeneratingMessageId = null;
            if (aiMessageElement && aiResponse) {
                aiMessageElement.setAttribute('data-raw-content', aiResponse);
                aiMessageElement.querySelector('.ai-message-text').innerHTML = renderMarkdown(aiResponse);
                // 初始化代码块按钮
                initCodeBlockButtons(aiMessageElement);
            }
            updateSessionInfo();
            return;
        }

        try {
            const data = JSON.parse(event.data);
            if (data.content) {
                // 如果是第一次收到内容，移除思考状态（三个点）
                if (aiResponse === '' && aiMessageElement) {
                    const messageTextEl = aiMessageElement.querySelector('.ai-message-text');
                    if (messageTextEl && messageTextEl.querySelector('.ai-typing-dots')) {
                        messageTextEl.innerHTML = '';
                    }
                }
                aiResponse += data.content;
                if (aiMessageElement) {
                    // 实时更新 data-raw-content，确保中断时能获取到完整内容
                    aiMessageElement.setAttribute('data-raw-content', aiResponse);
                    // 实时解析 Markdown
                    aiMessageElement.querySelector('.ai-message-text').innerHTML = renderMarkdownRealtime(aiResponse);
                    // 初始化新添加的代码块按钮
                    initCodeBlockButtons(aiMessageElement);
                }
                scrollToBottom();
                if (!currentGeneratingMessageId && aiMessageElement) {
                    currentGeneratingMessageId = aiMessageElement.getAttribute('data-message-id');
                }
            }
        } catch (e) {
            console.error('解析错误:', e);
        }
    };

    currentEventSource.onerror = function(event) {
        console.error('EventSource错误:', event);
        currentEventSource.close();
        isGenerating = false;
        aiLoadingIndicator.style.display = 'none';
        currentGeneratingMessageId = null;
        if (!aiResponse && aiMessageElement) {
            const messageTextEl = aiMessageElement.querySelector('.ai-message-text');
            // 移除思考状态
            if (messageTextEl && messageTextEl.querySelector('.ai-typing-dots')) {
                messageTextEl.innerHTML = '';
            }
            messageTextEl.textContent = '抱歉，AI回复生成失败，请重试。';
        }
    };
}

document.addEventListener('click', function(e) {
    if (e.target.closest('.ai-continue-generate-btn')) {
        const messageElement = e.target.closest('.ai-message');
        const messageId = messageElement.getAttribute('data-message-id');
        continueGeneration(messageId);
    }
});

function continueGeneration(messageId) {
    const formData = new FormData();
    formData.append('action', 'continue_generation');
    formData.append('message_id', messageId);
    
    fetch('./api/chat_session.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentSessionId = data.session_id;
            currentCharacterId = data.character_id;
            currentModelId = data.model_id;
            // 删除后续消息（与重新生成保持一致）
            removeMessagesAfter(data.message_id);
            triggerContinueGeneration(data.message_id);
        } else {
            alert('继续生成失败: ' + data.message);
        }
    })
    .catch(error => {
        console.error('继续生成错误:', error);
        alert('继续生成失败: ' + error.message);
    });
}

function triggerContinueGeneration(messageId) {
    const aiMessageElement = document.querySelector(`.ai-message.ai[data-message-id="${messageId}"]`);
    if (!aiMessageElement) {
        alert('找不到对应的AI消息');
        return;
    }
    
    const userMessageElement = document.querySelector(`.ai-message.user[data-message-id="${messageId}"]`);
    if (!userMessageElement) {
        alert('找不到对应的用户消息');
        return;
    }
    
    // 获取现有的AI回复内容（继续生成的基础）
    const existingAiResponse = aiMessageElement.getAttribute('data-raw-content') || 
                               aiMessageElement.querySelector('.ai-message-text').textContent || '';
    
    const userMessage = userMessageElement.querySelector('.ai-message-text').textContent;
    
    // 调用继续生成函数，而不是重新生成
    continueMessageGeneration(userMessage, messageId, existingAiResponse);
}

function markMessageAsInterrupted(messageId) {
    const formData = new FormData();
    formData.append('action', 'stop_generation');
    formData.append('message_id', messageId);
    
    fetch('./api/chat_session.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 明确查找AI消息，避免匹配到用户消息
            const messageElement = document.querySelector(`.ai-message.ai[data-message-id="${messageId}"]`);
            if (messageElement) {
                messageElement.classList.add('interrupted');
                console.log('消息已标记为中断，继续生成按钮应该显示');
                
                // 从后端响应中获取最新的消息内容，确保获取到所有已保存的内容
                if (data.ai_response !== undefined) {
                    const rawContent = data.ai_response;
                    const messageText = messageElement.querySelector('.ai-message-text');
                    
                    if (rawContent && rawContent.trim() && messageText) {
                        // 更新 data-raw-content
                        messageElement.setAttribute('data-raw-content', rawContent);
                        // 使用 renderMarkdownRealtime 来处理可能未完成的代码块
                        messageText.innerHTML = renderMarkdownRealtime(rawContent);
                        // 初始化代码块按钮
                        initCodeBlockButtons(messageElement);
                        console.log('已从后端刷新被中断的消息，内容长度:', rawContent.length);
                    } else {
                        // 如果后端没有返回内容，使用本地内容
                        refreshInterruptedMessage(messageElement);
                    }
                } else {
                    // 如果后端没有返回内容，使用本地内容
                    refreshInterruptedMessage(messageElement);
                }
            } else {
                console.error('找不到对应的AI消息:', messageId);
            }
            currentGeneratingMessageId = null;
        }
    })
    .catch(error => {
        console.error('标记中断错误:', error);
    });
}

function refreshInterruptedMessage(messageElement) {
    const messageText = messageElement.querySelector('.ai-message-text');
    // 优先从 data-raw-content 获取，如果没有则尝试从当前HTML中提取
    let rawContent = messageElement.getAttribute('data-raw-content');
    
    // 如果 data-raw-content 为空，尝试从当前渲染的HTML中提取原始文本
    if (!rawContent || rawContent.trim() === '') {
        // 从 textContent 获取（可能不准确，但总比没有好）
        rawContent = messageText ? messageText.textContent : '';
    }
    
    if (rawContent && rawContent.trim() && messageText) {
        // 确保保存到 data-raw-content
        messageElement.setAttribute('data-raw-content', rawContent);
        // 使用 renderMarkdownRealtime 来处理可能未完成的代码块
        messageText.innerHTML = renderMarkdownRealtime(rawContent);
        // 初始化代码块按钮
        initCodeBlockButtons(messageElement);
        console.log('已刷新被中断的消息（本地），内容长度:', rawContent.length);
    } else {
        console.warn('无法获取消息内容进行刷新');
    }
}

document.getElementById('aiNewChatBtn').addEventListener('click', function() {
    startNewChat();
});

function startNewChat() {
    if (!currentCharacterId) {
        alert('请先选择角色');
        return;
    }
    
    currentSessionId = 0;
    
    const aiMessagesContainer = document.getElementById('aiMessagesContainer');
    aiMessagesContainer.innerHTML = '';
    
    const emptyState = document.createElement('div');
    emptyState.className = 'ai-empty-state';
    emptyState.innerHTML = `
        <i class="fas fa-comments"></i>
        <h3>开始与 <?php echo htmlspecialchars($current_character['name'] ?? 'AI'); ?> 对话</h3>
        <p>输入消息开始聊天，AI将根据角色设定回复您</p>
    `;
    aiMessagesContainer.appendChild(emptyState);
    
    const newUrl = `chat?character_id=${currentCharacterId}`;
    window.history.replaceState({}, '', newUrl);
    
    document.querySelectorAll('.ai-session-item.active').forEach(item => {
        item.classList.remove('active');
    });
}

function addNewSessionToList(sessionData) {
    const sessionList = document.querySelector('.ai-session-list');
    if (!sessionList) {
        return;
    }
    
    // 检查会话是否已存在
    const existingItem = sessionList.querySelector(`.ai-session-item[data-session-id="${sessionData.id}"]`);
    if (existingItem) {
        // 如果已存在，只更新 active 状态
        existingItem.classList.add('active');
        return;
    }
    
    const emptyState = sessionList.querySelector('.ai-session-item--empty');
    if (emptyState) {
        emptyState.remove();
    }
    
    const messageCount = sessionData.message_count || 0;
    const lastActiveTime = sessionData.last_active || new Date().toISOString();
    
    const newSessionItem = document.createElement('div');
    newSessionItem.className = 'ai-session-item active';
    newSessionItem.setAttribute('data-session-id', sessionData.id);
    newSessionItem.setAttribute('data-character-id', sessionData.character_id);
    
    newSessionItem.innerHTML = `
        <div class="ai-session-header">
            <div class="ai-session-title-container">
                <div class="ai-session-title-text">${escapeHtml(sessionData.session_title || '新对话')}</div>
                <div class="ai-session-actions">
                    <button class="ai-session-edit-btn" data-session-id="${sessionData.id}" title="编辑标题">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="ai-session-delete-btn" data-session-id="${sessionData.id}" title="删除会话">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="ai-session-meta">
                <span>${messageCount} 条消息 • <span class="relative-time" data-time="${lastActiveTime}">${formatRelativeTime(lastActiveTime)}</span></span>
            </div>
        </div>
    `;
    
    sessionList.insertBefore(newSessionItem, sessionList.firstChild);
    
    newSessionItem.addEventListener('click', function(e) {
        if (e.target.closest('.ai-session-actions')) {
            return;
        }
        const sessionId = this.getAttribute('data-session-id');
        const characterId = this.getAttribute('data-character-id');
        if (sessionId && sessionId != currentSessionId) {
            window.location.href = `chat?session_id=${sessionId}&character_id=${characterId}`;
        }
    });
}
</script>

<?php
$content = ob_get_clean();
$contentClass = 'chat-layout';
include 'navbar.php';
?>

