<?php
/**
 * M4 CMS - Lógica de actualización (funciones puras, sin red ni sesión).
 *
 * Implementa:
 *  - Manifiesto SHA-256 de los ficheros gestionados (origen y destino).
 *  - Sincronización: copia solo ficheros nuevos/cambiados.
 *  - Limpieza: borra ficheros gestionados que ya no existen en la release.
 *  - Lista de borrado forzoso (utilidades peligrosas/obsoletas).
 *  - Backup y restauración del código, con remapeo de la carpeta admin.
 *
 * Todas las rutas del manifiesto son canónicas (la carpeta admin se guarda
 * siempre como "entrada"), y se remapean al slug real al aplicar al disco.
 */

if (!defined('M4_UPDATER_LOADED')) {
    define('M4_UPDATER_LOADED', true);
}

/**
 * Remapea el primer segmento de una ruta relativa (p. ej. entrada -> panel).
 */
function m4_remap_path(string $rel, array $remap): string {
    if (empty($remap)) {
        return $rel;
    }
    $parts = explode('/', $rel);
    if (isset($remap[$parts[0]])) {
        $parts[0] = $remap[$parts[0]];
    }
    return implode('/', $parts);
}

/**
 * Elimina un directorio recursivamente.
 */
function m4_rrmdir(string $dir): void {
    if (!is_dir($dir)) {
        return;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? m4_rrmdir($path) : @unlink($path);
    }
    @rmdir($dir);
}

/**
 * Copia un árbol de ficheros aplicando remapeo y exclusiones.
 */
function m4_copy_tree_mapped(string $src, string $dst, array $remap = [], array $sensitive = [], array $skipDirs = [], array $skipPrefixes = []): array {
    $copied = 0;
    $errors = [];
    if (!is_dir($src)) {
        return ['copied' => 0, 'errors' => ["Origen no encontrado: $src"]];
    }
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($rii as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($src) + 1));
        if ($relative === '' || in_array($relative, $sensitive, true)) {
            continue;
        }
        $parts = explode('/', $relative);
        if (in_array($parts[0], $skipDirs, true)) {
            continue;
        }
        $skip = false;
        foreach ($skipPrefixes as $p) {
            if (strpos($relative, $p) === 0) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }
        $target = rtrim($dst, '/\\') . '/' . m4_remap_path($relative, $remap);
        if (!is_dir(dirname($target))) {
            @mkdir(dirname($target), 0755, true);
        }
        if (@copy($file->getPathname(), $target)) {
            $copied++;
        } else {
            $errors[] = $relative;
        }
    }
    return ['copied' => $copied, 'errors' => $errors];
}

/**
 * Construye el manifiesto (ruta relativa canónica => sha256) de un directorio.
 */
function m4_build_manifest(string $dir, array $sensitive = [], array $skipDirs = [], array $skipPrefixes = []): array {
    $manifest = [];
    if (!is_dir($dir)) {
        return $manifest;
    }
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($rii as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
        if ($relative === '' || in_array($relative, $sensitive, true)) {
            continue;
        }
        $parts = explode('/', $relative);
        if (in_array($parts[0], $skipDirs, true)) {
            continue;
        }
        $skip = false;
        foreach ($skipPrefixes as $p) {
            if (strpos($relative, $p) === 0) {
                $skip = true;
                break;
            }
        }
        if ($skip) {
            continue;
        }
        $manifest[$relative] = hash_file('sha256', $file->getPathname());
    }
    ksort($manifest);
    return $manifest;
}

function m4_load_manifest(string $path): array {
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string)@file_get_contents($path), true);
    if (!is_array($data)) {
        return [];
    }
    $files = $data['files'] ?? $data;
    return is_array($files) ? $files : [];
}

function m4_save_manifest(string $path, string $version, array $files): bool {
    if (!is_dir(dirname($path))) {
        @mkdir(dirname($path), 0755, true);
    }
    $payload = [
        'version'      => $version,
        'generated_at' => date('c'),
        'files'        => $files,
    ];
    return @file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
}

/**
 * Sincroniza la instalación con una release extraída.
 *
 * - Copia solo ficheros nuevos o con hash distinto.
 * - Borra ficheros gestionados que ya no están en la release nueva.
 * - Borra la lista de utilidades obsoletas/peligrosas indicada en $forceDelete.
 * - Nunca toca ficheros marcados como $sensitive.
 * - Guarda el manifiesto nuevo.
 *
 * @return array{copied:int,skipped:int,deleted:int,errors:array,manifest:int}
 */
