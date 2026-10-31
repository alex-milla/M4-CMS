<?php
// Estadísticas web (analítica propia, estilo Matomo)
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

// Asegurar que las tablas existen (instalación nueva o BBDD vacía)
createTable();

// ------------------------------------------------------------
//  Periodo seleccionado (estilo Matomo)
// ------------------------------------------------------------
$pdo = analytics_db();
$today = $pdo ? analytics_today_date($pdo) : date('Y-m-d');

/** Devuelve [inicio, fin, etiqueta] para un preset de periodo. */
function stats_period($preset, $today) {
    $ts = strtotime($today);
    $d  = function ($n) use ($ts) { return date('Y-m-d', strtotime($n, $ts)); };

    switch ($preset) {
        case 'today':
            return [$today, $today, 'today'];
        case 'yesterday':
            return [$d('-1 day'), $d('-1 day'), 'yesterday'];
        case 'this_week':
            $dow = (int)date('N', $ts);           // 1 = lunes
            return [$d('-' . ($dow - 1) . ' days'), $today, 'this_week'];
        case 'last_week':
            $dow = (int)date('N', $ts);
            return [$d('-' . ($dow + 6) . ' days'), $d('-' . $dow . ' days'), 'last_week'];
        case 'this_month':
            return [date('Y-m-01', $ts), $today, 'this_month'];
        case 'last_month':
            $first = date('Y-m-01', $ts);
            return [date('Y-m-01', strtotime($first . ' -1 day')), date('Y-m-t', strtotime($first . ' -1 day')), 'last_month'];
        case 'this_year':
            return [date('Y-01-01', $ts), $today, 'this_year'];
        case 'last_7':
            return [$d('-6 days'), $today, 'last_7'];
        case 'last_30':
            return [$d('-29 days'), $today, 'last_30'];
        case 'last_90':
            return [$d('-89 days'), $today, 'last_90'];
    }
    return [$d('-29 days'), $today, 'last_30'];
}

$preset = (string)($_GET['period'] ?? 'last_30');
$customStart = trim((string)($_GET['from'] ?? ''));
$customEnd   = trim((string)($_GET['to'] ?? ''));

if ($customStart !== '' && $customEnd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customStart) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customEnd)) {
    $start = min($customStart, $customEnd);
    $end   = max($customStart, $customEnd);
    $preset = 'custom';
} else {
    list($start, $end, $preset) = stats_period($preset, $today);
}

// Métricas (nunca rompen la página si la BBDD no está disponible)
$stats = analytics_stats($start, $end);
$stats = is_array($stats) ? $stats : [];
$prev  = $stats['previous'] ?? [];

// Purgar registros antiguos (logrotate sin cron)
analytics_purge();

// Perfil de visitante (opcional)
$profileId = trim((string)($_GET['profile'] ?? ''));
$profile = ($profileId !== '') ? analytics_visitor_profile($pdo, $profileId, $start, $end) : null;

// Tema (FinSec claro/oscuro)
$theme = initTheme();
$lang  = $_SESSION['lang'] ?? 'es';

// ------------------------------------------------------------
//  Utilidades de pintado
// ------------------------------------------------------------

/** Formatea segundos como H:MM:SS (estilo Matomo). */
function stats_duration($seconds) {
    $seconds = max(0, (int)$seconds);
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    if ($h > 0) return sprintf('%d:%02d:%02d', $h, $m, $s);
    return sprintf('%d:%02d', $m, $s);
}

/** Variación porcentual respecto al periodo anterior. */
function stats_delta($current, $previous) {
    $current  = (float)$current;
    $previous = (float)$previous;
    if ($previous <= 0) {
        return ['text' => '—', 'class' => 'flat', 'pct' => null];
    }
    $pct = (($current - $previous) / $previous) * 100;
    $abs = abs($pct);
    if ($abs < 0.05) return ['text' => '0%', 'class' => 'flat', 'pct' => 0.0];
    return [
        'text'  => ($pct > 0 ? '▲ +' : '▼ −') . number_format($abs, 1, ',', '.') . '%',
        'class' => ($pct > 0 ? 'up' : 'down'),
        'pct'   => $pct,
    ];
}

