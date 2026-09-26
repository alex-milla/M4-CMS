<?php
// Conexicón a la base de datos SQLite
global $db;
if (!defined('DB_PATH')) define('DB_PATH', __DIR__ . '/cms.db');

try {
    if (!file_exists(DB_PATH)) {
        touch(DB_PATH);
    }
    
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Instead of dying, set db to null for graceful handling
    $db = null;
    error_log("Database connection failed: " . $e->getMessage());
}
 ?>