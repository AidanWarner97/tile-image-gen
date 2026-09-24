<?php
declare(strict_types=1);

define('FEEDBACK_LIBRARY_ONLY', true);
require_once __DIR__ . '/../feedback.php';

$publicId = (int)($_GET['id'] ?? 0);
$db = feedback_db();
$currentUser = auth_user();
$commentError = '';
$stmt = $db->prepare('SELECT id, public_id, first_name, last_name, email, subject, message, created_at, status FROM ' . FEEDBACK_TABLE . ' WHERE public_id = :public_id LIMIT 1');
$stmt->execute([':public_id' => $publicId]);
$entry = $stmt->fetch();
$responses = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_array($entry)) {
  $body = trim((string)($_POST['comment'] ?? ''));
  if (!$currentUser) {
    $commentError = 'Please sign in with Google before posting a comment.';
  } elseif (!hash_equals(feedback_token(), (string)($_POST['csrf_token'] ?? ''))) {
    $commentError = 'This form session has expired. Please try again.';
  } elseif ($body === '' || strlen($body) > 5000) {
    $commentError = 'Please enter a comment under 5,000 characters.';
  } else {
    feedback_sync_user($db, $currentUser);
    $responseStmt = $db->prepare('INSERT INTO ' . FEEDBACK_RESPONSES_TABLE . ' (feedback_id, user_id, body, author, created_at) VALUES (:feedback_id, :user_id, :body, :author, :created_at)');
    $responseStmt->execute([
      ':feedback_id' => (string)$entry['id'],
      ':user_id' => (string)$currentUser['sub'],
      ':body' => $body,
      ':author' => (string)$currentUser['name'],
      ':created_at' => feedback_timestamp(),
    ]);
    header('Location: /feedback/' . $publicId . '#comments-heading');
    exit;
  }
}

if (is_array($entry)) {
  $responseStmt = $db->prepare('SELECT body, author, created_at FROM ' . FEEDBACK_RESPONSES_TABLE . ' WHERE feedback_id = :feedback_id ORDER BY created_at ASC, id ASC');
  $responseStmt->execute([':feedback_id' => $entry['id']]);
  $responses = $responseStmt->fetchAll();
}

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
    <a href="/updates/">All Updates</a>
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
          <section class="feedback-response-thread" aria-labelledby="comments-heading">
            <div class="feedback-response-heading"><h3 id="comments-heading">Public comments</h3><span><?= count($responses) ?></span></div>
            <?php if (!$responses): ?><p class="feedback-response-empty">No public comments yet.</p><?php endif; ?>
            <?php foreach ($responses as $response): ?><article class="feedback-response"><div class="feedback-response-author"><strong><?= feedback_escape((string)$response['author']) ?></strong><span>End-User</span></div><p><?= nl2br(feedback_escape((string)$response['body'])) ?></p><time datetime="<?= feedback_escape(date('c', strtotime((string)$response['created_at']))) ?>"><?= feedback_escape(date('j M Y', strtotime((string)$response['created_at']))) ?></time></article><?php endforeach; ?>
            <?php if ($commentError): ?><p class="feedback-error"><?= feedback_escape($commentError) ?></p><?php endif; ?>
            <?php if ($currentUser): ?>
              <form method="post" class="feedback-comment-form">
                <input type="hidden" name="csrf_token" value="<?= feedback_escape(feedback_token()) ?>">
                <label>Add a comment <textarea name="comment" rows="4" maxlength="5000" required></textarea></label>
                <button type="submit" class="feedback-submit-button">Post comment</button>
              </form>
            <?php elseif (auth_google_configured()): ?>
              <a class="feedback-login-button" href="<?= feedback_escape(auth_login_url('/feedback/' . $publicId)) ?>">Sign in with Google to comment</a>
            <?php endif; ?>
          </section>
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