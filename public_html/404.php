<?php
// Página de error reutilizable para 403 (acceso restringido) y 404 (no encontrada).
// Se sirve vía ErrorDocument 403 y ErrorDocument 404.
// Sin dependencias (sin DB, sin sesión, sin includes) para que nunca falle.
$status = isset($_SERVER['REDIRECT_STATUS']) ? (int)$_SERVER['REDIRECT_STATUS'] : 404;
if ($status !== 403) {
    $status = 404;
}
http_response_code($status);

$acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
$lang = (stripos($acceptLang, 'es') !== false) ? 'es' : 'en';

$texts = [
    'es' => [
        403 => ['title' => '403 - Acceso restringido', 'msg' => 'No tienes permiso para acceder a este recurso.'],
        404 => ['title' => '404 - Página no encontrada', 'msg' => 'La página que buscas no existe.'],
    ],
    'en' => [
        403 => ['title' => '403 - Access denied', 'msg' => 'You do not have permission to access this resource.'],
        404 => ['title' => '404 - Page not found', 'msg' => "The page you're looking for doesn't exist."],
    ],
];
$t = $texts[$lang][$status];
$home = ($lang === 'es') ? 'Volver al inicio' : 'Back to home';

$icon = ($status === 403)
    ? '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1 1 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m12 8 0 4"/><path d="M12 16h.01"/>'
    : '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>';

// Rutas relativas a la raíz del CMS (funciona también en subdirectorio)
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$homeUrl = ($base === '' ? '' : $base) . '/index.php';
$css  = ($base === '' ? '' : $base) . '/assets/finsec.css';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $t['title']; ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($css); ?>">
</head>
<body class="theme-finsec">
    <main style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:0 16px;">
        <div style="width:100%;max-width:400px;">
            <div style="text-align:center;margin-bottom:32px;">
                <div style="display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;border-radius:1rem;background:var(--brand-500);color:#fff;margin-bottom:16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $icon; ?></svg>
                </div>
                <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-1);"><?php echo $status; ?></h1>
                <p style="font-size:0.875rem;color:var(--text-2);margin-top:4px;"><?php echo $t['msg']; ?></p>
            </div>
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:0.75rem;box-shadow:0 1px 2px 0 rgb(0 0 0 / 0.05);padding:24px;text-align:center;">
                <a href="<?php echo htmlspecialchars($homeUrl); ?>" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px 16px;background:var(--brand-500);color:#fff;border-radius:0.5rem;font-size:0.9375rem;font-weight:500;text-decoration:none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                    <?php echo htmlspecialchars($home); ?>
                </a>
            </div>
        </div>
    </main>
</body>
</html>
