<?php
// Funciones CRUD para el CMS

include_once __DIR__ . '/connect.php';
if (!defined('DB_INITIALIZED')) define('DB_INITIALIZED', true);

// Crear las tablas si no existen
function createTable() {
    global $db;

    $sql_posts = "CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'published',
        slug TEXT,
        category TEXT,
        tags TEXT,
        pinned_order INTEGER NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";

    $sql_settings = "CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        key_name TEXT UNIQUE NOT NULL,
        value TEXT NOT NULL
    )";

    $sql_categories = "CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE NOT NULL
    )";

    $sql_widgets = "CREATE TABLE IF NOT EXISTS widgets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        type TEXT NOT NULL DEFAULT 'links',
        content TEXT,
        url TEXT,
        zone TEXT NOT NULL DEFAULT 'footer',
        position INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT 'published',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";

    $db->exec($sql_posts);
    $db->exec($sql_settings);
    $db->exec($sql_categories);
    $db->exec($sql_widgets);

    // Migrar tabla posts existente: añadir campos nuevos si no existen
    $cols = $db->query("PRAGMA table_info(posts)")->fetchAll(PDO::FETCH_ASSOC);
    $col_names = array_column($cols, 'name');

    if (!in_array('status', $col_names)) {
        $db->exec("ALTER TABLE posts ADD COLUMN status TEXT NOT NULL DEFAULT 'published'");
    }
    if (!in_array('slug', $col_names)) {
        $db->exec("ALTER TABLE posts ADD COLUMN slug TEXT");
    }
    if (!in_array('category', $col_names)) {
        $db->exec("ALTER TABLE posts ADD COLUMN category TEXT");
    }
    if (!in_array('tags', $col_names)) {
        $db->exec("ALTER TABLE posts ADD COLUMN tags TEXT");
    }
    if (!in_array('pinned_order', $col_names)) {
        $db->exec("ALTER TABLE posts ADD COLUMN pinned_order INTEGER NOT NULL DEFAULT 0");
    }

    // Insertar valores por defecto si no existen
    $defaults = [
        ['site_title', 'M4 CMS'],
        ['site_tagline', 'Blog personal con PHP y SQLite']
    ];

    foreach ($defaults as [$key, $val]) {
        try {
            $stmt = $db->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?)");
            $stmt->execute([$key, $val]);
        } catch (Exception $e) {
            // ya existe, silenciar
        }
    }
}

