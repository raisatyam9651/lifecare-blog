<?php
// Standalone Blog Index
if (!function_exists('base_url')) {
    function base_url($path = '')
    {
        return '/' . ltrim($path, '/');
    }
}

// Read all php files to find blogs
$blog_files = glob(__DIR__ . '/*.php');

$blogs = [];

foreach ($blog_files as $file) {
    $file_name = basename($file);
    if ($file_name === 'index.php' || $file_name === 'scan_images.php')
        continue;

    if (is_file($file)) {
        // Optimized: Only read the first 8 KB to find metadata (much faster than full file)
        $handle = fopen($file, 'r');
        $content = fread($handle, 8192);
        fclose($handle);

        $slug = str_replace('.php', '', $file_name);

        $blog = [
            'url' => $slug,   // clean URL without .php
            'title' => ucwords(str_replace('-', ' ', $slug)),
            'description' => '',
            'date' => filemtime($file)
        ];

        // Try to parse the SEO/Schema block for data
        if (preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $content, $matches)) {
            $json_str = trim($matches[1]);
            $schema = json_decode($json_str, true);
            if ($schema && isset($schema['@type']) && $schema['@type'] == 'BlogPosting') {
                if (!empty($schema['headline']))
                    $blog['title'] = $schema['headline'];
                if (!empty($schema['description']))
                    $blog['description'] = $schema['description'];
                if (!empty($schema['datePublished']))
                    $blog['date'] = strtotime($schema['datePublished']);
            }
        }

        // Fallback title from <title> if still empty/basic
        if (empty($blog['description']) && preg_match('/<title>(.*?)<\/title>/i', $content, $m)) {
            $blog['title'] = str_replace(['| LifeCare Hospitals', '- LifeCare Hospitals'], '', $m[1]);
        }

        // Fallback descripton
        if (empty($blog['description']) && preg_match('/<meta\s+name="description"\s+content="(.*?)"/i', $content, $m)) {
            $blog['description'] = $m[1];
        }

        // Try to get category from a comment or meta tag, default to BLOG
        $blog['category'] = 'BLOG';
        if (preg_match('/<meta\s+name="category"\s+content="(.*?)"/i', $content, $m)) {
            $blog['category'] = strtoupper(trim($m[1]));
        }

        $blogs[] = (object) $blog;
    }
}

// Sort from newest to oldest
usort($blogs, function ($a, $b) {
    return $b->date - $a->date;
});

// Category Filter
$filter_category = isset($_GET['category']) ? strtoupper($_GET['category']) : null;
if ($filter_category) {
    $blogs = array_filter($blogs, function ($b) use ($filter_category) {
        return strtoupper($b->category) === $filter_category;
    });
}

