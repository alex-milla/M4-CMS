<?php
ob_start();
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Incluir primero los archivos necesarios para evitar errores 500
include_once '../db/functions.php';
include_once '../helpers/theme.php';
include_once '../helpers/i18n.php';
include_once '../helpers/icons.php';

// Asegurar que las tablas existen antes de leer/escribir settings
createTable();

// Tema (FinSec claro/oscuro) — initTheme persiste en DB + sesión
$theme = initTheme();
$themeToggle = ($theme === 'finsec-dark') ? 'finsec' : 'finsec-dark';
$lang  = $_SESSION['lang'] ?? 'es';

// Cadenas de traducción
list($msg_saved, )      = t('message_config_saved');
list($lbl_posts, )      = t('cfg_posts_in_system');
list($lbl_theme, )      = t('cfg_active_theme');
list($lbl_sitedata, )   = t('cfg_site_data');
list($lbl_adminacc, )   = t('cfg_admin_account');
list($lbl_sitetitle, )  = t('lbl_site_title');
list($lbl_sitetag, )    = t('lbl_site_tagline');
list($ph_title, )       = t('cfg_site_title_ph');
list($ph_tag, )         = t('cfg_site_tagline_ph');
list($lbl_adminuser, )  = t('field_admin_user_label');
list($lbl_newpass, )    = t('cfg_new_password');
list($btn_save, )       = t('btn_save_config_submit');
list($page_title, )     = t('nav_settings');
list($manage_posts, )   = t('admin_manage_posts');
list($btn_new_post, )   = t('btn_new_post');
list($nav_logs, )       = t('nav_logs');
list($nav_dashboard, )  = t('admin_dashboard_title');
list($nav_site, )       = t('nav_go_to_site');
list($nav_logout, )     = t('nav_logout');
list($_lbl_lang, )      = t('lbl_lang');
list($theme_lbl, )      = t('admin_theme_label');
list($lbl_adminpath, )  = t('cfg_admin_path_label');
list($help_adminpath, ) = t('cfg_admin_path_help');
list($lbl_pathpass, )   = t('cfg_admin_path_pass');
list($msg_path_changed, ) = t('msg_admin_path_changed');
list($err_path_format, )    = t('err_admin_path_format');
list($err_path_reserved, )  = t('err_admin_path_reserved');
list($err_path_exists, )    = t('err_admin_path_exists');
list($err_path_password, ) = t('err_admin_path_password');

$class = 'theme-' . htmlspecialchars($theme);

// Procesar formulario de actualización
$message_txt = '';
$error_txt   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!empty($_POST['site_title'])) {
            saveSetting('site_title', trim($_POST['site_title']));
        }

        if (!empty($_POST['site_tagline'])) {
            saveSetting('site_tagline', trim($_POST['site_tagline']));
        }

        if (!empty($_POST['admin_user'])) {
            saveSetting('admin_user', trim($_POST['admin_user']));
        }

        if (!empty($_POST['admin_pass'])) {
            $hashed = password_hash(trim($_POST['admin_pass']), PASSWORD_DEFAULT);
            saveSetting('admin_password', $hashed);
        }

        $message_txt = $msg_saved;
        logAdminEvent('settings_saved', $_SESSION['admin_user'] ?? 'unknown');
    } catch (Exception $e) {
        $error_txt = $e->getMessage();
    }

    // --- Cambio de ruta del panel admin (rename físico de carpeta) ---
    // Seguridad: primero rename en disco; la DB solo se toca si tuvo éxito.
    $newPath = trim($_POST['admin_path'] ?? '');
    $currentSlug = basename(__DIR__);
    if ($newPath !== '' && $newPath !== $currentSlug) {
        $cmsRoot = dirname(__DIR__);
        $reserved = ['post', 'page', 'feed', 'sitemap.xml'];
        if (!preg_match('/^[a-z0-9][a-z0-9\-]{1,29}$/', $newPath)) {
            $error_txt = $err_path_format;
        } elseif (in_array($newPath, $reserved, true)) {
            $error_txt = $err_path_reserved;
        } elseif (file_exists($cmsRoot . '/' . $newPath)) {
            $error_txt = $err_path_exists;
        } elseif (empty($_POST['current_password'])
                 || !verifyAdminCredential($_SESSION['admin_user'] ?? '', $_POST['current_password'])) {
            $error_txt = $err_path_password;
            logAdminEvent('admin_path_change_fail', 'wrong password');
        } else {
            // No renombramos aquí: este script vive dentro de la carpeta que se va a
            // renombrar y algunos SAPI bloquean eso. Se delega en apply-admin-path.php
            // (raíz del CMS) mediante una solicitud pendiente con token de un solo uso.
            $token = bin2hex(random_bytes(16));
            @saveSetting('admin_path_pending', json_encode([
                'slug'  => $newPath,
                'time'  => time(),
                'token' => $token,
            ]));
            session_write_close();
            header('Location: ../apply-admin-path.php?token=' . $token);
            exit;
        }
    }
}

// Mensaje tras redirect desde el propio cambio de ruta
if ($message_txt === '' && isset($_GET['changed'])) {
    $message_txt = $msg_path_changed;
}

