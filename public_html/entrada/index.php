<?php
ob_start();
include_once __DIR__ . '/../helpers/session.php';
m4_session_start();

// -- Logout (before any output) --
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ./');
    exit;
}

// i18n
include_once '../helpers/i18n.php';
include_once '../helpers/theme.php';
include_once '../helpers/icons.php';
include_once __DIR__ . '/../helpers/admin_layout.php';
$lang = $_SESSION['lang'] ?? 'es';

// Theme (FinSec claro/oscuro) — initTheme persiste en DB + sesión
try {
    $theme = initTheme();
} catch (\Throwable $e) {
    $theme = normalizeTheme($_SESSION['theme'] ?? 'finsec');
}
$themeToggle = ($theme === 'finsec-dark') ? 'finsec' : 'finsec-dark';

$loggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$userName = $_SESSION['admin_user'] ?? '';
$class = 'theme-' . htmlspecialchars($theme);

// Estadisticas para el dashboard (solo si esta logueado)
$stats = [
    'total' => 0,
    'published' => 0,
    'draft' => 0,
    'scheduled' => 0
];
if ($loggedIn) {
    try {
        include_once '../db/functions.php';
        createTable();
        $allPosts = getAllPosts();
        foreach ($allPosts as $post) {
            $stats['total']++;
            if (($post['status'] ?? '') === 'draft') {
                $stats['draft']++;
            } elseif (strcmp($post['created_at'], (function_exists('nowLocalString') ? nowLocalString() : date('Y-m-d H:i:s'))) > 0) {
                $stats['scheduled']++;
            } else {
                $stats['published']++;
            }
        }
    } catch (\Throwable $e) {
        // Fallback: sin estadisticas
    }
}

// Traducciones
list($page_title, )    = t('admin_index_title');
list($admin_h1, )      = t('admin_h1');
list($login_prompt, )  = t('admin_login_prompt');
list($btn_enter, )     = t('admin_enter');
list($welcome, )       = t('admin_welcome');
list($status, )        = t('admin_status');
list($manage_posts, )  = t('admin_manage_posts');
list($settings, )      = t('admin_settings');
list($logout, )        = t('admin_logout');
list($theme_lbl, )     = t('admin_theme_label');
list($_lbl_lang, )     = t('lbl_lang');
list($btn_new_post, )  = t('btn_new_post');
list($nav_logs, )      = t('nav_logs');
list($nav_update, )    = t('nav_update');
list($sys_section, )   = t('admin_system_section');
list($nav_widgets, )   = t('nav_widgets');

// Traducciones para dashboard
list($dashboard_title, ) = t('admin_dashboard_title');
list($stats_posts, ) = t('admin_stats_posts');
list($stats_published, ) = t('admin_stats_published');
list($stats_draft, ) = t('admin_stats_draft');
list($stats_scheduled, ) = t('admin_stats_scheduled');
list($quick_actions, ) = t('admin_quick_actions');
?>
    <?php if ($loggedIn): ?>
<?php adminLayoutHead([
    'title'  => $page_title,
    'h1'     => $admin_h1,
    'logo'   => $admin_h1,
    'active' => 'index',
    'theme'  => $theme,
    'user'   => $userName,
    'show_site_link' => false,
    'logout_url' => './?logout=1',
    'logout_confirm' => true,
]); ?>
            <div class="admin-content-header">
                <h2><?php echo htmlspecialchars($welcome); ?>, <?php echo htmlspecialchars($userName); ?></h2>
                <p><?php echo htmlspecialchars($status); ?></p>
            </div>

            <!-- Dashboard Stats (KPI cards) -->
            <div class="admin-stats">
                <div class="admin-stat-card">
                    <span class="icon"><?php echo finsec_icon('posts', 18); ?></span>
                    <div class="value"><?php echo $stats['total']; ?></div>
                    <div class="label"><?php echo htmlspecialchars($stats_posts ?? 'Posts'); ?></div>
                </div>
                <div class="admin-stat-card">
                    <span class="icon"><?php echo finsec_icon('check', 18); ?></span>
                    <div class="value"><?php echo $stats['published']; ?></div>
                    <div class="label"><?php echo htmlspecialchars($stats_published ?? 'Publicados'); ?></div>
                </div>
                <div class="admin-stat-card">
                    <span class="icon"><?php echo finsec_icon('pen', 18); ?></span>
                    <div class="value"><?php echo $stats['draft']; ?></div>
                    <div class="label"><?php echo htmlspecialchars($stats_draft ?? 'Borradores'); ?></div>
                </div>
                <div class="admin-stat-card">
                    <span class="icon"><?php echo finsec_icon('calendar', 18); ?></span>
                    <div class="value"><?php echo $stats['scheduled']; ?></div>
                    <div class="label"><?php echo htmlspecialchars($stats_scheduled ?? 'Programados'); ?></div>
                </div>
            </div>

            <!-- Quick Actions (tarjetas clicables) -->
            <h3 style="font-size:1rem;font-weight:600;margin-bottom:16px;"><?php echo htmlspecialchars($quick_actions ?? 'Acciones rápidas'); ?></h3>
            <div class="admin-quick-actions">
                <a href="posts.php" class="admin-quick-action">
                    <span class="icon"><?php echo finsec_icon('posts', 18); ?></span>
                    <span><?php echo htmlspecialchars($manage_posts); ?></span>
                </a>
                <a href="../create.php" target="_blank" class="admin-quick-action">
                    <span class="icon"><?php echo finsec_icon('pen', 18); ?></span>
                    <span><?php echo htmlspecialchars($btn_new_post); ?></span>
                </a>
                <a href="settings.php" class="admin-quick-action">
                    <span class="icon"><?php echo finsec_icon('settings', 18); ?></span>
                    <span><?php echo htmlspecialchars($settings); ?></span>
                </a>
                <a href="logs.php" class="admin-quick-action">
                    <span class="icon"><?php echo finsec_icon('history', 18); ?></span>
                    <span><?php echo htmlspecialchars($nav_logs); ?></span>
                </a>
                <a href="update.php" class="admin-quick-action">
                    <span class="icon"><?php echo finsec_icon('download', 18); ?></span>
                    <span><?php echo htmlspecialchars($nav_update); ?></span>
                </a>
            </div>
<?php
$dashboardJs = <<<'JS'
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.querySelector('.admin-sidebar');
        const overlay = document.querySelector('.admin-sidebar-overlay');
        if (window.innerWidth <= 1024) {
            sidebar?.classList.remove('open');
            overlay?.classList.remove('active');
        }
    });
JS;
adminLayoutFooter($dashboardJs);
?>
    <?php else: ?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="<?php echo $class; ?>">
    <!-- No logueado: tarjeta con acceso al login -->
    <div class="login-shell">
        <div class="login-col">
            <div class="login-brand">
                <div class="login-logo"><?php echo finsec_icon('feather', 32); ?></div>
                <h1 class="login-title"><?php echo htmlspecialchars($admin_h1); ?></h1>
            </div>
            <div class="login-card">
                <p style="font-size:0.875rem;color:var(--text-2);margin-bottom:16px;text-align:center;">
                    <?php echo htmlspecialchars($login_prompt); ?>
                </p>
                <a href="login.php" class="btn-primary" style="display:flex;align-items:center;justify-content:center;width:100%;padding:10px 16px;border-radius:0.5rem;text-decoration:none;">
                    <?php echo htmlspecialchars($btn_enter); ?>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
<?php endif; ?>
