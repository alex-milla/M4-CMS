<?php
// apply-admin-path.php - Aplica el cambio de ruta del panel admin.
// Se invoca con un redirect desde admin/settings.php (que vive dentro de la
// carpeta a renombrar; hacerlo desde la raíz evita bloqueos del SAPI/FS).
include_once __DIR__ . '/helpers/session.php';
m4_session_start();
include_once __DIR__ . '/db/functions.php';
include_once __DIR__ . '/helpers/i18n.php';

list($msg_invalid, ) = t('msg_apply_invalid');
list($btn_back, )    = t('btn_apply_back');
list($lbl_section, ) = t('cfg_admin_path_label');
list($err_format, )  = t('err_admin_path_format');
list($err_reserved, ) = t('err_admin_path_reserved');
list($err_exists, )  = t('err_admin_path_exists');
list($err_rename, )  = t('err_admin_path_rename');

// Solo admins logueados
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('Forbidden');
}

// Leer y validar la solicitud pendiente (token + caducidad 5 min)
$raw     = function_exists('getSetting') ? @getSetting('admin_path_pending') : null;
$pending = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
$token   = $_GET['token'] ?? '';

$valid = is_array($pending)
    && !empty($pending['slug']) && is_string($pending['slug'])
    && !empty($pending['token']) && is_string($pending['token'])
    && hash_equals($pending['token'], $token)
    && (time() - (int)($pending['time'] ?? 0)) <= 300;

if (!$valid) {
    @saveSetting('admin_path_pending', '');
    render_page($msg_invalid, true);
    exit;
}

$slug        = $pending['slug'];
$currentSlug = adminSlug();
$cmsRoot     = __DIR__;

// Re-validar todo (la solicitud pudo quedar stale entre el POST y este apply)
if (!preg_match('/^[a-z0-9][a-z0-9\-]{1,29}$/', $slug)) {
    finish_fail($err_format, $currentSlug);
} elseif (in_array($slug, ['post', 'page', 'feed', 'sitemap.xml'], true)) {
    finish_fail($err_reserved, $currentSlug);
} elseif (file_exists($cmsRoot . '/' . $slug) || !file_exists($cmsRoot . '/' . $currentSlug)) {
    finish_fail($err_exists, $currentSlug);
} elseif (!@rename($cmsRoot . '/' . $currentSlug, $cmsRoot . '/' . $slug)) {
    finish_fail($err_rename, $currentSlug);
}

// Éxito: persistir ruta, limpiar pendiente y redirigir al panel en su nueva URL
@saveSetting('admin_path', $slug);
@saveSetting('admin_path_pending', '');
logAdminEvent('admin_path_changed', $currentSlug . ' -> ' . $slug);
session_write_close();
header('Location: ' . $slug . '/settings.php?changed=1');
exit;

function finish_fail($msg, $currentSlug) {
    @saveSetting('admin_path_pending', '');
    logAdminEvent('admin_path_change_fail', 'apply rename failed');
    render_page($msg, true, $currentSlug);
    exit;
}

function render_page($msg, $isError = false, $slug = null) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php
        $st = function_exists('getSetting') ? @getSetting('site_title') : '';
        echo htmlspecialchars(is_string($st) && $st !== '' ? $st : 'Error');
    ?></title>
</head>
<body style="font-family: monospace; background: #1a1a2e; color: #eee; display: flex; min-height: 100vh; align-items: center; justify-content: center; margin: 0;">
    <div style="background: #16213e; border: 2px solid <?php echo $isError ? '#aa0000' : '#0f0'; ?>; padding: 30px; max-width: 480px;">
        <p><?php echo htmlspecialchars($msg); ?></p>
        <?php if ($slug): ?>
        <p><a href="<?php echo htmlspecialchars($slug); ?>/settings.php" style="color: #4CAF50;">&larr; <?php echo htmlspecialchars($GLOBALS['btn_back']); ?></a></p>
        <?php endif; ?>
    </div>
</body>
</html>
    <?php
}
