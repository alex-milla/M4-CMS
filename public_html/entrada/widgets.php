<?php
// Panel de gestión de bloques (widgets): enlaces de referencia, texto+enlace, embeds.
// Módulo desactivado por defecto: se activa desde aquí (setting widgets_enabled).
include_once __DIR__ . '/../helpers/auth.php';
m4_session_start();
requireAdmin();

include_once '../config.php';
include_once '../helpers/theme.php';
include_once '../helpers/i18n.php';
include_once '../helpers/icons.php';
include_once '../helpers/widgets.php';
include_once __DIR__ . '/../helpers/csrf.php';

// Cadenas de traducción
list($page_title, )        = t('page_widgets_title');
list($nav_widgets, )       = t('nav_widgets');
list($manage_posts, )      = t('admin_manage_posts');
list($btn_new_post, )      = t('btn_new_post');
list($nav_site, )          = t('nav_go_to_site');
list($nav_logout, )        = t('nav_logout');
list($nav_logs, )          = t('nav_logs');
list($nav_settings, )      = t('admin_settings');
list($nav_update, )        = t('nav_update');
list($sys_section, )       = t('admin_system_section');
list($nav_dashboard, )     = t('admin_dashboard_title');
list($_lbl_lang, )         = t('lbl_lang');
list($theme_lbl, )         = t('admin_theme_label');
list($lbl_content, )       = t('lbl_widget_content');
list($ph_widget_content, ) = t('ph_widget_content');
list($help_widget_content, ) = t('help_widget_content');
list($lbl_position, )      = t('lbl_widget_position');
list($lbl_status, )        = t('lbl_status');
list($lbl_published, )     = t('lbl_published');
list($lbl_draft, )          = t('lbl_draft');
list($btn_edit, )          = t('btn_edit');
list($btn_delete, )        = t('btn_delete');
list($btn_save, )          = t('btn_widget_save');
list($btn_new, )           = t('btn_widget_new');
list($btn_cancel, )        = t('btn_cancel');
list($btn_enable, )        = t('btn_widget_enable');
list($btn_disable, )       = t('btn_widget_disable');
list($wdg_module, )        = t('wdg_module');
list($wdg_module_help, )   = t('wdg_module_help');
list($wdg_enabled_msg, )   = t('wdg_module_enabled');
list($wdg_disabled_msg, )  = t('wdg_module_disabled');
list($wdg_none, )          = t('no_widgets');
list($wdg_saved, )         = t('widget_saved_msg');
list($wdg_deleted, )       = t('widget_deleted_msg');
list($wdg_status, )        = t('widget_status_msg');
list($wdg_module_msg, )    = t('widget_module_msg');
list($err_required, )      = t('err_widget_required');
list($confirm_delete, )    = t('confirm_delete_widget');
$lang = $_SESSION['lang'] ?? 'es';

// Theme (FinSec claro/oscuro) — initTheme persiste en DB + sesión
$theme = initTheme();
$themeToggle = ($theme === 'finsec-dark') ? 'finsec' : 'finsec-dark';
$class = 'theme-' . htmlspecialchars($theme);

createTable();

// Validar datos de un bloque. Devuelve '' si OK, o un mensaje de error.
function validateWidgetInput($content) {
    global $err_required;
    if (trim((string)$content) === '') return $err_required;
    return '';
}

$error_txt = '';

// CSRF: toda escritura admin pasa por POST con token de sesión
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfValidate()) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

// Activar / desactivar el módulo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['module_toggle'])) {
    $newState = ($_POST['module_toggle'] === '1') ? '1' : '0';
    saveSetting('widgets_enabled', $newState);
    logAdminEvent('widget_module', $newState === '1' ? 'enabled' : 'disabled');
    header('Location: widgets.php?module=1');
    exit;
}

// Crear / actualizar bloque
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['widget_save'])) {
    $id       = (int)($_POST['widget_id'] ?? 0);
    $title    = trim((string)($_POST['title'] ?? ''));
    $content  = (string)($_POST['content'] ?? '');
    $position = (int)($_POST['position'] ?? 0);
    $status   = normalizeWidgetStatus($_POST['status'] ?? 'published');

    $error_txt = validateWidgetInput($content);
    if ($error_txt === '') {
        if ($id > 0) {
            updateWidget($id, $title, $content, $position, $status);
            logAdminEvent('widget_updated', $title);
        } else {
            createWidget($title, $content, $position, $status);
            logAdminEvent('widget_created', $title);
        }
        header('Location: widgets.php?saved=1');
        exit;
    }
}

