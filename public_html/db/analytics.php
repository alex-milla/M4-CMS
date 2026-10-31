<?php
// ============================================================
//  Analítica web propia (mini-Matomo)
// ============================================================
//  Registra cada página pública servida por el CMS en una base de datos
//  SQLite INDEPENDIENTE (db/analytics.db). El módulo es AUTÓNOMO:
//  no depende de db/functions.php ni de la sesión, para poder incluirse
//  también desde 404.php, que debe seguir funcionando sin dependencias.
//
//  Conceptos (equivalentes a Matomo):
//    · Visitor  → cookie propia 'm4_vid' (90 días) o huella IP+UA
//    · Visit    → sesión de 30 minutos (cookie 'm4_sid')
//    · Action   → cada página vista (pageview), con término de búsqueda
//
//  Informes disponibles: resumen de visitas, métricas de acciones, serie
//  temporal, canales de tráfico, registro de visitas, perfil de visitante,
//  páginas, entradas, palabras clave, referentes, dispositivos, navegadores,
//  sistemas operativos, visitas por hora y códigos de respuesta.
//
//  Privacidad: la IP nunca se guarda en claro (hash salado), las cookies son
//  identificadores aleatorios sin datos personales y no interviene ningún
//  servicio de terceros.
//
//  Todo es tolerante a fallos: si la BBDD no es escribible, la web sigue
//  funcionando con normalidad.

if (!defined('ANALYTICS_DB_PATH')) define('ANALYTICS_DB_PATH', __DIR__ . '/analytics.db');
if (!defined('ANALYTICS_COOKIE'))  define('ANALYTICS_COOKIE', 'm4_sid');
if (!defined('ANALYTICS_VISITOR_COOKIE')) define('ANALYTICS_VISITOR_COOKIE', 'm4_vid');
if (!defined('ANALYTICS_SALT'))    define('ANALYTICS_SALT', 'm4-analytics-v1');
if (!defined('ANALYTICS_RETENTION_DAYS')) define('ANALYTICS_RETENTION_DAYS', 180);

// IP real del cliente (resuelve proxies/CDN como Cloudflare).
if (!function_exists('m4_client_ip')) {
    @include_once __DIR__ . '/../helpers/client.php';
}

// ------------------------------------------------------------
//  Conexión y esquema
// ------------------------------------------------------------

