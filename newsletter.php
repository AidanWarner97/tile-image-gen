<?php
declare(strict_types=1);

define('FEEDBACK_LIBRARY_ONLY', true);
require_once __DIR__ . '/feedback.php';
require_once __DIR__ . '/includes/newsletter.php';

header('Cache-Control: private, no-store');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

$token = feedback_token();
$email = trim((string)($_POST['email'] ?? ''));
$error = '';
if (!hash_equals($token, (string)($_POST['csrf_token'] ?? ''))) {
    $error = 'This signup session has expired. Please try again.';
} elseif (trim((string)($_POST['website'] ?? '')) !== '') {
    $error = 'Unable to save your signup.';
} elseif (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please enter a valid email address.';
} else {
    try {
        newsletter_set_preference(newsletter_db(), $email, true);
    } catch (Throwable $exception) {
        error_log('Newsletter signup failed: ' . $exception->getMessage());
        $error = 'Your signup could not be saved. Please try again later.';
    }
}

$_SESSION['newsletter_signup_result'] = [
    'error' => $error !== '',
    'message' => $error !== '' ? $error : 'Thank you. Your newsletter signup has been saved.',
];
header('Location: /#newsletter', true, 303);
exit;