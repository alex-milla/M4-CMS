<?php
// Render de bloques (widgets) para el frontend público.
// El módulo está desactivado por defecto: se activa desde el panel (widgets_enabled).
// Todo el contenido se escapa al salir; los embeds se generan desde una allowlist
// de proveedores, nunca desde HTML pegado por el usuario.

include_once __DIR__ . '/../db/functions.php';

// URL http/https válida (evita javascript:, data:, etc.)
function widgetValidUrl($url) {
    $url = trim((string)$url);
    if ($url === '') return false;
    if (!preg_match('#^https?://#i', $url)) return false;
    $host = parse_url($url, PHP_URL_HOST);
    return is_string($host) && $host !== '';
}

// Convertir una URL pública en URL de embed según proveedor (allowlist).
// Devuelve null si el proveedor no está permitido.
function widgetEmbedUrl($url) {
    if (!widgetValidUrl($url)) return null;
    $host = strtolower(parse_url($url, PHP_URL_HOST));
    $path = parse_url($url, PHP_URL_PATH);
    parse_str((string)parse_url($url, PHP_URL_QUERY), $query);

    // YouTube: youtube.com/watch?v=ID, youtu.be/ID, youtube.com/embed/ID
    if ($host === 'youtube.com' || $host === 'www.youtube.com' || $host === 'm.youtube.com' || $host === 'youtu.be') {
        if (preg_match('#^/embed/([A-Za-z0-9_-]{6,})#', (string)$path, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1];
        }
        $id = $query['v'] ?? '';
        if ($host === 'youtu.be' && preg_match('#^/([A-Za-z0-9_-]{6,})#', (string)$path, $m)) {
            $id = $m[1];
        }
        if ($id !== '' && preg_match('/^[A-Za-z0-9_-]{6,}$/', (string)$id)) {
            return 'https://www.youtube-nocookie.com/embed/' . $id;
        }
        return null;
    }

    // Vimeo: vimeo.com/ID, player.vimeo.com/video/ID
    if ($host === 'vimeo.com' || $host === 'www.vimeo.com' || $host === 'player.vimeo.com') {
        if (preg_match('#^/video/(\d+)#', (string)$path, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        if (preg_match('#^/(\d+)#', (string)$path, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        return null;
    }

    // Dailymotion: dailymotion.com/video/ID o dai.ly/ID
    if ($host === 'dailymotion.com' || $host === 'www.dailymotion.com' || $host === 'dai.ly') {
        if (preg_match('#^/video/([A-Za-z0-9]+)#', (string)$path, $m)) {
            return 'https://www.dailymotion.com/embed/video/' . $m[1];
        }
        if ($host === 'dai.ly' && preg_match('#^/([A-Za-z0-9]+)#', (string)$path, $m)) {
            return 'https://www.dailymotion.com/embed/video/' . $m[1];
        }
        return null;
    }

    // SoundCloud: soundcloud.com → widget de embed por URL
    if ($host === 'soundcloud.com' || $host === 'www.soundcloud.com') {
        return 'https://w.soundcloud.com/player/?url=' . rawurlencode($url);
    }

    return null;
}

// Parsear las líneas "texto | https://..." del tipo links.
// Devuelve [[label, url], ...] solo con URLs http/https válidas.
function widgetParseLinks($content) {
    $links = [];
    foreach (preg_split('/\r\n|\r|\n/', (string)$content) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '|') === false) continue;
        list($label, $url) = array_map('trim', explode('|', $line, 2));
        if ($label === '' || !widgetValidUrl($url)) continue;
        $links[] = [$label, $url];
    }
    return $links;
}

// Pintar la sección de bloques de una zona. Si el módulo está desactivado
// o no hay bloques publicados, no pinta nada.
function renderWidgets($zone = 'footer') {
    if (!widgetsModuleEnabled()) return;
    try {
        $widgets = getPublishedWidgets($zone);
    } catch (\Throwable $e) {
        return;
    }
    if (empty($widgets)) return;

    echo '<section class="site-widgets">';
    foreach ($widgets as $w) {
        $type = $w['type'] ?? 'links';
        echo '<div class="widget">';
        echo '<h3 class="widget-title">' . htmlspecialchars($w['title']) . '</h3>';

        if ($type === 'links') {
            $links = widgetParseLinks($w['content'] ?? '');
            if (!empty($links)) {
                echo '<ul class="widget-links">';
                foreach ($links as [$label, $url]) {
                    echo '<li><a href="' . htmlspecialchars($url, ENT_QUOTES) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($label) . '</a></li>';
                }
                echo '</ul>';
            }
        } elseif ($type === 'embed') {
            $embed = widgetEmbedUrl($w['url'] ?? '');
            if ($embed !== null) {
                echo '<div class="widget-embed">';
                echo '<iframe src="' . htmlspecialchars($embed, ENT_QUOTES) . '" loading="lazy" allowfullscreen '
                    . 'sandbox="allow-scripts allow-same-origin allow-popups allow-presentation" title="' . htmlspecialchars($w['title'], ENT_QUOTES) . '"></iframe>';
                echo '</div>';
            } elseif (widgetValidUrl($w['url'] ?? '')) {
                // Proveedor no soportado: caer a enlace plano, sin iframe
                echo '<p><a href="' . htmlspecialchars($w['url'], ENT_QUOTES) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($w['url']) . '</a></p>';
            }
        } else { // text
            $text = trim((string)($w['content'] ?? ''));
            if ($text !== '') {
                echo '<p class="widget-text">' . nl2br(htmlspecialchars($text, ENT_QUOTES)) . '</p>';
            }
            if (widgetValidUrl($w['url'] ?? '')) {
                echo '<p><a class="widget-cta" href="' . htmlspecialchars($w['url'], ENT_QUOTES) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($w['title']) . ' &#8594;</a></p>';
            }
        }

        echo '</div>';
    }
    echo '</section>';
}
