<?php
// 404.php - Página genérica para URLs inexistentes (se sirve vía ErrorDocument 404).
// Sin dependencias (sin DB, sin sesión, sin includes) para que nunca falle.
http_response_code(404);

$acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
$lang = (stripos($acceptLang, 'es') !== false) ? 'es' : 'en';

$texts = [
    'es' => [
        'title' => '404 - Página no encontrada',
        'msg'   => 'La página que buscas no existe.',
        'home'  => 'Volver al inicio',
    ],
    'en' => [
        'title' => '404 - Page not found',
        'msg'   => "The page you're looking for doesn't exist.",
        'home'  => 'Back to home',
    ],
];
$t = $texts[$lang];

// Rutas relativas a la raíz del CMS (funciona también en subdirectorio)
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$home = ($base === '' ? '' : $base) . '/index.php';
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
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                </div>
                <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-1);">404</h1>
                <p style="font-size:0.875rem;color:var(--text-2);margin-top:4px;"><?php echo $t['msg']; ?></p>
            </div>
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:0.75rem;box-shadow:0 1px 2px 0 rgb(0 0 0 / 0.05);padding:24px;text-align:center;">
                <a href="<?php echo htmlspecialchars($home); ?>" style="display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:10px 16px;background:var(--brand-500);color:#fff;border-radius:0.5rem;font-size:0.9375rem;font-weight:500;text-decoration:none;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
                    <?php echo $t['home']; ?>
                </a>
            </div>
        </div>
    </main>
</body>
</html>