// Pagination Logic
$limit = 12;
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$total_items = count($blogs);
$total_pages = ceil($total_items / $limit);
$offset = ($page - 1) * $limit;
$paginated_blogs = array_slice($blogs, $offset, $limit);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="keywords" content="Explore the latest and greatest insights from LifeCare Hospital's blog!" />
    <meta name="description"
        content="Explore the latest tips, updates, and expert medical advice from LifeCare Hospitals." />
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Blog - LifeCare Hospitals</title>

    <?php include(__DIR__ . '/../application/views/header.php'); ?>

    <style>
        .blog-page-header {
            background-color: #0c6574;
            color: white;
            padding: 60px 0 40px;
            text-align: center;
        }

        .blog-page-header h1 {
            color: #fff;
            font-weight: 700;
            font-size: 42px;
        }

        .blog-page-header h1 span {
            color: #00b2e3;
            border-bottom: 4px solid #00b2e3;
            padding-bottom: 2px;
        }

        .karma-blog-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }

        @media (max-width: 991px) {
            .karma-blog-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 767px) {
            .karma-blog-grid {
                grid-template-columns: 1fr;
            }
        }

        .karma-blog-card {
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0.05rem 0.1rem 0.3rem -0.03rem rgba(0, 0, 0, 0.45);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .karma-blog-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .karma-blog-image {
            position: relative;
            width: 100%;
            height: 260px;
            overflow: hidden;
            background: #eee;
            border-radius: 8px 8px 0 0;
        }

        .karma-blog-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .karma-blog-card:hover .karma-blog-image img {
            transform: scale(1.05);
        }

        .karma-category-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: #f39c12;
            color: #fff;
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
            padding: 5px 12px;
            border-radius: 20px;
            z-index: 10;
        }

        /* Category Navbar styling */
        .navbar-container {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 30px;
            text-align: center;
        }

        .menu-items {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .menu-items li a {
            background: #fff;
            color: #333;
            padding: 8px 20px;
            border-radius: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .menu-items li a:hover {
            background: #00b2e3;
            color: white;
            box-shadow: 0 4px 8px rgba(0, 178, 227, 0.3);
        }

        .karma-blog-content {
            padding: 24px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .karma-blog-meta {
            font-size: 13px;
            color: #888;
            text-transform: uppercase;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .karma-blog-title {
            font-size: 20px;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 12px;
        }

        .karma-blog-title a {
            color: #333;
            text-decoration: none;
        }

        .karma-blog-card:hover .karma-blog-title a {
            color: #00b2e3;
        }

        .karma-blog-desc {
            color: #555;
            margin-bottom: 20px;
            font-size: 15px;
            flex-grow: 1;
        }

        .karma-read-more {
            font-weight: bold;
            color: #00b2e3;
            text-transform: uppercase;
            font-size: 14px;
            text-decoration: none !important;
        }

        .karma-read-more:hover {
            color: #0c6574;
        }

        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 40px;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .pagination-container a,
        .pagination-container span {
            display: inline-block;
            padding: 8px 16px;
            background: #fff;
            color: #333;
            border-radius: 4px;
            text-decoration: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .pagination-container a:hover {
            background: #00b2e3;
            color: #fff;
        }

        .pagination-container .active {
            background: #0c6574;
            color: #fff;
        }
    </style>
</head>

<body>
    <div class="site-main">
        <section class="blog-page-header">
            <div class="container">
                <h1>Our Blog & <span>Insights</span></h1>
                <p class="mt-3" style="color: #e0e0e0; font-size: 18px;">Explore the latest tips, updates, and expert
                    medical advice.</p>
            </div>
        </section>

        <section class="py-5" style="background-color: #fcfcfc;">
            <div class="container">
                <!-- Categories Navigation -->
                <div class="navbar-container">
                    <ul class="menu-items">
                        <li><a href="/blog/">All</a></li>
                        <li><a href="/blog/?category=General">General</a></li>
                        <li><a href="/blog/?category=Neurology">Neurology</a></li>
                        <li><a href="/blog/?category=Orthopedics">Orthopedics</a></li>
                        <li><a href="/blog/?category=Gastroenterology">Gastroenterology</a></li>
                    </ul>
                </div>
                <div class="karma-blog-grid">
                    <?php if (!empty($paginated_blogs)): ?>
                        <?php foreach ($paginated_blogs as $value): ?>
                            <article class="karma-blog-card">
                                <div class="karma-blog-content">
                                    <div class="mb-2">
                                        <span class="karma-category-badge" style="position: static; display: inline-block; background: #00b2e3; color: #fff; padding: 4px 12px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                                            <?php echo htmlspecialchars($value->category); ?>
                                        </span>
                                    </div>
                                    <div class="karma-blog-meta">
                                        <i class="fa fa-calendar"></i> <?php echo strtoupper(date('F j, Y', $value->date)); ?>
                                        &bull; ADMINKARMA
                                    </div>
                                    <h3 class="karma-blog-title">
                                        <a
                                            href="<?php echo $value->url; ?>"><?php echo htmlspecialchars(trim($value->title)); ?></a>
                                    </h3>
                                    <div class="karma-blog-desc">
                                        <p><?php echo substr(strip_tags($value->description), 0, 110); ?>...</p>
                                    </div>
                                    <div style="margin-top: auto;">
                                        <a href="<?php echo $value->url; ?>" class="karma-read-more">READ MORE <i
                                                class="fa fa-arrow-right"></i></a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-4">
                            <p>No blog posts found.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination UI -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $filter_category ? '&category=' . urlencode($_GET['category']) : ''; ?>">&laquo; Prev</a>
                        <?php endif; ?>

                        <?php
                        // Calculate range for pagination to show at most 5 page numbers
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $start_page + 4);
                        if ($end_page - $start_page < 4) {
                            $start_page = max(1, $end_page - 4);
                        }
                        ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo $filter_category ? '&category=' . urlencode($_GET['category']) : ''; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $filter_category ? '&category=' . urlencode($_GET['category']) : ''; ?>">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <?php include(__DIR__ . '/../application/views/footer.php'); ?>
</body>

</html>