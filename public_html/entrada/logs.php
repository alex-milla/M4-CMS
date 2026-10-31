<?php
// Registro de actividad admin + intentos de login + IPs bloqueadas
include_once __DIR__ . '/../helpers/auth.php';
m4_session_start();
requireAdmin();

include_once '../config.php';
include_once '../db/functions.php';
include_once '../helpers/theme.php';
include_once '../helpers/i18n.php';
include_once '../helpers/icons.php';
include_once '../helpers/csrf.php';
include_once '../helpers/client.php';
include_once '../helpers/admin_layout.php';

// Asegurar que las tablas existen (instalación nueva o BBDD vacía)
createTable();

// Purgar logs viejos en cada visita (logrotate sin cron)
purgeOldLogs(90);

// ------------------------------------------------------------
//  Vistas: actividad | login | banned
// ------------------------------------------------------------
$view = (string)($_GET['view'] ?? 'activity');
if (!in_array($view, ['activity', 'login', 'banned'], true)) $view = 'activity';

$msg_txt = '';
$err_txt = '';

// Mensaje tras un redirect (PRG): evita repetir la acción al recargar
$flash = (string)($_GET['msg'] ?? '');
if ($flash === 'ban')        $msg_txt = t('msg_ip_banned')[0];
elseif ($flash === 'unban') $msg_txt = t('msg_ip_unbanned')[0];

// --- Acciones: bloquear / desbloquear IP ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValidate()) {
        $err_txt = t('err_csrf')[0];
        logAdminEvent('ip_ban_rejected', 'invalid csrf');
    } else {
        $action = (string)($_POST['action'] ?? '');
        $ip     = trim((string)($_POST['ip'] ?? ''));
        $note   = trim((string)($_POST['note'] ?? ''));

        if ($action === 'ban' && $ip !== '') {
            if (banIp($ip, $note)) {
                logAdminEvent('ip_banned', $ip . ($note !== '' ? ' · ' . $note : ''));
                header('Location: ?view=' . urlencode($view) . '&msg=ban');
                exit;
            } else {
                $err_txt = t('err_ip_ban_failed')[0];
            }
        } elseif ($action === 'unban' && $ip !== '') {
            if (unbanIp($ip)) {
                logAdminEvent('ip_unbanned', $ip);
                header('Location: ?view=' . urlencode($view) . '&msg=unban');
                exit;
            } else {
                $err_txt = t('err_ip_ban_failed')[0];
            }
        }
    }
}

// Paginacion
$perPage = 50;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;

$loginEvents = loginEventTypes();
$isLoginView = ($view === 'login');

if ($isLoginView) {
    $logs      = getAdminLogsFiltered($perPage, $offset, $loginEvents);
    $totalLogs = countAdminLogsFiltered($loginEvents);
} else {
    $logs      = getAdminLogsFiltered($perPage, $offset, null);
    $totalLogs = countAdminLogsFiltered(null);
}
$totalPages = max(1, (int)ceil($totalLogs / $perPage));

$bannedIps = ($view === 'banned') ? getBannedIps() : [];
$bannedCount = countBannedIps();

