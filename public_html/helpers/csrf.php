<?php
// Protección CSRF compartida: token por sesión, comparación en tiempo constante.
// El llamador debe haber iniciado la sesión antes de incluir este helper.

// Devuelve (y crea si hace falta) el token CSRF de la sesión actual.
function csrfToken() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return '';
    }
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Campo oculto listo para insertar en un formulario.
function csrfField() {
    $token = csrfToken();
    if ($token === '') return '';
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
}

// Valida el token recibido (por defecto POST). True si es válido.
function csrfValidate($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }
    $expected = $_SESSION['csrf_token'] ?? '';
    return is_string($token) && $token !== '' && is_string($expected) && $expected !== ''
        && hash_equals($expected, $token);
}
