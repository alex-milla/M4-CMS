<?php
// Registro de actividad y anti-fuerza bruta del login.
// Parte del agregador db/functions.php.

// Crear tabla de logs si no existe
function ensureLogTable() {
    global $db;
    if (!$db) return false;
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

// ===== FILTRADO DE LOGS =====

// Eventos de acceso al panel (apartado "Intentos de login").
function loginEventTypes() {
    return ['login_success', 'login_fail', 'login_blocked'];
}

// Logs filtrados por tipo de evento (array de eventos o null = todos).
function getAdminLogsFiltered($limit = 50, $offset = 0, $events = null) {
    global $db;
    try {
        ensureLogTable();
        if (is_array($events) && count($events) > 0) {
            $events = array_values(array_filter(array_map('strval', $events)));
            $ph = implode(',', array_fill(0, count($events), '?'));
            $stmt = $db->prepare("SELECT * FROM admin_logs WHERE event IN ($ph) ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?");
            $params = $events;
            $params[] = (int)$limit;
            $params[] = (int)$offset;
            $stmt->execute($params);
        } else {
            $stmt = $db->prepare("SELECT * FROM admin_logs ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?");
            $stmt->execute([(int)$limit, (int)$offset]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return [];
    }
}

// Contar logs filtrados por tipo de evento.
function countAdminLogsFiltered($events = null) {
    global $db;
    try {
        ensureLogTable();
        if (is_array($events) && count($events) > 0) {
            $events = array_values(array_filter(array_map('strval', $events)));
            $ph = implode(',', array_fill(0, count($events), '?'));
            $stmt = $db->prepare("SELECT COUNT(*) FROM admin_logs WHERE event IN ($ph)");
            $stmt->execute($events);
            return (int)$stmt->fetchColumn();
        }
        return (int)$db->query("SELECT COUNT(*) FROM admin_logs")->fetchColumn();
    } catch (\Throwable $e) {
        return 0;
    }
}

// ===== BLOQUEO MANUAL DE IPs (anti-fuerza bruta) =====

// Crear tabla de IPs bloqueadas si no existe
function ensureBanTable() {
    global $db;
    if (!$db) return false;
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS banned_ips (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ip TEXT UNIQUE NOT NULL,
            note TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

// Bloquear una IP (validada). Devuelve true si se ha registrado.
function banIp($ip, $note = '') {
    global $db;
    try {
        if (!$db) return false;
        $ip = trim((string)$ip);
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) return false;
        ensureBanTable();
        $note = substr(trim((string)$note), 0, 200);
        $stmt = $db->prepare("INSERT OR REPLACE INTO banned_ips (ip, note, created_at) VALUES (?, ?, datetime('now'))");
        return $stmt->execute([$ip, $note]);
    } catch (\Throwable $e) {
        return false;
    }
}

// Desbloquear una IP. Devuelve true si se ha eliminado.
function unbanIp($ip) {
    global $db;
    try {
        if (!$db) return false;
        $ip = trim((string)$ip);
        if ($ip === '') return false;
        ensureBanTable();
        $stmt = $db->prepare("DELETE FROM banned_ips WHERE ip = ?");
        $stmt->execute([$ip]);
        return $stmt->rowCount() > 0;
    } catch (\Throwable $e) {
        return false;
    }
}

// ¿La IP está bloqueada?
function isIpBanned($ip) {
    global $db;
    try {
        if (!$db) return false;
        $ip = trim((string)$ip);
        if ($ip === '') return false;
        ensureBanTable();
        $stmt = $db->prepare("SELECT COUNT(*) FROM banned_ips WHERE ip = ?");
        $stmt->execute([$ip]);
        return ((int)$stmt->fetchColumn()) > 0;
    } catch (\Throwable $e) {
        return false;
    }
}

// Listado de IPs bloqueadas (más recientes primero).
function getBannedIps($limit = 200) {
    global $db;
    try {
        if (!$db) return [];
        ensureBanTable();
        $stmt = $db->prepare("SELECT * FROM banned_ips ORDER BY created_at DESC, id DESC LIMIT ?");
        $stmt->execute([(int)$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
        return [];
    }
}

// Número de IPs bloqueadas.
function countBannedIps() {
    global $db;
    try {
        if (!$db) return 0;
        ensureBanTable();
        return (int)$db->query("SELECT COUNT(*) FROM banned_ips")->fetchColumn();
    } catch (\Throwable $e) {
        return 0;
    }
}
