<?php
// Registro de actividad admin
include_once __DIR__ . '/../helpers/auth.php';
m4_session_start();
requireAdmin();

include_once '../config.php';
include_once '../helpers/theme.php';
include_once '../helpers/i18n.php';
include_once '../helpers/icons.php';
include_once '../helpers/admin_layout.php';

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
<?php adminLayoutHead([
    'title'  => $page_title,
    'logo'   => $page_title,
    'active' => 'logs',
    'theme'  => $theme,
]); ?>
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
<?php adminLayoutFooter(); ?>
