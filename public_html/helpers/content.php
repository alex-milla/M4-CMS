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
