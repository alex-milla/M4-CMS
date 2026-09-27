<?php
// Panel de gestion de publicaciones
include_once __DIR__ . '/../helpers/auth.php';
m4_session_start();
requireAdmin();

include_once '../config.php';
include_once '../helpers/theme.php';
include_once '../helpers/i18n.php';
include_once '../helpers/icons.php';
include_once __DIR__ . '/../helpers/csrf.php';
include_once __DIR__ . '/../helpers/admin_layout.php';

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
list($lbl_pinned, )        = t('lbl_pinned');
list($pinned_badge, )      = t('pinned_badge');
list($btn_pin, )           = t('btn_pin');
list($btn_unpin, )         = t('btn_unpin');
list($btn_move_up, )       = t('btn_move_up');
list($btn_move_down, )     = t('btn_move_down');
list($post_pinned_msg, )   = t('post_pinned_msg');
list($post_unpinned_msg, ) = t('post_unpinned_msg');
list($post_pin_order_msg, ) = t('post_pin_order_msg');
list($err_pin_limit, )     = t('err_pin_limit');
list($confirm_unpin, )     = t('confirm_unpin');
$lang = $_SESSION['lang'] ?? 'es';

// Theme (FinSec claro/oscuro) — initTheme persiste en DB + sesión
$theme = initTheme();
$themeToggle = ($theme === 'finsec-dark') ? 'finsec' : 'finsec-dark';
$class = 'theme-' . htmlspecialchars($theme);

createTable();

// CSRF: toda escritura admin pasa por POST con token de sesión
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfValidate()) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

// Eliminar publicacion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    $post = getPostById($id);
    deletePost($id);
    logAdminEvent('post_deleted', $post['title'] ?? "id=$id");
    header('Location: posts.php?deleted=1');
    exit;
}

// Publicar borrador rapidamente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publish_id'])) {
    $id = (int)$_POST['publish_id'];
    $post = getPostById($id);
    if ($post && ($post['status'] ?? '') === 'draft') {
        updatePost($id, $post['title'], $post['content'], 'published', $post['slug'] ?? '', $post['category'] ?? '');
        logAdminEvent('post_published', $post['title'] ?? "id=$id");
    }
    header('Location: posts.php?published=1');
    exit;
}

// Anclar una publicacion (maximo 3)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin_id'])) {
    $id = (int)$_POST['pin_id'];
    $post = getPostById($id);
    if ($post && pinPost($id)) {
        logAdminEvent('post_pinned', $post['title'] ?? "id=$id");
        header('Location: posts.php?pinned=1');
    } else {
        header('Location: posts.php?pin_limit=1');
    }
    exit;
}

// Desanclar una publicacion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unpin_id'])) {
    $id = (int)$_POST['unpin_id'];
    $post = getPostById($id);
    if ($post) {
        unpinPost($id);
        logAdminEvent('post_unpinned', $post['title'] ?? "id=$id");
    }
    header('Location: posts.php?unpinned=1');
    exit;
}

// Reordenar anclados: subir (up) o bajar (down)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_id'], $_POST['dir'])) {
    $id = (int)$_POST['move_id'];
    $dir = ($_POST['dir'] === 'down') ? 1 : -1;
    $post = getPostById($id);
    if ($post && movePinnedPost($id, $dir)) {
        logAdminEvent('post_pin_reordered', $post['title'] ?? "id=$id");
        header('Location: posts.php?pin_moved=1');
    } else {
        header('Location: posts.php?pin_moved=0');
    }
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
$pinnedCount = countPinnedPosts();

