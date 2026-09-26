<?php
/**
 * M4 CMS - Sistema de actualizaciones
 *
 * Descarga la última release publicada en GitHub (repo público) y actualiza
 * los archivos de la aplicación mediante un manifiesto SHA-256 (origen/destino):
 *  - copia solo ficheros nuevos o modificados,
 *  - elimina ficheros gestionados que ya no existen en la release,
 *  - elimina utilidades obsoletas/peligrosas (reset-admin, install, cleanup...),
 *  - crea un backup del código antes de cada operación y permite restaurarlo.
 *
 * Nunca sobrescribe config.php, .env, admin_config.php ni la base de datos.
 */
ob_start();
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

@set_time_limit(300);
@ignore_user_abort(true);

include_once '../db/functions.php';
include_once '../helpers/theme.php';
include_once '../helpers/i18n.php';
include_once '../helpers/icons.php';
include_once '../helpers/updater.php';

createTable();

// Tema (FinSec claro/oscuro) — initTheme persiste en DB + sesión
$theme = initTheme();
$themeToggle = ($theme === 'finsec-dark') ? 'finsec' : 'finsec-dark';
$lang  = $_SESSION['lang'] ?? 'es';
$class = 'theme-' . htmlspecialchars($theme);

// ---------------------------------------------------------------------------
// Configuración del repositorio y rutas
// ---------------------------------------------------------------------------
$repoOwner = 'alex-milla';
$repoName  = 'M4-CMS';

// Token opcional (el repo es público). Se lee de la variable de entorno o de
// un archivo fuera del web root si el host lo permite.
$githubToken = getenv('GITHUB_TOKEN') ?: '';
$tokenFile   = dirname(__DIR__) . '/.github_token';
if (!$githubToken && @is_file($tokenFile)) {
    $githubToken = trim((string)@file_get_contents($tokenFile));
}

$appRoot     = dirname(__DIR__);            // public_html
$adminSlug   = basename(__DIR__);           // carpeta admin actual (renombrable)
$versionFile = $appRoot . '/VERSION';
$backupBase  = $appRoot . '/db/backups';

// Archivos que NUNCA se sobrescriben ni se incluyen en los backups
$sensitive = [
    'config.php',
    '.env',
    'admin_config.php',
    'cleanup.php',
    'install.php',
    'setup.php',
    '.github_token',
    'db/cms.db',
    'README.md',
    '.gitignore',
];
$skipDirs     = ['.git', '.github', 'node_modules', '.release'];
$skipPrefixes = ['db/backups/'];

// Utilidades que se eliminan siempre en cada actualización.
$forceDelete = ['entrada/reset-admin.php', 'install.php', 'cleanup.php', 'fix-bom.php'];
if (is_file($appRoot . '/.setup_completed')) {
    $forceDelete[] = 'setup.php';
}

// ---------------------------------------------------------------------------
// CSRF (autocontenido, solo para esta página)
// ---------------------------------------------------------------------------
if (empty($_SESSION['update_csrf'])) {
    $_SESSION['update_csrf'] = bin2hex(random_bytes(32));
}
function csrfField(): void {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['update_csrf']) . '">';
}
function validateCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $sent = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['update_csrf'] ?? '', (string)$sent)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

// ---------------------------------------------------------------------------
// Utilidades de red y ZIP
// ---------------------------------------------------------------------------
function githubApiGet(string $url, string $token = ''): array {
    $result = ['success' => false, 'data' => null, 'error' => ''];
    $headers = ['User-Agent: M4-CMS-Updater', 'Accept: application/vnd.github+json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($data !== false && $httpCode >= 200 && $httpCode < 300) {
            $result['success'] = true;
            $result['data'] = json_decode($data, true);
            return $result;
        }
        if ($httpCode == 403) {
            $result['error'] = 'GitHub API rate limit exceeded. Define GITHUB_TOKEN para aumentar el límite.';
        } elseif ($httpCode == 404) {
            $result['error'] = 'No releases found. Publica una release en GitHub primero.';
        } else {
            $result['error'] = "cURL error: {$curlError} (HTTP {$httpCode})";
        }
        return $result;
    }

    $opts = ['http' => ['method' => 'GET', 'header' => $headers, 'timeout' => 15]];
    $data = @file_get_contents($url, false, stream_context_create($opts));
    if ($data !== false) {
        $result['success'] = true;
        $result['data'] = json_decode($data, true);
        return $result;
    }
    $result['error'] = 'No se pudo consultar la API de GitHub (allow_url_fopen puede estar deshabilitado).';
    return $result;
}

