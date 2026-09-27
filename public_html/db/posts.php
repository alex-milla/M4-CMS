<?php
// Publicaciones: CRUD, consultas, anclados y categorias.
// Parte del agregador db/functions.php.

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
