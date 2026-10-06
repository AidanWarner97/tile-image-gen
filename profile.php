<?php
declare(strict_types=1);

define('FEEDBACK_LIBRARY_ONLY', true);
require_once __DIR__ . '/feedback.php';
require_once __DIR__ . '/includes/newsletter.php';

$currentUser = auth_user();
header('Cache-Control: private, no-store');
if (!$currentUser) {
  if (!auth_google_configured()) {
    http_response_code(503);
    exit('Google sign-in is not configured.');
  }
  header('Location: /auth/google?return=%2Fprofile', true, 302);
  exit;
}
$csrfToken = feedback_token();
$error = '';
$email = '';
$subscribed = false;
$notice = (string)($_SESSION['newsletter_notice'] ?? '');
unset($_SESSION['newsletter_notice']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $email = trim((string)($_POST['email'] ?? ''));
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        $error = 'This form session has expired. Please try again.';
    } elseif ($action !== 'preferences' || trim((string)($_POST['website'] ?? '')) !== '') {
        http_response_code(400);
        $error = 'Unable to save your request.';
    } else {
        if ($action === 'preferences') {
            $email = (string)$currentUser['email'];
        }
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                $db = newsletter_db();
                newsletter_set_preference($db, $email, isset($_POST['marketing_email']));
                $_SESSION['newsletter_notice'] = 'Your marketing preferences have been saved.';
                header('Location: /profile', true, 303);
                exit;
            } catch (Throwable $exception) {
                error_log('Newsletter preference save failed: ' . $exception->getMessage());
                $error = 'Your preferences could not be saved. Please try again later.';
            }
        }
    }
}

if ($currentUser) {
    try {
        $db = newsletter_db();
        feedback_sync_user($db, $currentUser);
        $subscribed = newsletter_subscribed($db, (string)$currentUser['email']);
    } catch (Throwable $exception) {
        error_log('Profile load failed: ' . $exception->getMessage());
        $error = 'Your account preferences are temporarily unavailable.';
    }
}
$picture = (string)($currentUser['picture'] ?? '');
$showPicture = filter_var($picture, FILTER_VALIDATE_URL) && parse_url($picture, PHP_URL_SCHEME) === 'https';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Profile | Tile Image Generator</title>
  <link rel="icon" href="/logo.png">
  <link rel="stylesheet" href="/static/style.css">
</head>
<body>
  <header class="hero-header"><img src="/logo.png" alt="Tile Image Generator Logo" height="100"><div><h1>TILE IMAGE GENERATOR</h1><p>Production layout tool</p></div></header>
  <nav class="top-nav" aria-label="Main navigation"><a href="/">Generator</a><a href="/updates">All Updates</a><a href="/feedback">Feedback</a><a href="/profile" aria-current="page">Profile</a></nav>
  <main class="sections-wrap">
    <section class="content-section">
      <div class="section-title"><h2>PROFILE</h2></div>
      <div class="section-content">
        <?php if ($notice): ?><p class="feedback-success" role="status"><?= feedback_escape($notice) ?></p><?php endif; ?>
        <?php if ($error): ?><p class="feedback-error" role="alert"><?= feedback_escape($error) ?></p><?php endif; ?>
        <?php if ($currentUser): ?>
          <div class="profile-identity">
            <?php if ($showPicture): ?><img class="profile-avatar" src="<?= feedback_escape($picture) ?>" alt="Profile picture" referrerpolicy="no-referrer"><?php else: ?><div class="profile-avatar profile-avatar-fallback" aria-label="No profile picture">?</div><?php endif; ?>
            <div><h2><?= feedback_escape((string)$currentUser['name']) ?></h2><p><?= feedback_escape((string)$currentUser['email']) ?></p><a href="/auth/logout">Sign out</a></div>
          </div>
          <form method="post" action="/profile" class="profile-preferences">
            <input type="hidden" name="csrf_token" value="<?= feedback_escape($csrfToken) ?>">
            <input type="hidden" name="action" value="preferences">
            <h3>Marketing preferences</h3>
            <label class="checkbox-label"><input type="checkbox" name="marketing_email" value="1" <?= $subscribed ? 'checked' : '' ?> <?= $error ? 'disabled' : '' ?>>Email me marketing news and updates from Tile Image Generator</label>
            <button type="submit" class="feedback-submit-button" <?= $error ? 'disabled' : '' ?>>Save preferences</button>
          </form>
        <?php endif; ?>
      </div>
    </section>
  </main>
  <footer class="site-footer"><p>&copy; <?= date('Y') ?> Aidan Warner. All rights reserved.</p></footer>
</body>
</html>