// Obtener el valor de un setting
function getSetting($key) {
    global $db;
    $stmt = $db->prepare("SELECT value FROM settings WHERE key_name = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['value'] : null;
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

// ===== POSTS =====

// Obtener todas las publicaciones (admin)
function getAllPosts() {
    global $db;
    $stmt = $db->query("SELECT * FROM posts ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener todas las publicaciones paginadas (admin)
function getAllPostsPaginated($limit, $offset) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM posts ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Contar todas las publicaciones (admin)
function countAllPosts() {
    global $db;
    return (int)$db->query("SELECT COUNT(*) FROM posts")->fetchColumn();
}

// Obtener solo publicaciones publicadas y ya visibles (frontend)
function getPublishedPosts() {
    global $db;
    $stmt = $db->query("SELECT * FROM posts WHERE status = 'published' AND created_at <= datetime('now','localtime') ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener publicaciones publicadas paginadas (frontend)
function getPublishedPostsPaginated($limit, $offset) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM posts WHERE status = 'published' AND created_at <= datetime('now','localtime') ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Contar publicaciones publicadas
function countPublishedPosts() {
    global $db;
    return (int)$db->query("SELECT COUNT(*) FROM posts WHERE status = 'published' AND created_at <= datetime('now','localtime')")->fetchColumn();
}

// Obtener publicaciones publicadas filtradas por categoria
function getPublishedPostsByCategory($category) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM posts WHERE status = 'published' AND created_at <= datetime('now','localtime') AND category = ? ORDER BY created_at DESC");
    $stmt->execute([$category]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener publicaciones publicadas filtradas por categoria paginadas
function getPublishedPostsByCategoryPaginated($category, $limit, $offset) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM posts WHERE status = 'published' AND created_at <= datetime('now','localtime') AND category = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute([$category, $limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Contar publicaciones publicadas por categoria
function countPublishedPostsByCategory($category) {
    global $db;
    $stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE status = 'published' AND created_at <= datetime('now','localtime') AND category = ?");
    $stmt->execute([$category]);
    return (int)$stmt->fetchColumn();
}

// Obtener etiquetas usadas en posts (lista de nombres unicos, ordenada)
function getUsedTags() {
    global $db;
    $stmt = $db->query("SELECT tags FROM posts WHERE tags IS NOT NULL AND tags != ''");
    $tags = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $row) {
        foreach (explode(',', $row) as $t) {
            $t = trim($t);
            if ($t === '') continue;
            $tags[strtolower($t)] = $t;
        }
    }
    natcasesort($tags);
    return array_values($tags);
}

// Obtener publicaciones publicadas con una etiqueta (coincidencia exacta sobre la lista de etiquetas)
function getPublishedPostsByTagPaginated($tag, $limit, $offset) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM posts WHERE status = 'published' AND created_at <= datetime('now','localtime') AND (',' || REPLACE(tags, ', ', ',') || ',') LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->execute(['%,' . $tag . ',%', $limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Contar publicaciones publicadas con una etiqueta
function countPublishedPostsByTag($tag) {
    global $db;
    $stmt = $db->prepare("SELECT COUNT(*) FROM posts WHERE status = 'published' AND created_at <= datetime('now','localtime') AND (',' || REPLACE(tags, ', ', ',') || ',') LIKE ?");
    $stmt->execute(['%,' . $tag . ',%']);
    return (int)$stmt->fetchColumn();
}

// Obtener una publicacion por ID
function getPostById($id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM posts WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtener una publicacion por slug
function getPostBySlug($slug) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM posts WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Buscar publicaciones publicadas y ya visibles (titulo, contenido o etiquetas)
function searchPosts($query) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM posts WHERE status = 'published' AND created_at <= datetime('now','localtime') AND (title LIKE ? OR content LIKE ? OR tags LIKE ?) ORDER BY created_at DESC");
    $like = '%' . $query . '%';
    $stmt->execute([$like, $like, $like]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ===== POSTS ANCLADOS (fijados arriba de la portada) =====

// Maximo de publicaciones ancladas
function maxPinnedPosts() {
    return 3;
}

// Publicaciones ancladas visibles (publicadas y ya en fecha), en el orden elegido
function getPinnedPosts() {
    global $db;
    $stmt = $db->query("SELECT * FROM posts WHERE pinned_order > 0 AND status = 'published' AND created_at <= datetime('now','localtime') ORDER BY pinned_order ASC, created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Numero de publicaciones ancladas (cualquier estado)
function countPinnedPosts() {
    global $db;
    return (int)$db->query("SELECT COUNT(*) FROM posts WHERE pinned_order > 0")->fetchColumn();
}

// ¿Esta anclada esta publicacion?
function isPostPinned($id) {
    global $db;
    $stmt = $db->prepare("SELECT pinned_order FROM posts WHERE id = ?");
    $stmt->execute([(int)$id]);
    $order = $stmt->fetchColumn();
    return $order !== false && (int)$order > 0;
}

// Renumerar las publicaciones ancladas a 1..N respetando su orden actual
function renumberPinnedPosts() {
    global $db;
    $rows = $db->query("SELECT id FROM posts WHERE pinned_order > 0 ORDER BY pinned_order ASC, id ASC")->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $db->prepare("UPDATE posts SET pinned_order = ? WHERE id = ?");
    $pos = 1;
    foreach ($rows as $rowId) {
        $stmt->execute([$pos++, (int)$rowId]);
    }
}

// Anclar una publicacion. false si ya hay el maximo; true si quedo anclada (o ya lo estaba)
function pinPost($id) {
    global $db;
    $id = (int)$id;
    if ($id <= 0 || !getPostById($id)) return false;
    if (isPostPinned($id)) return true;
    if (countPinnedPosts() >= maxPinnedPosts()) return false;

    $stmt = $db->query("SELECT COALESCE(MAX(pinned_order), 0) FROM posts WHERE pinned_order > 0");
    $next = (int)$stmt->fetchColumn() + 1;

    $upd = $db->prepare("UPDATE posts SET pinned_order = ? WHERE id = ?");
    return $upd->execute([$next, $id]);
}

// Desanclar una publicacion y compactar el orden restante
function unpinPost($id) {
    global $db;
    $id = (int)$id;
    if ($id <= 0) return false;
    $stmt = $db->prepare("UPDATE posts SET pinned_order = 0 WHERE id = ?");
    $ok = $stmt->execute([$id]);
    renumberPinnedPosts();
    return $ok;
}

// Mover una publicacion anclada arriba (-1) o abajo (+1) intercambiando con su vecina
function movePinnedPost($id, $delta) {
    global $db;
    $id = (int)$id;
    $delta = (int)$delta;
    if ($id <= 0 || !in_array($delta, [-1, 1], true)) return false;

    $curStmt = $db->prepare("SELECT pinned_order FROM posts WHERE id = ?");
    $curStmt->execute([$id]);
    $current = (int)$curStmt->fetchColumn();
    if ($current <= 0) return false;

    $target = $current + $delta;
    if ($target < 1) return false;

    $swapStmt = $db->prepare("SELECT id FROM posts WHERE pinned_order = ? LIMIT 1");
    $swapStmt->execute([$target]);
    $swapId = $swapStmt->fetchColumn();
    if ($swapId === false) return false; // ya esta en el extremo

    try {
        $db->beginTransaction();
        $upd = $db->prepare("UPDATE posts SET pinned_order = ? WHERE id = ?");
        $upd->execute([$target, $id]);
        $upd->execute([$current, (int)$swapId]);
        $db->commit();
        return true;
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        return false;
    }
}

// Generar slug unico a partir de un titulo
function generateSlug($title, $excludeId = 0) {
    $slug = strtolower(trim($title));
    // Quitar tildes y caracteres especiales latinos (titulo -> titulo), estilo WordPress
    $slug = strtr($slug, [
        'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','å'=>'a','Á'=>'a','À'=>'a','Ä'=>'a','Â'=>'a','Ã'=>'a','Å'=>'a',
        'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e','É'=>'e','È'=>'e','Ë'=>'e','Ê'=>'e',
        'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','Í'=>'i','Ì'=>'i','Ï'=>'i','Î'=>'i',
        'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o','Ó'=>'o','Ò'=>'o','Ö'=>'o','Ô'=>'o','Õ'=>'o',
        'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','Ú'=>'u','Ù'=>'u','Ü'=>'u','Û'=>'u',
        'ñ'=>'n','Ñ'=>'n','ç'=>'c','Ç'=>'c','ý'=>'y','ÿ'=>'y'
    ]);
    $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    if (empty($slug)) $slug = 'post';

    // Slugs reservados: rutas propias del CMS que se "comerian" la URL del post
    $reserved = ['post', 'page', 'feed', adminSlug()];
    foreach (glob(dirname(__DIR__) . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
        $reserved[] = strtolower(basename($dir));
    }
    $reserved = array_unique($reserved);
    if (in_array($slug, $reserved, true)) $slug .= '-2';

    // Asegurar unicidad
    global $db;
    $base = $slug;
    $i = 1;
    while (true) {
        if ($excludeId > 0) {
            $stmt = $db->prepare("SELECT id FROM posts WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $excludeId]);
        } else {
            $stmt = $db->prepare("SELECT id FROM posts WHERE slug = ?");
            $stmt->execute([$slug]);
        }
        if (!$stmt->fetch()) break;
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

// URL publica canonica de un post: /mi-slug (estilo WordPress "Nombre de la entrada")
// Acepta tambien un ID numerico: los posts sin slug resuelven por ID en post.php
function postUrl($slug) {
    $slug = trim((string)$slug);
    if ($slug === '') return '/';
    return '/' . rawurlencode($slug);
}

// Hora local del servidor (mismo "frame" que los filtros de fecha SQL)
function nowLocalString() {
    global $db;
    return $db->query("SELECT datetime('now','localtime')")->fetchColumn();
}

// Normalizar fecha desde un input datetime-local ('YYYY-MM-DDTHH:MM'); null si vacia o invalida
function normalizePublishDate($value) {
    $value = trim(str_replace('T', ' ', (string)$value));
    if ($value === '') return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) $value .= ':00';
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) return null;
    list($date, $time) = explode(' ', $value);
    list($y, $m, $d) = array_map('intval', explode('-', $date));
    list($h, $i, $s) = array_map('intval', explode(':', $time));
    if (!checkdate($m, $d, $y) || $h > 23 || $i > 59 || $s > 59) return null;
    return $value;
}

// Normalizar etiquetas: separadas por comas, recortadas y sin duplicados (sin distinguir mayusculas)
function normalizeTags($tags) {
    $out = [];
    foreach (preg_split('/,+/', (string)$tags) as $part) {
        $part = trim($part);
        if ($part === '') continue;
        $key = strtolower($part);
        if (!isset($out[$key])) $out[$key] = $part;
    }
    return implode(', ', array_values($out));
}

// Crear una nueva publicacion
// $createdAt = null → fecha y hora local actual
function createPost($title, $content, $status = 'published', $slug = '', $category = '', $tags = '', $createdAt = null) {
    global $db;

    if (empty($title) || empty($content)) {
        return false;
    }

    if (empty($slug)) {
        $slug = generateSlug($title);
    }

    if ($createdAt === null) {
        $stmt = $db->prepare("INSERT INTO posts (title, content, status, slug, category, tags, created_at) VALUES (?, ?, ?, ?, ?, ?, datetime('now','localtime'))");
        return $stmt->execute([$title, $content, $status, $slug, $category, normalizeTags($tags)]);
    }

    $stmt = $db->prepare("INSERT INTO posts (title, content, status, slug, category, tags, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$title, $content, $status, $slug, $category, normalizeTags($tags), $createdAt]);
}

// Actualizar una publicacion
// $tags === null: no toca las etiquetas; $createdAt === null: no toca la fecha de publicacion
function updatePost($id, $title, $content, $status = 'published', $slug = '', $category = '', $tags = null, $createdAt = null) {
    global $db;

    $id = (int)$id;

    if ($id <= 0 || empty($title) || empty($content)) {
        return false;
    }

    if (empty($slug)) {
        $slug = generateSlug($title, $id);
    }

    $sql = "UPDATE posts SET title = ?, content = ?, status = ?, slug = ?, category = ?";
    $params = [$title, $content, $status, $slug, $category];

    if ($tags !== null) {
        $sql .= ", tags = ?";
        $params[] = normalizeTags($tags);
    }
    if ($createdAt !== null) {
        $sql .= ", created_at = ?";
        $params[] = $createdAt;
    }

    $sql .= ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $params[] = $id;

    $stmt = $db->prepare($sql);
    return $stmt->execute($params);
}

// Eliminar una publicacion
function deletePost($id) {
    global $db;

    $id = (int)$id;

    if ($id <= 0) {
        return false;
    }

    $stmt = $db->prepare("DELETE FROM posts WHERE id = ?");
    return $stmt->execute([$id]);
}

// ===== CATEGORIAS =====

// Obtener todas las categorias
function getCategories() {
    global $db;
    $stmt = $db->query("SELECT name FROM categories ORDER BY name ASC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Anadir una categoria
function addCategory($name) {
    global $db;
    $name = trim($name);
    if (empty($name)) return false;
    try {
        $stmt = $db->prepare("INSERT INTO categories (name) VALUES (?)");
        return $stmt->execute([$name]);
    } catch (Exception $e) {
        return false; // ya existe
    }
}

// Obtener categorias usadas en posts
function getUsedCategories() {
    global $db;
    $stmt = $db->query("SELECT DISTINCT category FROM posts WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ===== BLOQUES (WIDGETS: enlaces, texto+enlace, embeds) =====

// ¿Está activo el módulo de bloques? Desactivado por defecto (widgets_enabled != '1')
function widgetsModuleEnabled() {
    try {
        return @getSetting('widgets_enabled') === '1';
    } catch (\Throwable $e) {
        return false;
    }
}

// Normalizar estado de bloque (published | draft)
function normalizeWidgetStatus($status) {
    $status = strtolower(trim((string)$status));
    return in_array($status, ['published', 'draft'], true) ? $status : 'published';
}

// Obtener todos los bloques (admin)
function getAllWidgets() {
    global $db;
    $stmt = $db->query("SELECT * FROM widgets ORDER BY position ASC, id ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Obtener bloques publicados de una zona (frontend)
function getPublishedWidgets($zone = 'footer') {
    global $db;
    $stmt = $db->prepare("SELECT * FROM widgets WHERE status = 'published' AND zone = ? ORDER BY position ASC, id ASC");
    $stmt->execute([$zone]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Contar bloques (admin)
function countWidgets() {
    global $db;
    return (int)$db->query("SELECT COUNT(*) FROM widgets")->fetchColumn();
}

// Obtener un bloque por ID
function getWidgetById($id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM widgets WHERE id = ?");
    $stmt->execute([(int)$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Crear un bloque (contenido libre: texto, HTML básico o URL embebida)
function createWidget($title, $content, $position = 0, $status = 'published') {
    global $db;
    $stmt = $db->prepare("INSERT INTO widgets (title, content, zone, position, status) VALUES (?, ?, 'footer', ?, ?)");
    return $stmt->execute([
        trim((string)$title),
        (string)$content,
        (int)$position,
        normalizeWidgetStatus($status)
    ]);
}

// Actualizar un bloque
function updateWidget($id, $title, $content, $position = 0, $status = 'published') {
    global $db;
    $id = (int)$id;
    if ($id <= 0) return false;
    $stmt = $db->prepare("UPDATE widgets SET title = ?, content = ?, position = ?, status = ? WHERE id = ?");
    return $stmt->execute([
        trim((string)$title),
        (string)$content,
        (int)$position,
        normalizeWidgetStatus($status),
        $id
    ]);
}

// Eliminar un bloque
function deleteWidget($id) {
    global $db;
    $id = (int)$id;
    if ($id <= 0) return false;
    $stmt = $db->prepare("DELETE FROM widgets WHERE id = ?");
    return $stmt->execute([$id]);
}

// ===== LOGS =====

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

// ===== RUTA DEL PANEL ADMIN (rename físico de carpeta, sin mod_rewrite) =====

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

syncAdminPath();
?>
