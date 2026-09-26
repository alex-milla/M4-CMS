<?php
// Registro de actividad admin
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include_once '../config.php';
include_once '../helpers/theme.php';
include_once '../helpers/i18n.php';
include_once '../helpers/icons.php';

// Purgar logs viejos en cada visita (logrotate sin cron)
purgeOldLogs(90);

// Paginacion
$perPage = 50;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;
$logs = getAdminLogs($perPage, $offset);
$totalLogs = countAdminLogs();
$totalPages = max(1, (int)ceil($totalLogs / $perPage));

// Tema (FinSec claro/oscuro) — initTheme persiste en DB + sesión
$theme = initTheme();
$themeToggle = ($theme === 'finsec-dark') ? 'finsec' : 'finsec-dark';
$lang  = $_SESSION['lang'] ?? 'es';
$class = 'theme-' . htmlspecialchars($theme);

// Traducciones
list($page_title, )    = t('page_logs_title');
list($nav_dashboard, )  = t('admin_dashboard_title');
list($manage_posts, )   = t('admin_manage_posts');
list($btn_new_post, )   = t('btn_new_post');
list($nav_settings, )   = t('admin_settings');
list($nav_logout, )     = t('nav_logout');
list($nav_site, )       = t('nav_go_to_site');
list($tbl_date, )       = t('tbl_col_date');
list($tbl_event, )      = t('tbl_col_event');
list($tbl_detail, )     = t('tbl_col_detail');
list($tbl_ip, )         = t('tbl_col_ip');
list($no_logs, )        = t('no_logs');
list($_lbl_lang, )      = t('lbl_lang');
list($theme_lbl, )      = t('admin_theme_label');
list($lbl_page, )       = t('lbl_page');
list($lbl_of, )         = t('lbl_of');
list($btn_prev, )       = t('btn_prev');
list($btn_next, )       = t('btn_next');
list($nav_update, )     = t('nav_update');
list($sys_section, )    = t('admin_system_section');
list($nav_widgets, )   = t('nav_widgets');
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
            <span><?php echo htmlspecialchars($page_title); ?></span>
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
            <a href="widgets.php">
                <?php echo finsec_icon('blocks', 18); ?>
                <span><?php echo htmlspecialchars($nav_widgets); ?></span>
            </a>

            <div class="admin-nav-section"><?php echo htmlspecialchars($nav_settings); ?></div>
            <a href="settings.php">
                <?php echo finsec_icon('settings', 18); ?>
                <span><?php echo htmlspecialchars($nav_settings); ?></span>
            </a>
            <a href="logs.php" class="active">
                <?php echo finsec_icon('history', 18); ?>
                <span><?php echo htmlspecialchars($page_title); ?></span>
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
            <?php if (empty($logs)): ?>
            <div class="admin-table-container">
                <div class="admin-empty">
                    <?php echo finsec_icon('history', 32); ?>
                    <p><?php echo htmlspecialchars($no_logs); ?></p>
                </div>
            </div>
            <?php else: ?>
            <div class="admin-table-container">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th><?php echo htmlspecialchars($tbl_date); ?></th>
                            <th><?php echo htmlspecialchars($tbl_event); ?></th>
                            <th><?php echo htmlspecialchars($tbl_detail); ?></th>
                            <th><?php echo htmlspecialchars($tbl_ip); ?></th>
                        </tr>
                    </thead>
                    <tbody>
<?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="mono"><?php echo htmlspecialchars($log['created_at']); ?></td>
                            <td><span class="badge scheduled"><?php echo htmlspecialchars($log['event']); ?></span></td>
                            <td><?php echo htmlspecialchars($log['detail'] ?? ''); ?></td>
                            <td class="mono"><?php echo htmlspecialchars($log['ip'] ?? ''); ?></td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="admin-pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?php echo $currentPage - 1; ?>"><?php echo finsec_icon('chevron-left', 14); ?> <?php echo $btn_prev; ?></a>
                <?php endif; ?>
                <span><?php echo $lbl_page; ?> <?php echo $currentPage; ?> <?php echo $lbl_of; ?> <?php echo $totalPages; ?></span>
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?php echo $currentPage + 1; ?>"><?php echo $btn_next; ?> <?php echo finsec_icon('chevron-right', 14); ?></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
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