function downloadFile(string $url, string $dest, string $token, ?string &$err): bool {
    $err = '';
    if (function_exists('curl_init')) {
        $fp = @fopen($dest, 'wb');
        if (!$fp) { $err = 'No se pudo escribir el archivo temporal.'; return false; }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'M4-CMS-Updater');
        if ($token) curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
        curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ce = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        if ($http >= 200 && $http < 300) return true;
        $err = "GitHub download failed: HTTP {$http}. {$ce}";
        return false;
    }
    $headers = "User-Agent: M4-CMS-Updater\r\n";
    if ($token) $headers .= "Authorization: Bearer {$token}\r\n";
    $ctx = stream_context_create(['http' => ['header' => $headers, 'timeout' => 120, 'follow_location' => 1, 'max_redirects' => 5]]);
    $data = @file_get_contents($url, false, $ctx);
    if ($data !== false && strlen($data) > 0) {
        @file_put_contents($dest, $data);
        return true;
    }
    $err = 'No se pudo descargar el ZIP (allow_url_fopen puede estar deshabilitado).';
    return false;
}

function extractZip(string $zipPath, string $dest, ?string &$err): bool {
    $err = '';
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($dest);
            $zip->close();
            return true;
        }
        $err = 'ZipArchive no pudo abrir el archivo.';
        return false;
    }
    if (class_exists('PharData')) {
        try {
            $phar = new PharData($zipPath);
            $phar->extractTo($dest, null, true);
            return true;
        } catch (\Throwable $e) {
            $err = 'PharData: ' . $e->getMessage();
        }
    }
    if (function_exists('exec')) {
        $out = [];
        $code = 1;
        @exec('unzip -o ' . escapeshellarg($zipPath) . ' -d ' . escapeshellarg($dest) . ' 2>&1', $out, $code);
        if ($code === 0) return true;
        $err = 'unzip: ' . implode(' ', $out);
    }
    if ($err === '') {
        $err = 'No hay extractor ZIP disponible. Habilita la extensión zip o instala unzip.';
    }
    return false;
}

