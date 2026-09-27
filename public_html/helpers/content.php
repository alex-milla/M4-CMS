<?php
// Helper de renderizado de contenido para el CMS

// Convierte URLs de texto plano (http/https) en enlaces clicables.
// Espera texto YA escapado con htmlspecialchars: el contenido del post
// nunca se interpreta como HTML, solo se añaden los <a> generados aqui.
function autoLinkUrls($text) {
    return preg_replace_callback(
        '~\bhttps?://[^\s<>"\']+~i',
        function ($m) {
            $url = $m[0];
            $trailing = '';
            // Separa puntuacion de cierre que no forma parte de la URL
            while ($url !== '') {
                if (preg_match('/&[A-Za-z][A-Za-z0-9]*;$/', $url)) break; // entidad HTML: es parte de la URL
                $last = substr($url, -1);
                if (!preg_match('/\p{P}/u', $last)) break;
                // conserva parentesis/corchetes equilibrados: https://es.wikipedia.org/wiki/Foo_(bar)
                if ($last === ')' && substr_count($url, '(') >= substr_count($url, ')')) break;
                if ($last === ']' && substr_count($url, '[') >= substr_count($url, ']')) break;
                $trailing = $last . $trailing;
                $url = substr($url, 0, -1);
            }
            if ($url === '') return $m[0];
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>' . $trailing;
        },
        $text
    );
}

// Extracto para listados: primer bloque del contenido, truncado a $length.
// Devuelve HTML seguro (contenido escapado + nl2br y autoenlace de URLs solo
// si el extracto no queda cortado, para no partir un <a> a medias).
function postExcerptHtml($post, $length = 200) {
    $raw = preg_replace('/\r\n|\r/', "\n", htmlspecialchars((string)($post['content'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $blocks = preg_split("/\n\n+/", $raw);
    $firstBlock = $blocks[0] ?? '';
    $excerpt = substr_count($firstBlock, "\n") > 0 ? trim($firstBlock) : $firstBlock;
    $truncated = strlen($excerpt) > $length;
    if ($truncated) $excerpt = substr($excerpt, 0, $length) . '...';
    $useLinks = !$truncated && function_exists('autoLinkUrls');
    return nl2br($useLinks ? autoLinkUrls($excerpt) : $excerpt);
}

// Contenido completo del post en la vista individual (bloques con separador).
// Devuelve HTML seguro (escapado + nl2br + autoenlace).
function postContentHtml($content) {
    $raw = preg_replace('/\r\n|\r/', "\n", htmlspecialchars((string)$content, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $blocks = preg_split("/\n\n+/", $raw);
    $out = '';
    foreach ($blocks as $i => $block) {
        if ($i > 0) $out .= '<div class="block-sep"></div>';
        $out .= nl2br(function_exists('autoLinkUrls') ? autoLinkUrls(trim($block)) : trim($block));
    }
    return $out;
}
