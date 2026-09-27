<?php
ob_start();
include_once __DIR__ . '/../helpers/session.php';
m4_session_start();

// --- i18n setup (login needs translations for UI) ---
include_once '../helpers/i18n.php';
include_once '../helpers/theme.php';
include_once '../helpers/icons.php';
include_once '../db/functions.php';
include_once __DIR__ . '/../helpers/csrf.php';
$lang = $_SESSION['lang'] ?? 'es';

// --- Theme initialization (FinSec: claro / oscuro) ---
$theme = 'finsec';

if (isset($_SESSION['theme'])) {
    $theme = normalizeTheme($_SESSION['theme']);
} elseif (!empty($_GET['site_theme']) || !empty($_GET['theme_setting'])) {
    $theme = normalizeTheme($_GET['site_theme'] ?? $_GET['theme_setting']);
} else {
    // Fallback: leer tema guardado en la DB
    try {
        $db_theme = getSiteTheme();
        if ($db_theme) $theme = $db_theme;
    } catch (\Throwable $e) { /* keep default finsec */ }
}

$class = 'theme-' . htmlspecialchars($theme);

// --- Traducciones ---
list($page_title, )    = t('page_login_title');
list($lbl_user, )       = t('label_user');
list($lbl_pass, )      = t('lbl_password');
list($ph_user, )       = t('ph_username_ph');
list($ph_pass, )       = t('ph_password_ph');
list($btn_enter, )     = t('btn_login_enter');
list($err_credentials, ) = t('err_user_pass_incorrect');
list($err_csrf, )        = t('err_csrf');
list($err_rate_limited, ) = t('err_login_rate_limited');
list($_back_site, )    = t('nav_back_to_site');

// Título de marca: nombre del sitio desde settings
try {
    $settings = getAllSettings();
    $siteTitle = $settings['site_title'] ?? 'M4 CMS';
    $siteTagline = $settings['site_tagline'] ?? '';
} catch (\Throwable $e) {
    $siteTitle = 'M4 CMS';
    $siteTagline = '';
}

// --- Credential verification: verifyAdminCredential() vive en db/functions.php ---

$error = null;
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['password'] ?? '';

    if (!csrfValidate()) {
        $error = $err_csrf;
    } elseif (loginAttemptsExceeded($clientIp)) {
        logAdminEvent('login_blocked', (string)$user);
        $error = $err_rate_limited;
    } elseif (!empty($user) && !empty($pass)) {
        if (verifyAdminCredential($user, $pass)) {
            logAdminEvent('login_success', $user);
            purgeOldLogs(90);
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $user;
            $_SESSION['theme'] = $theme;
            session_write_close();
            header('Location: posts.php');
            exit;
        } else {
            logAdminEvent('login_fail', $user);
            $error = $err_credentials;
        }
    }
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
    <div class="login-shell">
        <div class="login-col">
            <!-- Bloque de marca -->
            <div class="login-brand">
                <div class="login-logo"><?php echo finsec_icon('feather', 32); ?></div>
                <h1 class="login-title"><?php echo htmlspecialchars($siteTitle); ?></h1>
                <?php if ($siteTagline !== ''): ?>
                <p class="login-subtitle"><?php echo htmlspecialchars($siteTagline); ?></p>
                <?php endif; ?>
            </div>

            <!-- Tarjeta formulario -->
            <form method="POST" action="" class="login-card login-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="theme_setting" value="<?php echo $theme; ?>">

                <div>
                    <label for="user"><?php echo htmlspecialchars($lbl_user); ?></label>
                    <input type="text" id="user" name="user" required autofocus autocomplete="username"
                           class="login-input"
                           placeholder="<?php echo htmlspecialchars($ph_user); ?>"
                           value="<?php echo htmlspecialchars($_POST['user'] ?? ''); ?>">
                </div>

                <div>
                    <label for="password"><?php echo htmlspecialchars($lbl_pass); ?></label>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           class="login-input"
                           placeholder="<?php echo htmlspecialchars($ph_pass); ?>">
                </div>

                <?php if ($error): ?>
                    <div class="login-error">
                        <?php echo finsec_icon('alert', 16); ?>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <button type="submit" class="login-btn">
                    <?php echo htmlspecialchars($btn_enter); ?>
                </button>
            </form>

            <div class="login-footer">
                <a href="../index.php"><?php echo finsec_icon('arrow-left', 14); ?> <?php echo htmlspecialchars($_back_site); ?></a>
            </div>
        </div>
    </div>
</body>
</html>
