<?php
// Funciones del CMS (agregador). La lógica vive en los módulos de db/:
//   settings.php · ajustes y URL base
//   posts.php    · publicaciones, anclados y categorías
//   widgets.php  · bloques
//   logs.php     · registro de actividad y anti-fuerza bruta
//   auth.php     · credenciales y ruta del panel admin
//
// Se mantiene este archivo como punto de entrada para no cambiar los llamadores.

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

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/posts.php';
require_once __DIR__ . '/widgets.php';
require_once __DIR__ . '/logs.php';
require_once __DIR__ . '/auth.php';

syncAdminPath();