// Devuelve el PDO de la BBDD de analítica (null si no está disponible).
function analytics_db() {
    static $pdo = null;
    static $tried = false;
    if ($pdo !== null || $tried) return $pdo;
    $tried = true;
    try {
        $dir = dirname(ANALYTICS_DB_PATH);
        if (!is_dir($dir) || !is_writable($dir)) return null;
        $pdo = new PDO('sqlite:' . ANALYTICS_DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        analytics_schema($pdo);
    } catch (\Throwable $e) {
        $pdo = null;
    }
    return $pdo;
}

// Crea las tablas si no existen y migra las instalaciones anteriores.
function analytics_schema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pageviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        visit_id TEXT NOT NULL,
        visitor_id TEXT,
        path TEXT NOT NULL,
        status INTEGER NOT NULL DEFAULT 200,
        referrer TEXT,
        referrer_host TEXT,
        referrer_type TEXT NOT NULL DEFAULT 'direct',
        device TEXT NOT NULL DEFAULT 'unknown',
        browser TEXT NOT NULL DEFAULT 'other',
        os TEXT NOT NULL DEFAULT 'other',
        search_term TEXT,
        is_new_visit INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL
    )");

    // Migración de instalaciones v0.7.5.x (columnas nuevas)
    try {
        $cols = array_column($pdo->query("PRAGMA table_info(pageviews)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        if (!in_array('visitor_id', $cols, true)) {
            $pdo->exec("ALTER TABLE pageviews ADD COLUMN visitor_id TEXT");
        }
        if (!in_array('search_term', $cols, true)) {
            $pdo->exec("ALTER TABLE pageviews ADD COLUMN search_term TEXT");
        }
        if (!in_array('os', $cols, true)) {
            $pdo->exec("ALTER TABLE pageviews ADD COLUMN os TEXT");
        }
    } catch (\Throwable $e) { /* las columnas nuevas son opcionales */ }

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pv_created ON pageviews(created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pv_visit ON pageviews(visit_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pv_visitor ON pageviews(visitor_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pv_path ON pageviews(path)");
}

// ------------------------------------------------------------
//  Recogida de datos
// ------------------------------------------------------------

// Ruta solicitada (sin query string) para agrupar por página.
function analytics_current_path() {
    $uri  = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH);
    if (!is_string($path) || $path === '') $path = '/';
    return substr($path, 0, 255);
}

// User-Agent recortado.
function analytics_user_agent() {
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

// Hash salado de la IP (nunca se almacena en claro).
function analytics_ip_hash() {
    $ip = function_exists('m4_client_ip')
        ? m4_client_ip()
        : (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return hash_hmac('sha256', (string)$ip, ANALYTICS_SALT);
}

// ¿Es un bot/rastreador? No se registran (inflan las estadísticas).
function analytics_is_bot($ua) {
    if ($ua === '') return false;
    return (bool)preg_match(
        '/bot\b|bot\/|crawl|spider|slurp|bingpreview|facebookexternalhit|curl\/|wget\/|python-requests|headless|lighthouse|pingdom|uptimerobot|monitoring|semrush|ahrefs|mj12/i',
        $ua
    );
}

// Tipo de dispositivo deducido del User-Agent.
function analytics_device($ua) {
    if ($ua === '') return 'unknown';
    if (preg_match('/ipad|tablet|playbook|silk|kindle|android(?!.*mobile)/i', $ua)) return 'tablet';
    if (preg_match('/mobile|android|iphone|ipod|windows phone|blackberry|bb10|opera mini|iemobile/i', $ua)) return 'mobile';
    return 'desktop';
}

// Navegador deducido del User-Agent.
function analytics_browser($ua) {
    if ($ua === '') return 'unknown';
    if (preg_match('/Edg\//i', $ua)) return 'Edge';
    if (preg_match('/OPR\/|Opera/i', $ua)) return 'Opera';
    if (preg_match('/SamsungBrowser/i', $ua)) return 'Samsung Internet';
    if (preg_match('/Firefox\/|FxiOS/i', $ua)) return 'Firefox';
    if (preg_match('/Chrome\/|CriOS/i', $ua)) return 'Chrome';
    if (preg_match('/MSIE|Trident/i', $ua)) return 'Internet Explorer';
    if (preg_match('/Safari\//i', $ua)) return 'Safari';
    return 'other';
}

// Sistema operativo deducido del User-Agent.
function analytics_os($ua) {
    if ($ua === '') return 'unknown';
    if (preg_match('/windows nt|windows phone|win64|win32/i', $ua)) return 'Windows';
    if (preg_match('/iphone|ipad|ipod/i', $ua)) return 'iOS';
    if (preg_match('/android/i', $ua)) return 'Android';
    if (preg_match('/mac os x|macintosh/i', $ua)) return 'macOS';
    if (preg_match('/linux|x11|ubuntu|fedora|debian/i', $ua)) return 'GNU/Linux';
    if (preg_match('/cros/i', $ua)) return 'ChromeOS';
    return 'other';
}

// Dominios de buscadores (para agrupar el origen del tráfico).
function analytics_search_hosts() {
    return [
        'google.', 'bing.', 'duckduckgo.', 'yahoo.', 'baidu.', 'yandex.', 'ecosia.',
        'brave.', 'startpage.', 'qwant.', 'naver.', 'seznam.', 'ask.', 'aol.',
        'mojeek.', 'lite.duckduckgo.', 'search.yahoo.', 'www.google.',
    ];
}

// Dominios de redes sociales.
function analytics_social_hosts() {
    return [
        'facebook.', 'fb.com', 'instagram.', 'twitter.', 't.co', 'x.com', 'linkedin.',
        'reddit.', 'youtube.', 'youtu.be', 'tiktok.', 'pinterest.', 'telegram.', 't.me',
        'whatsapp.', 'mastodon.', 'news.ycombinator', 'vk.com', 'threads.net',
        'bsky.app', 'discord.', 'flipboard.', 'tumblr.', 'medium.com', 'substack.',
    ];
}

// Asistentes de IA (canal propio, como en Matomo).
function analytics_ai_hosts() {
    return [
        'chatgpt.com', 'chat.openai.com', 'openai.com', 'perplexity.ai', 'claude.ai',
        'anthropic.com', 'gemini.google.com', 'bard.google.com', 'copilot.microsoft.com',
        'grok.com', 'x.ai', 'you.com', 'phind.com', 'poe.com', 'mistral.ai',
        'deepseek.com', 'kagi.com', 'andisearch.com', 't3.chat', 'huggingface.co',
    ];
}

// Analiza el referer: host, tipo (direct|search|social|ai|other) y host limpio.
function analytics_referrer_info($referrer) {
    $info = ['host' => '', 'type' => 'direct', 'label' => ''];
    $referrer = trim((string)$referrer);
    if ($referrer === '') return $info;

    $host = (string)parse_url($referrer, PHP_URL_HOST);
    if ($host === '') return $info;
    $host = preg_replace('/^www\./i', '', $host);
    $info['host'] = $host;

    // Referencia interna (propio sitio) = tráfico directo
    $current = strtolower(preg_replace('/^www\./i', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    if ($current !== '' && $host === $current) {
        $info['type'] = 'direct';
        return $info;
    }

    $lower = strtolower($host);
    // Los asistentes de IA se comprueban antes: algunos cuelgan de google.*
    foreach (analytics_ai_hosts() as $needle) {
        if (strpos($lower, $needle) !== false) {
            $info['type'] = 'ai';
            return $info;
        }
    }
    foreach (analytics_social_hosts() as $needle) {
        if (strpos($lower, $needle) !== false) {
            $info['type'] = 'social';
            return $info;
        }
    }
    foreach (analytics_search_hosts() as $needle) {
        if (strpos($lower, $needle) !== false) {
            $info['type'] = 'search';
            return $info;
        }
    }
    $info['type'] = 'other';
    return $info;
}

// Fija una cookie de primera parte.
function analytics_set_cookie($name, $value, $days) {
    if (headers_sent()) return false;
    $expires = time() + (int)$days * 86400;
    $secure  = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');
    if (PHP_VERSION_ID >= 70300) {
        return (bool)setcookie($name, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    return (bool)setcookie($name, $value, $expires, '/', '', $secure, true);
}

// Identificador de visita: cookie aleatoria o huella alternativa sin cookies.
// Devuelve [visit_id, es_nueva, modo].
function analytics_visit_id() {
    $sid = isset($_COOKIE[ANALYTICS_COOKIE]) ? (string)$_COOKIE[ANALYTICS_COOKIE] : '';
    if ($sid !== '' && preg_match('/^[a-f0-9]{32}$/', $sid)) {
        analytics_set_cookie(ANALYTICS_COOKIE, $sid, 1); // ventana deslizante de 30 min
        return [$sid, false, 'cookie'];
    }
    $sid = bin2hex(random_bytes(16));
    if (analytics_set_cookie(ANALYTICS_COOKIE, $sid, 1)) {
        return [$sid, true, 'cookie'];
    }
    // Sin soporte de cookies: huella del día (IP hasheada + UA)
    $fp = hash_hmac('sha256', analytics_ip_hash() . '|' . analytics_user_agent() . '|' . date('Y-m-d'), ANALYTICS_SALT);
    return [substr($fp, 0, 32), false, 'fingerprint'];
}

// Identificador de visitante: cookie de 90 días o huella estable IP+UA.
function analytics_visitor_id() {
    $vid = isset($_COOKIE[ANALYTICS_VISITOR_COOKIE]) ? (string)$_COOKIE[ANALYTICS_VISITOR_COOKIE] : '';
    if ($vid !== '' && preg_match('/^[a-f0-9]{32}$/', $vid)) {
        analytics_set_cookie(ANALYTICS_VISITOR_COOKIE, $vid, 90);
        return $vid;
    }
    $vid = bin2hex(random_bytes(16));
    if (analytics_set_cookie(ANALYTICS_VISITOR_COOKIE, $vid, 90)) {
        return $vid;
    }
    // Sin cookies: huella estable (equivale al "config id" de Matomo)
    $fp = hash_hmac('sha256', analytics_ip_hash() . '|' . analytics_user_agent(), ANALYTICS_SALT);
    return substr($fp, 0, 32);
}

// ¿Existe ya alguna visita registrada con ese identificador?
function analytics_visit_exists($pdo, $visitId) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pageviews WHERE visit_id = ?");
        $stmt->execute([$visitId]);
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (\Throwable $e) {
        return false;
    }
}

// Rutas que NO son páginas del sitio: peticiones técnicas del navegador,
// bots o herramientas. No se registran como visitas ni como acciones.
//   /favicon.ico, /robots.txt, /apple-touch-icon*.png, /.well-known/...
function analytics_is_page_path($path) {
    $p = strtolower(trim((string)$path));
    if ($p === '') return false;

    $skip = [
        '/favicon.ico', '/favicon.png', '/favicon.svg',
        '/apple-touch-icon.png', '/apple-touch-icon-precomposed.png',
        '/robots.txt', '/humans.txt', '/ads.txt', '/app-ads.txt',
        '/browserconfig.xml', '/manifest.json', '/site.webmanifest',
    ];
    if (in_array($p, $skip, true)) return false;
    if (preg_match('#^/apple-touch-icon[\w\-]*\.png$#', $p)) return false;
    if (strpos($p, '/.well-known/') === 0) return false;
    return true;
}

// Filtro SQL equivalente: excluye las rutas técnicas también de los datos
// ya registrados en instalaciones anteriores.
function analytics_page_filter_sql() {
    return "path NOT IN ('/favicon.ico','/favicon.png','/favicon.svg',"
        . "'/apple-touch-icon.png','/apple-touch-icon-precomposed.png',"
        . "'/robots.txt','/humans.txt','/ads.txt','/app-ads.txt',"
        . "'/browserconfig.xml','/manifest.json','/site.webmanifest')"
        . " AND path NOT LIKE '/apple-touch-icon%.png'";
}

// Registra una página vista. Opciones: path, status, search.
function analytics_track(array $opts = []) {
    try {
        $pdo = analytics_db();
        if (!$pdo) return false;

        $ua = analytics_user_agent();
        if (analytics_is_bot($ua)) return false;

        $path = isset($opts['path']) ? (string)$opts['path'] : analytics_current_path();
        if ($path === '') $path = '/';
        $path = substr($path, 0, 255);
        $status = isset($opts['status']) ? (int)$opts['status'] : 200;

        // Las peticiones técnicas (favicon, robots.txt...) no son páginas
        if (!analytics_is_page_path($path)) return false;

        $search = isset($opts['search']) ? trim((string)$opts['search']) : '';
        if ($search !== '') $search = substr($search, 0, 120);

        list($visitId, $isNew, $mode) = analytics_visit_id();
        if ($mode === 'fingerprint' && analytics_visit_exists($pdo, $visitId)) $isNew = false;
        $visitorId = analytics_visitor_id();

        $referrer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
        $ref = analytics_referrer_info($referrer);

        $stmt = $pdo->prepare(
            "INSERT INTO pageviews
                (visit_id, visitor_id, path, status, referrer, referrer_host, referrer_type,
                 device, browser, os, search_term, is_new_visit, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now','localtime'))"
        );
        $stmt->execute([
            $visitId,
            $visitorId,
            $path,
            $status,
            $referrer !== '' ? substr($referrer, 0, 300) : null,
            $ref['host'] !== '' ? $ref['host'] : null,
            $ref['type'],
            analytics_device($ua),
            analytics_browser($ua),
            analytics_os($ua),
            $search !== '' ? $search : null,
            $isNew ? 1 : 0,
        ]);
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

// ------------------------------------------------------------
//  Agregaciones
// ------------------------------------------------------------

// Ejecuta una consulta con los parámetros indicados.
function analytics_query($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(is_array($params) ? $params : [$params]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return [];
    }
}

// Fecha de hoy en el marco temporal del servidor (SQLite 'localtime').
function analytics_today_date($pdo) {
    try {
        $stmt = $pdo->query("SELECT date('now','localtime')");
        $d = $stmt ? $stmt->fetchColumn() : false;
        return is_string($d) && $d !== '' ? $d : date('Y-m-d');
    } catch (\Throwable $e) {
        return date('Y-m-d');
    }
}

// Bloque CTE con las visitas del rango, con los datos de su primera acción.
// Debe usarse al inicio de la sentencia: "... FROM f ..." (SQLite no admite
// WITH dentro de un subquery entre paréntesis).
function analytics_visits_cte($start, $end) {
    return "WITH base AS (
                SELECT * FROM pageviews
                WHERE date(created_at) BETWEEN ? AND ? AND (" . analytics_page_filter_sql() . ")
            ),
            v AS (
                SELECT visit_id, MIN(id) AS first_id, MAX(created_at) AS last_at, COUNT(*) AS actions
                FROM base GROUP BY visit_id
            ),
            f AS (
                SELECT b.visit_id, b.visitor_id, b.referrer_host, b.referrer_type, b.referrer,
                       b.device, b.browser, b.os, b.created_at AS started_at,
                       v.last_at, v.actions
                FROM base b JOIN v ON v.visit_id = b.visit_id AND b.id = v.first_id
            )";
}

// Métricas principales de un rango (resumen de visitas + acciones).
function analytics_core_metrics($pdo, $start, $end) {
    $m = [
        'visits' => 0, 'unique_visitors' => 0, 'actions' => 0, 'unique_pageviews' => 0,
        'max_actions_per_visit' => 0, 'actions_per_visit' => 0.0, 'avg_duration' => 0,
        'bounce_rate' => 0.0, 'searches' => 0, 'unique_keywords' => 0,
    ];
    try {
        $rows = analytics_query($pdo,
            analytics_visits_cte($start, $end) . "
             SELECT COUNT(*) AS visits,
                    COUNT(DISTINCT visitor_id) AS visitors,
                    SUM(actions) AS actions,
                    MAX(actions) AS max_actions,
                    AVG(actions) AS apv,
                    AVG(strftime('%s', last_at) - strftime('%s', started_at)) AS avg_duration,
                    SUM(CASE WHEN actions = 1 THEN 1 ELSE 0 END) AS bounces
             FROM f", [$start, $end]);
        if (!empty($rows[0])) {
            $r = $rows[0];
            $m['visits'] = (int)$r['visits'];
            $m['unique_visitors'] = (int)$r['visitors'];
            $m['actions'] = (int)$r['actions'];
            $m['max_actions_per_visit'] = (int)$r['max_actions'];
            $m['actions_per_visit'] = round((float)$r['apv'], 2);
            $m['avg_duration'] = (int)round((float)$r['avg_duration']);
            if ($m['visits'] > 0) {
                $m['bounce_rate'] = round(((int)$r['bounces'] / $m['visits']) * 100, 1);
            }
        }

        $rows = analytics_query($pdo,
            "SELECT COUNT(DISTINCT path) AS unique_pages,
                    SUM(CASE WHEN search_term IS NOT NULL AND search_term <> '' THEN 1 ELSE 0 END) AS searches,
                    COUNT(DISTINCT CASE WHEN search_term IS NOT NULL AND search_term <> '' THEN search_term END) AS keywords
             FROM pageviews WHERE date(created_at) BETWEEN ? AND ? AND (" . analytics_page_filter_sql() . ")", [$start, $end]);
        if (!empty($rows[0])) {
            $m['unique_pageviews'] = (int)$rows[0]['unique_pages'];
            $m['searches']         = (int)$rows[0]['searches'];
            $m['unique_keywords']  = (int)$rows[0]['keywords'];
        }
    } catch (\Throwable $e) { /* valores por defecto */ }
    return $m;
}

// Resumen ligero (para la comparación con el periodo anterior).
function analytics_summary($pdo, $start, $end) {
    return analytics_core_metrics($pdo, $start, $end);
}

// Métricas completas de un rango de fechas ('YYYY-MM-DD').
function analytics_stats($start, $end = null) {
    // Compatibilidad: analytics_stats(30) → últimos 30 días
    if ($end === null && (is_int($start) || ctype_digit((string)$start))) {
        $pdoTmp = analytics_db();
        $today  = $pdoTmp ? analytics_today_date($pdoTmp) : date('Y-m-d');
        $days   = max(1, (int)$start);
        $end   = $today;
        $start = date('Y-m-d', strtotime($today . ' -' . ($days - 1) . ' days'));
    }
    $start = (string)$start;
    $end   = (string)$end;

    $stats = [
        'start' => $start, 'end' => $end,
        'days' => max(1, (int)round((strtotime($end) - strtotime($start)) / 86400) + 1),
        'available' => false,
        'visits' => 0, 'unique_visitors' => 0, 'actions' => 0, 'pageviews' => 0,
        'unique_pageviews' => 0, 'max_actions_per_visit' => 0, 'actions_per_visit' => 0.0,
        'avg_duration' => 0, 'bounce_rate' => 0.0, 'searches' => 0, 'unique_keywords' => 0,
        'daily' => [], 'hourly' => [], 'channels' => [], 'pages' => [], 'entry_pages' => [],
        'referrers' => [], 'devices' => [], 'browsers' => [], 'os' => [], 'statuses' => [],
        'referrer_types' => [],
        'keywords' => [], 'visit_log' => [], 'realtime' => [], 'previous' => [],
    ];

    try {
        $pdo = analytics_db();
        if (!$pdo) return $stats;
        $stats['available'] = true;
        $pf = analytics_page_filter_sql();   // excluye rutas técnicas (favicon, robots.txt...)

        // --- Resumen ---
        $core = analytics_core_metrics($pdo, $start, $end);
        foreach ($core as $k => $v) $stats[$k] = $v;
        $stats['pageviews'] = $stats['actions'];

        // --- Periodo anterior (para la comparación %) ---
        $days  = $stats['days'];
        $prevEnd   = date('Y-m-d', strtotime($start . ' -1 day'));
        $prevStart = date('Y-m-d', strtotime($prevEnd . ' -' . ($days - 1) . ' days'));
        $stats['previous'] = array_merge(
            analytics_core_metrics($pdo, $prevStart, $prevEnd),
            ['start' => $prevStart, 'end' => $prevEnd]
        );

        // --- Serie diaria ---
        $rows = analytics_query($pdo,
            "SELECT date(created_at) AS d,
                    COUNT(*) AS actions,
                    COUNT(DISTINCT visit_id) AS visits,
                    COUNT(DISTINCT visitor_id) AS visitors
             FROM pageviews WHERE date(created_at) BETWEEN ? AND ? AND ($pf)
             GROUP BY d ORDER BY d", [$start, $end]);
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['d']] = [
                'date'    => $row['d'],
                'views'   => (int)$row['actions'],
                'visits'  => (int)$row['visits'],
                'visitors'=> (int)$row['visitors'],
            ];
        }
        $cursor = new DateTime($start);
        $endTs  = strtotime($end);
        while (strtotime($cursor->format('Y-m-d')) <= $endTs) {
            $key = $cursor->format('Y-m-d');
            $stats['daily'][] = $byDay[$key] ?? ['date' => $key, 'views' => 0, 'visits' => 0, 'visitors' => 0];
            $cursor->modify('+1 day');
        }

        // --- Visitas por hora ---
        $stats['hourly'] = array_fill(0, 24, 0);
        $rows = analytics_query($pdo,
            "SELECT strftime('%H', created_at) AS h, COUNT(DISTINCT visit_id) AS visits
             FROM pageviews WHERE date(created_at) BETWEEN ? AND ? AND ($pf)
             GROUP BY h ORDER BY h", [$start, $end]);
        foreach ($rows as $row) {
            $h = (int)$row['h'];
            if ($h >= 0 && $h < 24) $stats['hourly'][$h] = (int)$row['visits'];
        }

        // --- Canales de tráfico ---
        $rows = analytics_query($pdo,
            analytics_visits_cte($start, $end) . "
             SELECT referrer_type AS channel, COUNT(*) AS visits, COUNT(DISTINCT visitor_id) AS visitors,
                    SUM(actions) AS actions, AVG(actions) AS apv,
                    AVG(strftime('%s', last_at) - strftime('%s', started_at)) AS avg_duration,
                    SUM(CASE WHEN actions = 1 THEN 1 ELSE 0 END) AS bounces
             FROM f GROUP BY referrer_type ORDER BY visits DESC", [$start, $end]);
        foreach ($rows as $row) {
            $visits = (int)$row['visits'];
            $stats['channels'][] = [
                'type'      => (string)$row['channel'],
                'visits'    => $visits,
                'visitors'  => (int)$row['visitors'],
                'actions'   => (int)$row['actions'],
                'apv'       => round((float)$row['apv'], 2),
                'duration'  => (int)round((float)$row['avg_duration']),
                'bounce'    => $visits > 0 ? round(((int)$row['bounces'] / $visits) * 100, 1) : 0.0,
            ];
        }

        // --- Páginas más vistas ---
        $rows = analytics_query($pdo,
            "SELECT path, COUNT(*) AS views, COUNT(DISTINCT visit_id) AS visits, COUNT(DISTINCT visitor_id) AS visitors
             FROM pageviews WHERE date(created_at) BETWEEN ? AND ? AND ($pf)
             GROUP BY path ORDER BY views DESC LIMIT 25", [$start, $end]);
        foreach ($rows as $row) {
            $stats['pages'][] = [
                'path'    => (string)$row['path'],
                'views'   => (int)$row['views'],
                'visits'  => (int)$row['visits'],
                'visitors'=> (int)$row['visitors'],
            ];
        }

        // --- Páginas de entrada ---
        $rows = analytics_query($pdo,
            "SELECT path, COUNT(*) AS entries FROM pageviews
             WHERE date(created_at) BETWEEN ? AND ? AND ($pf)
               AND id IN (SELECT MIN(id) FROM pageviews WHERE date(created_at) BETWEEN ? AND ? AND ($pf) GROUP BY visit_id)
             GROUP BY path ORDER BY entries DESC LIMIT 25", [$start, $end, $start, $end]);
        foreach ($rows as $row) {
            $stats['entry_pages'][] = ['path' => (string)$row['path'], 'visits' => (int)$row['entries']];
        }

        // --- Palabras clave buscadas ---
        $rows = analytics_query($pdo,
            "SELECT search_term AS term, COUNT(*) AS count, COUNT(DISTINCT visitor_id) AS visitors
             FROM pageviews
             WHERE date(created_at) BETWEEN ? AND ? AND ($pf) AND search_term IS NOT NULL AND search_term <> ''
             GROUP BY search_term ORDER BY count DESC LIMIT 25", [$start, $end]);
        foreach ($rows as $row) {
            $stats['keywords'][] = ['term' => (string)$row['term'], 'count' => (int)$row['count'], 'visitors' => (int)$row['visitors']];
        }

        // --- Referentes ---
        $rows = analytics_query($pdo,
            "SELECT referrer, referrer_host, referrer_type, COUNT(DISTINCT visit_id) AS visits
             FROM pageviews
             WHERE date(created_at) BETWEEN ? AND ? AND ($pf) AND referrer IS NOT NULL AND referrer <> ''
             GROUP BY referrer ORDER BY visits DESC LIMIT 25", [$start, $end]);
        foreach ($rows as $row) {
            $stats['referrers'][] = [
                'referrer' => (string)$row['referrer'],
                'host'     => (string)$row['referrer_host'],
                'type'     => (string)$row['referrer_type'],
                'visits'   => (int)$row['visits'],
            ];
        }

        // --- Tipos de referente (para las barras) ---
        $rows = analytics_query($pdo,
            "SELECT referrer_type, COUNT(DISTINCT visit_id) AS visits
             FROM pageviews WHERE date(created_at) BETWEEN ? AND ? AND ($pf)
             GROUP BY referrer_type", [$start, $end]);
        foreach ($rows as $row) {
            $stats['referrer_types'][(string)$row['referrer_type']] = (int)$row['visits'];
        }

        // --- Dispositivos, navegadores y sistemas operativos ---
        foreach ([
            ['devices', 'device'], ['browsers', 'browser'], ['os', 'os'],
        ] as $pair) {
            list($key, $col) = $pair;
            $rows = analytics_query($pdo,
                "SELECT $col AS v, COUNT(DISTINCT visit_id) AS visits, COUNT(DISTINCT visitor_id) AS visitors
                 FROM pageviews WHERE date(created_at) BETWEEN ? AND ? AND ($pf)
                 GROUP BY $col ORDER BY visits DESC", [$start, $end]);
            foreach ($rows as $row) {
                $stats[$key][(string)$row['v']] = ['visits' => (int)$row['visits'], 'visitors' => (int)$row['visitors']];
            }
        }

        // --- Códigos de respuesta ---
        $rows = analytics_query($pdo,
            "SELECT status, COUNT(*) AS views
             FROM pageviews WHERE date(created_at) BETWEEN ? AND ? AND ($pf)
             GROUP BY status ORDER BY views DESC", [$start, $end]);
        foreach ($rows as $row) {
            $stats['statuses'][(string)$row['status']] = (int)$row['views'];
        }

        // --- Registro de visitas (visitor log) ---
        $stats['visit_log'] = analytics_visit_log($pdo, $start, $end, 50);

        // --- Tiempo real ---
        $rows = analytics_query($pdo,
            "SELECT
                COUNT(DISTINCT CASE WHEN created_at >= datetime('now','localtime','-30 minutes') THEN visit_id END) AS m30,
                COUNT(DISTINCT CASE WHEN created_at >= datetime('now','localtime','-1 day') THEN visit_id END) AS d1,
                MAX(created_at) AS last_hit
             FROM pageviews WHERE date(created_at) BETWEEN ? AND ? AND ($pf)", [$start, $end]);
        $stats['realtime'] = [
            'last_30min' => !empty($rows[0]) ? (int)$rows[0]['m30'] : 0,
            'last_24h'   => !empty($rows[0]) ? (int)$rows[0]['d1'] : 0,
            'last_hit'   => !empty($rows[0]) ? (string)$rows[0]['last_hit'] : '',
        ];
    } catch (\Throwable $e) {
        // Devuelve los valores por defecto (nunca rompe la página)
    }

    return $stats;
}

// Registro de visitas: una entrada por sesión con la lista de acciones.
function analytics_visit_log($pdo, $start, $end, $limit = 50) {
    $log = [];
    try {
        $limit = max(1, (int)$limit);
        $rows = analytics_query($pdo,
            analytics_visits_cte($start, $end) . "
             SELECT visit_id, visitor_id, referrer_host, referrer_type, device, browser, os,
                    started_at, last_at, actions
             FROM f
             ORDER BY started_at DESC LIMIT $limit", [$start, $end]);
        if (empty($rows)) return $log;

        $ids = [];
        foreach ($rows as $row) $ids[] = (string)$row['visit_id'];

        $actions = [];
        $rows2 = analytics_query($pdo,
            "SELECT visit_id, path, status, created_at, search_term FROM pageviews
             WHERE date(created_at) BETWEEN ? AND ? AND (" . analytics_page_filter_sql() . ") AND visit_id IN (
                 SELECT visit_id FROM pageviews WHERE date(created_at) BETWEEN ? AND ? AND (" . analytics_page_filter_sql() . ")
                 GROUP BY visit_id ORDER BY MIN(created_at) DESC LIMIT $limit
             )
             ORDER BY created_at ASC", [$start, $end, $start, $end]);
        foreach ($rows2 as $row) {
            $actions[(string)$row['visit_id']][] = [
                'path'   => (string)$row['path'],
                'status' => (int)$row['status'],
                'at'     => (string)$row['created_at'],
                'search' => (string)($row['search_term'] ?? ''),
            ];
        }

        foreach ($rows as $row) {
            $vid = (string)$row['visit_id'];
            $started = (string)$row['started_at'];
            $last    = (string)$row['last_at'];
            $duration = 0;
            if ($started !== '' && $last !== '') {
                $duration = max(0, strtotime($last) - strtotime($started));
            }
            $log[] = [
                'visit_id'   => $vid,
                'visitor_id' => (string)($row['visitor_id'] ?? ''),
                'started_at' => $started,
                'duration'   => $duration,
                'actions'    => (int)$row['actions'],
                'referrer'   => (string)($row['referrer_host'] ?? ''),
                'ref_type'   => (string)($row['referrer_type'] ?? 'direct'),
                'device'     => (string)($row['device'] ?? 'unknown'),
                'browser'    => (string)($row['browser'] ?? 'other'),
                'os'         => (string)($row['os'] ?? 'other'),
                'actions_list' => $actions[$vid] ?? [],
            ];
        }
    } catch (\Throwable $e) {
        return [];
    }
    return $log;
}

// Perfil de un visitante: todas sus visitas en el rango indicado.
function analytics_visitor_profile($pdo, $visitorId, $start, $end) {
    $profile = ['visitor_id' => (string)$visitorId, 'visits' => 0, 'actions' => 0, 'duration' => 0, 'log' => []];
    try {
        $visitorId = trim((string)$visitorId);
        if ($visitorId === '') return $profile;
        $rows = analytics_query($pdo,
            analytics_visits_cte($start, $end) . "
             SELECT visit_id, visitor_id, referrer_host, referrer_type, device, browser, os,
                    started_at, last_at, actions
             FROM f WHERE visitor_id = ? ORDER BY started_at DESC LIMIT 100", [$start, $end, $visitorId]);
        foreach ($rows as $row) {
            $started = (string)$row['started_at'];
            $last    = (string)$row['last_at'];
            $duration = ($started !== '' && $last !== '') ? max(0, strtotime($last) - strtotime($started)) : 0;
            $profile['log'][] = [
                'visit_id'   => (string)$row['visit_id'],
                'started_at' => $started,
                'duration'   => $duration,
                'actions'    => (int)$row['actions'],
                'referrer'   => (string)($row['referrer_host'] ?? ''),
                'ref_type'   => (string)($row['referrer_type'] ?? 'direct'),
                'device'     => (string)($row['device'] ?? 'unknown'),
                'browser'    => (string)($row['browser'] ?? 'other'),
                'os'         => (string)($row['os'] ?? 'other'),
            ];
            $profile['visits']++;
            $profile['actions'] += (int)$row['actions'];
            $profile['duration'] += $duration;
        }
    } catch (\Throwable $e) { /* perfil vacío */ }
    return $profile;
}

// Purga de registros antiguos (logrotate manual, sin cron).
function analytics_purge($days = null) {
    try {
        if ($days === null) $days = ANALYTICS_RETENTION_DAYS;
        $days = max(1, (int)$days);
        $pdo = analytics_db();
        if (!$pdo) return 0;
        $stmt = $pdo->prepare("DELETE FROM pageviews WHERE created_at < datetime('now','localtime','-' || ? || ' days')");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    } catch (\Throwable $e) {
        return 0;
    }
}
