<?php
// Panel de gestion de publicaciones
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include_once '../config.php';
include_once '../helpers/theme.php';
include_once '../helpers/i18n.php';
include_once '../helpers/icons.php';

// Cadenas de traduccion
list($page_title, )        = t('page_manage_posts_title');
list($manage_posts, )      = t('admin_manage_posts');
list($btn_new_post, )      = t('btn_new_post');
list($nav_site, )          = t('nav_go_to_site');
list($nav_logout, )        = t('nav_logout');
list($post_deleted_msg, )  = t('post_deleted_msg');
list($post_bulk_deleted_msg, ) = t('post_bulk_deleted_msg');
list($tbl_col_title, )     = t('tbl_col_title');
list($tbl_col_date, )      = t('tbl_col_date');
list($tbl_col_actions, )   = t('tbl_col_actions');
list($tbl_col_id, )        = t('tbl_col_id');
list($tbl_col_select, )    = t('tbl_col_select');
list($btn_edit, )          = t('btn_edit');
list($btn_delete, )        = t('btn_delete');
list($no_posts_msg, )      = t('no_posts_msg');
list($confirm_delete, )    = t('confirm_delete_post');
list($confirm_bulk, )      = t('confirm_bulk_delete');
list($lbl_select_all, )    = t('btn_select_all');
list($btn_delete_sel, )    = t('btn_delete_selected');
list($nav_logs, )          = t('nav_logs');
list($nav_settings, )      = t('admin_settings');
list($nav_update, )        = t('nav_update');
list($sys_section, )       = t('admin_system_section');
list($nav_dashboard, )     = t('admin_dashboard_title');
list($_lbl_lang, )         = t('lbl_lang');
list($theme_lbl, )         = t('admin_theme_label');
list($tbl_col_status, )    = t('tbl_col_status');
list($tbl_col_category, )  = t('tbl_col_category');
list($lbl_published, )     = t('lbl_published');
list($lbl_draft, )          = t('lbl_draft');
list($lbl_no_cat, )        = t('lbl_no_category');
list($btn_publish, )       = t('btn_publish_post');
list($post_published_msg, ) = t('post_published_msg');
list($lbl_scheduled, )     = t('lbl_scheduled');
list($nav_widgets, )   = t('nav_widgets');
$lang = $_SESSION['lang'] ?? 'es';

// Theme (FinSec claro/oscuro) — initTheme persiste en DB + sesión
$theme = initTheme();
$themeToggle = ($theme === 'finsec-dark') ? 'finsec' : 'finsec-dark';
$class = 'theme-' . htmlspecialchars($theme);

createTable();

// Eliminar publicacion
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $post = getPostById($id);
    deletePost($id);
    logAdminEvent('post_deleted', $post['title'] ?? "id=$id");
    header('Location: posts.php?deleted=1');
    exit;
}

// Publicar borrador rapidamente
if (isset($_GET['publish_id'])) {
    $id = (int)$_GET['publish_id'];
    $post = getPostById($id);
    if ($post && ($post['status'] ?? '') === 'draft') {
        updatePost($id, $post['title'], $post['content'], 'published', $post['slug'] ?? '', $post['category'] ?? '');
        logAdminEvent('post_published', $post['title'] ?? "id=$id");
    }
    header('Location: posts.php?published=1');
    exit;
}

// Eliminar publicaciones masivas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    $selected = $_POST['selected_posts'] ?? [];
    if (!empty($selected)) {
        foreach ($selected as $id) {
            deletePost((int)$id);
        }
        logAdminEvent('post_deleted', 'bulk: ' . count($selected) . ' posts');
        header('Location: posts.php?bulk_deleted=1');
        exit;
    }
}

// Paginacion: 15 posts por pagina en admin
$perPage = 15;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;
$posts = getAllPostsPaginated($perPage, $offset);
$totalPosts = countAllPosts();
$totalPages = max(1, (int)ceil($totalPosts / $perPage));

