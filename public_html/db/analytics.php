<?php
// ============================================================
//  Analítica web propia (mini-Matomo, versión ligera)
// ============================================================
//  Registra cada página pública servida por el CMS en una base de datos
//  SQLite INDEPENDIENTE (db/analytics.db). El módulo es AUTÓNOMO:
//  no depende de db/functions.php ni de la sesión, para poder incluirse
//  también desde 404.php, que debe seguir funcionando sin dependencias.
//
//  Características (equivalencias con Matomo):
//    · Visitas (sesiones de 30 min) y páginas vistas
//    · Visitas nuevas, rebote y páginas por visita
//    · Páginas más vistas y páginas de entrada
//    · Origen del tráfico: directo / buscador / red social / otro sitio
//    · Dispositivo y navegador (User-Agent)
//    · Errores 404/403
//
//  Privacidad: la IP nunca se guarda en claro (hash salado) y la cookie es
//  un identificador aleatorio de visita, sin datos personales.
//
//  Todo es tolerante a fallos: si la BBDD no es escribible, la web sigue
//  funcionando con normalidad (se registra el error y se sigue).

if (!defined('ANALYTICS_DB_PATH')) define('ANALYTICS_DB_PATH', __DIR__ . '/analytics.db');
if (!defined('ANALYTICS_COOKIE'))  define('ANALYTICS_COOKIE', 'm4_sid');
if (!defined('ANALYTICS_SALT'))    define('ANALYTICS_SALT', 'm4-analytics-v1');
if (!defined('ANALYTICS_RETENTION_DAYS')) define('ANALYTICS_RETENTION_DAYS', 180);

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