// Eliminar bloque
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int)$_POST['delete_id'];
    $w = getWidgetById($id);
    deleteWidget($id);
    logAdminEvent('widget_deleted', $w['title'] ?? "id=$id");
    header('Location: widgets.php?deleted=1');
    exit;
}

// Alternar estado published/draft
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    $id = (int)$_POST['toggle_id'];
    $w = getWidgetById($id);
    if ($w) {
        $newStatus = ($w['status'] === 'published') ? 'draft' : 'published';
        updateWidget($id, $w['title'], $w['content'] ?? '', (int)$w['position'], $newStatus);
        logAdminEvent('widget_updated', $w['title'] . ' -> ' . $newStatus);
    }
    header('Location: widgets.php?status=1');
    exit;
}

$moduleEnabled = widgetsModuleEnabled();
$widgets = getAllWidgets();

// Edición: precargar el formulario
$editing = null;
if (isset($_GET['edit_id'])) {
    $editing = getWidgetById((int)$_GET['edit_id']);
}
$widget_count = countWidgets();
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
            <span><?php echo htmlspecialchars($nav_widgets); ?></span>
        </div>
        <nav>
            <div class="admin-nav-section"><?php echo htmlspecialchars($nav_dashboard ?? 'Panel'); ?></div>
            <a href="index.php">
                <?php echo finsec_icon('layout', 18); ?>
                <span><?php echo htmlspecialchars($nav_dashboard ?? 'Panel'); ?></span>
            </a>

            <div class="admin-nav-section"><?php echo htmlspecialchars($manage_posts); ?></div>
            <a href="posts.php">
                <?php echo finsec_icon('posts', 18); ?>
                <span><?php echo htmlspecialchars($manage_posts); ?></span>
            </a>
            <a href="../create.php" target="_blank">
                <?php echo finsec_icon('pen', 18); ?>
                <span><?php echo htmlspecialchars($btn_new_post); ?></span>
            </a>
            <a href="widgets.php" class="active">
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
                <?php echo csrfField(); ?>
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
            <div class="admin-content-header">
                <h2><?php echo htmlspecialchars($page_title); ?></h2>
                <p class="settings-stat"><?php echo finsec_icon('blocks', 14); ?> <?php echo htmlspecialchars($wdg_module); ?>: <strong><?php echo $widget_count; ?></strong></p>
            </div>

            <?php
            $toastType = null;
            $toastMsg = null;
            if (isset($_GET['saved']) && $_GET['saved'] == 1) {
                $toastMsg = $wdg_saved;
            } elseif (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
                $toastMsg = $wdg_deleted;
            } elseif (isset($_GET['status']) && $_GET['status'] == 1) {
                $toastMsg = $wdg_status;
            } elseif (isset($_GET['module']) && $_GET['module'] == 1) {
                $toastMsg = $wdg_module_msg;
            }
            if ($toastMsg && $error_txt === ''):
                echo '<div class="admin-toast success" onclick="this.style.display=\'none\'">' . finsec_icon('check', 16) . ' ' . htmlspecialchars($toastMsg) . '</div>';
            endif;
            ?>

            <?php if ($error_txt !== ''): ?>
                <div class="msg-banner error">
                    <?php echo finsec_icon('alert', 16); ?>
                    <span><?php echo htmlspecialchars($error_txt); ?></span>
                </div>
            <?php endif; ?>

            <!-- Estado del módulo -->
            <div class="admin-card">
                <h3><?php echo finsec_icon('blocks', 16); ?> <?php echo htmlspecialchars($wdg_module); ?></h3>
                <div class="admin-form-group">
                    <p style="margin:0 0 12px 0;color:var(--text-2);"><?php echo htmlspecialchars($wdg_module_help); ?></p>
                    <?php if ($moduleEnabled): ?>
                        <span class="badge published"><?php echo htmlspecialchars($lbl_published); ?></span>
                        <span style="color:var(--text-2);margin-left:8px;"><?php echo htmlspecialchars($wdg_enabled_msg); ?></span>
                        <form method="POST" action="" style="display:inline;margin-left:12px;">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="module_toggle" value="0">
                            <button type="submit" class="btn-danger"><?php echo htmlspecialchars($btn_disable); ?></button>
                        </form>
                    <?php else: ?>
                        <span class="badge draft"><?php echo htmlspecialchars($lbl_draft); ?></span>
                        <span style="color:var(--text-2);margin-left:8px;"><?php echo htmlspecialchars($wdg_disabled_msg); ?></span>
                        <form method="POST" action="" style="display:inline;margin-left:12px;">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="module_toggle" value="1">
                            <button type="submit" class="btn-primary"><?php echo htmlspecialchars($btn_enable); ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Alta / edición de bloque -->
            <div class="admin-card">
                <h3><?php echo finsec_icon('plus', 16); ?> <?php echo htmlspecialchars($editing ? $btn_edit : $btn_new); ?></h3>
                <form method="POST" action="">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="widget_save" value="1">
                    <input type="hidden" name="widget_id" value="<?php echo (int)($editing['id'] ?? 0); ?>">

                    <div class="admin-form-group">
                        <label for="w_title"><?php echo htmlspecialchars(t('post_title')[0]); ?></label>
                        <input type="text" id="w_title" name="title" maxlength="120" value="<?php echo htmlspecialchars($editing['title'] ?? ''); ?>">
                    </div>

                    <div class="admin-form-group" id="w_content_group">
                        <label for="w_content"><?php echo htmlspecialchars($lbl_content); ?></label>
                        <textarea id="w_content" name="content" rows="6" placeholder="<?php echo htmlspecialchars($ph_widget_content); ?>"><?php echo htmlspecialchars($editing['content'] ?? ''); ?></textarea>
                        <p style="margin:6px 0 0 0;color:var(--text-3);font-size:0.75rem;" id="w_content_hint"><?php echo htmlspecialchars($help_widget_content); ?></p>
                    </div>

                    <div class="admin-form-group">
                        <label for="w_position"><?php echo htmlspecialchars($lbl_position); ?></label>
                        <input type="number" id="w_position" name="position" value="<?php echo (int)($editing['position'] ?? 0); ?>" style="max-width:120px;">
                    </div>

                    <div class="admin-form-group">
                        <label for="w_status"><?php echo htmlspecialchars($lbl_status); ?></label>
                        <select id="w_status" name="status">
                            <option value="published"<?php echo (($editing['status'] ?? 'published') === 'published') ? ' selected' : ''; ?>><?php echo htmlspecialchars($lbl_published); ?></option>
                            <option value="draft"<?php echo (($editing['status'] ?? '') === 'draft') ? ' selected' : ''; ?>><?php echo htmlspecialchars($lbl_draft); ?></option>
                        </select>
                    </div>

                    <button type="submit" class="btn-primary"><?php echo htmlspecialchars($btn_save); ?></button>
                    <?php if ($editing): ?>
                        <a href="widgets.php" class="action-btn"><?php echo htmlspecialchars($btn_cancel); ?></a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Lista de bloques -->
            <?php if (!empty($widgets)): ?>
            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th><?php echo htmlspecialchars(t('post_title')[0]); ?></th>
                            <th><?php echo htmlspecialchars($lbl_position); ?></th>
                            <th><?php echo htmlspecialchars($lbl_status); ?></th>
                            <th><?php echo htmlspecialchars(t('tbl_col_actions')[0]); ?></th>
                        </tr>
                    </thead>
                    <tbody>
