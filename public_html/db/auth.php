<?php
// Autenticación de admin y ruta del panel.
// Parte del agregador db/functions.php.

// Slug de la carpeta admin según la DB (con formato validado y fallback)
function adminSlug() {
    static $slug = null;
    if ($slug !== null) return $slug;
    $slug = 'admin';
    try {
        $dbSlug = function_exists('getSetting') ? @getSetting('admin_path') : null;
        if (is_string($dbSlug) && preg_match('/^[a-z0-9][a-z0-9\-]{1,29}$/', $dbSlug)) {
            $slug = $dbSlug;
        }
    } catch (\Throwable $e) { /* DB no disponible */ }
    return $slug;
}

// URL relativa (desde la raíz del CMS) hacia un archivo del panel admin
function adminUrl($path = '') {
    return adminSlug() . '/' . ltrim($path, '/');
}

// Verificación de credenciales de admin (compartida por login.php y settings.php)
function verifyAdminCredential($inputUser, $inputPassword) {
    $cmsRoot = dirname(__DIR__);
    $stored_user = '';
    $stored_pass = '';

    if (file_exists($cmsRoot . '/admin_config.php')) {
        try {
            @include_once $cmsRoot . '/admin_config.php';
            if (defined('ADMIN_USER') && defined('ADMIN_PASSWORD_HASH')) {
                if ($inputUser === ADMIN_USER && password_verify($inputPassword, ADMIN_PASSWORD_HASH)) return true;
            }
            if (defined('ADMIN_USER') && defined('ADMIN_PASSWORD')) {
                if ($inputUser === ADMIN_USER && password_verify($inputPassword, ADMIN_PASSWORD)) return true;
            }
        } catch (\Throwable $e) { /* ignore */ }
    }

    if (file_exists($cmsRoot . '/.env')) {
        try {
            $lines = file($cmsRoot . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (stripos($line, 'ADMIN_USER=') === 0) list(,$stored_user) = explode('=', $line, 2);
                if (stripos($line, 'ADMIN_PASSWORD_HASH=') === 0) list(,$stored_pass) = explode('=', $line, 2);
            }
        } catch (\Throwable $e) { /* ignore */ }
    }

    if (!empty($stored_user) && !empty($stored_pass)) {
        if ($inputUser === $stored_user && password_verify($inputPassword, $stored_pass)) return true;
    }

    try {
        @include_once $cmsRoot . '/config.php';
        if (defined('ADMIN_USER') && ADMIN_USER !== '__fallback__' && defined('ADMIN_PASSWORD')) {
            if ($inputUser === ADMIN_USER && password_verify($inputPassword, ADMIN_PASSWORD)) return true;
        }
    } catch (\Throwable $e) { /* ignore */ }

    // Capa DB: credenciales guardadas desde settings.php (tabla settings)
    try {
        $db_user = getSetting('admin_user');
        $db_pass = getSetting('admin_password');
        if (!empty($db_user) && !empty($db_pass)) {
            if ($inputUser === $db_user && password_verify($inputPassword, $db_pass)) return true;
        }
    } catch (\Throwable $e) { /* DB no disponible */ }

    return false;
}

// Red de seguridad: la carpeta real del admin es la fuente de verdad.
// Si la DB diverge (rename manual por FTP, operación interrumpida), se corrige sola.
function syncAdminPath() {
    static $done = false;
    if ($done) return;
    $done = true;
    if (!function_exists('getSetting')) return;
    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    if ($script === '') return;
    $dir = dirname($script);
    // Solo actuar cuando el script en ejecución vive dentro de la carpeta admin
    if (!file_exists($dir . '/login.php') || !file_exists($dir . '/logout.php')) return;
    $slug = basename($dir);
    try {
        $dbSlug = @getSetting('admin_path');
        if (!is_string($dbSlug) || $dbSlug === '' || $dbSlug !== $slug) {
            @saveSetting('admin_path', $slug);
        }
    } catch (\Throwable $e) { /* DB no disponible */ }
}
