<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/updates.php';

$slug = (string)($_GET['slug'] ?? '');
if ($slug === '') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (preg_match('#/updates/post/([^/]+)#', $path, $m)) {
        $slug = rawurldecode($m[1]);
    }
}

$post = $slug !== '' ? get_post_by_slug($slug) : null;

if ($post === null) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $post ? escape_html($post['title']) : 'Update Not Found' ?> | Tile Image Generator</title>
  <link rel="stylesheet" href="/static/style.css">
  <link rel="icon" type="image/x-icon" href="/logo.png">
  <!-- Analytics -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-SJ4XFG0ZS9"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-SJ4XFG0ZS9');
  </script>
</head>
<body>
  <header class="hero-header">
    <img src="logo.png" alt="Tile Image Generator Logo" height="100" />
    <div id="header-title">
      <h1>TILE IMAGE GENERATOR</h1>
      <p>Production layout tool</p>
    </div>
  </header>
  <nav class="top-nav" aria-label="Main navigation">
    <a href="/">Generator</a>
    <a href="/updates/">All Updates</a>
    <a href="/feedback">Feedback</a>
  </nav>
  <main class="sections-wrap">
    <section class="content-section">
      <div class="section-title"><h2>UPDATE</h2></div>
      <article class="section-content update-detail">
        <?php if ($post): ?>
          <h2><?= escape_html($post['title']) ?></h2>
          <hr>
          <p class="update-meta"><?= escape_html($post['date']) ?> | <?= escape_html($post['author']) ?></p>
          <hr>
          <div class="update-body">
            <?= $post['content_html'] ?>
          </div>
        <?php else: ?>
          <h2>Update Not Found</h2>
          <p>The requested update could not be found.</p>
          <p><a href="/updates/">Back to all updates</a></p>
        <?php endif; ?>
      </article>
    </section>
    <!-- Advertisement -->
    <div id="ads" class="sidebar-ads">
      <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8424385314773719" crossorigin="anonymous"></script>
      <ins class="adsbygoogle"
            style="display:block"
            data-ad-format="fluid"
            data-ad-layout-key="-gw-3+1f-3d+2z"
            data-ad-client="ca-pub-8424385314773719"
            data-ad-slot="8630400101"></ins>
      <script>
        (adsbygoogle = window.adsbygoogle || []).push({});
      </script>
    </div>
    <!-- Advertisement -->
    <div id="ads" class="sidebar-ads">
      <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-8424385314773719" crossorigin="anonymous"></script>
      <ins class="adsbygoogle"
            style="display:block"
            data-ad-format="fluid"
            data-ad-layout-key="-gw-3+1f-3d+2z"
            data-ad-client="ca-pub-8424385314773719"
            data-ad-slot="8630400101"></ins>
      <script>
        (adsbygoogle = window.adsbygoogle || []).push({});
      </script>
    </div>
  </main>

  <footer class="site-footer">
    <p>&copy; <span id="year"></span> Aidan Warner. All rights reserved.</p>
  </footer>

  <script>
    document.getElementById("year").textContent = String(new Date().getFullYear());
  </script>
</body>
</html>
