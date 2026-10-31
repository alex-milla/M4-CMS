<?php
// Estadísticas web (analítica propia, tipo Matomo)
include_once __DIR__ . '/../helpers/auth.php';
m4_session_start();
requireAdmin();

include_once '../config.php';
include_once '../db/functions.php';
include_once '../db/analytics.php';
include_once '../helpers/theme.php';
include_once '../helpers/i18n.php';
include_once '../helpers/icons.php';
include_once '../helpers/admin_layout.php';

// Rango de tiempo
$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90], true)) $days = 30;

// Métricas (nunca rompen la página si la BBDD no está disponible)
$stats = analytics_stats($days);
$stats = is_array($stats) ? $stats : [];

// Purgar registros antiguos (logrotate sin cron)
analytics_purge();

// Tema (FinSec claro/oscuro)
$theme = initTheme();
$lang  = $_SESSION['lang'] ?? 'es';

// Traducciones
list($page_title, )     = t('page_stats_title');
list($nav_dashboard, )   = t('admin_dashboard_title');
list($manage_posts, )    = t('admin_manage_posts');
list($btn_new_post, )    = t('btn_new_post');
list($nav_settings, )    = t('nav_settings');
list($nav_logout, )      = t('nav_logout');
list($nav_site, )        = t('nav_go_to_site');
list($_lbl_lang, )       = t('lbl_lang');
list($theme_lbl, )      = t('admin_theme_label');
list($nav_update, )      = t('nav_update');
list($sys_section, )     = t('admin_system_section');
list($nav_widgets, )     = t('nav_widgets');
list($nav_logs, )        = t('nav_logs');
list($st_visits, )       = t('stats_visits');
list($st_pageviews, )    = t('stats_pageviews');
list($st_ppv, )          = t('stats_pages_per_visit');
list($st_new, )          = t('stats_new_visits');
list($st_bounce, )       = t('stats_bounce_rate');
list($st_daily, )        = t('stats_daily_visits');
list($st_top_pages, )    = t('stats_top_pages');
list($st_entry_pages, )  = t('stats_entry_pages');
list($st_referrers, )    = t('stats_referrers');
list($st_devices, )      = t('stats_devices');
list($st_browsers, )     = t('stats_browsers');
list($st_errors, )       = t('stats_errors');
list($st_no_data, )      = t('stats_no_data');
list($st_unavailable, )  = t('stats_unavailable');
list($st_views, )        = t('stats_col_views');
list($st_visits_col, )   = t('stats_col_visits');
list($st_path, )         = t('stats_col_path');
list($st_referrer, )     = t('stats_col_referrer');
list($st_type, )         = t('stats_col_type');
list($st_range, )        = t('stats_range');
list($st_7d, )           = t('stats_range_7');
list($st_30d, )          = t('stats_range_30');
list($st_90d, )          = t('stats_range_90');
list($st_direct, )       = t('stats_type_direct');
list($st_search, )       = t('stats_type_search');
list($st_social, )       = t('stats_type_social');
list($st_other, )        = t('stats_type_other');
list($st_desktop, )      = t('stats_device_desktop');
list($st_mobile, )       = t('stats_device_mobile');
list($st_tablet, )       = t('stats_device_tablet');
list($st_status, )       = t('stats_col_status');
list($st_self, )         = t('stats_self_excluded');

// --- Utilidades de pintado ---

// Barra horizontal proporcional (para listas de ranking)
if (!function_exists('stats_bar_row')) {
function stats_bar_row($label, $value, $max, $mono = false) {
    $pct = ($max > 0) ? (int)round(($value / $max) * 100) : 0;
    $pct = max(0, min(100, $pct));
    $labelHtml = $mono
        ? '<span class="mono">' . htmlspecialchars((string)$label) . '</span>'
        : htmlspecialchars((string)$label);
    return '<div class="bar-row">'
        . '<span class="bar-label" title="' . htmlspecialchars((string)$label) . '">' . $labelHtml . '</span>'
        . '<span class="bar-track"><span class="bar-fill" style="width:' . $pct . '%"></span></span>'
        . '<span class="bar-value">' . (int)$value . '</span>'
        . '</div>';
}
}
?>
<?php adminLayoutHead([
    'title'  => $page_title,
    'logo'   => $page_title,
    'active' => 'stats',
    'theme'  => $theme,
]); ?>

            <div class="admin-content-header">
                <h2><?php echo htmlspecialchars($page_title); ?></h2>
                <p><?php echo htmlspecialchars($st_range); ?>:
                    <a class="range-link<?php echo $days === 7 ? ' active' : ''; ?>" href="?days=7"><?php echo htmlspecialchars($st_7d); ?></a>
                    <a class="range-link<?php echo $days === 30 ? ' active' : ''; ?>" href="?days=30"><?php echo htmlspecialchars($st_30d); ?></a>
                    <a class="range-link<?php echo $days === 90 ? ' active' : ''; ?>" href="?days=90"><?php echo htmlspecialchars($st_90d); ?></a>
                </p>
            </div>

