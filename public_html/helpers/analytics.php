<?php
// ============================================================
//  Extras del <head> para las páginas públicas
// ============================================================
//  Meta-etiquetas de verificación de buscadores (Google Webmaster Tools
//  y Bing Webmaster Tools) configuradas desde el panel de ajustes.
//
//  Todo es tolerante a fallos: si la BBDD no está disponible, la web se
//  sirve igual, simplemente sin las meta-etiquetas.

// Lee un ajuste del CMS. Usa getSetting() si está disponible y, si no
// (por ejemplo en 404.php, que va sin dependencias), lee la tabla
// 'settings' directamente. Devuelve null si no existe.
function m4_setting_value($key) {
    try {
        if (function_exists('getSetting')) {
            return getSetting($key);
        }
        static $pdo = null;
        if ($pdo === null) {
            $path = defined('DB_PATH') ? DB_PATH : dirname(__DIR__) . '/db/cms.db';
            if (!is_file($path)) return null;
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = ?");
        $stmt->execute([(string)$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['value'] : null;
    } catch (\Throwable $e) {
        return null;
    }
}

// Limpia un token de verificación (solo caracteres seguros y sin comillas).
// Acepta tanto el token suelto ("AbCd1234") como la meta-etiqueta completa
// (<meta name="google-site-verification" content="AbCd1234">).
function m4_sanitize_verification_token($token) {
    $token = trim((string)$token);
    if ($token === '') return '';
    if (stripos($token, 'content=') !== false && preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $token, $m)) {
        $token = trim($m[1]);
    }
    $token = preg_replace('/[^A-Za-z0-9_\-]/', '', $token);
    return substr((string)$token, 0, 128);
}

// Imprime las meta-etiquetas de verificación de los buscadores.
// Llamar dentro del <head> de las páginas públicas.
function renderHeadExtras() {
    try {
        $google = m4_sanitize_verification_token(m4_setting_value('wt_google_token'));
        $bing   = m4_sanitize_verification_token(m4_setting_value('wt_bing_token'));

        if ($google !== '') {
            echo '    <meta name="google-site-verification" content="' . htmlspecialchars($google, ENT_QUOTES) . '">' . "\n";
        }
        if ($bing !== '') {
            echo '    <meta name="msvalidate.01" content="' . htmlspecialchars($bing, ENT_QUOTES) . '">' . "\n";
        }
    } catch (\Throwable $e) {
        // Nunca romper el renderizado de la página
    }
}
