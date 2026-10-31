<?php
// ============================================================
//  Lectura segura de ajustes (sin depender de db/functions.php)
// ============================================================
//  Permite leer y guardar valores de la tabla `settings` tanto desde el
//  panel (donde db/functions.php ya está cargado) como desde páginas
//  ligeras como 404.php, que deben funcionar sin dependencias.

if (!defined('M4_SETTINGS_HELPERS')) define('M4_SETTINGS_HELPERS', true);

// Ruta del archivo de la BBDD del CMS
function m4_cms_db_path() {
    if (defined('DB_PATH')) return DB_PATH;
    return dirname(__DIR__) . '/db/cms.db';
}

// Conexión propia a la BBDD del CMS (solo si no hay una ya abierta).
function m4_settings_pdo() {
    static $pdo = null;
    static $tried = false;
    if ($pdo !== null || $tried) return $pdo;
    $tried = true;
    try {
        $path = m4_cms_db_path();
        if (!is_file($path)) return null;
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (\Throwable $e) {
        $pdo = null;
    }
    return $pdo;
}

// Devuelve el valor de un ajuste (null si no existe). Nunca falla.
function m4_setting_value($key) {
    try {
        if (function_exists('getSetting')) {
            return getSetting($key);
        }
        $pdo = m4_settings_pdo();
        if (!$pdo) return null;
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key_name = ?");
        $stmt->execute([(string)$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['value'] : null;
    } catch (\Throwable $e) {
        return null;
    }
}

// Guarda un ajuste. Devuelve true si se ha guardado.
function m4_save_setting($key, $value) {
    try {
        if (function_exists('saveSetting')) {
            return (bool)saveSetting($key, $value);
        }
        $pdo = m4_settings_pdo();
        if (!$pdo) return false;
        $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?)
            ON CONFLICT(key_name) DO UPDATE SET value = excluded.value");
        return $stmt->execute([(string)$key, (string)$value]);
    } catch (\Throwable $e) {
        return false;
    }
}
