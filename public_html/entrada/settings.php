<?php
ob_start();
include_once __DIR__ . '/../helpers/auth.php';
m4_session_start();
requireAdmin();

// Incluir primero los archivos necesarios para evitar errores 500
include_once '../db/functions.php';
include_once '../helpers/theme.php';
include_once '../helpers/i18n.php';
include_once '../helpers/icons.php';
include_once '../helpers/csrf.php';
include_once '../helpers/admin_layout.php';

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
list($nav_update, )     = t('nav_update');
list($sys_section, )    = t('admin_system_section');
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
list($nav_widgets, )   = t('nav_widgets');
list($err_csrf, )          = t('err_csrf');
list($err_creds_password, ) = t('err_creds_password');
list($lbl_admin_curpass, ) = t('lbl_admin_current_password');

$class = 'theme-' . htmlspecialchars($theme);

// Procesar formulario de actualización
$message_txt = '';
$error_txt   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValidate()) {
        // Token CSRF ausente o inválido: no se procesa ninguna escritura.
        $error_txt = $err_csrf;
        logAdminEvent('settings_saved', 'rejected: invalid csrf');
    } else {
        try {
            if (!empty($_POST['site_title'])) {
                saveSetting('site_title', trim($_POST['site_title']));
            }

            if (!empty($_POST['site_tagline'])) {
                saveSetting('site_tagline', trim($_POST['site_tagline']));
            }

            // Cambios de cuenta admin (usuario o contraseña): exigen la
            // contraseña actual. Evita que un CSRF cambie las credenciales.
            $currentStoredUser = (string)(getSetting('admin_user') ?? '');
            $newUser = trim((string)($_POST['admin_user'] ?? ''));
            $newPass = (string)($_POST['admin_pass'] ?? '');
            $credsChange = ($newPass !== '') || ($newUser !== '' && $newUser !== $currentStoredUser);

            if ($credsChange) {
                $currentPass = (string)($_POST['admin_current_password'] ?? '');
                if ($currentPass === ''
                    || !verifyAdminCredential($_SESSION['admin_user'] ?? '', $currentPass)) {
                    $error_txt = $err_creds_password;
                    logAdminEvent('settings_saved', 'rejected: wrong current password');
                } else {
                    if ($newUser !== '') {
                        saveSetting('admin_user', $newUser);
                        $_SESSION['admin_user'] = $newUser;
                    }
                    if ($newPass !== '') {
                        $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                        saveSetting('admin_password', $hashed);
                    }
                    $message_txt = $msg_saved;
                    logAdminEvent('settings_saved', $_SESSION['admin_user'] ?? 'unknown');
                }
            } else {
                $message_txt = $msg_saved;
                logAdminEvent('settings_saved', $_SESSION['admin_user'] ?? 'unknown');
            }
        } catch (Exception $e) {
            error_log('settings save failed: ' . $e->getMessage());
            $error_txt = t('err_generic')[0];
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
<?php adminLayoutHead([
    'title'  => $page_title,
    'logo'   => $page_title,
    'active' => 'settings',
    'theme'  => $theme,
]); ?>
            <div class="admin-content-header">
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
                <?php echo csrfField(); ?>
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

                    <div class="admin-form-group">
                        <label for="admin_current_password"><?php echo htmlspecialchars($lbl_admin_curpass); ?></label>
                        <input type="password" id="admin_current_password" name="admin_current_password" autocomplete="current-password" style="max-width:480px;">
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
<?php adminLayoutFooter(); ?>
