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

// Extrae del contenido los bloques [code]...[/code] (opcionalmente con
// título: [code=Indicadores]) y los sustituye por marcadores.
// Devuelve [contenido_con_marcadores, bloques].
function postExtractCodeBlocks(&$content) {
    $blocks = [];
    $content = preg_replace_callback(
        '/\[code(?:=([^\]\n]{1,60}))?\]([\s\S]*?)\[\/code\]/i',
        function ($m) use (&$blocks) {
            $label = trim((string)($m[1] ?? ''));
            $code  = trim((string)$m[2]);
            $blocks[] = ['label' => $label, 'code' => $code];
            return "\n\n@@M4CODE" . (count($blocks) - 1) . "@@\n\n";
        },
        (string)$content
    );
    return $blocks;
}

// HTML de un bloque de código plegable con botón de copiar.
// El texto se escapa tal cual: sin autoenlaces ni saltos añadidos, para que
// lo que se copia sea exactamente lo que el autor escribió.
function postCodeBlockHtml($code, $label = '') {
    static $counter = 0;
    $counter++;
    $id = 'm4code-' . $counter;

    $title  = ($label !== '') ? $label : t('post_code_default_label')[0];
    $copy   = t('post_code_copy')[0];
    $copied = t('post_code_copied')[0];
    $lines  = substr_count((string)$code, "\n") + 1;
    $linesLabel = ($lines === 1) ? t('post_code_line_one')[0] : t('post_code_lines')[0];

    $html  = '<div class="post-code">';
    $html .= '<div class="post-code__bar">';
    $html .= '<details class="post-code__details">';
    $html .= '<summary class="post-code__head">';
    $html .= '<svg class="post-code__chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>';
    $html .= '<span class="post-code__title">' . htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</span>';
    $html .= '<span class="post-code__meta">' . (int)$lines . ' ' . htmlspecialchars($linesLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</span>';
    $html .= '</summary>';
    $html .= '<pre class="post-code__pre"><code id="' . htmlspecialchars($id, ENT_QUOTES) . '">'
          . htmlspecialchars((string)$code, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</code></pre>';
    $html .= '</details>';
    $html .= '<button type="button" class="post-code__copy" data-for="' . htmlspecialchars($id, ENT_QUOTES) . '"'
          . ' data-label="' . htmlspecialchars($copy, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"'
          . ' data-copied="' . htmlspecialchars($copied, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '">'
          . '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>'
          . '<span class="post-code__copy-label">' . htmlspecialchars($copy, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</span>'
          . '</button>';
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}

// Sustituye los marcadores @@M4CODE n@@ por el HTML del bloque.
function postRenderCodeBlocks($html, array $blocks) {
    if (empty($blocks)) return $html;
    return preg_replace_callback('/@@M4CODE(\d+)@@/', function ($m) use ($blocks) {
        $i = (int)$m[1];
        if (!isset($blocks[$i])) return '';
        return postCodeBlockHtml($blocks[$i]['code'], $blocks[$i]['label']);
    }, $html);
}

// Script (inline, sin dependencias) para copiar el contenido de un bloque.
function postCodeCopyScript() {
    return <<<'JS'
    (function () {
        document.addEventListener('click', function (ev) {
            var btn = ev.target.closest('.post-code__copy');
            if (!btn) return;
            var code = document.getElementById(btn.getAttribute('data-for'));
            if (!code) return;
            var text = code.textContent;
            var label = btn.querySelector('.post-code__copy-label');
            var original = btn.getAttribute('data-label') || '';
            var doneText = btn.getAttribute('data-copied') || '';

            function feedback(ok) {
                if (!label) return;
                label.textContent = ok ? doneText : original;
                btn.classList.toggle('is-copied', ok);
                window.setTimeout(function () {
                    label.textContent = original;
                    btn.classList.remove('is-copied');
                }, 1500);
            }

            function fallback() {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', 'readonly');
                ta.style.position = 'absolute';
                ta.style.left = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                var ok = false;
                try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
                document.body.removeChild(ta);
                feedback(ok);
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(function () { feedback(true); }, fallback);
            } else {
                fallback();
            }
        });
    })();
JS;
}

// Extrae para listados: primer bloque del contenido, truncado a $length.
// Devuelve HTML seguro (contenido escapado + nl2br y autoenlace de URLs solo
// si el extracto no queda cortado, para no partir un <a> a medias).
function postExcerptHtml($post, $length = 200) {
    $raw = preg_replace('/\r\n|\r/', "\n", (string)($post['content'] ?? ''));
    $codeBlocks = postExtractCodeBlocks($raw);
    $raw = htmlspecialchars($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $blocks = preg_split("/\n\n+/", $raw);
    $firstBlock = '';
    foreach ($blocks as $block) {
        $trimmed = trim($block);
        if ($trimmed === '') continue;
        // Los bloques de código no sirven como extracto: se pasa al siguiente
        if (preg_match('/^@@M4CODE\d+@@$/', $trimmed)) continue;
        $firstBlock = $block;
        break;
    }
    $excerpt = substr_count($firstBlock, "\n") > 0 ? trim($firstBlock) : $firstBlock;
    $truncated = strlen($excerpt) > $length;
    if ($truncated) $excerpt = substr($excerpt, 0, $length) . '...';
    $useLinks = !$truncated && function_exists('autoLinkUrls');
    return nl2br($useLinks ? autoLinkUrls($excerpt) : $excerpt);
}

// Contenido completo del post en la vista individual (bloques con separador).
// Devuelve HTML seguro (escapado + nl2br + autoenlace + bloques de código).
function postContentHtml($content) {
    $raw = preg_replace('/\r\n|\r/', "\n", (string)$content);
    $codeBlocks = postExtractCodeBlocks($raw);
    $raw = htmlspecialchars($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $blocks = preg_split("/\n\n+/", $raw);
    $out = '';
    foreach ($blocks as $i => $block) {
        if ($i > 0) $out .= '<div class="block-sep"></div>';
        $out .= nl2br(function_exists('autoLinkUrls') ? autoLinkUrls(trim($block)) : trim($block));
    }
    return postRenderCodeBlocks($out, $codeBlocks);
}