// Safe truncation (works without mbstring)
$truncFunc = function_exists('mb_substr') ? 'mb_substr' : 'substr';
$lenFunc = function_exists('mb_strlen') ? 'mb_strlen' : 'strlen';
?>
<?php adminLayoutHead([
    'title'  => $page_title,
    'logo'   => $manage_posts,
    'active' => 'posts',
    'theme'  => $theme,
]); ?>
            <?php
            $toastType = 'success';
            $toastMsg = null;
            if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
                $toastMsg = $post_deleted_msg;
            } elseif (isset($_GET['bulk_deleted']) && $_GET['bulk_deleted'] == 1) {
                $toastMsg = $post_bulk_deleted_msg;
            } elseif (isset($_GET['published']) && $_GET['published'] == 1) {
                $toastMsg = $post_published_msg;
            } elseif (isset($_GET['pinned']) && $_GET['pinned'] == 1) {
                $toastMsg = $post_pinned_msg;
            } elseif (isset($_GET['unpinned']) && $_GET['unpinned'] == 1) {
                $toastMsg = $post_unpinned_msg;
            } elseif (isset($_GET['pin_moved']) && $_GET['pin_moved'] == 1) {
                $toastMsg = $post_pin_order_msg;
            } elseif (isset($_GET['pin_limit']) && $_GET['pin_limit'] == 1) {
                $toastMsg = $err_pin_limit;
                $toastType = 'error';
            }
            if ($toastMsg):
                $toastClass = ($toastType === 'error') ? 'error' : 'success';
                $toastIcon = ($toastType === 'error') ? 'alert' : 'check';
                echo '<div class="admin-toast ' . $toastClass . '" onclick="this.style.display=\'none\'">' . finsec_icon($toastIcon, 16) . ' ' . htmlspecialchars($toastMsg) . '</div>';
            endif;
            ?>

            <?php if (!empty($posts)): ?>
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
                                <td><input type="checkbox" name="selected_posts[]" value="<?php echo $post['id']; ?>" form="bulkForm" onchange="toggleBulkAction()"></td>
                                <td class="mono">#<?php echo htmlspecialchars($post['id']); ?></td>
                                <td>
                                    <?php if ((int)($post['pinned_order'] ?? 0) > 0): ?>
                                    <span class="badge pinned" title="<?php echo htmlspecialchars($lbl_pinned); ?>"><?php echo finsec_icon('pin', 12); ?> <?php echo (int)$post['pinned_order']; ?></span>
                                    <?php endif; ?>
                                    <a href="../edit.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($truncFunc($post['title'], 0, 50)); ?><?php echo $lenFunc($post['title']) > 50 ? '...' : ''; ?></a>
                                </td>
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
                                    <?php $pinOrder = (int)($post['pinned_order'] ?? 0); ?>
                                    <?php if ($pinOrder > 0): ?>
                                        <?php if ($pinOrder > 1): ?>
                                        <form method="POST" action="" style="display:inline;">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="move_id" value="<?php echo (int)$post['id']; ?>">
                                            <input type="hidden" name="dir" value="up">
                                            <button type="submit" class="action-btn" title="<?php echo htmlspecialchars($btn_move_up); ?>"><?php echo finsec_icon('arrow-up', 14); ?></button>
                                        </form>
                                        <?php endif; ?>
                                        <?php if ($pinOrder < $pinnedCount): ?>
                                        <form method="POST" action="" style="display:inline;">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="move_id" value="<?php echo (int)$post['id']; ?>">
                                            <input type="hidden" name="dir" value="down">
                                            <button type="submit" class="action-btn" title="<?php echo htmlspecialchars($btn_move_down); ?>"><?php echo finsec_icon('arrow-down', 14); ?></button>
                                        </form>
                                        <?php endif; ?>
                                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('<?php echo htmlspecialchars($confirm_unpin, ENT_QUOTES); ?>');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="unpin_id" value="<?php echo (int)$post['id']; ?>">
                                            <button type="submit" class="action-btn"><?php echo finsec_icon('pin', 14); ?> <?php echo htmlspecialchars($btn_unpin); ?></button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="" style="display:inline;">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="pin_id" value="<?php echo (int)$post['id']; ?>">
                                            <button type="submit" class="action-btn"><?php echo finsec_icon('pin', 14); ?> <?php echo htmlspecialchars($btn_pin); ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (($post['status'] ?? 'published') === 'draft'): ?>
                                    <form method="POST" action="" style="display:inline;">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="publish_id" value="<?php echo (int)$post['id']; ?>">
                                        <button type="submit" class="action-btn"><?php echo finsec_icon('eye', 14); ?> <?php echo htmlspecialchars($btn_publish); ?></button>
                                    </form>
                                    <?php endif; ?>
                                    <a href="../edit.php?id=<?php echo $post['id']; ?>" class="action-btn"><?php echo finsec_icon('pencil', 14); ?> <?php echo htmlspecialchars($btn_edit); ?></a>
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('<?php echo htmlspecialchars($confirm_delete, ENT_QUOTES); ?>');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="delete_id" value="<?php echo (int)$post['id']; ?>">
                                        <button type="submit" class="action-btn delete"><?php echo finsec_icon('trash', 14); ?> <?php echo htmlspecialchars($btn_delete); ?></button>
                                    </form>
                                </td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <form method="POST" action="" id="bulkForm">
                <?php echo csrfField(); ?>
                <input type="hidden" name="bulk_delete" value="1">
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
<?php
$postListJs = <<<'JS'
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
JS;
adminLayoutFooter($postListJs);
?>
