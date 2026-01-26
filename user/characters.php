<?php
/* 
作者：殒狐FOX
文件创建时间：2025-10-7
最后编辑时间：2025-11-15
文件描述：AI角色广场页面

*/
ob_start();
require_once(__DIR__ . '/../config/config.php');

$categories = [];
$characters = [];
$search_query = trim($_GET['search'] ?? '');
$category_id = $_GET['category'] ?? '';
$sort_by = $_GET['sort'] ?? 'popular';
$settings = [];

try {
    $stmt = $db->query("SELECT * FROM web_setting ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("SELECT * FROM character_category WHERE status = 1 ORDER BY sort_order ASC");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $where_conditions = ["ac.status = 1", "ac.is_public = 1"];
    $params = [];

    if ($search_query !== '') {
        $where_conditions[] = "(ac.name LIKE ? OR ac.introduction LIKE ?)";
        $search_term = "%{$search_query}%";
        $params[] = $search_term;
        $params[] = $search_term;
    }

    if (!empty($category_id) && is_numeric($category_id)) {
        $where_conditions[] = "ac.category_id = ?";
        $params[] = $category_id;
    }

    $order_sql = "ac.usage_count DESC, ac.avg_rating DESC";
    switch ($sort_by) {
        case 'rating':
            $order_sql = "ac.avg_rating DESC, ac.usage_count DESC";
            break;
        case 'newest':
            $order_sql = "ac.create_time DESC";
            break;
        case 'name':
            $order_sql = "ac.name ASC";
            break;
    }

    $where_sql = implode(" AND ", $where_conditions);

    $stmt = $db->prepare("
        SELECT ac.*, cc.name AS category_name
        FROM ai_character ac
        LEFT JOIN character_category cc ON ac.category_id = cc.id
        WHERE {$where_sql}
        ORDER BY {$order_sql}
    ");
    $stmt->execute($params);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("数据库查询错误: " . $e->getMessage());
}

$total_characters = count($characters);
$categories_count = count($categories);
$active_category_name = null;
if ($category_id && is_numeric($category_id)) {
    foreach ($categories as $category) {
        if ((string)$category['id'] === (string)$category_id) {
            $active_category_name = $category['name'];
            break;
        }
    }
}
?>

<section class="section-card hero-card">
    <div>
        <h1 class="hero-card__title">
            <i class="fas fa-robot"></i>
            角色广场
        </h1>
        <p class="hero-card__description">
            探索各种精心设计的 AI 角色，按需求筛选并与他们开启一段奇妙的对话旅程。
        </p>
        <div class="hero-card__meta">
            <span class="stat-pill">
                <i class="fas fa-layer-group"></i>
                <?php echo $categories_count; ?> 个分类
            </span>
            <span class="stat-pill">
                <i class="fas fa-users"></i>
                <?php echo $total_characters; ?> 个角色
            </span>
            <?php if ($active_category_name): ?>
                <span class="stat-pill">
                    <i class="fas fa-tag"></i>
                    当前分类：<?php echo htmlspecialchars($active_category_name); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section-card">
    <div class="section-card__header">
        <div>
            <h2 class="section-card__title">筛选与排序</h2>
            <p class="section-card__subtitle">按名称、分类或排序方式快速定位合适的角色</p>
        </div>
        <?php if ($search_query !== '' || $category_id !== '' || $sort_by !== 'popular'): ?>
            <form method="GET">
                <button type="submit" class="button button--ghost">重置筛选</button>
            </form>
        <?php endif; ?>
    </div>

    <form method="GET" class="search-panel" id="filtersForm">
        <div class="search-panel__bar">
            <input
                type="text"
                name="search"
                class="search-panel__input"
                placeholder="搜索角色名称或简介..."
                value="<?php echo htmlspecialchars($search_query); ?>"
                aria-label="搜索角色"
            >

            <select name="sort" class="search-panel__input" aria-label="排序方式">
                <option value="popular" <?php echo $sort_by === 'popular' ? 'selected' : ''; ?>>最受欢迎</option>
                <option value="rating" <?php echo $sort_by === 'rating' ? 'selected' : ''; ?>>最高评分</option>
                <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>最新创建</option>
                <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>名称排序</option>
            </select>

            <button type="submit" class="button button--primary">
                <i class="fas fa-filter"></i>
                应用筛选
            </button>
        </div>

        <input type="hidden" name="category" id="categoryInput" value="<?php echo htmlspecialchars($category_id); ?>">

        <div class="filter-chips" role="group" aria-label="角色分类筛选">
            <button type="button" class="filter-chip <?php echo $category_id === '' ? 'is-active' : ''; ?>" data-category="">
                全部分类
            </button>
            <?php foreach ($categories as $category): ?>
                <button
                    type="button"
                    class="filter-chip <?php echo ((string)$category_id === (string)$category['id']) ? 'is-active' : ''; ?>"
                    data-category="<?php echo htmlspecialchars($category['id']); ?>"
                >
                    <?php echo htmlspecialchars($category['name']); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </form>
</section>

<section class="section-card">
    <div class="section-card__header">
        <h2 class="section-card__title">角色列表</h2>
        <span class="section-meta">
            找到 <?php echo $total_characters; ?> 个角色
            <?php if ($search_query !== ''): ?>
                ，搜索词 “<?php echo htmlspecialchars($search_query); ?>”
            <?php endif; ?>
        </span>
    </div>

    <?php if (!empty($characters)): ?>
        <div class="card-grid">
            <?php foreach ($characters as $character): ?>
                <article class="character-card" onclick="startChat(<?php echo $character['id']; ?>)">
                    <div class="character-card__cover">
                        <?php if (!empty($character['avatar'])): ?>
                            <img
                                src="<?php echo htmlspecialchars($character['avatar']); ?>"
                                alt="<?php echo htmlspecialchars($character['name']); ?>"
                                onerror="this.src='/static/ai-images/ai.png'"
                            >
                        <?php else: ?>
                            <div class="character-card__placeholder">
                                <i class="fas fa-robot"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="character-card__body">
                        <h3 class="character-card__name"><?php echo htmlspecialchars($character['name']); ?></h3>
                        <?php if (!empty($character['category_name'])): ?>
                            <span class="character-card__badge"><?php echo htmlspecialchars($character['category_name']); ?></span>
                        <?php endif; ?>
                        <p class="character-card__intro">
                            <?php echo htmlspecialchars($character['introduction'] ?? '暂无介绍'); ?>
                        </p>
                        <div class="character-card__stats">
                            <span class="character-card__stat">
                                <i class="fas fa-star"></i>
                                <?php echo number_format((float)$character['avg_rating'], 1); ?>
                            </span>
                            <span class="character-card__stat">
                                <i class="fas fa-comments"></i>
                                <?php echo (int)$character['usage_count']; ?> 对话
                            </span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state__icon"><i class="fas fa-search"></i></div>
            <div class="empty-state__title">没有找到相关角色</div>
            <p class="empty-state__description">请尝试更换搜索关键词或切换其他分类。</p>
            <a href="characters" class="button button--primary">
                <i class="fas fa-robot"></i>
                查看全部角色
            </a>
        </div>
    <?php endif; ?>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('filtersForm');
        const categoryInput = document.getElementById('categoryInput');
        const categoryChips = form.querySelectorAll('.filter-chip');
        const sortSelect = form.querySelector('select[name="sort"]');
        const searchInput = form.querySelector('input[name="search"]');

        function submitForm() {
            form.submit();
        }

        categoryChips.forEach(chip => {
            chip.addEventListener('click', () => {
                const value = chip.getAttribute('data-category') || '';
                categoryInput.value = value;
                submitForm();
            });
        });

        if (sortSelect) {
            sortSelect.addEventListener('change', submitForm);
        }

        if (searchInput) {
            searchInput.addEventListener('keypress', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    submitForm();
                }
            });
        }
    });

    function startChat(characterId) {
        window.location.href = `Introduction?character_id=${characterId}`;
    }
</script>

<?php
$content = ob_get_clean();
include 'navbar.php';
?>

