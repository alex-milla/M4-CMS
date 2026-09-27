<?php
// Bootstrap de sesión compartido: fija los flags de la cookie antes de iniciar.
// Reemplaza a las llamadas sueltas a session_start() en toda la aplicación.

// ¿La petición llega por HTTPS? (directo o detrás de un proxy de confianza)
function m4_is_https() {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = strtolower(trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        if ($proto === 'https') return true;
    }
    return false;
}

// Inicia la sesión una sola vez con cookie HttpOnly + SameSite (y Secure en HTTPS).
function m4_session_start() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $lifetime = 0;
    $path     = '/';
    $domain   = '';
    $secure   = m4_is_https();
    $httponly = true;

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => $path,
            'domain'   => $domain,
            'secure'   => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);
    }

    session_start();
}
