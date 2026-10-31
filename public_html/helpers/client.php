<?php
// ============================================================
//  Cliente (IP real y User-Agent)
// ============================================================
//  Resuelve la IP real del visitante cuando el sitio está detrás de un
//  proxy o CDN (Cloudflare, Nginx, etc.) y clasifica el User-Agent.
//
//  Regla de seguridad: las cabeceras de reenvío SOLO se usan si la
//  conexión directa (REMOTE_ADDR) pertenece a un proxy de confianza
//  (rangos de Cloudflare + los configurados en Ajustes). Así nadie puede
//  falsificar su IP para evadir el rate limit o la lista de IPs bloqueadas.

if (!defined('M4_CLIENT_HELPERS')) define('M4_CLIENT_HELPERS', true);

require_once __DIR__ . '/settings.php';

// Rangos oficiales de IP de Cloudflare (proxy inverso por defecto).
function m4_cloudflare_cidrs() {
    return [
        '173.245.48.0/20',   '103.21.244.0/22',  '103.22.200.0/22',
        '103.31.4.0/22',     '141.101.64.0/18',  '108.162.192.0/18',
        '190.93.240.0/20',   '188.114.96.0/20',  '197.234.240.0/22',
        '198.41.128.0/17',   '162.158.0.0/15',   '104.16.0.0/13',
        '104.24.0.0/14',     '172.64.0.0/13',    '131.0.72.0/22',
        '2400:cb00::/32',    '2606:4700::/32',   '2803:f800::/32',
        '2405:b500::/32',    '2405:8100::/32',   '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];
}

// Rangos privados/reservados: nunca son IPs públicas de cliente.
function m4_private_cidrs() {
    return [
        '0.0.0.0/8',        '10.0.0.0/8',       '100.64.0.0/10',
        '127.0.0.0/8',      '169.254.0.0/16',   '172.16.0.0/12',
        '192.0.0.0/24',     '192.0.2.0/24',     '192.168.0.0/16',
        '198.18.0.0/15',    '198.51.100.0/24',  '203.0.113.0/24',
        '224.0.0.0/4',      '240.0.0.0/4',
        '::1/128',          'fc00::/7',         'fe80::/10',
        '2001:db8::/32',
    ];
}

// Lista de proxies de confianza: Cloudflare + los configurados en Ajustes.
function m4_trusted_proxy_cidrs() {
    static $cache = null;
    if ($cache !== null) return $cache;

    $list = m4_cloudflare_cidrs();
    $extra = (string)(m4_setting_value('trusted_proxies') ?? '');
    if (trim($extra) !== '') {
        foreach (preg_split('/[\s,;]+/', $extra) as $cidr) {
            $cidr = trim((string)$cidr);
            if ($cidr !== '') $list[] = $cidr;
        }
    }
    $cache = $list;
    return $cache;
}

// ¿La IP pertenece a alguno de los rangos indicados?
function m4_ip_in_cidrs($ip, array $cidrs) {
    $ipBin = @inet_pton((string)$ip);
    if ($ipBin === false) return false;
    foreach ($cidrs as $cidr) {
        $cidr = trim((string)$cidr);
        if ($cidr === '') continue;
        if (strpos($cidr, '/') === false) {
            $net = @inet_pton($cidr);
            if ($net !== false && $net === $ipBin) return true;
            continue;
        }
        list($subnet, $bits) = array_pad(explode('/', $cidr, 2), 2, '32');
        $subnetBin = @inet_pton($subnet);
        if ($subnetBin === false) continue;
        if (strlen($subnetBin) !== strlen($ipBin)) continue;
        $bits = (int)$bits;
        $bytes = intdiv($bits, 8);
        $rest  = $bits % 8;
        if ($bytes > 0 && strncmp($ipBin, $subnetBin, $bytes) !== 0) continue;
        if ($rest > 0) {
            $mask = (~((1 << (8 - $rest)) - 1)) & 0xFF;
            if ((ord($ipBin[$bytes]) & $mask) !== (ord($subnetBin[$bytes]) & $mask)) continue;
        }
        return true;
    }
    return false;
}

// ¿Es una IP pública válida (ni privada ni reservada)?
function m4_is_public_ip($ip) {
    $ip = trim((string)$ip);
    if ($ip === '') return false;
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) return false;
    if (m4_ip_in_cidrs($ip, m4_private_cidrs())) return false;
    return true;
}