<?php if (empty($stats['available'])): ?>
            <div class="msg-banner info">
                <?php echo finsec_icon('alert', 16); ?>
                <span><?php echo htmlspecialchars($st_unavailable); ?></span>
            </div>
<?php else: ?>

            <!-- Resumen -->
            <div class="admin-stats">
                <div class="admin-stat-card">
                    <span class="icon"><?php echo finsec_icon('globe', 18); ?></span>
                    <div class="value"><?php echo (int)($stats['visits'] ?? 0); ?></div>
                    <div class="label"><?php echo htmlspecialchars($st_visits); ?></div>
                </div>
                <div class="admin-stat-card">
                    <span class="icon"><?php echo finsec_icon('posts', 18); ?></span>
                    <div class="value"><?php echo (int)($stats['pageviews'] ?? 0); ?></div>
                    <div class="label"><?php echo htmlspecialchars($st_pageviews); ?></div>
                </div>
                <div class="admin-stat-card">
                    <span class="icon"><?php echo finsec_icon('layout', 18); ?></span>
                    <div class="value"><?php echo htmlspecialchars(number_format((float)($stats['pages_per_visit'] ?? 0), 2, ',', '.')); ?></div>
                    <div class="label"><?php echo htmlspecialchars($st_ppv); ?></div>
                </div>
                <div class="admin-stat-card">
                    <span class="icon"><?php echo finsec_icon('check', 18); ?></span>
                    <div class="value"><?php echo (int)($stats['new_visits'] ?? 0); ?></div>
                    <div class="label"><?php echo htmlspecialchars($st_new); ?></div>
                </div>
                <div class="admin-stat-card">
                    <span class="icon"><?php echo finsec_icon('alert', 18); ?></span>
                    <div class="value"><?php echo htmlspecialchars(number_format((float)($stats['bounce_rate'] ?? 0), 1, ',', '.')); ?>%</div>
                    <div class="label"><?php echo htmlspecialchars($st_bounce); ?></div>
                </div>
            </div>

            <!-- Visitas por día -->
            <div class="admin-card">
                <h3><?php echo finsec_icon('chart', 16); ?> <?php echo htmlspecialchars($st_daily); ?></h3>
<?php
$daily = $stats['daily'] ?? [];
$maxViews = 0;
foreach ($daily as $d) { $maxViews = max($maxViews, (int)$d['views']); }
if ($maxViews === 0) $maxViews = 1;
?>
                <?php if (empty($daily) || (int)($stats['pageviews'] ?? 0) === 0): ?>
                    <p class="help-text"><?php echo htmlspecialchars($st_no_data); ?></p>
                <?php else: ?>
                <div class="chart-bars">
<?php foreach ($daily as $d): ?>
<?php $h = (int)round(((int)$d['views'] / $maxViews) * 100); if ($h < 2) $h = ((int)$d['views'] > 0) ? 2 : 0; ?>
                    <div class="chart-col" title="<?php echo htmlspecialchars($d['date'] . ': ' . $d['views'] . ' ' . $st_pageviews . ' / ' . $d['visits'] . ' ' . $st_visits); ?>">
                        <span class="chart-bar" style="height:<?php echo $h; ?>%"></span>
                    </div>
<?php endforeach; ?>
                </div>
                <div class="chart-axis">
                    <span><?php echo htmlspecialchars($daily[0]['date'] ?? ''); ?></span>
                    <span><?php echo htmlspecialchars($daily[count($daily) - 1]['date'] ?? ''); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Páginas más vistas -->
            <div class="admin-card">
                <h3><?php echo finsec_icon('posts', 16); ?> <?php echo htmlspecialchars($st_top_pages); ?></h3>
                <?php if (empty($stats['pages'])): ?>
                    <p class="help-text"><?php echo htmlspecialchars($st_no_data); ?></p>
                <?php else: ?>
                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?php echo htmlspecialchars($st_path); ?></th>
                                <th><?php echo htmlspecialchars($st_views); ?></th>
                                <th><?php echo htmlspecialchars($st_visits_col); ?></th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($stats['pages'] as $p): ?>
                            <tr>
                                <td class="mono"><?php echo htmlspecialchars($p['path']); ?></td>
                                <td><?php echo (int)$p['views']; ?></td>
                                <td><?php echo (int)$p['visits']; ?></td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Páginas de entrada -->
            <div class="admin-card">
                <h3><?php echo finsec_icon('external-link', 16); ?> <?php echo htmlspecialchars($st_entry_pages); ?></h3>
                <?php if (empty($stats['entry_pages'])): ?>
                    <p class="help-text"><?php echo htmlspecialchars($st_no_data); ?></p>
                <?php else: ?>
                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?php echo htmlspecialchars($st_path); ?></th>
                                <th><?php echo htmlspecialchars($st_visits_col); ?></th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($stats['entry_pages'] as $p): ?>
                            <tr>
                                <td class="mono"><?php echo htmlspecialchars($p['path']); ?></td>
                                <td><?php echo (int)$p['visits']; ?></td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Origen del tráfico -->
            <div class="admin-card">
                <h3><?php echo finsec_icon('external-link', 16); ?> <?php echo htmlspecialchars($st_referrers); ?></h3>
