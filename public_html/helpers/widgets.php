<?php
// Render de bloques (widgets) para el frontend público.
// El módulo está desactivado por defecto: se activa desde el panel (widgets_enabled).
// Todo el contenido se escapa al salir; los embeds se generan desde una allowlist
// de proveedores, nunca desde HTML pegado por el usuario.

include_once __DIR__ . '/../db/functions.php';
include_once __DIR__ . '/content.php';

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

    // Spotify: open.spotify.com/(track|album|playlist|episode|show|artist)/ID
    if ($host === 'open.spotify.com' || $host === 'spotify.com' || $host === 'www.spotify.com') {
        if (preg_match('#^/embed/(track|album|playlist|episode|show|artist)/([A-Za-z0-9]+)#', (string)$path, $m)) {
            return 'https://open.spotify.com/embed/' . $m[1] . '/' . $m[2];
        }
        if (preg_match('#^/(track|album|playlist|episode|show|artist)/([A-Za-z0-9]+)#', (string)$path, $m)) {
            return 'https://open.spotify.com/embed/' . $m[1] . '/' . $m[2];
        }
        return null;
    }

    return null;
}

// Sanea HTML básico (allowlist). Elimina etiquetas peligrosas con su contenido,
// desenvuelve las desconocidas y limpia atributos. Pensado para contenido
// escrito por el admin; el frontend nunca confía en HTML pegado sin filtrar.
function widgetSanitizeHtml($html) {
    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del',
        'ul', 'ol', 'li', 'a', 'h3', 'h4', 'h5', 'blockquote', 'code', 'pre',
        'span', 'hr'];
    $dangerous = ['script', 'style', 'iframe', 'object', 'embed', 'form',
        'input', 'button', 'textarea', 'select', 'option', 'svg', 'math',
        'link', 'meta', 'base', 'title', 'head', 'html', 'body', 'frame',
        'frameset', 'applet', 'audio', 'video', 'source', 'track', 'canvas'];

    if (!class_exists('DOMDocument')) {
        return nl2br(htmlspecialchars($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $ok = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$ok) {
        return nl2br(htmlspecialchars($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    foreach (iterator_to_array($doc->childNodes) as $child) {
        if ($child->nodeType === XML_PI_NODE) {
            $doc->removeChild($child);
        }
    }

    widgetSanitizeNode($doc, $allowed, $dangerous);

    $out = '';
    foreach ($doc->childNodes as $child) {
        $out .= $doc->saveHTML($child);
    }
    return $out;
}

// Recorrido recursivo del DOM aplicando la allowlist. Uso interno.
function widgetSanitizeNode(DOMNode $node, array $allowed, array $dangerous) {
    for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
        $child = $node->childNodes->item($i);
        if ($child->nodeType === XML_TEXT_NODE) {
            continue;
        }
        if ($child->nodeType !== XML_ELEMENT_NODE) {
            $node->removeChild($child);
            continue;
        }

        $tag = strtolower($child->nodeName);

        // Etiquetas peligrosas: fuera con todo su contenido.
        if (in_array($tag, $dangerous, true)) {
            $node->removeChild($child);
            continue;
        }

        // Desconocidas: desenvolver (subir los hijos, quitar la etiqueta).
        if (!in_array($tag, $allowed, true)) {
            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child);
            }
            $node->removeChild($child);
            continue;
        }

        // Limpiar atributos: solo se conservan href/title en <a>.
        if ($child->hasAttributes()) {
            for ($a = $child->attributes->length - 1; $a >= 0; $a--) {
                $attr = $child->attributes->item($a);
                $name = strtolower($attr->name);
                if ($tag === 'a' && $name === 'href' && widgetValidHref($attr->value)) {
                    continue;
                }
                if ($tag === 'a' && $name === 'title') {
                    continue;
                }
                $child->removeAttribute($attr->name);
            }
        }
        if ($tag === 'a') {
            $href = $child->getAttribute('href');
            if ($href === '' || !widgetValidHref($href)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            $child->setAttribute('target', '_blank');
            $child->setAttribute('rel', 'noopener noreferrer');
        }

        widgetSanitizeNode($child, $allowed, $dangerous);
    }
}

// href permitido en HTML saneado: http/https o mailto.
function widgetValidHref($href) {
    $href = trim((string)$href);
    return $href !== '' && preg_match('#^(https?://|mailto:)#i', $href) === 1;
}

// Pintar el contenido de un bloque con detección automática:
//  - una única URL de medios soportada -> embed;
//  - HTML básico -> saneado y pintado;
//  - texto plano -> párrafos, saltos de línea y URLs autoenlazadas.
function widgetRenderContent($content, $title = '') {
    $trim = trim((string)$content);
    if ($trim === '') return '';

    // 1) URL única de un proveedor soportado: embed.
    if (preg_match('~^https?://\S+$~i', $trim)) {
        $embed = widgetEmbedUrl($trim);
        if ($embed !== null) {
            return '<div class="widget-embed"><iframe src="' . htmlspecialchars($embed, ENT_QUOTES)
                . '" loading="lazy" allowfullscreen '
                . 'sandbox="allow-scripts allow-same-origin allow-popups allow-presentation"'
                . ($title !== '' ? ' title="' . htmlspecialchars($title, ENT_QUOTES) . '"' : '')
                . '></iframe></div>';
        }
    }

    // 2) HTML básico: sanear y devolver.
    if (preg_match('#</?[a-z][a-z0-9]*(\s[^>]*)?>#i', $trim)) {
        return '<div class="widget-content">' . widgetSanitizeHtml($trim) . '</div>';
    }

    // 3) Texto plano: párrafos, saltos y URLs autoenlazadas.
    $raw = htmlspecialchars($trim, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $out = '';
    foreach (preg_split("/\n\s*\n+/", $raw) as $para) {
        $para = trim($para);
        if ($para === '') continue;
        $out .= '<p class="widget-text">' . nl2br(autoLinkUrls($para)) . '</p>';
    }
    return $out;
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
        $title = trim((string)($w['title'] ?? ''));
        echo '<div class="widget">';
        if ($title !== '') {
            echo '<h3 class="widget-title">' . htmlspecialchars($title) . '</h3>';
        }
        echo widgetRenderContent($w['content'] ?? '', $title);
        echo '</div>';
    }
    echo '</section>';
}