/** Gráfico de líneas SVG (visitas + páginas vistas). */
function stats_line_chart($daily) {
    $count = count($daily);
    if ($count === 0) return '';
    $w = 1000; $h = 300; $padL = 46; $padR = 12; $padT = 16; $padB = 34;
    $max = 1;
    foreach ($daily as $d) {
        $max = max($max, (int)$d['visits'], (int)$d['views']);
    }
    $step = ($count > 1) ? ($w - $padL - $padR) / ($count - 1) : 0;
    $x = function ($i) use ($padL, $step) { return $padL + $i * $step; };
    $y = function ($v) use ($h, $padT, $padB, $max) { return $padT + ($h - $padT - $padB) * (1 - ($v / $max)); };

    $svg = '<svg class="chart-svg" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-label="Visitas en el tiempo">';

    // Rejilla y eje Y
    for ($g = 0; $g <= 4; $g++) {
        $val = (int)round($max * $g / 4);
        $yy  = $y($val);
        $svg .= '<line x1="' . $padL . '" y1="' . round($yy, 1) . '" x2="' . ($w - $padR) . '" y2="' . round($yy, 1) . '" class="chart-grid" />';
        $svg .= '<text x="' . ($padL - 8) . '" y="' . round($yy + 4, 1) . '" class="chart-axis-label" text-anchor="end">' . $val . '</text>';
    }

    // Series
    $ptsVisits = []; $ptsViews = [];
    foreach ($daily as $i => $d) {
        $ptsVisits[] = round($x($i), 1) . ',' . round($y((int)$d['visits']), 1);
        $ptsViews[]  = round($x($i), 1) . ',' . round($y((int)$d['views']), 1);
    }
    $svg .= '<polyline points="' . implode(' ', $ptsViews) . '" class="chart-line chart-line--views" />';
    $svg .= '<polyline points="' . implode(' ', $ptsVisits) . '" class="chart-line chart-line--visits" />';

    // Eje X (máx. 8 etiquetas)
    $every = max(1, (int)ceil($count / 8));
    foreach ($daily as $i => $d) {
        if ($i % $every !== 0 && $i !== $count - 1) continue;
        $label = date('d/m', strtotime($d['date']));
        $svg .= '<text x="' . round($x($i), 1) . '" y="' . ($h - 10) . '" class="chart-axis-label" text-anchor="middle">' . htmlspecialchars($label) . '</text>';
    }
    $svg .= '</svg>';
    return $svg;
}

/** Mini gráfico de tendencia (sparkline). */
function stats_sparkline($values, $class = 'spark--up') {
    $values = array_values(array_map('intval', $values));
    $count = count($values);
    if ($count < 2) return '';
    $max = max(1, max($values));
    $w = 120; $h = 32;
    $pts = [];
    foreach ($values as $i => $v) {
        $x = round($i * ($w / ($count - 1)), 1);
        $y = round($h - 2 - (($h - 4) * ($v / $max)), 1);
        $pts[] = $x . ',' . $y;
    }
    return '<svg class="spark ' . htmlspecialchars($class) . '" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" aria-hidden="true">'
        . '<polyline points="' . implode(' ', $pts) . '" /></svg>';
}

/** Fila etiqueta/valor (estilo "Resumen de visitas" de Matomo). */
function stats_metric_row($label, $value, $extra = '') {
    return '<div class="metric-row"><span class="metric-label">' . htmlspecialchars($label) . '</span>'
        . '<span class="metric-value">' . $value . ($extra !== '' ? ' ' . $extra : '') . '</span></div>';
}

/** Agrupa acciones consecutivas repetidas y limita las que se muestran. */
function stats_compact_actions($actions, $limit = 10) {
    $out = [];
    foreach ((array)$actions as $a) {
        $n = count($out);
        if ($n > 0
            && $out[$n - 1]['path'] === $a['path']
            && (int)$out[$n - 1]['status'] === (int)$a['status']) {
            $out[$n - 1]['count']++;
            $out[$n - 1]['at'] = $a['at'];
        } else {
            $a['count'] = 1;
            $out[] = $a;
        }
    }
    $total = count($out);
    $hidden = max(0, $total - $limit);
    return ['items' => array_slice($out, 0, $limit), 'hidden' => $hidden];
}

/** Barra de ranking con etiqueta y valor. */
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

// ------------------------------------------------------------
//  Traducciones
// ------------------------------------------------------------
list($page_title, )      = t('page_stats_title');
list($nav_dashboard, )    = t('admin_dashboard_title');
list($manage_posts, )     = t('admin_manage_posts');
list($btn_new_post, )     = t('btn_new_post');
list($nav_settings, )     = t('nav_settings');
list($nav_logout, )       = t('nav_logout');
list($nav_site, )         = t('nav_go_to_site');
list($_lbl_lang, )        = t('lbl_lang');
list($theme_lbl, )        = t('admin_theme_label');
list($nav_update, )       = t('nav_update');
list($sys_section, )      = t('admin_system_section');
list($nav_widgets, )      = t('nav_widgets');
list($nav_logs, )         = t('nav_logs');
list($nav_stats, )        = t('nav_stats');