<?php foreach ($widgets as $w): ?>
                        <tr>
                            <td class="mono">#<?php echo (int)$w['id']; ?></td>
                            <td><?php echo htmlspecialchars($w['title']); ?></td>
                            <td class="mono"><?php echo (int)$w['position']; ?></td>
                            <td>
                                <?php if (($w['status'] ?? '') === 'draft'): ?>
                                    <span class="badge draft"><?php echo htmlspecialchars($lbl_draft); ?></span>
                                <?php else: ?>
                                    <span class="badge published"><?php echo htmlspecialchars($lbl_published); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" action="" style="display:inline;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="toggle_id" value="<?php echo (int)$w['id']; ?>">
                                    <button type="submit" class="action-btn"><?php echo finsec_icon('eye', 14); ?></button>
                                </form>
                                <a href="?edit_id=<?php echo (int)$w['id']; ?>" class="action-btn"><?php echo finsec_icon('pencil', 14); ?> <?php echo htmlspecialchars($btn_edit); ?></a>
                                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('<?php echo htmlspecialchars($confirm_delete, ENT_QUOTES); ?>');">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="delete_id" value="<?php echo (int)$w['id']; ?>">
                                    <button type="submit" class="action-btn delete"><?php echo finsec_icon('trash', 14); ?> <?php echo htmlspecialchars($btn_delete); ?></button>
                                </form>
                            </td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="admin-table-container">
                <div class="admin-empty">
                    <?php echo finsec_icon('blocks', 32); ?>
                    <p><?php echo htmlspecialchars($wdg_none); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
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
