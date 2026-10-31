<?php
// RSS/Atom feed - M4 CMS
include_once 'config.php';
include_once 'db/functions.php';
include_once 'helpers/i18n.php';
include_once 'helpers/content.php';

createTable();

$settings = getAllSettings();
$siteTitle = $settings['site_title'] ?? 'M4 CMS';
$siteTagline = $settings['site_tagline'] ?? 'Blog personal con PHP y SQLite';
$posts = getPublishedPosts();

// URL base: setting 'base_url' si existe, o Host validado (siteBaseUrl)
$base = siteBaseUrl();

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
    <channel>
        <title><?php echo htmlspecialchars($siteTitle); ?></title>
        <link><?php echo htmlspecialchars($base . '/'); ?></link>
        <description><?php echo htmlspecialchars($siteTagline); ?></description>
        <language><?php echo 'es'; ?></language>
        <lastBuildDate><?php echo date('r'); ?></lastBuildDate>
<?php foreach ($posts as $post): ?>
        <item>
            <title><?php echo htmlspecialchars($post['title']); ?></title>
            <link><?php echo htmlspecialchars($base . postUrl($post['slug'] ?? $post['id'])); ?></link>
            <guid isPermaLink="false"><?php echo htmlspecialchars($base . postUrl($post['slug'] ?? $post['id'])); ?></guid>
            <pubDate><?php echo date('r', strtotime($post['created_at'])); ?></pubDate>
            <?php if (!empty($post['category'])): ?>
            <category><?php echo htmlspecialchars($post['category']); ?></category>
            <?php endif; ?>
            <description><?php
                // Los bloques [code] no aportan en un feed: se omiten
                $rawDesc = (string)$post['content'];
                if (function_exists('postExtractCodeBlocks')) {
                    $tmpDesc = $rawDesc;
                    postExtractCodeBlocks($tmpDesc);
                    $rawDesc = $tmpDesc;
                }
                $rawDesc = preg_replace('/@@M4CODE\d+@@/', '', $rawDesc);
                $desc = trim(preg_replace('/\s+/', ' ', strip_tags($rawDesc)));
                $desc = function_exists('mb_substr') ? mb_substr($desc, 0, 300) : substr($desc, 0, 300);
                echo htmlspecialchars($desc, ENT_QUOTES | ENT_XML1, 'UTF-8');
            ?></description>
        </item>
<?php endforeach; ?>
    </channel>
</rss>