list($st_visits, )        = t('stats_visits');
list($st_unique_visitors, )= t('stats_unique_visitors');
list($st_actions, )       = t('stats_actions');
list($st_action_one, )    = t('stats_action_one');
list($st_bounce_short, )  = t('stats_bounce_short');
list($st_pageviews, )     = t('stats_pageviews');
list($st_unique_pages, )  = t('stats_unique_pageviews');
list($st_max_actions, )   = t('stats_max_actions');
list($st_apv, )           = t('stats_pages_per_visit');
list($st_duration, )      = t('stats_avg_duration');
list($st_bounce, )        = t('stats_bounce_rate');
list($st_searches, )      = t('stats_searches');
list($st_keywords, )      = t('stats_unique_keywords');
list($st_over_time, )     = t('stats_visits_over_time');
list($st_visit_summary, ) = t('stats_visit_summary');
list($st_action_metrics, )= t('stats_action_metrics');
list($st_overview, )      = t('stats_overview');
list($st_channels, )      = t('stats_channel_types');
list($st_visit_log, )     = t('stats_visit_log');
list($st_realtime, )      = t('stats_realtime');
list($st_last_30min, )    = t('stats_last_30min');
list($st_last_24h, )      = t('stats_last_24h');
list($st_top_pages, )     = t('stats_top_pages');
list($st_entry_pages, )   = t('stats_entry_pages');
list($st_keywords_tbl, )  = t('stats_keywords_table');
list($st_referrers, )     = t('stats_referrers');
list($st_devices, )       = t('stats_devices');
list($st_browsers, )      = t('stats_browsers');
list($st_os, )            = t('stats_os_family');
list($st_hourly, )        = t('stats_visits_per_hour');
list($st_statuses, )      = t('stats_errors');
list($st_no_data, )       = t('stats_no_data');
list($st_unavailable, )   = t('stats_unavailable');
list($st_range, )         = t('stats_range');
list($st_custom, )        = t('stats_custom_range');
list($st_from, )          = t('stats_from');
list($st_to, )            = t('stats_to');
list($st_apply, )         = t('stats_apply');
list($st_views, )         = t('stats_col_views');
list($st_visits_col, )    = t('stats_col_visits');
list($st_path, )          = t('stats_col_path');
list($st_referrer, )      = t('stats_col_referrer');
list($st_type, )          = t('stats_col_type');
list($st_status, )        = t('stats_col_status');
list($st_channel, )       = t('stats_col_channel');
list($st_col_visitor, )   = t('stats_col_unique_visitors');
list($st_col_actions, )   = t('stats_col_actions');
list($st_col_apv, )       = t('stats_col_actions_per_visit');
list($st_col_duration, )  = t('stats_col_avg_duration');
list($st_col_bounce, )    = t('stats_col_bounce');
list($st_col_device, )    = t('stats_col_device');
list($st_col_browser, )   = t('stats_col_browser');
list($st_col_os, )        = t('stats_col_os');
list($st_col_visitor_id, )= t('stats_col_visitor');
list($st_direct, )        = t('stats_type_direct');
list($st_search, )        = t('stats_type_search');
list($st_social, )        = t('stats_type_social');
list($st_ai, )            = t('stats_type_ai');
list($st_other, )         = t('stats_type_other');
list($st_desktop, )       = t('stats_device_desktop');
list($st_mobile, )        = t('stats_device_mobile');
list($st_tablet, )        = t('stats_device_tablet');
list($st_profile, )      = t('stats_visitor_profile');
list($st_profile_none, ) = t('stats_no_visitor_profile');
list($st_profile_visits, )= t('stats_profile_visits');
list($st_profile_actions,)= t('stats_profile_actions');
list($st_profile_time, )  = t('stats_profile_duration');
list($st_back, )          = t('stats_back');
list($st_legend_visits, ) = t('stats_legend_visits');
list($st_legend_views, )  = t('stats_legend_views');
list($st_self, )          = t('stats_self_excluded');
list($st_period, )        = t('stats_period_label');

