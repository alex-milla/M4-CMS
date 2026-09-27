<?php
// Bloques (widgets): enlaces, texto+enlace, embeds.
// Parte del agregador db/functions.php.

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

// Mover un bloque arriba (-1) o abajo (+1) intercambiando su `position` con la del vecino
function moveWidget($id, $delta) {
    global $db;
    $id = (int)$id;
    $delta = (int)$delta;
    if ($id <= 0 || !in_array($delta, [-1, 1], true)) return false;

    $cur = $db->prepare("SELECT position FROM widgets WHERE id = ?");
    $cur->execute([$id]);
    $pos = $cur->fetchColumn();
    if ($pos === false) return false;
    $pos = (int)$pos;

    if ($delta < 0) {
        $q = $db->prepare("SELECT id, position FROM widgets WHERE position < ? OR (position = ? AND id < ?) ORDER BY position DESC, id DESC LIMIT 1");
        $q->execute([$pos, $pos, $id]);
    } else {
        $q = $db->prepare("SELECT id, position FROM widgets WHERE position > ? OR (position = ? AND id > ?) ORDER BY position ASC, id ASC LIMIT 1");
        $q->execute([$pos, $pos, $id]);
    }
    $neighbor = $q->fetch(PDO::FETCH_ASSOC);
    if (!$neighbor) return false; // ya está en el extremo

    try {
        $db->beginTransaction();
        $upd = $db->prepare("UPDATE widgets SET position = ? WHERE id = ?");
        $upd->execute([(int)$neighbor['position'], $id]);
        $upd->execute([$pos, (int)$neighbor['id']]);
        $db->commit();
        return true;
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        return false;
    }
}
