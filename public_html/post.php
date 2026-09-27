<?php
// Pagina individual de una publicacion por slug
include_once __DIR__ . '/helpers/session.php';
m4_session_start();
include_once 'config.php';
include_once 'db/functions.php';
include_once 'helpers/theme.php';
include_once 'helpers/i18n.php';
include_once 'helpers/content.php';
include_once 'helpers/icons.php';
include_once __DIR__ . '/helpers/csrf.php';

createTable();

$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Accion del menu de administracion: alternar publicado <-> borrador
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin && ($_POST['action'] ?? '') === 'toggle_status') {
    if (!csrfValidate()) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
    $toggleId = (int)($_POST['post_id'] ?? 0);
    $togglePost = $toggleId > 0 ? getPostById($toggleId) : null;
    if ($togglePost) {
        $newStatus = ($togglePost['status'] ?? 'published') === 'published' ? 'draft' : 'published';
        // updatePost existe en todas las versiones de functions.php (no depender de funciones nuevas)
        updatePost($toggleId, $togglePost['title'], $togglePost['content'], $newStatus, $togglePost['slug'] ?? '', $togglePost['category'] ?? '');
        logAdminEvent('post_status_changed', ($togglePost['title'] ?? "id=$toggleId") . ' -> ' . $newStatus);
        $back = !empty($togglePost['slug']) ? $togglePost['slug'] : $togglePost['id'];
        header('Location: ' . postUrl($back) . '?updated=1');
        exit;
    }
}

$slug = $_GET['slug'] ?? '';

// URL canonica (SEO): redirigir post.php?slug=X y /post/X a /X (estilo WordPress "Nombre de la entrada")
// Solo redirigir si estamos accediendo directamente a post.php (no a una URL amigable reescrita)
if ($slug !== '' && PHP_SAPI !== 'cli-server' && !empty($_SERVER['HTTP_HOST'])) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

    // Si la URL actual no es ya una URL amigable (no es /slug o /slug/), redirigir
    // Una URL amigable reescrita por .htaccess tendrá REQUEST_URI = /slug pero SCRIPT_NAME = /post.php
    // Si REQUEST_URI contiene 'post.php', estamos accediendo directamente a post.php?slug=X
    $isDirectPostAccess = strpos($_SERVER['REQUEST_URI'] ?? '', 'post.php') !== false;

    if ($isDirectPostAccess) {
        header('Location: ' . $proto . '://' . $_SERVER['HTTP_HOST'] . $dir . '/' . rawurlencode($slug), true, 301);
        exit;
    }
}

$post = null;

if (!empty($slug)) {
    $post = getPostBySlug($slug);
    // Si no encuentra por slug, intentar por ID (compatibilidad)
    if (!$post && is_numeric($slug)) {
        $post = getPostById((int)$slug);
    }
}

if (!$post || $post['status'] !== 'published') {
    // Si es admin, puede ver borradores
    if (!$isAdmin) {
        $post = null;
    }
}

// Post programado (fecha futura): los no-admins no lo ven hasta su fecha
if ($post && !$isAdmin && strcmp($post['created_at'], nowLocalString()) > 0) {
    $post = null;
}

if (!$post) {
    // Contenido inexistente o no visible: usar la página de error personalizada.
    include __DIR__ . '/404.php';
    exit;
}

// Theme (FinSec claro/oscuro)
if (isset($_SESSION['theme'])) {
    $theme = normalizeTheme($_SESSION['theme']);
} else {
    // Respeta la cookie del visitante; por defecto claro si no está logado
    $theme = resolveTheme($isAdmin);
}
$class = themeClass($theme);
$lang = $_SESSION['lang'] ?? 'es';

// Cabecera del sitio (mismos valores que index.php)
$settings = getAllSettings();
$siteTitle = $settings['site_title'] ?? 'M4 CMS';
$siteTagline = $settings['site_tagline'] ?? 'Blog personal con PHP y SQLite';

// Traducciones
list($page_title, )    = t('page_post_title');
list($_no_posts, )     = t('post_not_found');
list($_back, )         = t('lbl_back_to_list');
list($_published_on, ) = t('lbl_published_on');
list($_admin_menu, )       = t('post_admin_actions');
list($_btn_edit, )         = t('btn_edit');
list($_btn_delete, )       = t('btn_delete');
list($_confirm_delete, )   = t('confirm_delete_post');
list($_btn_to_draft, )     = t('btn_send_to_draft');
list($_btn_publish, )      = t('btn_publish_post');
list($_confirm_draft, )    = t('confirm_unpublish_post');
list($_status_updated, )   = t('msg_status_updated');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?></title>
    <link rel="stylesheet" href="assets/finsec.css">