// Safe truncation (works without mbstring)
$truncFunc = function_exists('mb_substr') ? 'mb_substr' : 'substr';
$lenFunc = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="<?php echo $class; ?>">
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="logo">
            <span class="logo-mark"><?php echo finsec_icon('feather', 18); ?></span>
            <span><?php echo htmlspecialchars($manage_posts); ?></span>
        </div>
        <nav>
            <div class="admin-nav-section"><?php echo htmlspecialchars($nav_dashboard ?? 'Panel'); ?></div>
            <a href="index.php">
                <?php echo finsec_icon('layout', 18); ?>
                <span><?php echo htmlspecialchars($nav_dashboard ?? 'Panel'); ?></span>
            </a>

            <div class="admin-nav-section"><?php echo htmlspecialchars($manage_posts); ?></div>
            <a href="posts.php" class="active">
                <?php echo finsec_icon('posts', 18); ?>
                <span><?php echo htmlspecialchars($manage_posts); ?></span>
            </a>
            <a href="../create.php" target="_blank">
                <?php echo finsec_icon('pen', 18); ?>
                <span><?php echo htmlspecialchars($btn_new_post); ?></span>
            </a>
            <a href="widgets.php">
                <?php echo finsec_icon('blocks', 18); ?>
                <span><?php echo htmlspecialchars($nav_widgets); ?></span>
            </a>

            <div class="admin-nav-section"><?php echo htmlspecialchars($nav_settings); ?></div>
            <a href="settings.php">
                <?php echo finsec_icon('settings', 18); ?>
                <span><?php echo htmlspecialchars($nav_settings); ?></span>
            </a>
            <a href="logs.php">
                <?php echo finsec_icon('history', 18); ?>
                <span><?php echo htmlspecialchars($nav_logs); ?></span>
            </a>

            <div class="admin-nav-section"><?php echo htmlspecialchars($sys_section); ?></div>
            <a href="update.php">
                <?php echo finsec_icon('download', 18); ?>
                <span><?php echo htmlspecialchars($nav_update); ?></span>
            </a>

            <div class="admin-nav-section">&nbsp;</div>
            <a href="logout.php" class="nav-danger">
                <?php echo finsec_icon('logout', 18); ?>
                <span><?php echo htmlspecialchars($nav_logout); ?></span>
            </a>
        </nav>
    </aside>

    <!-- Overlay para mobile -->
    <div class="admin-sidebar-overlay"></div>

    <!-- Header -->
    <header class="admin-header">
        <div class="admin-header-left">
            <button class="admin-menu-toggle" onclick="toggleSidebar()" aria-label="Menu"><?php echo finsec_icon('menu', 20); ?></button>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
        </div>
        <div class="admin-header-right">
            <a href="../index.php" class="icon-btn" title="<?php echo htmlspecialchars($nav_site); ?>"><?php echo finsec_icon('globe', 18); ?></a>
            <form method="GET" action="" style="display:inline;">
                <select name="lang" onchange="this.form.submit();" class="lang-selector" aria-label="<?php echo htmlspecialchars($_lbl_lang); ?>">
                    <option value="es"<?php echo $lang === 'es' ? ' selected' : ''; ?>>Español</option>
                    <option value="en"<?php echo $lang === 'en' ? ' selected' : ''; ?>>English</option>
                </select>
            </form>
            <form method="POST" action="" style="display:inline;">
                <input type="hidden" name="site_theme" value="<?php echo $themeToggle; ?>">
                <input type="hidden" name="theme_setting" value="<?php echo $themeToggle; ?>">
                <button type="submit" class="icon-btn" aria-label="<?php echo htmlspecialchars($theme_lbl); ?>" title="<?php echo htmlspecialchars($theme_lbl); ?>">
                    <?php echo finsec_icon($theme === 'finsec-dark' ? 'sun' : 'moon', 18); ?>
                </button>
            </form>
        </div>
    </header>

    <!-- Main -->
    <main class="admin-main">
        <div class="admin-container">
            <?php
            $toastType = null;
            $toastMsg = null;
            if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
                $toastMsg = $post_deleted_msg;
            } elseif (isset($_GET['bulk_deleted']) && $_GET['bulk_deleted'] == 1) {
                $toastMsg = $post_bulk_deleted_msg;
            } elseif (isset($_GET['published']) && $_GET['published'] == 1) {
                $toastMsg = $post_published_msg;
            }
            if ($toastMsg):
                echo '<div class="admin-toast success" onclick="this.style.display=\'none\'">' . finsec_icon('check', 16) . ' ' . htmlspecialchars($toastMsg) . '</div>';
            endif;
            ?>

            <?php if (!empty($posts)): ?>
            <form method="POST" action="" id="bulkForm">
                <input type="hidden" name="bulk_delete" value="1">

                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?php echo htmlspecialchars($tbl_col_select); ?></th>
                                <th><?php echo htmlspecialchars($tbl_col_id); ?></th>
                                <th><?php echo htmlspecialchars($tbl_col_title); ?></th>
                                <th><?php echo htmlspecialchars($tbl_col_status); ?></th>
                                <th><?php echo htmlspecialchars($tbl_col_category); ?></th>
                                <th><?php echo htmlspecialchars($tbl_col_date); ?></th>
                                <th><?php echo htmlspecialchars($tbl_col_actions); ?></th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($posts as $post): ?>
                            <tr>
                                <td><input type="checkbox" name="selected_posts[]" value="<?php echo $post['id']; ?>" onchange="toggleBulkAction()"></td>
                                <td class="mono">#<?php echo htmlspecialchars($post['id']); ?></td>
                                <td><a href="../edit.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($truncFunc($post['title'], 0, 50)); ?><?php echo $lenFunc($post['title']) > 50 ? '...' : ''; ?></a></td>
                                <td>
                                    <?php
                                    $st = $post['status'] ?? 'published';
                                    $isScheduled = strcmp($post['created_at'], nowLocalString()) > 0;
                                    if ($st === 'draft') {
                                        echo '<span class="badge draft">' . htmlspecialchars($lbl_draft) . '</span>';
                                    } elseif ($isScheduled) {
                                        echo '<span class="badge scheduled">' . htmlspecialchars($lbl_scheduled) . '</span>';
                                    } else {
                                        echo '<span class="badge published">' . htmlspecialchars($lbl_published) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo !empty($post['category']) ? htmlspecialchars($post['category']) : '<em style="color:var(--text-3);">' . htmlspecialchars($lbl_no_cat) . '</em>'; ?></td>
                                <td class="mono"><?php echo htmlspecialchars($post['created_at']); ?></td>
                                <td>
                                    <?php if (($post['status'] ?? 'published') === 'draft'): ?>
                                    <a href="?publish_id=<?php echo $post['id']; ?>" class="action-btn"><?php echo finsec_icon('eye', 14); ?> <?php echo htmlspecialchars($btn_publish); ?></a>
                                    <?php endif; ?>
                                    <a href="../edit.php?id=<?php echo $post['id']; ?>" class="action-btn"><?php echo finsec_icon('pencil', 14); ?> <?php echo htmlspecialchars($btn_edit); ?></a>
                                    <a href="?delete_id=<?php echo $post['id']; ?>" class="action-btn delete" onclick="return confirm('<?php echo htmlspecialchars($confirm_delete, ENT_QUOTES); ?>');"><?php echo finsec_icon('trash', 14); ?> <?php echo htmlspecialchars($btn_delete); ?></a>
                                </td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="admin-bulk-actions">
                    <button type="button" id="selectAllBtn" onclick="toggleSelectAll()"><?php echo htmlspecialchars($lbl_select_all); ?></button>
                    <button type="submit" id="bulkDeleteBtn" class="btn-danger" disabled onclick="return confirm('<?php echo htmlspecialchars($confirm_bulk, ENT_QUOTES); ?>');"><?php echo htmlspecialchars($btn_delete_sel); ?></button>
                </div>
            </form>
            <?php else: ?>
            <div class="admin-table-container">
                <div class="admin-empty">
                    <?php echo finsec_icon('posts', 32); ?>
                    <p><?php echo htmlspecialchars($no_posts_msg); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
            <div class="admin-pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?php echo $currentPage - 1; ?>"><?php echo finsec_icon('chevron-left', 14); ?> <?php echo t('btn_prev')[0]; ?></a>
                <?php endif; ?>
                <span><?php echo t('lbl_page')[0]; ?> <?php echo $currentPage; ?> <?php echo t('lbl_of')[0]; ?> <?php echo $totalPages; ?></span>
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?php echo $currentPage + 1; ?>"><?php echo t('btn_next')[0]; ?> <?php echo finsec_icon('chevron-right', 14); ?></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
    function toggleBulkAction() {
        var checkboxes = document.querySelectorAll('input[name="selected_posts[]"]');
        var btn = document.getElementById('bulkDeleteBtn');
        var hasChecked = false;
        for (var i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].checked) { hasChecked = true; break; }
        }
        btn.disabled = !hasChecked;
    }
    function toggleSelectAll() {
        var checkboxes = document.querySelectorAll('input[name="selected_posts[]"]');
        var allChecked = true;
        for (var i = 0; i < checkboxes.length; i++) {
            if (!checkboxes[i].checked) { allChecked = false; break; }
        }
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = !allChecked;
        }
        toggleBulkAction();
    }
    function toggleSidebar() {
        const sidebar = document.querySelector('.admin-sidebar');
        const overlay = document.querySelector('.admin-sidebar-overlay');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    }
    document.querySelector('.admin-sidebar-overlay')?.addEventListener('click', function() {
        document.querySelector('.admin-sidebar')?.classList.remove('open');
        this.classList.remove('active');
    });
    </script>
</body>
</html>
