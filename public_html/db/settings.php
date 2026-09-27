<?php
// Ajustes del sitio (tabla settings) y URL base.
// Parte del agregador db/functions.php.

// Obtener el valor de un setting (null si no existe o si la DB aún no está lista)
function getSetting($key) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT value FROM settings WHERE key_name = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['value'] : null;
    } catch (\Throwable $e) {
        return null;
    }
}

// Guardar un setting
function saveSetting($key, $value) {
    global $db;
    try {
        $existing = getSetting($key);
        if ($existing !== null) {
            $stmt = $db->prepare("UPDATE settings SET value = ? WHERE key_name = ?");
            return $stmt->execute([$value, $key]);
        } else {
            $stmt = $db->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?)");
            return $stmt->execute([$key, $value]);
        }
    } catch (Exception $e) {
        return false;
    }
}

// Obtener el tema del sitio desde la DB
function getSiteTheme() {
    $db_theme = getSetting('site_theme');
    $valid_themes = ['finsec', 'finsec-dark'];

    if ($db_theme && in_array($db_theme, $valid_themes)) {
        return $db_theme;
    }
    return 'finsec';
}

// Obtener todos los settings como array asociativo
function getAllSettings() {
    global $db;
    $stmt = $db->query("SELECT key_name, value FROM settings");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $config = [];
    foreach ($rows as $row) {
        $config[$row['key_name']] = $row['value'];
    }
    return $config;
}

// URL base absoluta del sitio para feeds/sitemap.
// Prioriza el setting 'base_url' (p. ej. https://midominio.com) y, si no existe,
// reconstruye desde el Host validando su formato (evita Host header poisoning).
function siteBaseUrl() {
    $configured = function_exists('getSetting') ? @getSetting('base_url') : null;
    if (is_string($configured) && $configured !== '' && preg_match('#^https?://#i', $configured)) {
        return rtrim($configured, '/');
    }

    $protocol = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) {
        $host = 'localhost';
    }
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $protocol . '://' . $host . $dir;
}