</head>
<body class="<?php echo $class; ?>">
    <div class="container">
        <header class="site-header">
            <div>
                <h1 class="site-title"><a href="index.php"><?php echo htmlspecialchars($siteTitle); ?></a></h1>
                <p class="site-tagline"><?php echo htmlspecialchars($siteTagline); ?></p>
            </div>
        </header>

        <main>
            <article class="post">
                <h2><?php echo htmlspecialchars($post['title']); ?></h2>
                <p class="date">
                    <?php echo finsec_icon('calendar', 12); ?>
                    <?php echo htmlspecialchars($_published_on . ' ' . $post['created_at']); ?>
                </p>

                <?php if (!empty($post['category']) || !empty($post['tags'])): ?>
                <div class="meta-row" style="margin-top: 0; margin-bottom: 16px;">
                    <?php if (!empty($post['category'])): ?>
                    <a class="chip" href="index.php?cat=<?php echo urlencode($post['category']); ?>"><?php echo finsec_icon('folder', 12); ?> <?php echo htmlspecialchars($post['category']); ?></a>
                    <?php endif; ?>
                    <?php if (!empty($post['tags'])): ?>
                        <?php foreach (explode(',', $post['tags']) as $_tag): $_tag = trim($_tag); if ($_tag === '') continue; ?>
                        <a class="chip" href="index.php?tag=<?php echo urlencode($_tag); ?>"><?php echo finsec_icon('tag', 12); ?> <?php echo htmlspecialchars($_tag); ?></a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($isAdmin): ?>
                <div class="post-admin">
                    <span class="post-admin__label"><?php echo finsec_icon('settings', 14); ?> <?php echo htmlspecialchars($_admin_menu); ?></span>
                    <a href="edit.php?id=<?php echo $post['id']; ?>" class="post-admin__edit"><?php echo finsec_icon('pencil', 14); ?> <?php echo htmlspecialchars($_btn_edit); ?></a>
                    <a href="delete.php?id=<?php echo $post['id']; ?>" onclick="return confirm('<?php echo htmlspecialchars($_confirm_delete, ENT_QUOTES); ?>');"><?php echo finsec_icon('trash', 14); ?> <?php echo htmlspecialchars($_btn_delete); ?></a>
                    <form method="POST" action=""<?php if (($post['status'] ?? 'published') === 'published'): ?> onsubmit="return confirm('<?php echo htmlspecialchars($_confirm_draft, ENT_QUOTES); ?>');"<?php endif; ?>>
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="toggle_status">
                        <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                        <button type="submit"><?php echo finsec_icon('eye', 14); ?> <?php echo htmlspecialchars(($post['status'] ?? 'published') === 'draft' ? $_btn_publish : $_btn_to_draft); ?></button>
                    </form>
                </div>

                <?php if (isset($_GET['updated']) && $_GET['updated'] == '1'): ?>
                <div class="msg-banner success">
                    <?php echo finsec_icon('check', 16); ?>
                    <span><?php echo htmlspecialchars($_status_updated); ?></span>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($post['status'] === 'draft'): ?>
                <div class="msg-banner warning">
                    <?php echo finsec_icon('alert', 16); ?>
                    <span><?php echo htmlspecialchars(t('lbl_draft')[0]); ?></span>
                </div>
                <?php elseif (strcmp($post['created_at'], nowLocalString()) > 0): ?>
                <div class="msg-banner info">
                    <?php echo finsec_icon('calendar', 16); ?>
                    <span><?php echo htmlspecialchars(t('lbl_scheduled_info')[0] . ' ' . $post['created_at']); ?></span>
                </div>
                <?php endif; ?>

                <div class="content"><?php echo postContentHtml($post['content']); ?></div>
                </article>

                <p style="margin-top: 24px;"><a href="index.php"><?php echo finsec_icon('arrow-left', 14); ?> <?php echo htmlspecialchars($_back); ?></a></p>
        </main>

        <footer>
            <p><?php echo htmlspecialchars($siteTitle); ?></p>
        </footer>
    </div>
</body>
</html>
