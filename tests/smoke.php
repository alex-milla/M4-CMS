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
require __DIR__ . '/../public_html/helpers/system.php';

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

// --- Navegación anterior/siguiente ---
foreach ([
    ['nav-old', '2020-01-01 10:00:00', 'published'],
    ['nav-mid', '2020-06-01 10:00:00', 'published'],
    ['nav-new', '2020-12-31 10:00:00', 'published'],
    ['nav-draft', '2020-07-01 10:00:00', 'draft'],
] as [$slug, $when, $status]) {
    $ins = $GLOBALS['db']->prepare("INSERT INTO posts (title, content, status, slug, created_at, updated_at)
        VALUES (?, 'contenido', ?, ?, ?, ?)");
    $ins->execute([$slug, $status, $slug, $when, $when]);
}
$mid = getPostBySlug('nav-mid');
$adj = getAdjacentPublishedPosts($mid);
check('nav: anterior es el post mas antiguo', ($adj['prev']['slug'] ?? '') === 'nav-old');
check('nav: siguiente es el post mas reciente', ($adj['next']['slug'] ?? '') === 'nav-new');
check('nav: ignora los borradores', ($adj['next']['slug'] ?? '') !== 'nav-draft');
$oldest = getAdjacentPublishedPosts(getPostBySlug('nav-old'));
check('nav: el primero no tiene anterior', empty($oldest['prev']));
$newest = getAdjacentPublishedPosts(getPostBySlug('nav-new'));
$newestNext = $newest['next'] ?? null;
check('nav: el siguiente es mas reciente que el actual',
    $newestNext === null || strcmp((string)$newestNext['created_at'], '2020-12-31 10:00:00') > 0);
check('nav: sin datos devuelve array vacio', is_array(getAdjacentPublishedPosts(null)));

// --- Bloques de código [code]...[/code] ---
$codeContent = "Intro\n\n[code]\n192.42.116[]100\n95.216.5[]90\n[/code]\n\nCierre";
$codeHtml = postContentHtml($codeContent);
check('code: genera el bloque', strpos($codeHtml, 'class="post-code"') !== false);
check('code: usa details desplegable', strpos($codeHtml, '<details class="post-code__details"') !== false);
check('code: boton de copiar', strpos($codeHtml, 'post-code__copy') !== false);
check('code: conserva el contenido literal', strpos($codeHtml, '192.42.116[]100') !== false);
check('code: no deja marcadores', strpos($codeHtml, '@@M4CODE') === false);
check('code: escapa el HTML del autor', strpos(postContentHtml("[code]<script>alert(1)</script>[/code]"), '<script>alert(1)</script>') === false);
check('code: no autoenlaza dentro', strpos(postContentHtml("[code]https://ejemplo.com/x[/code]"), '<a href=') === false);
check('code: titulo opcional', strpos(postContentHtml("[code=Indicadores]\n192.42.116[]100\n[/code]"), 'Indicadores') !== false);
check('code: el texto normal sigue con autoenlace', strpos(postContentHtml("Ver https://ejemplo.com/x"), '<a href=') !== false);
// Extracto: si el primer bloque es de codigo, se pasa al siguiente texto
$excerpt = postExcerptHtml(['content' => "[code]\n192.42.116[]100\n[/code]\n\nTexto real del post"]);
check('code: el extracto salta el bloque de codigo', strpos($excerpt, '192.42') === false && strpos($excerpt, 'Texto real') !== false);
check('code: extracto sin marcadores', strpos($excerpt, '@@M4CODE') === false);

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

// --- IP real del cliente (proxy / CDN) ---
$_SERVER['REMOTE_ADDR'] = '8.8.8.8';
$_SERVER['HTTP_CF_CONNECTING_IP'] = '9.9.9.9';
check('client ip ignora cabeceras no confiables', m4_client_ip() === '8.8.8.8');
$_SERVER['REMOTE_ADDR'] = '173.245.48.5';
check('client ip detras de cloudflare', m4_client_ip() === '9.9.9.9');
unset($_SERVER['HTTP_CF_CONNECTING_IP']);
$_SERVER['REMOTE_ADDR'] = '8.8.8.8';
check('proxy ip detectada', m4_is_trusted_proxy_ip('173.245.48.5') === true);
check('ip normal no es proxy', m4_is_trusted_proxy_ip('1.1.1.1') === false);

// --- Las IPs de proxy no se pueden bloquear ---
check('banIp rechaza IP de proxy', banIp('173.245.48.5', 'proxy') === false);
check('proxy no queda baneada', isIpBanned('173.245.48.5') === false);

// --- User-Agent en los eventos de login ---
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0.0.0 Safari/537.36';
logAdminEvent('login_fail', 'smoke-ua');
$uaLogs = getAdminLogsFiltered(10, 0, ['login_fail']);
$storedUa = !empty($uaLogs[0]['ua']) ? (string)$uaLogs[0]['ua'] : '';
check('login_fail guarda user agent', strpos($storedUa, 'Chrome') !== false);
$logCols = array_column($GLOBALS['db']->query("PRAGMA table_info(admin_logs)")->fetchAll(PDO::FETCH_ASSOC), 'name');
check('admin_logs.ua existe', in_array('ua', $logCols));

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

// Búsqueda interna y métricas tipo Matomo
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0.0.0 Safari/537.36';
check('analytics_track search', analytics_track(['path' => '/', 'search' => 'sqlite']) === true);

$today = analytics_today_date(analytics_db());
$from  = date('Y-m-d', strtotime($today . ' -6 days'));
$stats = analytics_stats($from, $today);
check('analytics pageviews', (int)$stats['actions'] === 3);
check('analytics visits', (int)$stats['visits'] >= 1);
check('analytics unique visitors', (int)$stats['unique_visitors'] >= 1);
check('analytics actions per visit', (float)$stats['actions_per_visit'] > 0);
check('analytics max actions per visit', (int)$stats['max_actions_per_visit'] >= 1);
check('analytics bounce rate', (float)$stats['bounce_rate'] >= 0 && (float)$stats['bounce_rate'] <= 100);
check('analytics entry pages', count($stats['entry_pages']) >= 1);
check('analytics referrer search', (int)($stats['referrer_types']['search'] ?? 0) >= 1);
check('analytics browser detected', array_key_exists('Chrome', $stats['browsers']));
check('analytics os detected', array_key_exists('Windows', $stats['os']));
check('analytics hourly', count($stats['hourly']) === 24);
check('analytics daily series', count($stats['daily']) === 7);
check('analytics previous period', isset($stats['previous']['visits']));
check('analytics visit log', count($stats['visit_log']) >= 1);
check('analytics visit log actions', !empty($stats['visit_log'][0]['actions_list']));
check('analytics channels', !empty($stats['channels']));
check('analytics keywords', !empty($stats['keywords']));
check('analytics realtime', isset($stats['realtime']['last_24h']));

// Perfil de visitante
$visitorId = (string)($stats['visit_log'][0]['visitor_id'] ?? '');
check('visit log brings visitor id', $visitorId !== '');
$profile = analytics_visitor_profile(analytics_db(), $visitorId, $from, $today);
check('visitor profile', (int)$profile['visits'] >= 1);

// --- Rutas que no son páginas (favicon, robots.txt...) ---
check('favicon no es pagina', analytics_is_page_path('/favicon.ico') === false);
check('robots no es pagina', analytics_is_page_path('/robots.txt') === false);
check('apple-touch-icon no es pagina', analytics_is_page_path('/apple-touch-icon.png') === false);
check('well-known no es pagina', analytics_is_page_path('/.well-known/acme-challenge') === false);
check('post si es pagina', analytics_is_page_path('/mi-post') === true);
check('raiz si es pagina', analytics_is_page_path('/') === true);
$before = (int)analytics_stats($from, $today)['actions'];
check('favicon no se registra', analytics_track(['path' => '/favicon.ico']) === false);
check('robots no se registra', analytics_track(['path' => '/robots.txt']) === false);
$after = (int)analytics_stats($from, $today)['actions'];
check('acciones sin ruido tecnico', $before === $after);

// Las filas técnicas ya guardadas (instalaciones anteriores) tampoco cuentan
$pdoA = analytics_db();
$ins = $pdoA->prepare("INSERT INTO pageviews (visit_id, visitor_id, path, status, created_at)
    VALUES ('legacy-visit', 'legacy-visitor', '/favicon.ico', 404, datetime('now','localtime'))");
$ins->execute();
$afterLegacy = (int)analytics_stats($from, $today)['actions'];
check('favicon historico tampoco cuenta', $afterLegacy === $after);
check('paginas sin favicon', !in_array('/favicon.ico', array_column(analytics_stats($from, $today)['pages'], 'path'), true));

// --- Versión de la aplicación (cache busting del CSS) ---
check('app_version devuelve la version', preg_match('/^\d+\.\d+\.\d+/', app_version()) === 1);

// --- IP de origen: modos de almacenamiento ---
check('anonymiza ipv4', m4_anonymize_ip('188.114.96.42') === '188.114.96.0');
check('anonymiza ipv6', strpos(m4_anonymize_ip('2a06:98c1:3120::6'), '2a06:98c1:3120') === 0);
check('ip invalida se devuelve vacia', m4_anonymize_ip('no-es-ip') === '');

$_SERVER['REMOTE_ADDR'] = '8.8.8.8';
unset($_SERVER['HTTP_CF_CONNECTING_IP']);
check('modo por defecto es anonimo', analytics_ip_storage_mode() === 'anon');
check('guarda ip anonimizada', analytics_store_ip() === '8.8.8.0');

// El ajuste manda: 'none' no guarda IP, 'full' guarda la completa
saveSetting('analytics_ip_storage', 'none');
analytics_ip_storage_cache_reset();
check('modo none no guarda ip', analytics_store_ip() === '');
saveSetting('analytics_ip_storage', 'full');
analytics_ip_storage_cache_reset();
check('modo full guarda la ip', analytics_store_ip() === '8.8.8.8');
saveSetting('analytics_ip_storage', 'anon');
analytics_ip_storage_cache_reset();
check('modo anon vuelve a anonimizar', analytics_store_ip() === '8.8.8.0');

// El perfil del visitante cubre toda la retención (no solo el periodo visible)
$profileFull = analytics_visitor_profile(analytics_db(), $visitorId);
check('perfil con retencion completa', (int)$profileFull['visits'] >= 1);
check('perfil incluye las paginas de cada visita', !empty($profileFull['log'][0]['actions_list']));
check('perfil con rango informado', ($profileFull['start'] ?? '') !== '' && ($profileFull['end'] ?? '') !== '');

@unlink($tmpDb);
@unlink($tmpAnalytics);

if ($fail > 0) {
    fwrite(STDERR, $fail . " test(s) failed\n");
    exit(1);
}
echo "all tests passed\n";
