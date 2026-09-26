<?php
// Cerrar sesion admin preservando tema e idioma
session_start();

// 1. Capturar theme y lang antes de destruir
$theme = $_SESSION['theme'] ?? null;
$lang  = $_SESSION['lang']   ?? null;

// 2. Limpiar cookie de sesion
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// 3. Destruir la sesion actual (borra admin_logged_in)
$_SESSION = array();
session_destroy();

// 4. Iniciar sesion nueva y restaurar theme/lang
session_start();
if ($theme) $_SESSION['theme'] = $theme;
if ($lang)  $_SESSION['lang']  = $lang;

header('Location: ../index.php');
exit;
?>