// Etiquetas de los presets de periodo
$periodLabels = [
    'today'      => t('stats_range_today')[0],
    'yesterday'  => t('stats_range_yesterday')[0],
    'this_week'  => t('stats_range_this_week')[0],
    'last_week'  => t('stats_range_last_week')[0],
    'this_month' => t('stats_range_this_month')[0],
    'last_month' => t('stats_range_last_month')[0],
    'this_year'  => t('stats_range_this_year')[0],
    'last_7'     => t('stats_range_7')[0],
    'last_30'    => t('stats_range_30')[0],
    'last_90'    => t('stats_range_90')[0],
    'custom'     => t('stats_custom_range')[0],
];
$periodOrder = ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'this_year', 'last_7', 'last_30', 'last_90'];

// Etiquetas de canal
$channelLabels = [
    'direct' => $st_direct, 'search' => $st_search, 'social' => $st_social,
    'ai' => $st_ai, 'other' => $st_other,
];
$deviceLabels = ['desktop' => $st_desktop, 'mobile' => $st_mobile, 'tablet' => $st_tablet];
?>
<?php adminLayoutHead([
    'title'  => $page_title,
    'logo'   => $page_title,
    'active' => 'stats',
    'theme'  => $theme,
]); ?>

            <div class="admin-content-header">
                <h2><?php echo htmlspecialchars($page_title); ?></h2>
                <p><?php echo htmlspecialchars($st_period); ?>: <strong><?php echo htmlspecialchars($start . ' → ' . $end); ?></strong></p>
            </div>

<?php if (empty($stats['available'])): ?>
            <div class="msg-banner info">
                <?php echo finsec_icon('alert', 16); ?>
                <span><?php echo htmlspecialchars($st_unavailable); ?></span>
            </div>
<?php else: ?>

            <!-- Selector de periodo -->
            <div class="stat-period">
                <div class="stat-period__chips">
                    <?php foreach ($periodOrder as $key): ?>
                        <a class="stat-chip<?php echo $preset === $key ? ' active' : ''; ?>"
                           href="?period=<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($periodLabels[$key]); ?></a>
                    <?php endforeach; ?>
                </div>
                <form method="GET" action="" class="stat-period__custom">
                    <input type="hidden" name="period" value="custom">
                    <label><?php echo htmlspecialchars($st_from); ?> <input type="date" name="from" value="<?php echo htmlspecialchars($start); ?>"></label>
                    <label><?php echo htmlspecialchars($st_to); ?> <input type="date" name="to" value="<?php echo htmlspecialchars($end); ?>"></label>
                    <button type="submit" class="btn-secondary"><?php echo htmlspecialchars($st_apply); ?></button>
                </form>
            </div>

<?php if ($profile !== null): ?>
            <!-- Perfil del visitante -->
            <div class="admin-card">
                <h3><?php echo finsec_icon('eye', 16); ?> <?php echo htmlspecialchars($st_profile); ?></h3>
                <?php if (empty($profile['log'])): ?>
                    <p class="help-text"><?php echo htmlspecialchars($st_profile_none); ?></p>
                <?php else: ?>
                <div class="admin-stats">
                    <div class="admin-stat-card">
                        <span class="icon"><?php echo finsec_icon('globe', 18); ?></span>
                        <div class="value"><?php echo (int)$profile['visits']; ?></div>
                        <div class="label"><?php echo htmlspecialchars($st_profile_visits); ?></div>
                    </div>
                    <div class="admin-stat-card">
                        <span class="icon"><?php echo finsec_icon('posts', 18); ?></span>
                        <div class="value"><?php echo (int)$profile['actions']; ?></div>
                        <div class="label"><?php echo htmlspecialchars($st_profile_actions); ?></div>
                    </div>
                    <div class="admin-stat-card">
                        <span class="icon"><?php echo finsec_icon('history', 18); ?></span>
                        <div class="value"><?php echo htmlspecialchars(stats_duration($profile['duration'])); ?></div>
                        <div class="label"><?php echo htmlspecialchars($st_profile_time); ?></div>
                    </div>
                </div>
                <p class="help-text mono" style="margin-top:12px;">ID: <?php echo htmlspecialchars(substr((string)$profileId, 0, 12)); ?>…</p>
                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?php echo htmlspecialchars(t('tbl_col_date')[0]); ?></th>
                                <th><?php echo htmlspecialchars($st_col_actions); ?></th>
                                <th><?php echo htmlspecialchars($st_col_duration); ?></th>
                                <th><?php echo htmlspecialchars($st_referrer); ?></th>
                                <th><?php echo htmlspecialchars($st_col_device); ?></th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($profile['log'] as $pv): ?>
                            <tr>
                                <td class="mono"><?php echo htmlspecialchars($pv['started_at']); ?></td>
                                <td><?php echo (int)$pv['actions']; ?></td>
                                <td><?php echo htmlspecialchars(stats_duration($pv['duration'])); ?></td>
                                <td><?php echo htmlspecialchars($pv['referrer'] !== '' ? $pv['referrer'] : '—'); ?></td>
                                <td><?php echo htmlspecialchars(($deviceLabels[$pv['device']] ?? $pv['device']) . ' · ' . $pv['browser'] . ' · ' . $pv['os']); ?></td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="margin-top:12px;"><a class="btn-link" href="?period=<?php echo htmlspecialchars($preset); ?>&amp;from=<?php echo htmlspecialchars($start); ?>&amp;to=<?php echo htmlspecialchars($end); ?>"><?php echo finsec_icon('arrow-left', 14); ?> <?php echo htmlspecialchars($st_back); ?></a></p>
                <?php endif; ?>
            </div>
