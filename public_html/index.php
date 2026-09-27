<?php
// Página principal del CMS (Visitor View - no admin links)
include_once __DIR__ . '/helpers/session.php';
m4_session_start();
include_once 'config.php';
include_once 'db/functions.php';
include_once 'helpers/theme.php';
include_once 'helpers/i18n.php';
include_once 'helpers/content.php';
include_once 'helpers/icons.php';
include_once 'helpers/widgets.php';

createTable();

$settings = getAllSettings();
$siteTitle = $settings['site_title'] ?? 'M4 CMS';
$siteTagline = $settings['site_tagline'] ?? 'Blog personal con PHP y SQLite';

// Buscador y filtros de categoria / tag
$searchQuery = trim($_GET['q'] ?? '');
$categoryFilter = trim($_GET['cat'] ?? '');
$tagFilter = trim($_GET['tag'] ?? '');

// Paginacion: 10 posts por pagina
$perPage = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

if (!empty($searchQuery)) {
    $posts = searchPosts($searchQuery);
    $totalPosts = count($posts);
} elseif (!empty($categoryFilter)) {
    $posts = getPublishedPostsByCategoryPaginated($categoryFilter, $perPage, $offset);
    $totalPosts = countPublishedPostsByCategory($categoryFilter);
} elseif (!empty($tagFilter)) {
    $posts = getPublishedPostsByTagPaginated($tagFilter, $perPage, $offset);
    $totalPosts = countPublishedPostsByTag($tagFilter);
} else {
    $posts = getPublishedPostsPaginated($perPage, $offset);
    $totalPosts = countPublishedPosts();
}
$totalPages = max(1, (int)ceil($totalPosts / $perPage));

// Categorias para el filtro
$categories = getUsedCategories();

// Theme: FinSec claro/oscuro — el admin persiste en DB; un visitante solo en sesión
if (isset($_POST['site_theme']) && !empty($_POST['site_theme'])) {
    $theme = normalizeTheme($_POST['site_theme']);
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
        saveSetting('site_theme', $theme);
    }
    $_SESSION['theme'] = $theme;
} elseif (isset($_SESSION['theme'])) {
    $theme = normalizeTheme($_SESSION['theme']);
} else {
    $theme = getSiteTheme();
}
$themeToggle = ($theme === 'finsec-dark') ? 'finsec' : 'finsec-dark';

$class = themeClass($theme);
$isLogged = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];
$lang = $_SESSION['lang'] ?? 'es';
list($_msg_admin_panel, )      = t('nav_admin_panel');
list($_msg_posts, )            = t('nav_posts');
list($_msg_settings, )         = t('nav_settings');
list($_msg_logout, )          = t('nav_logout');
list($_recent_posts, )    = t('page_recent_posts');
list($_no_posts, )        = t('no_posts_yet');
list($_btn_new_post, )    = t('btn_new_post');
list($_msg_logs, )        = t('nav_logs');
list($_lbl_search, )     = t('lbl_search');
list($_ph_search, )       = t('ph_search');
list($_btn_search, )      = t('btn_search');
list($_no_search, )       = t('no_search_results');
list($_all_cats, )        = t('lbl_all_categories');
list($_published_on, )    = t('lbl_published_on');
list($_theme_lbl, )       = t('admin_theme_label');
list($_read_more, )       = t('lbl_back_to_list');
list($_lbl_lang, )        = t('lbl_lang');
list($_lbl_pinned, )      = t('lbl_pinned');
list($_pinned_badge, )    = t('pinned_badge');
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle); ?></title>
    <link rel="stylesheet" href="assets/finsec.css">
