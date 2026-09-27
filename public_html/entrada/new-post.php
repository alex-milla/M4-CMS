<?php
// Crear una publicación desde el panel (misma ventana, sin abrir pestaña).
include_once __DIR__ . '/../helpers/auth.php';
m4_session_start();
requireAdmin();

include_once __DIR__ . '/../config.php';
include_once __DIR__ . '/../db/functions.php';
include_once __DIR__ . '/../helpers/theme.php';
include_once __DIR__ . '/../helpers/i18n.php';
include_once __DIR__ . '/../helpers/icons.php';
include_once __DIR__ . '/../helpers/admin_layout.php';

createTable();

// Tema (el admin persiste en DB + sesión)
$theme = initTheme();

$pageTitle = t('nav_new_post')[0];

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_save'])) {
    if (!csrfValidate()) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
    $title = trim(html_entity_decode((string)($_POST['title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $content = trim((string)($_POST['content'] ?? ''));
    $status = (($_POST['status'] ?? 'published') === 'draft') ? 'draft' : 'published';
    $slug = trim((string)($_POST['slug'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $tags = (string)($_POST['tags'] ?? '');
    $createdAt = normalizePublishDate($_POST['created_at'] ?? '');

    if (!empty($title) && !empty($content)) {
        createPost($title, $content, $status, $slug, $category, $tags, $createdAt);
        logAdminEvent('post_created', $title);
        header('Location: posts.php');
        exit;
    }
    $error = t('err_missing_fields')[0];
}

$categories = getCategories();
$usedTags = getUsedTags();

adminLayoutHead([
    'title'  => $pageTitle,
    'h1'     => $pageTitle,
    'active' => 'new',
    'theme'  => $theme,
]);
?>
<?php if ($error !== null): ?>
<div class="msg-banner error">
    <?php echo finsec_icon('alert', 16); ?>
    <span><?php echo htmlspecialchars($error); ?></span>
</div>
<?php endif; ?>

<div class="admin-card">
    <form method="POST" action="">
        <?php echo csrfField(); ?>
        <input type="hidden" name="post_save" value="1">

        <div class="admin-form-group">
            <label for="title"><?php echo htmlspecialchars(t('field_title_label')[0]); ?></label>
            <input type="text" id="title" name="title" placeholder="<?php echo htmlspecialchars(t('field_title_ph')[0]); ?>" required>
        </div>

        <div class="admin-form-group">
            <label for="slug"><?php echo htmlspecialchars(t('lbl_slug')[0]); ?></label>
            <input type="text" id="slug" name="slug" class="mono" placeholder="<?php echo htmlspecialchars(t('ph_slug')[0]); ?>">
        </div>

        <div class="admin-form-group">
            <label for="category"><?php echo htmlspecialchars(t('lbl_category')[0]); ?></label>
            <input type="text" id="category" name="category" placeholder="<?php echo htmlspecialchars(t('ph_category')[0]); ?>" list="category-list">
            <datalist id="category-list">
                <?php foreach ($categories as $cat): ?><option value="<?php echo htmlspecialchars($cat); ?>"><?php endforeach; ?>
            </datalist>
        </div>

        <div class="admin-form-group">
            <label for="tags"><?php echo htmlspecialchars(t('lbl_tags')[0]); ?></label>
            <input type="text" id="tags" name="tags" placeholder="<?php echo htmlspecialchars(t('ph_tags')[0]); ?>" list="tags-list">
            <datalist id="tags-list">
                <?php foreach ($usedTags as $tg): ?><option value="<?php echo htmlspecialchars($tg); ?>"><?php endforeach; ?>
            </datalist>
        </div>

        <div class="admin-form-group">
            <label for="status"><?php echo htmlspecialchars(t('lbl_status')[0]); ?></label>
            <select id="status" name="status">
                <option value="published"><?php echo htmlspecialchars(t('lbl_published')[0]); ?></option>
                <option value="draft"><?php echo htmlspecialchars(t('lbl_draft')[0]); ?></option>
            </select>
        </div>

        <div class="admin-form-group">
            <label for="created_at"><?php echo htmlspecialchars(t('lbl_publish_date')[0]); ?></label>
            <input type="datetime-local" id="created_at" name="created_at">
            <p class="help-text" style="margin:6px 0 0;font-size:0.75rem;color:var(--text-3);"><?php echo htmlspecialchars(t('help_publish_date')[0]); ?></p>
        </div>

        <div class="admin-form-group">
            <label for="content"><?php echo htmlspecialchars(t('field_content_label')[0]); ?></label>
            <textarea id="content" name="content" rows="10" placeholder="<?php echo htmlspecialchars(t('field_content_ph')[0]); ?>" required></textarea>
        </div>

        <button type="submit" class="btn-primary"><?php echo finsec_icon('check', 16); ?> <?php echo htmlspecialchars(t('btn_save_post')[0]); ?></button>
    </form>
</div>
<?php
adminLayoutFooter();
