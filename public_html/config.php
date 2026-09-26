<?php
// Configuración general del CMS
define('BASE_URL', 'http://localhost');
define('DB_PATH', __DIR__ . '/db/cms.db');

// Pepper - clave maestra derivada, no es una contraseña sino un valor fijo que acompaña al hash
define('CMS_PEPPER', 'xK9#mP2$vkL7@nQ4wR8zT5yB1cF6hD0jA3gN9sV');

/**
 * Obtener credenciales admin con prioridad en capas:
 * 1. .env ( generada por setup.php o reset-admin.php, más seguro )
 * 2. admin_config.php ( persistencia fallback )
 */
function getCreds() {
    // Capa 1: .env si existe
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        @chmod($envFile, 0600);
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $envVars = [];
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            list($key, $value) = explode('=', $line, 2) + [null, null];
            if ($key && $value !== null) {
                $envVars[trim($key)] = trim($value);
            }
        }
        
        if (!empty($envVars['ADMIN_USER']) && !empty($envVars['ADMIN_PASSWORD_HASH'])) {
            return ['user' => $envVars['ADMIN_USER'], 'hash' => $envVars['ADMIN_PASSWORD_HASH']];
        }
    }
    
    // Capa 2: admin_config.php ( solo si .env no lo resolvió )
    if (file_exists(__DIR__ . '/admin_config.php')) {
        include_once __DIR__ . '/admin_config.php';
        if (defined('ADMIN_USER') && defined('ADMIN_PASSWORD') 
            && ADMIN_USER !== '' && ADMIN_PASSWORD !== '') {
            return ['user' => ADMIN_USER, 'hash' => ADMIN_PASSWORD];
        }
    }
    
    // Sin credenciales configuradas - lanzar excepción para detener ejecución limpia
    throw new RuntimeException(
        'No admin credentials configured. Run setup.php or use admin/reset-admin.php (or your custom admin path).'
    );
}

// Cargar credenciales con protección de 500 en web-mode
$creds_loaded = false;
try {
    $creds = getCreds();
    if (!$creds_loaded) { define('ADMIN_USER', $creds['user']); define('ADMIN_PASSWORD', $creds['hash']); }
    $creds_loaded = true;
} catch (RuntimeException $e) {
    // Web-mode: fallback seguro si no hay credenciales configuradas
    if (@php_sapi_name() === 'cli') throw $e;
    if (!defined('ADMIN_USER')) define('ADMIN_USER', '__fallback__');
    if (!defined('ADMIN_PASSWORD')) define('ADMIN_PASSWORD', '$2y$10$invalidhashfallback');
}

?>
