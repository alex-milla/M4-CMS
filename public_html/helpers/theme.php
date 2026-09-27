<?php
// Helpers de tema para el CMS
// Sistema de diseño FinSec: dos modos, claro ('finsec') y oscuro ('finsec-dark').

include_once __DIR__ . '/../db/functions.php';

function finsecThemes() {
    return ['finsec', 'finsec-dark'];
}

// Los temas retro legacy ya no existen: se normalizan al modo claro.
function normalizeTheme($theme) {
    return in_array($theme, finsecThemes(), true) ? $theme : 'finsec';
}

function initTheme() {
    // 1. POST/GET — override más reciente. Solo un admin persiste el tema global;
    //    un visitante anónimo solo cambia su preferencia de sesión.
    $isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];
    foreach ([$_POST, $_GET] as $input) {
        if (isset($input['site_theme']) || isset($input['theme_setting'])) {
            $value = $input['site_theme'] ?? $input['theme_setting'];
            if (in_array($value, finsecThemes(), true)) {
                if ($isAdmin) {
                    saveSetting('site_theme', $value);
                }
                $_SESSION['theme'] = $value;
                return $value;
            }
        }
    }

    // 2. Session — preferida por rendimiento (normalizar valores legacy)
    if (isset($_SESSION['theme'])) {
        return normalizeTheme($_SESSION['theme']);
    }

    // 3. Base de datos como fallback
    return getSiteTheme();
}

// Resuelve el tema para el frontend público. Prioridad:
//   1) preferencia en sesión; 2) cookie del visitante (no logado);
//   3) tema global del sitio (solo admin); 4) claro por defecto.
function resolveTheme($isLogged = false) {
    if (isset($_SESSION['theme'])) {
        return normalizeTheme($_SESSION['theme']);
    }
    if (!$isLogged && isset($_COOKIE['m4_theme'])) {
        return normalizeTheme($_COOKIE['m4_theme']);
    }
    if ($isLogged) {
        return getSiteTheme();
    }
    return 'finsec';
}

function themeClass($theme) {
    return "theme-" . htmlspecialchars($theme);
}

function themeOptions() {
    return [
        'finsec'      => 'FinSec Claro',
        'finsec-dark' => 'FinSec Oscuro',
    ];
}

function themeSelected($options, $current = null) {
    $html = '';
    foreach ($options as $key => $label) {
        $selected = ($current === $key) ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($key) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>' . "\n";
    }
    return $html;
}