// Obtener datos actuales - con manejo de errores
try {
    $settings = getAllSettings();
    $posts_count = count(getAllPosts());
} catch (Exception $e) {
    $settings = [];
    $posts_count = 0;
}
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

            <div class="admin-nav-section"><?php echo htmlspecialchars($page_title); ?></div>
            <a href="settings.php" class="active">
                <?php echo finsec_icon('settings', 18); ?>
                <span><?php echo htmlspecialchars($page_title); ?></span>
            </a>
            <a href="logs.php">
                <?php echo finsec_icon('history', 18); ?>
                <span><?php echo htmlspecialchars($nav_logs); ?></span>
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
            <div class="admin-content-header">
                <h2><?php echo htmlspecialchars($page_title); ?></h2>
                <p class="settings-stat"><?php echo finsec_icon('posts', 14); ?> <?php echo htmlspecialchars($lbl_posts); ?> <strong><?php echo (int)$posts_count; ?></strong></p>
            </div>

            <?php if ($message_txt): ?>
                <div class="msg-banner success">
                    <?php echo finsec_icon('check', 16); ?>
                    <span><?php echo htmlspecialchars($message_txt); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_txt): ?>
                <div class="msg-banner error">
                    <?php echo finsec_icon('alert', 16); ?>
                    <span><?php echo htmlspecialchars($error_txt); ?></span>
                </div>
            <?php endif; ?>

            <!-- Apariencia -->
            <div class="admin-card">
                <h3><?php echo finsec_icon('moon', 16); ?> <?php echo htmlspecialchars($lbl_theme); ?></h3>
                <form method="GET" action="">
                    <div class="admin-form-group">
                        <label for="theme_setting"><?php echo htmlspecialchars($lbl_theme); ?></label>
                        <select id="theme_setting" name="theme_setting" onchange="this.form.submit();" class="theme-selector" style="max-width:280px;">
                            <?php echo themeSelected(themeOptions(), $theme); ?>
                        </select>
                    </div>
                </form>
            </div>

            <form method="POST" action="">
                <!-- Datos del sitio -->
                <div class="admin-card">
                    <h3><?php echo finsec_icon('folder', 16); ?> <?php echo htmlspecialchars($lbl_sitedata); ?></h3>

                    <div class="admin-form-group">
                        <label for="site_title"><?php echo htmlspecialchars($lbl_sitetitle); ?></label>
                        <input type="text" id="site_title" name="site_title" placeholder="<?php echo htmlspecialchars($ph_title); ?>" value="<?php echo htmlspecialchars($settings['site_title'] ?? 'M4 CMS'); ?>" style="max-width:480px;">
                    </div>

                    <div class="admin-form-group">
                        <label for="site_tagline"><?php echo htmlspecialchars($lbl_sitetag); ?></label>
                        <input type="text" id="site_tagline" name="site_tagline" placeholder="<?php echo htmlspecialchars($ph_tag); ?>" value="<?php echo htmlspecialchars($settings['site_tagline'] ?? 'Blog personal con PHP y SQLite'); ?>" style="max-width:480px;">
                    </div>
                </div>

                <!-- Cuenta de administrador -->
                <div class="admin-card">
                    <h3><?php echo finsec_icon('shield', 16); ?> <?php echo htmlspecialchars($lbl_adminacc); ?></h3>

                    <div class="admin-form-group">
                        <label for="admin_user"><?php echo htmlspecialchars($lbl_adminuser); ?></label>
                        <input type="text" id="admin_user" name="admin_user" value="<?php echo htmlspecialchars($settings['admin_user'] ?? ''); ?>" style="max-width:480px;">
                    </div>

                    <div class="admin-form-group">
                        <label for="admin_pass"><?php echo htmlspecialchars($lbl_newpass); ?></label>
                        <input type="password" id="admin_pass" name="admin_pass" autocomplete="new-password" style="max-width:480px;">
                    </div>
                </div>

                <!-- Ruta del panel admin -->
                <div class="admin-card">
                    <h3><?php echo finsec_icon('globe', 16); ?> <?php echo htmlspecialchars($lbl_adminpath); ?></h3>

                    <p class="help-text" style="margin-bottom:12px;"><?php echo htmlspecialchars($help_adminpath); ?></p>

                    <div class="admin-form-group">
                        <label for="admin_path">URL: /<?php echo htmlspecialchars(basename(__DIR__)); ?>/ → /</label>
                        <input type="text" id="admin_path" name="admin_path" value="<?php echo htmlspecialchars(basename(__DIR__)); ?>" autocomplete="off" class="mono" style="max-width:280px;">
                    </div>

                    <div class="admin-form-group">
                        <label for="current_password"><?php echo htmlspecialchars($lbl_pathpass); ?></label>
                        <input type="password" id="current_password" name="current_password" autocomplete="off" style="max-width:480px;">
                    </div>
                </div>

                <div>
                    <button type="submit" class="btn-primary"><?php echo finsec_icon('check', 16); ?> <?php echo htmlspecialchars($btn_save); ?></button>
                </div>
            </form>
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