// ¿Esta IP es la de un proxy/CDN de confianza (p. ej. Cloudflare)?
function m4_is_trusted_proxy_ip($ip) {
    $ip = trim((string)$ip);
    if ($ip === '') return false;
    return m4_ip_in_cidrs($ip, m4_trusted_proxy_cidrs());
}

// Extrae la IP del cliente de una cabecera de reenvío.
function m4_ip_from_header($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    foreach (explode(',', $value) as $part) {
        $ip = trim($part);
        if ($ip === '') continue;
        // Algunos proxies envían "IP:puerto"
        if (strpos($ip, ':') !== false && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            $ip = explode(':', $ip)[0];
        }
        if (m4_is_public_ip($ip)) return $ip;
    }
    return '';
}

// IP real del cliente. Detrás de Cloudflare devuelve la del visitante.
function m4_client_ip() {
    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($remote === '') return 'unknown';

    // Solo si la conexión viene de un proxy de confianza nos fiamos de las cabeceras
    if (m4_is_trusted_proxy_ip($remote)) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $header) {
            $candidate = m4_ip_from_header((string)($_SERVER[$header] ?? ''));
            if ($candidate !== '') return $candidate;
        }
    }
    return $remote;
}

// User-Agent recortado del cliente.
function m4_client_user_agent() {
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

// ¿El User-Agent parece un bot o un rastreador?
function m4_ua_is_bot($ua) {
    $ua = (string)$ua;
    if (trim($ua) === '') return false;
    return (bool)preg_match(
        '/bot\b|bot\/|crawl|spider|slurp|bingpreview|facebookexternalhit|curl\/|wget\/|python-requests|headless|lighthouse|pingdom|uptimerobot|monitoring|semrush|ahrefs|mj12|scrapy|phantomjs/i',
        $ua
    );
}

// Anonimiza una IP para poder mostrarla sin guardar el dato completo:
//   IPv4 -> se oculta el último octeto (188.114.96.0), igual que hace Matomo
//   IPv6 -> se conservan los 3 primeros grupos (prefijo /48)
function m4_anonymize_ip($ip) {
    $ip = trim((string)$ip);
    if ($ip === '') return '';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
        $parts = explode('.', $ip);
        $parts[3] = '0';
        return implode('.', $parts);
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
        $packed = @inet_pton($ip);
        if ($packed === false) return '';
        $masked = substr($packed, 0, 6) . str_repeat("\0", 10);
        $out = @inet_ntop($masked);
        return $out !== false ? $out : '';
    }
    return '';
}

// Nombre corto del navegador/cliente a partir del User-Agent.
function m4_ua_summary($ua) {
    $ua = (string)$ua;
    if (trim($ua) === '') return '—';
    if (m4_ua_is_bot($ua)) return 'Bot';
    if (preg_match('/Edg\//i', $ua)) return 'Edge';
    if (preg_match('/OPR\/|Opera/i', $ua)) return 'Opera';
    if (preg_match('/SamsungBrowser/i', $ua)) return 'Samsung Internet';
    if (preg_match('/Firefox\/|FxiOS/i', $ua)) return 'Firefox';
    if (preg_match('/Chrome\/|CriOS/i', $ua)) return 'Chrome';
    if (preg_match('/MSIE|Trident/i', $ua)) return 'Internet Explorer';
    if (preg_match('/Safari\//i', $ua)) return 'Safari';
    return 'Otro';
}

// Limpia la lista de proxies de confianza: solo IPs y rangos CIDR válidos.
function m4_sanitize_trusted_proxies($value) {
    $out = [];
    $parts = preg_split('/[\s,;]+/', (string)$value);
    foreach ($parts as $part) {
        $part = trim((string)$part);
        if ($part === '') continue;
        if (strpos($part, '/') !== false) {
            list($ip, $bits) = array_pad(explode('/', $part, 2), 2, '');
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) continue;
            $bits = (int)$bits;
            $max  = (strpos($ip, ':') !== false) ? 128 : 32;
            if ($bits < 0 || $bits > $max) continue;
            $out[] = $ip . '/' . $bits;
        } else {
            if (filter_var($part, FILTER_VALIDATE_IP) === false) continue;
            $out[] = $part;
        }
        if (count($out) >= 50) break;
    }
    return implode(', ', $out);
}
