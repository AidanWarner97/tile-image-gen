<?php
declare(strict_types=1);

define('FEEDBACK_LIBRARY_ONLY', true);
require_once __DIR__ . '/../feedback.php';

$publicId = (int)($_GET['id'] ?? 0);
$stmt = feedback_db()->prepare('SELECT public_id, first_name, last_name, email, subject, message, created_at, status FROM feedback WHERE public_id = :public_id LIMIT 1');
$stmt->execute([':public_id' => $publicId]);
$entry = $stmt->fetch();

if (!is_array($entry)) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $entry ? feedback_escape((string)$entry['subject']) : 'Feedback Not Found' ?> | Tile Image Generator</title>
  <link rel="stylesheet" href="/static/style.css">
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
    <h1>FEEDBACK</h1>
    <p>Community feedback and discussion.</p>
  </header>
  <nav class="top-nav" aria-label="Main navigation">
    <a href="/#generator">Generator</a>
    <a href="/updates/">Updates</a>
    <a href="/feedback.php">Feedback</a>
  </nav>
  <main class="sections-wrap">
    <section class="content-section">
      <div class="section-title"><h2>DETAILS</h2></div>
      <article class="section-content feedback-detail-page">
        <?php if ($entry): ?>
          <h2><?= feedback_escape((string)$entry['subject']) ?></h2>
          <hr>
          <p class="feedback-meta"><?= feedback_escape(date('j M Y', strtotime((string)$entry['created_at']))) ?> | <?= feedback_escape((string)$entry['first_name']) ?> | <span class="feedback-status feedback-status-<?= feedback_escape((string)$entry['status']) ?>"><?= feedback_escape(feedback_status_label((string)$entry['status'])) ?></span></p>
          <hr>
          <p class="feedback-detail-message"><?= nl2br(feedback_escape((string)$entry['message'])) ?></p>
        <?php else: ?>
          <h2>Feedback Not Found</h2>
          <p>This feedback entry is not public or no longer exists.</p>
        <?php endif; ?>
      </article>
    </section>
  </main>
  <footer class="site-footer"><p>&copy; <?= date('Y') ?> Aidan Warner. All rights reserved.</p></footer>
</body>
</html>