</head>
<body class="<?php echo $class; ?><?php echo $isLogged ? ' has-admin-bar' : ''; ?>">
    <?php if ($isLogged): ?>
    <!-- Barra admin fija arriba -->
    <div class="admin-bar">
        <div class="admin-bar__inner">
            <a href="<?php echo htmlspecialchars(adminUrl()); ?>" class="admin-bar__brand"><?php echo finsec_icon('feather', 16); ?> <?php echo $_msg_admin_panel; ?></a>
            <span class="admin-bar__sep"></span>
            <a href="<?php echo htmlspecialchars(adminUrl('posts.php')); ?>" class="admin-bar__link"><?php echo finsec_icon('posts', 14); ?> <span class="bar-label"><?php echo $_msg_posts; ?></span></a>
            <a href="create.php" class="admin-bar__link admin-bar__link--primary"><?php echo finsec_icon('plus', 14); ?> <span class="bar-label"><?php echo $_btn_new_post; ?></span></a>
            <a href="<?php echo htmlspecialchars(adminUrl('settings.php')); ?>" class="admin-bar__link"><?php echo finsec_icon('settings', 14); ?> <span class="bar-label"><?php echo $_msg_settings; ?></span></a>
            <a href="<?php echo htmlspecialchars(adminUrl('logs.php')); ?>" class="admin-bar__link"><?php echo finsec_icon('history', 14); ?> <span class="bar-label"><?php echo $_msg_logs; ?></span></a>
            <span class="admin-bar__sep"></span>
            <form method="GET" action="" style="display:inline;">
                <select name="lang" onchange="this.form.submit();" class="lang-selector" aria-label="<?php echo htmlspecialchars($_lbl_lang); ?>">
                    <option value="es"<?php echo $lang === 'es' ? ' selected' : ''; ?>>Español</option>
                    <option value="en"<?php echo $lang === 'en' ? ' selected' : ''; ?>>English</option>
                </select>
            </form>
            <form method="POST" action="" style="display:inline;">
                <input type="hidden" name="site_theme" value="<?php echo $themeToggle; ?>">
                <button type="submit" class="icon-btn" aria-label="<?php echo htmlspecialchars($_theme_lbl); ?>" title="<?php echo htmlspecialchars($_theme_lbl); ?>">
                    <?php echo finsec_icon($theme === 'finsec-dark' ? 'sun' : 'moon', 16); ?>
                </button>
            </form>
            <a href="#" onclick="confirmLogout(); return false;" class="admin-bar__link admin-bar__link--logout"><?php echo finsec_icon('logout', 14); ?> <span class="bar-label"><?php echo $_msg_logout; ?></span></a>
        </div>
    </div>
    <?php endif; ?>

    <div class="container">
        <header class="site-header">
            <div>
                <h1 class="site-title"><a href="index.php"><?php echo htmlspecialchars($siteTitle); ?></a></h1>
                <p class="site-tagline"><?php echo htmlspecialchars($siteTagline); ?></p>
            </div>
            <div class="header-actions">
                <button type="button" class="icon-btn" onclick="var b=document.getElementById('search-box'); b.classList.toggle('open'); if(b.classList.contains('open'))b.querySelector('input').focus();" aria-label="<?php echo htmlspecialchars($_lbl_search); ?>"><?php echo finsec_icon('search', 18); ?></button>
                <?php if ($isLogged): ?>
                <form method="POST" action="" style="display:inline;">
                    <input type="hidden" name="site_theme" value="<?php echo $themeToggle; ?>">
                    <button type="submit" class="icon-btn" aria-label="<?php echo htmlspecialchars($_theme_lbl); ?>" title="<?php echo htmlspecialchars($_theme_lbl); ?>">
                        <?php echo finsec_icon($theme === 'finsec-dark' ? 'sun' : 'moon', 18); ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <form method="GET" action="" id="search-box" class="search-box<?php echo (!empty($searchQuery) || !empty($categoryFilter)) ? ' open' : ''; ?>">
                <input type="text" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="<?php echo htmlspecialchars($_ph_search); ?>">
                <?php if (!empty($categories)): ?>
                <div class="cat-filter">
                    <span class="cat-filter__label"><?php echo htmlspecialchars(t('filter_by_category')[0]); ?>:</span>
                    <a href="index.php"<?php echo (empty($categoryFilter) && empty($searchQuery)) ? ' class="active"' : ''; ?>><?php echo htmlspecialchars($_all_cats); ?></a>
                    <?php foreach ($categories as $cat): ?>
                    <a href="?cat=<?php echo urlencode($cat); ?>"<?php echo $categoryFilter === $cat ? ' class="active"' : ''; ?>><?php echo htmlspecialchars($cat); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </form>
        </header>

        <main>

            <?php if (empty($searchQuery) && empty($categoryFilter) && empty($tagFilter)): ?>
                <?php $pinnedPosts = getPinnedPosts(); ?>
                <?php if (!empty($pinnedPosts)): ?>
                <section class="pinned-posts">
                    <h2 class="pinned-section-title"><?php echo finsec_icon('pin', 16); ?> <?php echo htmlspecialchars($_lbl_pinned); ?></h2>
                    <?php foreach ($pinnedPosts as $post): ?>
                    <article class="post pinned">
                        <h3><a href="<?php echo htmlspecialchars(postUrl($post['slug'] ?? $post['id'])); ?>"><?php echo htmlspecialchars($post['title']); ?></a></h3>
                        <p class="date">
                            <?php echo finsec_icon('calendar', 12); ?>
                            <?php echo htmlspecialchars($_published_on . ' ' . $post['created_at']); ?>
                            <span class="pin-badge"><?php echo finsec_icon('pin', 12); ?> <?php echo htmlspecialchars($_pinned_badge); ?></span>
                        </p>
                        <div class="content"><?php echo postExcerptHtml($post); ?></div>
                        <?php if (!empty($post['category']) || !empty($post['tags'])): ?>
                        <div class="meta-row">
                            <?php if (!empty($post['category'])): ?>
                            <a class="chip" href="?cat=<?php echo urlencode($post['category']); ?>"><?php echo finsec_icon('folder', 12); ?> <?php echo htmlspecialchars($post['category']); ?></a>
                            <?php endif; ?>
                            <?php if (!empty($post['tags'])): ?>
                                <?php foreach (explode(',', $post['tags']) as $t): $t = trim($t); if ($t === '') continue; ?>
                                <a class="chip" href="?tag=<?php echo urlencode($t); ?>"><?php echo finsec_icon('tag', 12); ?> <?php echo htmlspecialchars($t); ?></a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <p style="margin-top: 16px;"><a href="<?php echo htmlspecialchars(postUrl($post['slug'] ?? $post['id'])); ?>"><?php echo htmlspecialchars($_read_more); ?> <?php echo finsec_icon('arrow-right', 14); ?></a></p>
                    </article>
                    <?php endforeach; ?>
                </section>
                <?php endif; ?>
                <?php renderWidgets('footer'); ?>
            <?php endif; ?>

            <?php if (!empty($searchQuery)): ?>
                <h2><?php echo htmlspecialchars($_btn_search . ': ' . $searchQuery); ?></h2>
            <?php elseif (!empty($categoryFilter)): ?>
                <h2><?php echo htmlspecialchars($categoryFilter); ?></h2>
            <?php elseif (!empty($tagFilter)): ?>
                <h2>#<?php echo htmlspecialchars($tagFilter); ?></h2>
            <?php else: ?>
                <h2><?php echo htmlspecialchars($_recent_posts); ?></h2>
            <?php endif; ?>

            <?php if (empty($posts)): ?>
                <p style="color: var(--text-2); font-size: 0.9375rem;"><?php echo htmlspecialchars(!empty($searchQuery) ? $_no_search : $_no_posts); ?></p>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <article class="post">
                        <h3><a href="<?php echo htmlspecialchars(postUrl($post['slug'] ?? $post['id'])); ?>"><?php echo htmlspecialchars($post['title']); ?></a></h3>
                        <p class="date">
                            <?php echo finsec_icon('calendar', 12); ?>
                            <?php echo htmlspecialchars($_published_on . ' ' . $post['created_at']); ?>
                        </p>
                        <div class="content"><?php echo postExcerptHtml($post); ?></div>
                        <?php if (!empty($post['category']) || !empty($post['tags'])): ?>
                        <div class="meta-row">
                            <?php if (!empty($post['category'])): ?>
                            <a class="chip" href="?cat=<?php echo urlencode($post['category']); ?>"><?php echo finsec_icon('folder', 12); ?> <?php echo htmlspecialchars($post['category']); ?></a>
                            <?php endif; ?>
                            <?php if (!empty($post['tags'])): ?>
                                <?php foreach (explode(',', $post['tags']) as $t): $t = trim($t); if ($t === '') continue; ?>
                                <a class="chip" href="?tag=<?php echo urlencode($t); ?>"><?php echo finsec_icon('tag', 12); ?> <?php echo htmlspecialchars($t); ?></a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <p style="margin-top: 16px;"><a href="<?php echo htmlspecialchars(postUrl($post['slug'] ?? $post['id'])); ?>"><?php echo htmlspecialchars($_read_more); ?> <?php echo finsec_icon('arrow-right', 14); ?></a></p>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php
                $baseQ = '';
                if (!empty($searchQuery)) $baseQ .= '&q=' . urlencode($searchQuery);
                if (!empty($categoryFilter)) $baseQ .= '&cat=' . urlencode($categoryFilter);
                if (!empty($tagFilter)) $baseQ .= '&tag=' . urlencode($tagFilter);
                ?>
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?php echo $currentPage - 1 . $baseQ; ?>"><?php echo finsec_icon('chevron-left', 14); ?> <?php echo t('btn_prev')[0]; ?></a>
                <?php endif; ?>
                <span class="pagination__info"><?php echo t('lbl_page')[0]; ?> <?php echo $currentPage; ?> <?php echo t('lbl_of')[0]; ?> <?php echo $totalPages; ?></span>
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?php echo $currentPage + 1 . $baseQ; ?>"><?php echo t('btn_next')[0]; ?> <?php echo finsec_icon('chevron-right', 14); ?></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </main>

        <footer>
            <p><?php echo htmlspecialchars($siteTitle); ?></p>
        </footer>
    </div>

    <?php if ($isLogged): ?>
    <script>
    function confirmLogout() {
        if (window.confirm('¿Seguro que deseas cerrar la sesión?')) {
            window.location.href = '<?php echo adminUrl('logout.php'); ?>';
        }
    }
    </script>
    <?php endif; ?>
</body>
</html>
