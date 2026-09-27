<?php
// Eliminar una publicación
include_once 'config.php';
include_once 'db/functions.php';
session_start();

// Solo administradores autenticados pueden eliminar publicaciones
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

include_once 'helpers/theme.php';
include_once 'helpers/i18n.php';
include_once 'helpers/content.php';
include_once 'helpers/icons.php';

// Theme (FinSec claro/oscuro) for session
if (isset($_POST['theme_setting']) && !empty($_POST['theme_setting'])) {
    $_SESSION['theme'] = normalizeTheme($_POST['theme_setting']);
    saveSetting('site_theme', $_SESSION['theme']);
} elseif (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = getSiteTheme();
}
$theme = normalizeTheme($_SESSION['theme']);
$lang  = $_SESSION['lang'] ?? 'es';

createTable();

// Cadenas de traducción
list($page_title, )      = t('page_delete_title');
list($cms_header, )      = t('page_m4_cms_header');
list($btn_back, )        = t('page_return');
list($delete_h2, )       = t('page_delete_title');
list($confirm_text, )    = t('delete_confirm_text');
list($btn_delete, )      = t('btn_delete');
list($btn_cancel, )      = t('btn_cancel');
list($_footer_cms, )     = t('footer_cms');
list($_lbl_lang, )       = t('lbl_lang');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    deletePost($id);
    header('Location: index.php');
    exit;
} else {
    $post = getPostById($id);

    if (!$post) {
        header('Location: index.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="assets/finsec.css">
</head>
<body class="<?php echo themeClass($theme); ?>">
    <div class="container">
        <header class="site-header">
            <div>
                <h1 class="site-title"><?php echo htmlspecialchars($cms_header); ?></h1>
            </div>
            <nav style="margin-bottom:0;">
                <a href="index.php"><?php echo finsec_icon('arrow-left', 14); ?> <?php echo htmlspecialchars($btn_back); ?></a>
            </nav>
        </header>

        <main>
            <h2><?php echo htmlspecialchars($delete_h2); ?></h2>

            <p><?php echo htmlspecialchars($confirm_text); ?></p>

            <article class="post">
                <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                <p class="date"><?php echo htmlspecialchars($post['created_at']); ?></p>
                <div class="content">
                    <?php
                    $raw = preg_replace('/\r\n|\r/', "\n", htmlspecialchars($post['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    $blocks = preg_split("/\n\n+/", $raw);
                    foreach ($blocks as $i => $block):
                        if ($i > 0):
            ?><div class="block-sep"></div><?php endif;
                        echo nl2br(function_exists('autoLinkUrls') ? autoLinkUrls(trim($block)) : trim($block));
                    endforeach;
                    ?>
                </div>
            </article>

            <form method="POST" action="" style="margin-top: 20px;">
                <button type="submit" class="btn-danger"><?php echo finsec_icon('trash', 16); ?> <?php echo htmlspecialchars($btn_delete); ?></button>
                <a href="index.php" class="btn"><?php echo htmlspecialchars($btn_cancel); ?></a>
            </form>
        </main>

        <footer>
            <p><?php echo htmlspecialchars($_footer_cms); ?></p>
        </footer>
    </div>
</body>
</html>