// Crea las tablas si no existen.
function analytics_schema($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pageviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        visit_id TEXT NOT NULL,
        path TEXT NOT NULL,
        status INTEGER NOT NULL DEFAULT 200,
        referrer TEXT,
        referrer_host TEXT,
        referrer_type TEXT NOT NULL DEFAULT 'direct',
        device TEXT NOT NULL DEFAULT 'unknown',
        browser TEXT NOT NULL DEFAULT 'other',
        is_new_visit INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pv_created ON pageviews(created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pv_visit ON pageviews(visit_id)");
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
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return hash_hmac('sha256', $ip, ANALYTICS_SALT);
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
    if (preg_match('/Safari\//i', $ua)) return 'Safari';
    return 'other';
}

// Dominios de buscadores (para agrupar el origen del tráfico).
function analytics_search_hosts() {
    return [
        'google.', 'bing.', 'duckduckgo.', 'yahoo.', 'baidu.', 'yandex.', 'ecosia.',
        'brave.', 'startpage.', 'qwant.', 'naver.', 'seznam.', 'ask.', 'aol.',
        'mojeek.', 'search.brave.', 'lite.duckduckgo.', 'search.yahoo.', 'www.google.',
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

// Analiza el referer: host, tipo (direct|search|social|other) y host limpio.
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
    foreach (analytics_search_hosts() as $needle) {
        if (strpos($lower, $needle) !== false) {
            $info['type'] = 'search';
            return $info;
        }
    }
    foreach (analytics_social_hosts() as $needle) {
        if (strpos($lower, $needle) !== false) {
            $info['type'] = 'social';
            return $info;
        }
    }
    $info['type'] = 'other';
    return $info;
}

// Fija la cookie de visita (ventana deslizante de 30 minutos).
function analytics_set_cookie($value) {
    if (headers_sent()) return false;
    $expires = time() + 1800;
    $secure  = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');
    if (PHP_VERSION_ID >= 70300) {
        return (bool)setcookie(ANALYTICS_COOKIE, $value, [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    return (bool)setcookie(ANALYTICS_COOKIE, $value, $expires, '/', '', $secure, true);
}

// Identificador de visita: cookie aleatoria o huella alternativa sin cookies.
// Devuelve [visit_id, es_nueva, modo].
function analytics_visit_id() {
    $sid = isset($_COOKIE[ANALYTICS_COOKIE]) ? (string)$_COOKIE[ANALYTICS_COOKIE] : '';
    if ($sid !== '' && preg_match('/^[a-f0-9]{32}$/', $sid)) {
        analytics_set_cookie($sid); // renueva la ventana de 30 min
        return [$sid, false, 'cookie'];
    }
    $sid = bin2hex(random_bytes(16));
    if (analytics_set_cookie($sid)) {
        return [$sid, true, 'cookie'];
    }
    // Sin soporte de cookies: huella del día (IP hasheada + UA)
    $fp = hash_hmac('sha256', analytics_ip_hash() . '|' . analytics_user_agent() . '|' . date('Y-m-d'), ANALYTICS_SALT);
    return [substr($fp, 0, 32), false, 'fingerprint'];
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

// Registra una página vista. Opciones: path, status.
// Devuelve true si se ha registrado.
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

        list($visitId, $isNew, $mode) = analytics_visit_id();
        if ($mode === 'fingerprint' && analytics_visit_exists($pdo, $visitId)) $isNew = false;

        $referrer = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
        $ref = analytics_referrer_info($referrer);

        $stmt = $pdo->prepare(
            "INSERT INTO pageviews
                (visit_id, path, status, referrer, referrer_host, referrer_type, device, browser, is_new_visit, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now','localtime'))"
        );
        $stmt->execute([
            $visitId,
            $path,
            $status,
            $referrer !== '' ? substr($referrer, 0, 300) : null,
            $ref['host'] !== '' ? $ref['host'] : null,
            $ref['type'],
            analytics_device($ua),
            analytics_browser($ua),
            $isNew ? 1 : 0,
        ]);
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

// ------------------------------------------------------------
//  Agregaciones para el panel de estadísticas
// ------------------------------------------------------------

// Ejecuta una consulta de agregación con los parámetros indicados.
// Devuelve array de filas (nunca null).
function analytics_query($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(is_array($params) ? $params : [$params]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return [];
    }
}

// Métricas completas de los últimos X días.
function analytics_stats($days = 30) {
    $days = max(1, min(365, (int)$days));

    $stats = [
        'days'          => $days,
        'pageviews'     => 0,
        'visits'        => 0,
        'new_visits'    => 0,
        'bounce_rate'   => 0.0,
        'pages_per_visit' => 0.0,
        'daily'         => [],
        'pages'         => [],
        'entry_pages'   => [],
        'referrers'     => [],
        'referrer_types'=> [],
        'devices'       => [],
        'browsers'      => [],
        'statuses'      => [],
        'available'     => false,
    ];

    try {
        $pdo = analytics_db();
        if (!$pdo) return $stats;
        $stats['available'] = true;

        $since = "datetime('now','localtime','-' || ? || ' days')";

        // Totales
        $rows = analytics_query($pdo,
            "SELECT COUNT(*) AS views,
                    COUNT(DISTINCT visit_id) AS visits,
                    SUM(is_new_visit) AS new_visits
             FROM pageviews WHERE created_at >= $since", $days);
        if (!empty($rows[0])) {
            $stats['pageviews']  = (int)$rows[0]['views'];
            $stats['visits']     = (int)$rows[0]['visits'];
            $stats['new_visits'] = (int)$rows[0]['new_visits'];
        }

        // Rebote: visitas con una sola página vista
        $rows = analytics_query($pdo,
            "SELECT COUNT(*) AS bounces FROM (
                SELECT visit_id FROM pageviews WHERE created_at >= $since
                GROUP BY visit_id HAVING COUNT(*) = 1
             )", $days);
        $bounces = !empty($rows[0]) ? (int)$rows[0]['bounces'] : 0;
        if ($stats['visits'] > 0) {
            $stats['bounce_rate']     = round(($bounces / $stats['visits']) * 100, 1);
            $stats['pages_per_visit'] = round($stats['pageviews'] / $stats['visits'], 2);
        }

        // Serie diaria (con los días sin datos rellenados a cero).
        // El anclaje de fechas se calcula en SQLite ('localtime') para que
        // coincida con el marco temporal usado al insertar los registros.
        $rows = analytics_query($pdo,
            "SELECT date('now','localtime','-' || ? || ' days') AS d", [$days - 1]);
        $firstDay = !empty($rows[0]['d']) ? (string)$rows[0]['d'] : date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

        $rows = analytics_query($pdo,
            "SELECT date(created_at) AS d,
                    COUNT(*) AS views,
                    COUNT(DISTINCT visit_id) AS visits
             FROM pageviews WHERE created_at >= $since
             GROUP BY d ORDER BY d", $days);
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['d']] = ['date' => $row['d'], 'views' => (int)$row['views'], 'visits' => (int)$row['visits']];
        }
        $cursor = DateTime::createFromFormat('Y-m-d', $firstDay);
        if ($cursor === false) {
            $cursor = new DateTime('today');
            $cursor->modify('-' . ($days - 1) . ' days');
        }
        for ($i = 0; $i < $days; $i++) {
            $key = $cursor->format('Y-m-d');
            $stats['daily'][] = $byDay[$key] ?? ['date' => $key, 'views' => 0, 'visits' => 0];
            $cursor->modify('+1 day');
        }

        // Páginas más vistas
        $rows = analytics_query($pdo,
            "SELECT path, COUNT(*) AS views, COUNT(DISTINCT visit_id) AS visits
             FROM pageviews WHERE created_at >= $since
             GROUP BY path ORDER BY views DESC LIMIT 25", $days);
        foreach ($rows as $row) {
            $stats['pages'][] = [
                'path'   => $row['path'],
                'views'  => (int)$row['views'],
                'visits' => (int)$row['visits'],
            ];
        }

        // Páginas de entrada (primera página vista de cada visita)
        // La subconsulta repite el filtro de fechas: dos parámetros.
        $rows = analytics_query($pdo,
            "SELECT path, COUNT(*) AS entries FROM pageviews
             WHERE created_at >= $since
               AND id IN (SELECT MIN(id) FROM pageviews WHERE created_at >= $since GROUP BY visit_id)
             GROUP BY path ORDER BY entries DESC LIMIT 25", [$days, $days]);
        foreach ($rows as $row) {
            $stats['entry_pages'][] = ['path' => $row['path'], 'visits' => (int)$row['entries']];
        }

        // Referentes
        $rows = analytics_query($pdo,
            "SELECT referrer, referrer_host, referrer_type, COUNT(DISTINCT visit_id) AS visits
             FROM pageviews
             WHERE created_at >= $since AND referrer IS NOT NULL AND referrer <> ''
             GROUP BY referrer ORDER BY visits DESC LIMIT 25", $days);
        foreach ($rows as $row) {
            $stats['referrers'][] = [
                'referrer' => (string)$row['referrer'],
                'host'     => (string)$row['referrer_host'],
                'type'     => (string)$row['referrer_type'],
                'visits'   => (int)$row['visits'],
            ];
        }

        // Tipos de referente
        $rows = analytics_query($pdo,
            "SELECT referrer_type, COUNT(DISTINCT visit_id) AS visits
             FROM pageviews WHERE created_at >= $since
             GROUP BY referrer_type", $days);
        foreach ($rows as $row) {
            $stats['referrer_types'][(string)$row['referrer_type']] = (int)$row['visits'];
        }

        // Dispositivos
        $rows = analytics_query($pdo,
            "SELECT device, COUNT(DISTINCT visit_id) AS visits
             FROM pageviews WHERE created_at >= $since GROUP BY device ORDER BY visits DESC", $days);
        foreach ($rows as $row) {
            $stats['devices'][(string)$row['device']] = (int)$row['visits'];
        }

        // Navegadores
        $rows = analytics_query($pdo,
            "SELECT browser, COUNT(DISTINCT visit_id) AS visits
             FROM pageviews WHERE created_at >= $since GROUP BY browser ORDER BY visits DESC", $days);
        foreach ($rows as $row) {
            $stats['browsers'][(string)$row['browser']] = (int)$row['visits'];
        }

        // Códigos de estado (para detectar 404/403)
        $rows = analytics_query($pdo,
            "SELECT status, COUNT(*) AS views
             FROM pageviews WHERE created_at >= $since GROUP BY status ORDER BY views DESC", $days);
        foreach ($rows as $row) {
            $stats['statuses'][(string)$row['status']] = (int)$row['views'];
        }
    } catch (\Throwable $e) {
        // Devuelve los valores por defecto (nunca rompe la página)
    }

    return $stats;
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
