<?php
// sitemap.php - Sitemap XML automatico para el CMS
include_once 'config.php';
include_once 'db/functions.php';

createTable();

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = $protocol . '://' . $host . dirname($_SERVER['SCRIPT_NAME'] ?? '');
$base = rtrim($base, '/\\');

$posts = getPublishedPosts();

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?php echo htmlspecialchars($base . '/'); ?></loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
<?php foreach ($posts as $post): ?>
    <url>
        <loc><?php echo htmlspecialchars($base . postUrl($post['slug'] ?? $post['id'])); ?></loc>
        <lastmod><?php echo date('Y-m-d', strtotime($post['updated_at'] ?? $post['created_at'])); ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
<?php endforeach; ?>
</urlset>
