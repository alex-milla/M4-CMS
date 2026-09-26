<?php
// Formulario para crear nueva publicación
session_start();
include_once 'config.php';
include_once 'db/functions.php';
include_once 'helpers/theme.php';
include_once 'helpers/i18n.php';
include_once 'helpers/icons.php';

// Verificar si el usuario está logeado como administrador
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ' . adminUrl('login.php'));
    exit;
}

// Theme (FinSec claro/oscuro) para admin session
if (isset($_POST['theme_setting']) && !empty($_POST['theme_setting'])) {
    $_SESSION['theme'] = normalizeTheme($_POST['theme_setting']);
    saveSetting('site_theme', $_SESSION['theme']);
} elseif (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = getSiteTheme();
}

$theme = normalizeTheme($_SESSION['theme']);
list($new_post_title, )   = t('nav_new_post');
$new_post_page_title      = $new_post_title . ' - M4 CMS';
list($btn_back, )         = t('page_return');
$lang                      = $_SESSION['lang'] ?? 'es';
list($lbl_title_label, )  = t('field_title_label');
list($ph_title_val, )     = t('field_title_ph');
list($lbl_content_label, ) = t('field_content_label');
list($ph_content_val, )   = t('field_content_ph');
list($btn_save_post, )    = t('btn_save_post');
list($lbl_status, )       = t('lbl_status');
list($lbl_published, )    = t('lbl_published');
list($lbl_draft, )        = t('lbl_draft');
list($lbl_slug, )         = t('lbl_slug');
list($ph_slug, )          = t('ph_slug');
list($lbl_category, )     = t('lbl_category');
list($ph_category, )      = t('ph_category');
list($lbl_no_cat, )       = t('lbl_no_category');

createTable();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim(html_entity_decode((string)($_POST['title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $content = trim((string)($_POST['content'] ?? ''));
    $status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';
    $slug = trim((string)($_POST['slug'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $tags = (string)($_POST['tags'] ?? '');
    $createdAt = normalizePublishDate($_POST['created_at'] ?? '');

    if (!empty($title) && !empty($content)) {
        createPost($title, $content, $status, $slug, $category, $tags, $createdAt);
        logAdminEvent('post_created', $title);
        header('Location: index.php');
        exit;
    } else {
        list($err_missing_fields, ) = t('err_missing_fields');
        $error = $err_missing_fields;
    }
}
$categories = getCategories();
$usedTags = getUsedTags();
list($cms_header, ) = t('page_m4_cms_header');
list($_footer_cms, ) = t('footer_cms');
list($_lbl_lang, ) = t('lbl_lang');
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($new_post_page_title); ?></title>
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
            <!-- New Post -->
            <h2><?php echo htmlspecialchars($new_post_title); ?></h2>

            <?php if (isset($error)): ?>
                <div class="msg-banner error">
                    <?php echo finsec_icon('alert', 16); ?>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="post" style="padding-top: 4px;">
                <div>
                    <label for="title"><?php echo htmlspecialchars($lbl_title_label); ?></label>
                    <input type="text" id="title" name="title" placeholder="<?php echo htmlspecialchars($ph_title_val); ?>" required value="">
                </div>

                <div>
                    <label for="slug"><?php echo htmlspecialchars($lbl_slug); ?></label>
                    <input type="text" id="slug" name="slug" class="mono" placeholder="<?php echo htmlspecialchars($ph_slug); ?>" value="">
                </div>

                <div>
                    <label for="category"><?php echo htmlspecialchars($lbl_category); ?></label>
                    <input type="text" id="category" name="category" placeholder="<?php echo htmlspecialchars($ph_category); ?>" list="category-list" value="">
                    <datalist id="category-list">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div>
                    <label for="tags"><?php echo htmlspecialchars(t('lbl_tags')[0]); ?></label>
                    <input type="text" id="tags" name="tags" placeholder="<?php echo htmlspecialchars(t('ph_tags')[0]); ?>" list="tags-list" value="">
                    <datalist id="tags-list">
                        <?php foreach ($usedTags as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div>
                    <label for="status"><?php echo htmlspecialchars($lbl_status); ?></label>
                    <select id="status" name="status">
                        <option value="published"><?php echo htmlspecialchars($lbl_published); ?></option>
                        <option value="draft"><?php echo htmlspecialchars($lbl_draft); ?></option>
                    </select>
                </div>

                <div>
                    <label for="created_at"><?php echo htmlspecialchars(t('lbl_publish_date')[0]); ?></label>
                    <input type="datetime-local" id="created_at" name="created_at" value="">
                    <small style="color: var(--texto-suave, #666);"><?php echo htmlspecialchars(t('help_publish_date')[0]); ?></small>
                </div>

                <div>
                    <label for="content"><?php echo htmlspecialchars($lbl_content_label); ?></label>
                    <textarea id="content" name="content" rows="10" placeholder="<?php echo htmlspecialchars($ph_content_val); ?>" required></textarea>
                </div>

                <button type="submit" class="btn-primary"><?php echo finsec_icon('check', 16); ?> <?php echo htmlspecialchars($btn_save_post); ?></button>
            </form>
        </main>

        <footer>
            <p><?php echo htmlspecialchars($_footer_cms); ?></p>
        </footer>
    </div>
</body>
</html>