// Mapa de IPs bloqueadas para marcar las filas del log de login
$bannedMap = [];
if ($isLoginView && $totalLogs > 0) {
    foreach (getBannedIps(1000) as $b) {
        $bannedMap[(string)$b['ip']] = true;
    }
}

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
list($nav_settings, )   = t('nav_settings');
list($nav_logout, )     = t('nav_logout');
list($nav_site, )       = t('nav_go_to_site');
list($tbl_date, )       = t('tbl_col_date');
list($tbl_event, )      = t('tbl_col_event');
list($tbl_detail, )     = t('tbl_col_detail');
list($tbl_ip, )         = t('tbl_col_ip');
list($no_logs, )        = t('no_logs');
list($_lbl_lang, )      = t('lbl_lang');
list($theme_lbl, )     = t('admin_theme_label');
list($lbl_page, )       = t('lbl_page');
list($lbl_of, )         = t('lbl_of');
list($btn_prev, )       = t('btn_prev');
list($btn_next, )       = t('btn_next');
list($nav_update, )     = t('nav_update');
list($sys_section, )    = t('admin_system_section');
list($nav_widgets, )    = t('nav_widgets');
list($tab_activity, )   = t('logs_tab_activity');
list($tab_login, )      = t('logs_tab_login');
list($tab_banned, )     = t('logs_tab_banned');
list($lbl_banned_ips, ) = t('logs_banned_ips');
list($btn_ban_ip, )     = t('logs_btn_ban_ip');
list($btn_unban_ip, )   = t('logs_btn_unban_ip');
list($lbl_user_attempt,) = t('logs_col_user');
list($lbl_note, )       = t('logs_col_note');
list($lbl_actions, )    = t('logs_col_actions');
list($msg_ip_banned, )  = t('msg_ip_banned');
list($msg_ip_unbanned,) = t('msg_ip_unbanned');
list($err_ip_ban_failed,) = t('err_ip_ban_failed');
list($err_csrf, )       = t('err_csrf');
list($lbl_ua, )         = t('logs_col_ua');
list($badge_proxy, )    = t('logs_proxy_badge');
list($badge_bot, )      = t('logs_bot_badge');
list($help_client_ip, ) = t('logs_help_client_ip');
?>
<?php adminLayoutHead([
    'title'  => $page_title,
    'logo'   => $page_title,
    'active' => 'logs',
    'theme'  => $theme,
]); ?>

            <?php if ($msg_txt): ?>
                <div class="msg-banner success">
                    <?php echo finsec_icon('check', 16); ?>
                    <span><?php echo htmlspecialchars($msg_txt); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($err_txt): ?>
                <div class="msg-banner error">
                    <?php echo finsec_icon('alert', 16); ?>
                    <span><?php echo htmlspecialchars($err_txt); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($isLoginView): ?>
                <p class="help-text" style="margin-bottom:12px;"><?php echo htmlspecialchars($help_client_ip); ?></p>
            <?php endif; ?>

            <!-- Pestañas de vista -->
            <div class="log-tabs">
                <a class="log-tab<?php echo $view === 'activity' ? ' active' : ''; ?>" href="?view=activity"><?php echo htmlspecialchars($tab_activity); ?></a>
                <a class="log-tab<?php echo $view === 'login' ? ' active' : ''; ?>" href="?view=login"><?php echo finsec_icon('shield', 14); ?> <?php echo htmlspecialchars($tab_login); ?></a>
                <a class="log-tab<?php echo $view === 'banned' ? ' active' : ''; ?>" href="?view=banned"><?php echo finsec_icon('ban', 14); ?> <?php echo htmlspecialchars($tab_banned); ?> (<?php echo (int)$bannedCount; ?>)</a>
            </div>

            <?php if ($view === 'banned'): ?>
                <!-- IPs bloqueadas -->
                <div class="admin-table-container">
                    <?php if (empty($bannedIps)): ?>
                        <div class="admin-empty">
                            <?php echo finsec_icon('shield', 32); ?>
                            <p><?php echo htmlspecialchars(t('logs_no_banned')[0]); ?></p>
                        </div>
                    <?php else: ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th><?php echo htmlspecialchars($tbl_ip); ?></th>
                                    <th><?php echo htmlspecialchars($lbl_note); ?></th>
                                    <th><?php echo htmlspecialchars($tbl_date); ?></th>
                                    <th><?php echo htmlspecialchars($lbl_actions); ?></th>
                                </tr>
                            </thead>
                            <tbody>
<?php foreach ($bannedIps as $ban): ?>
                                <tr>
                                    <td class="mono"><?php echo htmlspecialchars($ban['ip']); ?></td>
                                    <td><?php echo htmlspecialchars($ban['note'] ?? ''); ?></td>
                                    <td class="mono"><?php echo htmlspecialchars($ban['created_at'] ?? ''); ?></td>
                                    <td>
                                        <form method="POST" action="?view=banned" class="inline-form">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="unban">
                                            <input type="hidden" name="ip" value="<?php echo htmlspecialchars($ban['ip']); ?>">
                                            <button type="submit" class="btn-link danger"><?php echo finsec_icon('x', 14); ?> <?php echo htmlspecialchars($btn_unban_ip); ?></button>
                                        </form>
                                    </td>
                                </tr>
<?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php elseif (empty($logs)): ?>
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
<?php if ($isLoginView): ?>
                            <th><?php echo htmlspecialchars($lbl_user_attempt); ?></th>
