<?php
// Helpers de autenticación del panel de administración.
// Centraliza la comprobación de sesión que antes se repetía en cada página.
include_once __DIR__ . '/session.php';

// ¿Hay una sesión de administrador activa?
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Exige sesión de admin; si no la hay redirige al login y detiene la ejecución.
function requireAdmin($loginUrl = 'login.php') {
    if (!isAdminLoggedIn()) {
        header('Location: ' . $loginUrl);
        exit;
    }
}