function m4_sync_from_source(string $srcWeb, string $appRoot, string $adminSlug, string $version, array $sensitive, array $skipDirs, array $skipPrefixes, array $forceDelete = []): array {
    $installed   = is_file($appRoot . '/.setup_completed');
    $manifestPath = $appRoot . '/db/.manifest.json';
    $remap       = ($adminSlug !== 'entrada') ? ['entrada' => $adminSlug] : [];

    $newManifest = m4_build_manifest($srcWeb, $sensitive, $skipDirs, $skipPrefixes);
    // No re-copiar el instalador si el sitio ya está instalado.
    if ($installed) {
        unset($newManifest['setup.php']);
    }
    // Las utilidades de la lista de borrado nunca forman parte del manifiesto.
    foreach ($forceDelete as $rel) {
        unset($newManifest[$rel]);
    }

    $copied = 0;
    $skipped = 0;
    $deleted = 0;
    $errors = [];

    foreach ($newManifest as $rel => $sha) {
        $dst = $appRoot . '/' . m4_remap_path($rel, $remap);
        if (is_file($dst) && hash_file('sha256', $dst) === $sha) {
            $skipped++;
            continue;
        }
        if (!is_dir(dirname($dst))) {
            @mkdir(dirname($dst), 0755, true);
        }
        if (@copy($srcWeb . '/' . $rel, $dst)) {
            $copied++;
        } else {
            $errors[] = $rel;
        }
    }

    // Borrar ficheros gestionados que ya no existen en la release.
    $oldManifest = m4_load_manifest($manifestPath);
    foreach ($oldManifest as $rel => $sha) {
        if (isset($newManifest[$rel])) {
            continue;
        }
        $path = $appRoot . '/' . m4_remap_path($rel, $remap);
        if (is_file($path) && @unlink($path)) {
            $deleted++;
        }
    }

    // Lista de borrado forzoso (seguridad / obsoletos).
    foreach ($forceDelete as $rel) {
        $path = $appRoot . '/' . m4_remap_path($rel, $remap);
        if (is_file($path) && @unlink($path)) {
            $deleted++;
        }
    }

    m4_save_manifest($manifestPath, $version, $newManifest);

    return [
        'copied'   => $copied,
        'skipped'  => $skipped,
        'deleted'  => $deleted,
        'errors'   => $errors,
        'manifest' => count($newManifest),
    ];
}

function m4_ensure_backup_guards(string $dir): void {
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, "# Bloquear el acceso web a las copias de seguridad\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\nOptions -Indexes\n");
    }
    $index = $dir . '/index.php';
    if (!is_file($index)) {
        @file_put_contents($index, "<?php http_response_code(403); exit('Forbidden');\n");
    }
}

/**
 * Crea un backup del código actual. La carpeta admin se normaliza a "entrada".
 * Incluye db/.manifest.json para poder restaurar el estado exacto.
 */
function m4_create_backup(string $appRoot, string $backupBase, string $adminSlug, array $sensitive, array $skipDirs, array $skipPrefixes): array {
    if (!is_dir($backupBase)) {
        @mkdir($backupBase, 0755, true);
    }
    m4_ensure_backup_guards($backupBase);
    $name = 'backup_' . date('Ymd_His');
    $dir  = rtrim($backupBase, '/\\') . '/' . $name;
    @mkdir($dir, 0755, true);
    $remap = ($adminSlug !== 'entrada') ? [$adminSlug => 'entrada'] : [];
    $res = m4_copy_tree_mapped($appRoot, $dir, $remap, $sensitive, $skipDirs, $skipPrefixes);
    return ['name' => $name, 'dir' => $dir] + $res;
}

/**
 * Restaura un backup sobre la instalación (remapea "entrada" al slug actual).
 */
function m4_restore_backup(string $backupDir, string $appRoot, string $adminSlug, string $backupBase, array $sensitive, array $skipDirs, array $skipPrefixes): array {
    $pre = m4_create_backup($appRoot, $backupBase, $adminSlug, $sensitive, $skipDirs, $skipPrefixes);
    $remap = ($adminSlug !== 'entrada') ? ['entrada' => $adminSlug] : [];
    $res = m4_copy_tree_mapped($backupDir, $appRoot, $remap, $sensitive, $skipDirs, $skipPrefixes);
    return ['pre' => $pre] + $res;
}