<?php else: ?>
                            <th><?php echo htmlspecialchars($tbl_detail); ?></th>
<?php endif; ?>
                            <th><?php echo htmlspecialchars($tbl_ip); ?></th>
<?php if ($isLoginView): ?>
                            <th><?php echo htmlspecialchars($lbl_ua); ?></th>
                            <th><?php echo htmlspecialchars($lbl_actions); ?></th>
<?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
<?php foreach ($logs as $log): ?>
<?php
$rowIp = (string)($log['ip'] ?? '');
$rowUa = (string)($log['ua'] ?? '');
$isProxyIp = ($rowIp !== '' && function_exists('m4_is_trusted_proxy_ip') && m4_is_trusted_proxy_ip($rowIp));
$isBotUa = ($rowUa !== '' && function_exists('m4_ua_is_bot') && m4_ua_is_bot($rowUa));
?>
                        <tr>
                            <td class="mono"><?php echo htmlspecialchars($log['created_at']); ?></td>
                            <td><span class="badge scheduled"><?php echo htmlspecialchars($log['event']); ?></span></td>
<?php if ($isLoginView): ?>
                            <td><?php echo htmlspecialchars($log['detail'] ?? ''); ?></td>
<?php else: ?>
                            <td><?php echo htmlspecialchars($log['detail'] ?? ''); ?></td>
<?php endif; ?>
                            <td class="mono"><?php echo htmlspecialchars($rowIp); ?><?php if ($isLoginView && !empty($bannedMap[$rowIp])): ?> <span class="badge danger"><?php echo htmlspecialchars(t('logs_banned_badge')[0]); ?></span><?php endif; ?><?php if ($isLoginView && $isProxyIp): ?> <span class="badge draft"><?php echo htmlspecialchars($badge_proxy); ?></span><?php endif; ?></td>
<?php if ($isLoginView): ?>
                            <td title="<?php echo htmlspecialchars($rowUa); ?>">
                                <?php echo htmlspecialchars($rowUa !== '' ? m4_ua_summary($rowUa) : '—'); ?>
<?php if ($isBotUa): ?> <span class="badge danger"><?php echo htmlspecialchars($badge_bot); ?></span><?php endif; ?>
                            </td>
<?php endif; ?>
<?php if ($isLoginView): ?>
                            <td>
<?php if ($rowIp !== '' && $rowIp !== 'unknown' && !$isProxyIp): ?>
<?php if (!empty($bannedMap[$rowIp])): ?>
                                <form method="POST" action="?view=login" class="inline-form">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="unban">
                                    <input type="hidden" name="ip" value="<?php echo htmlspecialchars($rowIp); ?>">
                                    <button type="submit" class="btn-link"><?php echo finsec_icon('x', 14); ?> <?php echo htmlspecialchars($btn_unban_ip); ?></button>
                                </form>
<?php else: ?>
                                <form method="POST" action="?view=login" class="inline-form">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="ban">
                                    <input type="hidden" name="ip" value="<?php echo htmlspecialchars($rowIp); ?>">
                                    <button type="submit" class="btn-link danger"><?php echo finsec_icon('ban', 14); ?> <?php echo htmlspecialchars($btn_ban_ip); ?></button>
                                </form>
<?php endif; ?>
<?php elseif ($isProxyIp): ?>
                                <span class="help-text"><?php echo htmlspecialchars($badge_proxy); ?></span>
<?php endif; ?>
                            </td>
<?php endif; ?>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="admin-pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="?view=<?php echo htmlspecialchars($view); ?>&amp;page=<?php echo $currentPage - 1; ?>"><?php echo finsec_icon('chevron-left', 14); ?> <?php echo htmlspecialchars($btn_prev); ?></a>
                <?php endif; ?>
                <span><?php echo htmlspecialchars($lbl_page); ?> <?php echo $currentPage; ?> <?php echo htmlspecialchars($lbl_of); ?> <?php echo $totalPages; ?></span>
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?view=<?php echo htmlspecialchars($view); ?>&amp;page=<?php echo $currentPage + 1; ?>"><?php echo htmlspecialchars($btn_next); ?> <?php echo finsec_icon('chevron-right', 14); ?></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
<?php adminLayoutFooter(); ?>
