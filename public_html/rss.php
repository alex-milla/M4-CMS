<?php
// RSS/Atom feed - M4 CMS
include_once 'config.php';
include_once 'db/functions.php';
include_once 'helpers/i18n.php';

createTable();

$settings = getAllSettings();
$siteTitle = $settings['site_title'] ?? 'M4 CMS';
$siteTagline = $settings['site_tagline'] ?? 'Blog personal con PHP y SQLite';
$posts = getPublishedPosts();

// Determinar URL base
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = $protocol . '://' . $host . dirname($_SERVER['SCRIPT_NAME'] ?? '');
$base = rtrim($base, '/\\');

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
            <description><![CDATA[<?php echo substr(strip_tags($post['content']), 0, 300); ?>]]></description>
        </item>
<?php endforeach; ?>
    </channel>
</rss>