<?php endif; ?>

            <div class="stats-grid">
                <!-- Visitas en el tiempo -->
                <div class="admin-card stats-card--wide">
                    <h3><?php echo finsec_icon('chart', 16); ?> <?php echo htmlspecialchars($st_over_time); ?></h3>
                    <?php if (empty($stats['daily'])): ?>
                        <p class="help-text"><?php echo htmlspecialchars($st_no_data); ?></p>
                    <?php else: ?>
                        <div class="chart-wrap"><?php echo stats_line_chart($stats['daily']); ?></div>
                        <div class="chart-legend">
                            <span class="legend-item"><i class="legend-dot legend-dot--visits"></i><?php echo htmlspecialchars($st_legend_visits); ?></span>
                            <span class="legend-item"><i class="legend-dot legend-dot--views"></i><?php echo htmlspecialchars($st_legend_views); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Resumen de visitas -->
                <div class="admin-card">
                    <h3><?php echo finsec_icon('eye', 16); ?> <?php echo htmlspecialchars($st_visit_summary); ?></h3>
                    <div class="metrics">
                        <?php echo stats_metric_row($st_visits, number_format((int)($stats['visits'] ?? 0), 0, ',', '.')); ?>
                        <?php echo stats_metric_row($st_unique_visitors, number_format((int)($stats['unique_visitors'] ?? 0), 0, ',', '.')); ?>
                        <?php echo stats_metric_row($st_actions, number_format((int)($stats['actions'] ?? 0), 0, ',', '.')); ?>
                        <?php echo stats_metric_row($st_max_actions, number_format((int)($stats['max_actions_per_visit'] ?? 0), 0, ',', '.')); ?>
                        <?php echo stats_metric_row($st_apv, number_format((float)($stats['actions_per_visit'] ?? 0), 2, ',', '.')); ?>
                        <?php echo stats_metric_row($st_duration, htmlspecialchars(stats_duration($stats['avg_duration'] ?? 0))); ?>
                        <?php echo stats_metric_row($st_bounce, number_format((float)($stats['bounce_rate'] ?? 0), 1, ',', '.') . '%'); ?>
                    </div>
                </div>

                <!-- Acciones - métricas principales -->
                <div class="admin-card">
                    <h3><?php echo finsec_icon('layout', 16); ?> <?php echo htmlspecialchars($st_action_metrics); ?></h3>
                    <div class="metrics">
                        <?php echo stats_metric_row($st_pageviews, number_format((int)($stats['actions'] ?? 0), 0, ',', '.')); ?>
                        <?php echo stats_metric_row($st_unique_pages, number_format((int)($stats['unique_pageviews'] ?? 0), 0, ',', '.')); ?>
                        <?php echo stats_metric_row($st_searches, number_format((int)($stats['searches'] ?? 0), 0, ',', '.')); ?>
                        <?php echo stats_metric_row($st_keywords, number_format((int)($stats['unique_keywords'] ?? 0), 0, ',', '.')); ?>
                    </div>
                </div>
            </div>

            <!-- Visión general (con comparación y sparkline) -->
            <div class="admin-stats admin-stats--overview">
