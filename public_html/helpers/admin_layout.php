<?php
// Layout compartido del panel de administración (sidebar + cabecera).
// Evita duplicar ~120 líneas de estructura en cada página de `entrada/`.
include_once __DIR__ . '/session.php';
include_once __DIR__ . '/i18n.php';
include_once __DIR__ . '/icons.php';
include_once __DIR__ . '/csrf.php';

// Cabecera + sidebar + apertura del contenedor principal.
// Opciones: title, logo, active (index|posts|widgets|settings|logs|update),
//           theme, user (nombre, muestra el chip), show_site_link,
//           logout_url, logout_confirm.
function adminLayoutHead(array $opts = []) {
    $title        = (string)($opts['title'] ?? '');
    $h1           = (string)($opts['h1'] ?? $title);
    $logo         = (string)($opts['logo'] ?? $title);
    $active       = (string)($opts['active'] ?? '');
    $theme        = (string)($opts['theme'] ?? 'finsec');
    $themeToggle  = ($theme === 'finsec-dark') ? 'finsec' : 'finsec-dark';
    $user         = $opts['user'] ?? null;
    $showSiteLink = $opts['show_site_link'] ?? true;
    $logoutUrl    = (string)($opts['logout_url'] ?? 'logout.php');
    $logoutConfirm = (bool)($opts['logout_confirm'] ?? false);
    $lang = $_SESSION['lang'] ?? 'es';

    list($nav_dashboard, ) = t('admin_dashboard_title');
    list($manage_posts, )  = t('admin_manage_posts');
    list($nav_settings, )  = t('admin_settings');
    list($nav_logs, )      = t('nav_logs');
    list($nav_widgets, )   = t('nav_widgets');
    list($btn_new_post, )  = t('btn_new_post');
    list($nav_update, )    = t('nav_update');
    list($sys_section, )   = t('admin_system_section');
    list($nav_site, )      = t('nav_go_to_site');
    list($nav_logout, )    = t('nav_logout');
    list($_lbl_lang, )     = t('lbl_lang');
    list($theme_lbl, )     = t('admin_theme_label');

    $act = function ($key) use ($active) {
        return $active === $key ? ' class="active"' : '';
    };
    ?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?></title>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="theme-<?php echo htmlspecialchars($theme); ?>">
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="logo">
            <span class="logo-mark"><?php echo finsec_icon('feather', 18); ?></span>
            <span><?php echo htmlspecialchars($logo); ?></span>
        </div>
        <nav>
            <div class="admin-nav-section"><?php echo htmlspecialchars($nav_dashboard); ?></div>
            <a href="index.php"<?php echo $act('index'); ?>>
                <?php echo finsec_icon('layout', 18); ?>
                <span><?php echo htmlspecialchars($nav_dashboard); ?></span>
            </a>

            <div class="admin-nav-section"><?php echo htmlspecialchars($manage_posts); ?></div>
            <a href="posts.php"<?php echo $act('posts'); ?>>
                <?php echo finsec_icon('posts', 18); ?>
                <span><?php echo htmlspecialchars($manage_posts); ?></span>
            </a>
            <a href="../create.php" target="_blank">
                <?php echo finsec_icon('pen', 18); ?>
                <span><?php echo htmlspecialchars($btn_new_post); ?></span>
            </a>
            <a href="widgets.php"<?php echo $act('widgets'); ?>>
                <?php echo finsec_icon('blocks', 18); ?>
                <span><?php echo htmlspecialchars($nav_widgets); ?></span>
            </a>

            <div class="admin-nav-section"><?php echo htmlspecialchars($nav_settings); ?></div>
            <a href="settings.php"<?php echo $act('settings'); ?>>
                <?php echo finsec_icon('settings', 18); ?>
                <span><?php echo htmlspecialchars($nav_settings); ?></span>
            </a>
            <a href="logs.php"<?php echo $act('logs'); ?>>
                <?php echo finsec_icon('history', 18); ?>
                <span><?php echo htmlspecialchars($nav_logs); ?></span>
            </a>

            <div class="admin-nav-section"><?php echo htmlspecialchars($sys_section); ?></div>
            <a href="update.php"<?php echo $act('update'); ?>>
                <?php echo finsec_icon('download', 18); ?>
                <span><?php echo htmlspecialchars($nav_update); ?></span>
            </a>

            <div class="admin-nav-section">&nbsp;</div>
            <a href="<?php echo htmlspecialchars($logoutUrl); ?>" class="nav-danger"<?php if ($logoutConfirm): ?> onclick="return confirm('<?php echo htmlspecialchars($nav_logout, ENT_QUOTES); ?>?');"<?php endif; ?>>
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
            <h1><?php echo htmlspecialchars($h1); ?></h1>
        </div>
        <div class="admin-header-right">
            <?php if ($showSiteLink): ?>
            <a href="../index.php" class="icon-btn" title="<?php echo htmlspecialchars($nav_site); ?>"><?php echo finsec_icon('globe', 18); ?></a>
            <?php endif; ?>
            <form method="GET" action="" style="display:inline;">
                <select name="lang" onchange="this.form.submit();" class="lang-selector" aria-label="<?php echo htmlspecialchars($_lbl_lang); ?>">
                    <option value="es"<?php echo $lang === 'es' ? ' selected' : ''; ?>>Español</option>
                    <option value="en"<?php echo $lang === 'en' ? ' selected' : ''; ?>>English</option>
                </select>
            </form>
            <form method="POST" action="" style="display:inline;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="site_theme" value="<?php echo htmlspecialchars($themeToggle); ?>">
                <input type="hidden" name="theme_setting" value="<?php echo htmlspecialchars($themeToggle); ?>">
                <button type="submit" class="icon-btn" aria-label="<?php echo htmlspecialchars($theme_lbl); ?>" title="<?php echo htmlspecialchars($theme_lbl); ?>">
                    <?php echo finsec_icon($theme === 'finsec-dark' ? 'sun' : 'moon', 18); ?>
                </button>
            </form>
            <?php if ($user !== null): ?>
            <span class="user-chip">
                <span class="user-name"><?php echo htmlspecialchars((string)$user); ?></span>
                <span class="role-chip">admin</span>
            </span>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main -->
    <main class="admin-main">
        <div class="admin-container">
    <?php
}

// Cierra el contenedor, pinta los scripts (JS extra + toggle de sidebar) y cierra el documento.
function adminLayoutFooter($scripts = '') {
    ?>
        </div>
    </main>

    <script>
    <?php echo $scripts; ?>
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
    <?php
}