// ---------------------------------------------------------------------------
// Operación de actualización
// ---------------------------------------------------------------------------
function doUpdate(string $zipUrl, string $token, string $appRoot, string $adminSlug, string $versionFile, string $backupBase, string $remoteVersion, array $sensitive, array $skipDirs, array $skipPrefixes, array $forceDelete): array {
    $backup = m4_create_backup($appRoot, $backupBase, $adminSlug, $sensitive, $skipDirs, $skipPrefixes);

    $tempZip = sys_get_temp_dir() . '/m4_update_' . time() . '.zip';
    $extractDir = sys_get_temp_dir() . '/m4_extract_' . time();

    $dlErr = '';
    if (!downloadFile($zipUrl, $tempZip, $token, $dlErr)) {
        @unlink($tempZip);
        return ['success' => false, 'error' => $dlErr, 'backup' => $backup];
    }
    if (!is_file($tempZip) || filesize($tempZip) < 1024) {
        @unlink($tempZip);
        return ['success' => false, 'error' => 'El ZIP descargado está vacío o corrupto.', 'backup' => $backup];
    }

    $exErr = '';
    if (!extractZip($tempZip, $extractDir, $exErr)) {
        @unlink($tempZip);
        m4_rrmdir($extractDir);
        return ['success' => false, 'error' => 'No se pudo extraer el ZIP: ' . $exErr, 'backup' => $backup];
    }

    // Localizar la raíz del proyecto dentro del ZIP (GitHub: repo-sha/)
    $sourceDir = $extractDir;
    if (!is_dir($sourceDir . '/public_html')) {
        foreach ((array)scandir($extractDir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (is_dir($extractDir . '/' . $entry) && is_dir($extractDir . '/' . $entry . '/public_html')) {
                $sourceDir = $extractDir . '/' . $entry;
                break;
            }
        }
    }
    $srcWeb = $sourceDir . '/public_html';
    if (!is_dir($srcWeb)) {
        @unlink($tempZip);
        m4_rrmdir($extractDir);
        return ['success' => false, 'error' => 'El ZIP no contiene public_html/. Se aborta la actualización.', 'backup' => $backup];
    }

    $res = m4_sync_from_source($srcWeb, $appRoot, $adminSlug, $remoteVersion, $sensitive, $skipDirs, $skipPrefixes, $forceDelete);

    @unlink($tempZip);
    m4_rrmdir($extractDir);

    if ($res['copied'] === 0 && $res['deleted'] === 0) {
        return ['success' => false, 'error' => 'No había nada que actualizar (¿manifiesto o release vacíos?).', 'backup' => $backup, 'copied' => 0, 'deleted' => 0];
    }
    @file_put_contents($versionFile, $remoteVersion . "\n");

    return ['success' => true, 'copied' => $res['copied'], 'skipped' => $res['skipped'], 'deleted' => $res['deleted'], 'errors' => $res['errors'], 'backup' => $backup];
}

// ---------------------------------------------------------------------------
// Versión instalada
// ---------------------------------------------------------------------------
$currentVersion = '0.0.0';
if (is_file($versionFile)) {
    $v = trim((string)@file_get_contents($versionFile));
    if ($v !== '') $currentVersion = $v;
}

// ---------------------------------------------------------------------------
// Última release en GitHub
// ---------------------------------------------------------------------------
$apiResult = githubApiGet("https://api.github.com/repos/{$repoOwner}/{$repoName}/releases/latest", $githubToken);
$release = $apiResult['success'] ? $apiResult['data'] : null;
$remoteVersion = '';
$zipUrl = '';
$releaseNotes = '';
$publishedAt = '';
$apiError = $apiResult['error'] ?? '';

if (is_array($release)) {
    $remoteVersion = ltrim((string)($release['tag_name'] ?? ''), 'v');
    $zipUrl = $release['zipball_url'] ?? '';
    $releaseNotes = $release['body'] ?? '';
    $publishedAt = $release['published_at'] ?? '';
}

// Fallback: si no hay release, usar el último tag
if (!$release && ($apiError === '' || strpos($apiError, '404') !== false)) {
    $tagsResult = githubApiGet("https://api.github.com/repos/{$repoOwner}/{$repoName}/tags?per_page=1", $githubToken);
    if ($tagsResult['success'] && !empty($tagsResult['data'][0])) {
        $tag = $tagsResult['data'][0];
        $tagName = $tag['name'] ?? '';
        $remoteVersion = ltrim((string)$tagName, 'v');
        $zipUrl = "https://github.com/{$repoOwner}/{$repoName}/archive/refs/tags/{$tagName}.zip";
        $release = ['tag_name' => $tagName];
    }
}

// ---------------------------------------------------------------------------
// Flash + acciones POST
// ---------------------------------------------------------------------------
$flash = $_SESSION['update_flash'] ?? null;
unset($_SESSION['update_flash']);
$error = '';
$info  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? 'install';
    try {
        if ($action === 'restore') {
            $name = (string)($_POST['backup'] ?? '');
            if (!preg_match('/^backup_\d{8}_\d{6}$/', $name)) {
                $error = 'Nombre de copia no válido.';
            } else {
                $dir = $backupBase . '/' . $name;
                $realBase = realpath($backupBase);
                $realDir  = realpath($dir);
                if (!$realBase || !$realDir || strpos($realDir, $realBase) !== 0 || !is_dir($realDir)) {
                    $error = 'La copia de seguridad no existe.';
                } else {
                    $res = m4_restore_backup($realDir, $appRoot, $adminSlug, $backupBase, $sensitive, $skipDirs, $skipPrefixes);
                    if (function_exists('opcache_reset')) @opcache_reset();
                    logAdminEvent('system_restored', $name);
                    $_SESSION['update_flash'] = ['success', "Copia {$name} restaurada ({$res['copied']} archivos). Estado previo guardado como {$res['pre']['name']}."];
                    header('Location: update.php');
                    exit;
                }
            }
        } else {
            $force = (($_POST['force'] ?? '') === '1');
            if (!$release) {
                $error = $apiError !== '' ? $apiError : t('update_err_no_release')[0];
            } elseif (!$force && version_compare($currentVersion, $remoteVersion, '>=')) {
                $info = t('update_already_latest')[0] . ' (v' . $currentVersion . ')';
            } elseif (empty($zipUrl)) {
                $error = 'La release no contiene una URL de descarga.';
            } else {
                $res = doUpdate($zipUrl, $githubToken, $appRoot, $adminSlug, $versionFile, $backupBase, $remoteVersion, $sensitive, $skipDirs, $skipPrefixes, $forceDelete);
                if ($res['success']) {
                    if (function_exists('opcache_reset')) @opcache_reset();
                    logAdminEvent('system_updated', 'v' . $remoteVersion);
                    $_SESSION['update_flash'] = ['success', "Actualizado a v{$remoteVersion}. Copiados: {$res['copied']}, sin cambios: {$res['skipped']}, eliminados: {$res['deleted']}. Copia: {$res['backup']['name']}."];
                    header('Location: update.php');
                    exit;
                }
                $error = $res['error'];
                if (!empty($res['backup']['name'])) {
                    $error .= ' (copia: ' . $res['backup']['name'] . ')';
                }
            }
        }
    } catch (\Throwable $e) {
        $error = 'Error inesperado: ' . $e->getMessage();
    }
}