<?php
$dailyVisits = array_column($stats['daily'] ?? [], 'visits');
$dailyViews  = array_column($stats['daily'] ?? [], 'views');
$cards = [
    ['label' => $st_visits,        'value' => number_format((int)($stats['visits'] ?? 0), 0, ',', '.'),          'now' => (int)($stats['visits'] ?? 0),                 'prev' => (int)($prev['visits'] ?? 0),                 'spark' => $dailyVisits],
    ['label' => $st_unique_visitors,'value' => number_format((int)($stats['unique_visitors'] ?? 0), 0, ',', '.'), 'now' => (int)($stats['unique_visitors'] ?? 0),      'prev' => (int)($prev['unique_visitors'] ?? 0),      'spark' => array_column($stats['daily'] ?? [], 'visitors')],
    ['label' => $st_pageviews,     'value' => number_format((int)($stats['actions'] ?? 0), 0, ',', '.'),         'now' => (int)($stats['actions'] ?? 0),                'prev' => (int)($prev['actions'] ?? 0),                'spark' => $dailyViews],
    ['label' => $st_duration,      'value' => htmlspecialchars(stats_duration($stats['avg_duration'] ?? 0)),      'now' => (int)($stats['avg_duration'] ?? 0),           'prev' => (int)($prev['avg_duration'] ?? 0),           'spark' => []],
    ['label' => $st_bounce,        'value' => number_format((float)($stats['bounce_rate'] ?? 0), 1, ',', '.') . '%', 'now' => (float)($stats['bounce_rate'] ?? 0),       'prev' => (float)($prev['bounce_rate'] ?? 0),          'spark' => []],
    ['label' => $st_searches,      'value' => number_format((int)($stats['searches'] ?? 0), 0, ',', '.'),          'now' => (int)($stats['searches'] ?? 0),              'prev' => (int)($prev['searches'] ?? 0),              'spark' => []],
];
foreach ($cards as $card):
    $delta = stats_delta($card['now'], $card['prev']);
    $spark = stats_sparkline($card['spark'], 'spark--' . $delta['class']);
?>
                <div class="admin-stat-card overview-card">
                    <div class="label"><?php echo htmlspecialchars($card['label']); ?></div>
                    <div class="value"><?php echo $card['value']; ?></div>
                    <div class="delta delta--<?php echo htmlspecialchars($delta['class']); ?>"><?php echo htmlspecialchars($delta['text']); ?></div>
                    <?php if ($spark !== ''): ?><div class="overview-card__spark"><?php echo $spark; ?></div><?php endif; ?>
                </div>
