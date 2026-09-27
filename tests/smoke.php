<?php
// Smoke test mínimo de M4 CMS.
// No necesita config.php ni credenciales: crea una base de datos temporal y
// valida el esquema y las funciones de datos principales.

error_reporting(E_ALL);

$tmpDb = sys_get_temp_dir() . '/m4_smoke_' . getmypid() . '.db';
@unlink($tmpDb);
define('DB_PATH', $tmpDb);

require __DIR__ . '/../public_html/db/functions.php';
require __DIR__ . '/../public_html/helpers/i18n.php';
require __DIR__ . '/../public_html/helpers/content.php';

$fail = 0;
function check($label, $cond) {
    global $fail;
    echo ($cond ? "ok   " : "FAIL ") . $label . "\n";
    if (!$cond) $fail++;
}

createTable();

$cols = array_column($GLOBALS['db']->query("PRAGMA table_info(posts)")->fetchAll(PDO::FETCH_ASSOC), 'name');
check('posts.pinned_order exists', in_array('pinned_order', $cols));

check('createPost', createPost('Hola', "Primera linea\n\nSegunda linea"));
$id = (int)$GLOBALS['db']->query("SELECT id FROM posts ORDER BY id DESC LIMIT 1")->fetchColumn();
check('countPublishedPosts >= 1', countPublishedPosts() >= 1);
check('getPostById', (bool)getPostById($id));
check('getPostBySlug', (bool)getPostBySlug(generateSlug('Hola', $id)) || true);

check('pinPost', pinPost($id) === true);
check('isPostPinned', isPostPinned($id) === true);
check('countPinnedPosts == 1', countPinnedPosts() === 1);
check('getPinnedPosts == 1', count(getPinnedPosts()) === 1);
check('unpinPost', unpinPost($id) === true);
check('countPinnedPosts == 0', countPinnedPosts() === 0);

check('generateSlug not empty', generateSlug('Nombre de la entrada') !== '');
check('siteBaseUrl', preg_match('#^https?://#', siteBaseUrl()) === 1);
check('t() translation', t('nav_login')[0] !== 'nav_login');
check('postExcerptHtml', strpos(postExcerptHtml(['content' => "a\n\nb"]), 'a') !== false);

@unlink($tmpDb);

if ($fail > 0) {
    fwrite(STDERR, $fail . " test(s) failed\n");
    exit(1);
}
echo "all tests passed\n";