// ---------------------------------------------------------------------------
// Listado de backups
// ---------------------------------------------------------------------------
$backups = [];
if (is_dir($backupBase)) {
    foreach ((array)glob($backupBase . '/backup_*') as $b) {
        if (is_dir($b) && preg_match('/^backup_\d{8}_\d{6}$/', basename($b))) {
            $backups[] = ['name' => basename($b), 'time' => @filemtime($b) ?: 0];
        }
    }
    usort($backups, fn($a, $b) => $b['time'] <=> $a['time']);
}

// Traducciones
list($page_title, )        = t('update_page_title');
list($nav_update, )        = t('nav_update');
list($nav_dashboard, )     = t('admin_dashboard_title');
list($manage_posts, )      = t('admin_manage_posts');
list($btn_new_post, )      = t('btn_new_post');
list($nav_settings, )      = t('admin_settings');
list($nav_logs, )          = t('nav_logs');
list($nav_logout, )        = t('nav_logout');
list($nav_site, )          = t('nav_go_to_site');
list($sys_section, )       = t('admin_system_section');
list($_lbl_lang, )         = t('lbl_lang');
list($theme_lbl, )         = t('admin_theme_label');
list($hint, )              = t('update_check_hint');
list($lbl_repo, )          = t('update_repo');
list($lbl_installed, )     = t('update_installed_version');
list($lbl_latest, )        = t('update_latest_release');
list($lbl_published, )     = t('update_published');
list($lbl_notes, )         = t('update_release_notes');
list($lbl_unknown, )       = t('update_unknown');
list($btn_install, )       = t('update_btn_install');
list($btn_force, )         = t('update_btn_force');
list($preserve_note, )     = t('update_preserve_note');
list($lbl_backups, )       = t('update_backups');
list($lbl_no_backups, )    = t('update_no_backups');
list($tbl_backup, )        = t('update_tbl_backup');
list($tbl_date, )          = t('update_tbl_date');
list($tbl_actions, )       = t('update_tbl_actions');
list($btn_restore, )       = t('update_btn_restore');
list($confirm_restore, )   = t('update_confirm_restore');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="admin.css">
</head>
<body class="<?php echo $class; ?>">
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="logo">
            <span class="logo-mark"><?php echo finsec_icon('feather', 18); ?></span>
            <span><?php echo htmlspecialchars($page_title); ?></span>
        </div>
        <nav>
            <div class="admin-nav-section"><?php echo htmlspecialchars($nav_dashboard ?? 'Panel'); ?></div>
            <a href="index.php">
                <?php echo finsec_icon('layout', 18); ?>
                <span><?php echo htmlspecialchars($nav_dashboard ?? 'Panel'); ?></span>
            </a>

            <div class="admin-nav-section"><?php echo htmlspecialchars($manage_posts); ?></div>
            <a href="posts.php">
                <?php echo finsec_icon('posts', 18); ?>
                <span><?php echo htmlspecialchars($manage_posts); ?></span>
            </a>
            <a href="../create.php" target="_blank">
                <?php echo finsec_icon('pen', 18); ?>
                <span><?php echo htmlspecialchars($btn_new_post); ?></span>
            </a>

            <div class="admin-nav-section"><?php echo htmlspecialchars($nav_settings); ?></div>
            <a href="settings.php">
                <?php echo finsec_icon('settings', 18); ?>
                <span><?php echo htmlspecialchars($nav_settings); ?></span>
            </a>
            <a href="logs.php">
                <?php echo finsec_icon('history', 18); ?>
                <span><?php echo htmlspecialchars($nav_logs); ?></span>
            </a>

            <div class="admin-nav-section"><?php echo htmlspecialchars($sys_section); ?></div>
            <a href="update.php" class="active">
                <?php echo finsec_icon('download', 18); ?>
                <span><?php echo htmlspecialchars($nav_update); ?></span>
            </a>

            <div class="admin-nav-section">&nbsp;</div>
            <a href="logout.php" class="nav-danger">
                <?php echo finsec_icon('logout', 18); ?>
                <span><?php echo htmlspecialchars($nav_logout); ?></span>
            </a>
        </nav>
    </aside>

    <!-- Overlay para mobile -->
    <div class="admin-sidebar-overlay"></div>

    <!-- Header -->
    <header class="admin-header">
        <div class="admin-header-left">
            <button class="admin-menu-toggle" onclick="toggleSidebar()" aria-label="Menu"><?php echo finsec_icon('menu', 20); ?></button>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
        </div>
        <div class="admin-header-right">
            <a href="../index.php" class="icon-btn" title="<?php echo htmlspecialchars($nav_site); ?>"><?php echo finsec_icon('globe', 18); ?></a>
            <form method="GET" action="" style="display:inline;">
                <select name="lang" onchange="this.form.submit();" class="lang-selector" aria-label="<?php echo htmlspecialchars($_lbl_lang); ?>">
                    <option value="es"<?php echo $lang === 'es' ? ' selected' : ''; ?>>Español</option>
                    <option value="en"<?php echo $lang === 'en' ? ' selected' : ''; ?>>English</option>
                </select>
            </form>
            <form method="POST" action="" style="display:inline;">
                <input type="hidden" name="site_theme" value="<?php echo $themeToggle; ?>">
                <input type="hidden" name="theme_setting" value="<?php echo $themeToggle; ?>">
                <button type="submit" class="icon-btn" aria-label="<?php echo htmlspecialchars($theme_lbl); ?>" title="<?php echo htmlspecialchars($theme_lbl); ?>">
                    <?php echo finsec_icon($theme === 'finsec-dark' ? 'sun' : 'moon', 18); ?>
                </button>
            </form>
        </div>
    </header>

    <!-- Main -->
    <main class="admin-main">
        <div class="admin-container">
            <?php if ($flash): ?>
                <div class="msg-banner <?php echo $flash[0] === 'success' ? 'success' : 'error'; ?>">
                    <?php echo finsec_icon($flash[0] === 'success' ? 'check' : 'alert', 16); ?>
                    <span><?php echo htmlspecialchars($flash[1]); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="msg-banner error">
                    <?php echo finsec_icon('alert', 16); ?>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($info): ?>
                <div class="msg-banner info">
                    <?php echo finsec_icon('check', 16); ?>
                    <span><?php echo htmlspecialchars($info); ?></span>
                </div>
            <?php endif; ?>

            <div class="admin-card">
                <h3><?php echo finsec_icon('download', 16); ?> <?php echo htmlspecialchars($page_title); ?></h3>
                <p class="help-text" style="margin-bottom:12px;"><?php echo htmlspecialchars($hint); ?></p>

                <table class="admin-table" style="margin-bottom:16px;">
                    <tbody>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($lbl_repo); ?></strong></td>
                            <td class="mono"><?php echo htmlspecialchars("{$repoOwner}/{$repoName}"); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($lbl_installed); ?></strong></td>
                            <td class="mono">v<?php echo htmlspecialchars($currentVersion); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($lbl_latest); ?></strong></td>
                            <td class="mono"><?php echo $remoteVersion !== '' ? 'v' . htmlspecialchars($remoteVersion) : '<em>' . htmlspecialchars($lbl_unknown) . '</em>'; ?></td>
                        </tr>
                        <?php if ($publishedAt): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($lbl_published); ?></strong></td>
                            <td class="mono"><?php echo htmlspecialchars($publishedAt); ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($releaseNotes): ?>
                <details class="release-notes" style="margin-bottom:16px;">
                    <summary><?php echo htmlspecialchars($lbl_notes); ?></summary>
                    <pre><?php echo htmlspecialchars($releaseNotes); ?></pre>
                </details>
                <?php endif; ?>

                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <form method="POST">
                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="install">
                        <button type="submit" class="btn-primary"><?php echo finsec_icon('download', 16); ?> <?php echo htmlspecialchars($btn_install); ?></button>
                    </form>
                    <form method="POST">
                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="install">
                        <input type="hidden" name="force" value="1">
                        <button type="submit" class="btn-danger"><?php echo finsec_icon('refresh', 16); ?> <?php echo htmlspecialchars($btn_force); ?></button>
                    </form>
                </div>

                <p class="help-text" style="margin-top:12px;"><?php echo htmlspecialchars($preserve_note); ?></p>
            </div>

            <div class="admin-card">
                <h3><?php echo finsec_icon('history', 16); ?> <?php echo htmlspecialchars($lbl_backups); ?></h3>
                <?php if (empty($backups)): ?>
                    <p class="help-text"><?php echo htmlspecialchars($lbl_no_backups); ?></p>
                <?php else: ?>
                <div class="admin-table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th><?php echo htmlspecialchars($tbl_backup); ?></th>
                                <th><?php echo htmlspecialchars($tbl_date); ?></th>
                                <th><?php echo htmlspecialchars($tbl_actions); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($backups, 0, 15) as $b): ?>
                            <tr>
                                <td class="mono"><?php echo htmlspecialchars($b['name']); ?></td>
                                <td class="mono"><?php echo htmlspecialchars($b['time'] ? date('Y-m-d H:i:s', $b['time']) : ''); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <?php csrfField(); ?>
                                        <input type="hidden" name="action" value="restore">
                                        <input type="hidden" name="backup" value="<?php echo htmlspecialchars($b['name']); ?>">
                                        <button type="submit" class="action-btn" onclick="return confirm('<?php echo htmlspecialchars($confirm_restore, ENT_QUOTES); ?>');"><?php echo finsec_icon('refresh', 14); ?> <?php echo htmlspecialchars($btn_restore); ?></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
    function toggleSidebar() {
        const sidebar = document.querySelector('.admin-sidebar');
        const overlay = document.querySelector('.admin-sidebar-overlay');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    }
    document.querySelector('.admin-sidebar-overlay')?.addEventListener('click', function() {
        document.querySelector('.admin-sidebar')?.classList.remove('open');
        this.classList.remove('active');
    });
    </script>
</body>
</html>