<?php endforeach; ?>
            </div>

            <!-- Tipos de canal -->
            <div class="admin-card">
                <h3><?php echo finsec_icon('external-link', 16); ?> <?php echo htmlspecialchars($st_channels); ?></h3>
                <?php if (empty($stats['channels'])): ?>
                    <p class="help-text"><?php echo htmlspecialchars($st_no_data); ?></p>
                <?php else: ?>
                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?php echo htmlspecialchars($st_channel); ?></th>
                                <th><?php echo htmlspecialchars($st_visits_col); ?></th>
                                <th><?php echo htmlspecialchars($st_col_visitor); ?></th>
                                <th><?php echo htmlspecialchars($st_col_actions); ?></th>
                                <th><?php echo htmlspecialchars($st_col_apv); ?></th>
                                <th><?php echo htmlspecialchars($st_col_duration); ?></th>
                                <th><?php echo htmlspecialchars($st_col_bounce); ?></th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($stats['channels'] as $ch): ?>
                            <tr>
                                <td><span class="badge scheduled"><?php echo htmlspecialchars($channelLabels[$ch['type']] ?? $ch['type']); ?></span></td>
                                <td><?php echo (int)$ch['visits']; ?></td>
                                <td><?php echo (int)$ch['visitors']; ?></td>
                                <td><?php echo (int)$ch['actions']; ?></td>
                                <td><?php echo number_format((float)$ch['apv'], 2, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars(stats_duration($ch['duration'])); ?></td>
                                <td><?php echo number_format((float)$ch['bounce'], 1, ',', '.') . '%'; ?></td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Registro de visitas -->
            <div class="admin-card">
                <h3><?php echo finsec_icon('history', 16); ?> <?php echo htmlspecialchars($st_visit_log); ?></h3>
                <div class="realtime-line">
                    <span><?php echo finsec_icon('clock', 12); ?> <?php echo htmlspecialchars($st_realtime); ?>:
                        <strong><?php echo (int)($stats['realtime']['last_30min'] ?? 0); ?></strong> <?php echo htmlspecialchars($st_last_30min); ?> ·
                        <strong><?php echo (int)($stats['realtime']['last_24h'] ?? 0); ?></strong> <?php echo htmlspecialchars($st_last_24h); ?></span>
                </div>
                <?php if (empty($stats['visit_log'])): ?>
                    <p class="help-text"><?php echo htmlspecialchars($st_no_data); ?></p>
                <?php else: ?>
                    <div class="visit-log">
<?php foreach ($stats['visit_log'] as $visit): ?>
                        <div class="visit-card">
                            <div class="visit-card__head">
                                <span class="visit-card__date mono"><?php echo htmlspecialchars($visit['started_at']); ?></span>
                                <span class="visit-card__meta">
                                    <?php echo htmlspecialchars(($deviceLabels[$visit['device']] ?? $visit['device'])); ?> ·
                                    <?php echo htmlspecialchars($visit['browser']); ?> · <?php echo htmlspecialchars($visit['os']); ?>
                                </span>
                                <span class="visit-card__meta">
                                    <?php echo (int)$visit['actions']; ?> <?php echo htmlspecialchars((int)$visit['actions'] === 1 ? $st_action_one : strtolower($st_actions)); ?> ·
                                    <?php echo htmlspecialchars(stats_duration($visit['duration'])); ?>
<?php if ((int)$visit['actions'] === 1): ?>
                                    <span class="badge draft"><?php echo htmlspecialchars($st_bounce_short); ?></span>
<?php endif; ?>
                                </span>
                                <span class="visit-card__ref">
<?php if ($visit['referrer'] !== ''): ?>
                                    <?php echo htmlspecialchars($channelLabels[$visit['ref_type']] ?? $visit['ref_type']); ?> · <?php echo htmlspecialchars($visit['referrer']); ?>
<?php else: ?>
                                    <?php echo htmlspecialchars($st_direct); ?>
<?php endif; ?>
                                </span>
                            </div>
                            <div class="visit-card__actions">
<?php
$compact = stats_compact_actions($visit['actions_list'], 10);
foreach ($compact['items'] as $action):
?>
                                <div class="visit-action">
                                    <span class="visit-action__time mono"><?php echo htmlspecialchars(substr((string)$action['at'], 11, 8)); ?></span>
                                    <span class="visit-action__path mono"><?php echo htmlspecialchars($action['path']); ?></span>
<?php if ((int)$action['count'] > 1): ?>
                                    <span class="visit-action__count">&times;<?php echo (int)$action['count']; ?></span>
<?php endif; ?>
<?php if ($action['search'] !== ''): ?>
                                    <span class="visit-action__search">“<?php echo htmlspecialchars($action['search']); ?>”</span>
<?php endif; ?>
<?php if ((int)$action['status'] >= 400): ?>
                                    <span class="badge danger"><?php echo (int)$action['status']; ?></span>
<?php endif; ?>
                                </div>
<?php endforeach; ?>
<?php if ($compact['hidden'] > 0): ?>
                                <div class="visit-action visit-action--more">
                                    +<?php echo (int)$compact['hidden']; ?> <?php echo htmlspecialchars(strtolower($st_actions)); ?>
                                </div>
<?php endif; ?>
                            </div>
<?php if (!empty($visit['visitor_id'])): ?>
                            <div class="visit-card__foot">
                                <a class="btn-link" href="?period=<?php echo htmlspecialchars($preset); ?>&amp;from=<?php echo htmlspecialchars($start); ?>&amp;to=<?php echo htmlspecialchars($end); ?>&amp;profile=<?php echo urlencode((string)$visit['visitor_id']); ?>">
                                    <?php echo finsec_icon('eye', 14); ?> <?php echo htmlspecialchars($st_profile); ?>
                                </a>
                            </div>
<?php endif; ?>
                        </div>
<?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Informes -->
            <div class="stats-grid">
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

                <div class="admin-card">
                    <h3><?php echo finsec_icon('search', 16); ?> <?php echo htmlspecialchars($st_keywords_tbl); ?></h3>
                    <?php if (empty($stats['keywords'])): ?>
                        <p class="help-text"><?php echo htmlspecialchars($st_no_data); ?></p>
                    <?php else: ?>
                    <div class="admin-table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th><?php echo htmlspecialchars($st_col_visitor_id); ?></th>
                                    <th><?php echo htmlspecialchars($st_searches); ?></th>
                                </tr>
                            </thead>
                            <tbody>
<?php foreach ($stats['keywords'] as $k): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($k['term']); ?></td>
                                    <td><?php echo (int)$k['count']; ?></td>
                                </tr>
<?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

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

                <div class="admin-card">
                    <h3><?php echo finsec_icon('globe', 16); ?> <?php echo htmlspecialchars($st_referrers); ?></h3>
                    <?php if (empty($stats['referrers'])): ?>
                        <p class="help-text"><?php echo htmlspecialchars($st_no_data); ?></p>
                    <?php else: ?>
                    <div class="admin-table-container">
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
                                    <td><span class="badge scheduled"><?php echo htmlspecialchars($channelLabels[$r['type']] ?? $r['type']); ?></span></td>
                                    <td><?php echo (int)$r['visits']; ?></td>
                                </tr>
<?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="admin-card">
                    <h3><?php echo finsec_icon('globe', 16); ?> <?php echo htmlspecialchars($st_devices); ?></h3>
                    <?php if (empty($stats['devices'])): ?>
                        <p class="help-text"><?php echo htmlspecialchars($st_no_data); ?></p>
                    <?php else: ?>
                        <?php $dmax = 0; foreach ($stats['devices'] as $dv) { $dmax = max($dmax, (int)$dv['visits']); } ?>
                        <div class="bars-list">
<?php foreach ($stats['devices'] as $key => $dv): ?>
                            <?php echo stats_bar_row($deviceLabels[$key] ?? $key, (int)$dv['visits'], $dmax); ?>
<?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="admin-card">
                    <h3><?php echo finsec_icon('layout', 16); ?> <?php echo htmlspecialchars($st_browsers); ?></h3>
                    <?php if (empty($stats['browsers'])): ?>
                        <p class="help-text"><?php echo htmlspecialchars($st_no_data); ?></p>
                    <?php else: ?>
                        <?php $bmax = 0; foreach ($stats['browsers'] as $bv) { $bmax = max($bmax, (int)$bv['visits']); } ?>
                        <div class="bars-list">
<?php foreach ($stats['browsers'] as $key => $bv): ?>
                            <?php echo stats_bar_row($key === 'other' ? t('stats_other_browser')[0] : $key, (int)$bv['visits'], $bmax); ?>
<?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="admin-card">
                    <h3><?php echo finsec_icon('layout', 16); ?> <?php echo htmlspecialchars($st_os); ?></h3>
                    <?php if (empty($stats['os'])): ?>
                        <p class="help-text"><?php echo htmlspecialchars($st_no_data); ?></p>
                    <?php else: ?>
                        <?php $omax = 0; foreach ($stats['os'] as $ov) { $omax = max($omax, (int)$ov['visits']); } ?>
                        <div class="bars-list">
<?php foreach ($stats['os'] as $key => $ov): ?>
                            <?php echo stats_bar_row($key === 'other' ? t('stats_other_browser')[0] : $key, (int)$ov['visits'], $omax); ?>
<?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="admin-card">
                    <h3><?php echo finsec_icon('clock', 16); ?> <?php echo htmlspecialchars($st_hourly); ?></h3>
                    <?php
                    $hourly = $stats['hourly'] ?? [];
                    $hmax = 0;
                    foreach ($hourly as $hv) { $hmax = max($hmax, (int)$hv); }
                    if ($hmax === 0) $hmax = 1;
                    ?>
                    <div class="chart-bars chart-bars--hourly">
<?php foreach ($hourly as $h => $hv): ?>
<?php $hh = (int)round(((int)$hv / $hmax) * 100); if ($hh < 2 && (int)$hv > 0) $hh = 2; ?>
                        <div class="chart-col" title="<?php echo sprintf('%02d:00 · %d visitas', (int)$h, (int)$hv); ?>">
                            <span class="chart-bar" style="height:<?php echo $hh; ?>%"></span>
                        </div>
<?php endforeach; ?>
                    </div>
                    <div class="chart-axis">
                        <span>00:00</span><span>12:00</span><span>23:00</span>
                    </div>
                </div>

                <div class="admin-card">
                    <h3><?php echo finsec_icon('alert', 16); ?> <?php echo htmlspecialchars($st_statuses); ?></h3>
                    <?php if (empty($stats['statuses'])): ?>
                        <p class="help-text"><?php echo htmlspecialchars($st_no_data); ?></p>
                    <?php else: ?>
                        <?php $smax = max(1, (int)($stats['actions'] ?? 1)); ?>
                        <div class="bars-list">
<?php foreach ($stats['statuses'] as $code => $sv): ?>
                            <?php echo stats_bar_row($code, (int)$sv, $smax); ?>
<?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <p class="help-text"><?php echo htmlspecialchars($st_self); ?></p>
<?php endif; ?>
<?php adminLayoutFooter(); ?>