<?php
$types = $stats['referrer_types'] ?? [];
$typeLabels = ['direct' => $st_direct, 'search' => $st_search, 'social' => $st_social, 'other' => $st_other];
$typeMax = 0;
foreach ($types as $v) { $typeMax = max($typeMax, (int)$v); }
if ($typeMax === 0) $typeMax = 1;
?>
                <?php if (empty($types)): ?>
                    <p class="help-text"><?php echo htmlspecialchars($st_no_data); ?></p>
                <?php else: ?>
                <div class="bars-list">
<?php foreach ($typeLabels as $key => $label): ?>
<?php $val = (int)($types[$key] ?? 0); ?>
                    <?php echo stats_bar_row($label, $val, $typeMax); ?>
<?php endforeach; ?>
                </div>

                <?php if (!empty($stats['referrers'])): ?>
                <div class="admin-table-container" style="margin-top:16px;">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?php echo htmlspecialchars($st_referrer); ?></th>
                                <th><?php echo htmlspecialchars($st_type); ?></th>
                                <th><?php echo htmlspecialchars($st_visits_col); ?></th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($stats['referrers'] as $r): ?>
                            <tr>
                                <td class="mono" title="<?php echo htmlspecialchars($r['referrer']); ?>"><?php echo htmlspecialchars($r['host'] !== '' ? $r['host'] : $r['referrer']); ?></td>
                                <td><span class="badge scheduled"><?php echo htmlspecialchars($typeLabels[$r['type']] ?? $r['type']); ?></span></td>
                                <td><?php echo (int)$r['visits']; ?></td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Dispositivos y navegadores -->
<?php
$devices = $stats['devices'] ?? [];
$browsers = $stats['browsers'] ?? [];
$devLabels = ['desktop' => $st_desktop, 'mobile' => $st_mobile, 'tablet' => $st_tablet];
$devMax = 0; foreach ($devices as $v) { $devMax = max($devMax, (int)$v); }
if ($devMax === 0) $devMax = 1;
$brMax = 0; foreach ($browsers as $v) { $brMax = max($brMax, (int)$v); }
if ($brMax === 0) $brMax = 1;
?>
            <div class="admin-card">
                <h3><?php echo finsec_icon('globe', 16); ?> <?php echo htmlspecialchars($st_devices); ?></h3>
                <?php if (empty($devices)): ?>
                    <p class="help-text"><?php echo htmlspecialchars($st_no_data); ?></p>
                <?php else: ?>
                <div class="bars-list">
<?php foreach ($devices as $key => $val): ?>
                    <?php echo stats_bar_row(isset($devLabels[$key]) ? $devLabels[$key] : $key, (int)$val, $devMax); ?>
<?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="admin-card">
                <h3><?php echo finsec_icon('layout', 16); ?> <?php echo htmlspecialchars($st_browsers); ?></h3>
                <?php if (empty($browsers)): ?>
                    <p class="help-text"><?php echo htmlspecialchars($st_no_data); ?></p>
                <?php else: ?>
                <div class="bars-list">
<?php foreach ($browsers as $key => $val): ?>
                    <?php echo stats_bar_row($key === 'other' ? t('stats_other_browser')[0] : $key, (int)$val, $brMax); ?>
<?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Errores -->
            <div class="admin-card">
                <h3><?php echo finsec_icon('alert', 16); ?> <?php echo htmlspecialchars($st_errors); ?></h3>
                <?php if (empty($stats['statuses'])): ?>
                    <p class="help-text"><?php echo htmlspecialchars($st_no_data); ?></p>
                <?php else: ?>
                <div class="bars-list">
<?php foreach ($stats['statuses'] as $code => $val): ?>
                    <?php echo stats_bar_row($code, (int)$val, max(1, (int)$stats['pageviews'])); ?>
<?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <p class="help-text"><?php echo htmlspecialchars($st_self); ?></p>
<?php endif; ?>
<?php adminLayoutFooter(); ?>
