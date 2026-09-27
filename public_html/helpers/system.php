<?php
// Métricas de sistema para el panel: ocupación de la BBDD, respaldos y disco.
// Todo tolerante a funciones deshabilitadas (hosting compartido): nunca rompe.

// Formatea bytes a un tamaño legible. Devuelve '—' si no hay dato.
function formatBytes($bytes, $precision = 1) {
    if ($bytes === null || $bytes === false || !is_numeric($bytes) || $bytes < 0) {
        return '—';
    }
    $bytes = (float)$bytes;
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return number_format($bytes, $i === 0 ? 0 : $precision, '.', ',') . ' ' . $units[$i];
}

// Ruta del archivo de la base de datos.
function systemDbPath() {
    if (defined('DB_PATH')) return DB_PATH;
    return dirname(__DIR__) . '/db/cms.db';
}

// Tamaño de un directorio (recursivo). 0 si no existe o falla.
function getDirSize($dir) {
    if (!is_dir($dir)) return 0;
    $size = 0;
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
    } catch (\Throwable $e) {
        return $size;
    }
    return $size;
}

// Métricas de sistema: BBDD, respaldos y disco de la cuenta.
function getSystemMetrics() {
    $dbPath     = systemDbPath();
    $dbDir      = dirname($dbPath);
    $backupsDir = dirname(__DIR__) . '/db/backups';

    $dbSize      = is_file($dbPath) ? (int)filesize($dbPath) : 0;
    $backupsSize = getDirSize($backupsDir);

    $diskFree  = function_exists('disk_free_space')  ? @disk_free_space($dbDir)  : false;
    $diskTotal = function_exists('disk_total_space') ? @disk_total_space($dbDir) : false;
    if ($diskFree === false)  $diskFree  = null;
    if ($diskTotal === false) $diskTotal = null;

    $usedPct = null;
    if ($diskFree !== null && $diskTotal !== null && $diskTotal > 0) {
        $usedPct = (1 - ($diskFree / $diskTotal)) * 100;
    }

    return [
        'db_size'        => $dbSize,
        'backups_size'   => $backupsSize,
        'disk_free'      => $diskFree,
        'disk_total'     => $diskTotal,
        'disk_used_pct'  => $usedPct,
        'disk_available' => ($diskFree !== null && $diskTotal !== null),
    ];
}
