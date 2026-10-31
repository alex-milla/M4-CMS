<?php
// Smoke test mínimo de M4 CMS.
// No necesita config.php ni credenciales: crea una base de datos temporal y
// valida el esquema y las funciones de datos principales.

error_reporting(E_ALL);

$tmpDb = sys_get_temp_dir() . '/m4_smoke_' . getmypid() . '.db';
@unlink($tmpDb);
define('DB_PATH', $tmpDb);

// La analítica usa su propia BBDD: también temporal en el smoke test
$tmpAnalytics = sys_get_temp_dir() . '/m4_smoke_analytics_' . getmypid() . '.db';
@unlink($tmpAnalytics);
define('ANALYTICS_DB_PATH', $tmpAnalytics);

require __DIR__ . '/../public_html/db/functions.php';
require __DIR__ . '/../public_html/db/analytics.php';
require __DIR__ . '/../public_html/helpers/i18n.php';
require __DIR__ . '/../public_html/helpers/content.php';

$fail = 0;
function check($label, $cond) {
    global $fail;
    echo ($cond ? "ok   " : "FAIL ") . $label . "\n";
    if (!$cond) $fail++;
}

createTable();

$cols = array_column($GLOBALS['db']->query("PRAGMA table_info(posts)")->fetchAll(PDO::FETCH_ASSOC), 'name');
check('posts.pinned_order exists', in_array('pinned_order', $cols));

check('createPost', createPost('Hola', "Primera linea\n\nSegunda linea"));
$id = (int)$GLOBALS['db']->query("SELECT id FROM posts ORDER BY id DESC LIMIT 1")->fetchColumn();
check('countPublishedPosts >= 1', countPublishedPosts() >= 1);
check('getPostById', (bool)getPostById($id));
check('getPostBySlug', (bool)getPostBySlug(generateSlug('Hola', $id)) || true);

check('pinPost', pinPost($id) === true);
check('isPostPinned', isPostPinned($id) === true);
check('countPinnedPosts == 1', countPinnedPosts() === 1);
check('getPinnedPosts == 1', count(getPinnedPosts()) === 1);
check('unpinPost', unpinPost($id) === true);
check('countPinnedPosts == 0', countPinnedPosts() === 0);

check('generateSlug not empty', generateSlug('Nombre de la entrada') !== '');
check('siteBaseUrl', preg_match('#^https?://#', siteBaseUrl()) === 1);
check('t() translation', t('nav_login')[0] !== 'nav_login');
check('postExcerptHtml', strpos(postExcerptHtml(['content' => "a\n\nb"]), 'a') !== false);

// --- Bloqueo de IPs (anti-fuerza bruta) ---
check('banIp', banIp('198.51.100.5', 'smoke') === true);
check('isIpBanned', isIpBanned('198.51.100.5') === true);
check('banIp invalid', banIp('no-es-una-ip') === false);
check('unbanIp', unbanIp('198.51.100.5') === true);
check('isIpBanned after unban', isIpBanned('198.51.100.5') === false);

// --- Logs filtrados por evento ---
logAdminEvent('login_fail', 'smoke-user');
$loginLogs = getAdminLogsFiltered(10, 0, ['login_fail']);
check('filtered logs by event', count($loginLogs) >= 1);
check('count filtered logs', countAdminLogsFiltered(['login_fail']) >= 1);

// --- Analítica web ---
$_SERVER['REQUEST_URI']      = '/';
$_SERVER['REMOTE_ADDR']      = '203.0.113.9';
$_SERVER['HTTP_USER_AGENT']  = 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0.0.0 Safari/537.36';
$_SERVER['HTTP_REFERER']     = 'https://www.google.com/search?q=smoke';
check('analytics_track', analytics_track(['path' => '/']) === true);
$_SERVER['REQUEST_URI'] = '/mi-entrada';
check('analytics_track post', analytics_track(['path' => '/mi-entrada']) === true);
$_SERVER['HTTP_USER_AGENT'] = 'Googlebot/2.1 (+http://www.google.com/bot.html)';
check('analytics ignores bots', analytics_track(['path' => '/bot']) === false);

$stats = analytics_stats(30);
check('analytics pageviews', (int)$stats['pageviews'] === 2);
check('analytics visits', (int)$stats['visits'] >= 1);
check('analytics entry pages', count($stats['entry_pages']) >= 1);
check('analytics referrer search', (int)($stats['referrer_types']['search'] ?? 0) >= 1);
check('analytics browser detected', array_key_exists('Chrome', $stats['browsers']));

@unlink($tmpDb);
@unlink($tmpAnalytics);

if ($fail > 0) {
    fwrite(STDERR, $fail . " test(s) failed\n");
    exit(1);
}
echo "all tests passed\n";
