<?php
// Registro de actividad y anti-fuerza bruta del login.
// Parte del agregador db/functions.php.

// Crear tabla de logs si no existe
function ensureLogTable() {
    global $db;
    $db->exec("CREATE TABLE IF NOT EXISTS admin_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event TEXT NOT NULL,
        detail TEXT,
        ip TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}

// Registrar un evento de admin
function logAdminEvent($event, $detail = '') {
    try {
        global $db;
        if (!$db) return;
        ensureLogTable();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $db->prepare("INSERT INTO admin_logs (event, detail, ip) VALUES (?, ?, ?)");
        $stmt->execute([$event, $detail, $ip]);
    } catch (\Throwable $e) { /* no romper por logs */ }
}

// Purgar logs mayores a N dias (logrotate manual, sin cron)
function purgeOldLogs($days = 90) {
    try {
        global $db;
        if (!$db) return;
        ensureLogTable();
        $db->exec("DELETE FROM admin_logs WHERE created_at < datetime('now', '-$days days')");
    } catch (\Throwable $e) { /* ignore */ }
}

// ===== ANTI-FUERZA BRUTA (login) =====

// Intentos de login fallidos desde una IP en los ultimos N minutos
// (admin_logs.created_at usa CURRENT_TIMESTAMP = UTC, por eso no se usa localtime)
function countRecentLoginFails($ip, $minutes = 15) {
    try {
        global $db;
        if (!$db) return 0;
        ensureLogTable();
        $stmt = $db->prepare("SELECT COUNT(*) FROM admin_logs WHERE event = 'login_fail' AND ip = ? AND created_at > datetime('now', '-' || ? || ' minutes')");
        $stmt->execute([(string)$ip, (int)$minutes]);
        return (int)$stmt->fetchColumn();
    } catch (\Throwable $e) {
        return 0;
    }
}

// ¿Se ha superado el limite de intentos fallidos para esta IP?
function loginAttemptsExceeded($ip, $max = 8, $minutes = 15) {
    return countRecentLoginFails($ip, $minutes) >= (int)$max;
}

// Obtener logs (paginados)
function getAdminLogs($limit = 50, $offset = 0) {
    global $db;
    ensureLogTable();
    $stmt = $db->prepare("SELECT * FROM admin_logs ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Contar logs
function countAdminLogs() {
    global $db;
    ensureLogTable();
    return (int)$db->query("SELECT COUNT(*) FROM admin_logs")->fetchColumn();
}
