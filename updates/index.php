<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/updates.php';

$posts = get_all_posts();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Updates | Tile Image Generator</title>
  <link rel="stylesheet" href="/static/style.css">
  <link rel="icon" type="image/x-icon" href="/logo_transparent.png">
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
    <h1>Updates | Tile Image Generator</h1>
    <p>Product notes and release updates.</p>
  </header>
  <nav class="top-nav" aria-label="Main navigation">
    <a href="/">Generator</a>
    <a href="/updates/">All Updates</a>
    <a href="/feedback">Feedback</a>
  </nav>
  <main class="sections-wrap">
    <section class="content-section">
      <div class="section-title"><h2>POSTS</h2></div>
      <div class="section-content">
        <?php if (empty($posts)): ?>
          <p>No updates found in the posts directory.</p>
        <?php else: ?>
          <?php foreach ($posts as $post): ?>
            <article class="update-post">
              <h3><a href="/updates/post/<?= rawurlencode($post['slug']) ?>"><?= escape_html($post['title']) ?></a></h3>
              <p class="update-meta"><?= escape_html($post['date']) ?> | <?= escape_html($post['author']) ?></p>
              <p><?= escape_html($post['excerpt']) ?></p>
            </article>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <p>&copy; <span id="year"></span> Aidan Warner. All rights reserved.</p>
  </footer>

  <script>
    document.getElementById("year").textContent = String(new Date().getFullYear());
  </script>
</body>
</html>
