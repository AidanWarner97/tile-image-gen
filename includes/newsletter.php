<?php
declare(strict_types=1);

function newsletter_db(): PDO
{
    $db = feedback_db();
    $db->exec('CREATE TABLE IF NOT EXISTS tig_newsletter_subscribers (
        email VARCHAR(254) PRIMARY KEY,
        subscribed INTEGER NOT NULL DEFAULT 0,
        consent_at DATETIME NULL,
        updated_at DATETIME NOT NULL
    )');
    return $db;
}

function newsletter_subscribed(PDO $db, string $email): bool
{
    $stmt = $db->prepare('SELECT subscribed FROM tig_newsletter_subscribers WHERE email = :email');
    $stmt->execute([':email' => strtolower(trim($email))]);
    return (int)$stmt->fetchColumn() === 1;
}

function newsletter_set_preference(PDO $db, string $email, bool $subscribed): void
{
    $email = strtolower(trim($email));
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Please enter a valid email address.');
    }
    $sql = feedback_is_mysql()
        ? 'INSERT INTO tig_newsletter_subscribers (email, subscribed, consent_at, updated_at) VALUES (:email, :subscribed, :consent_at, :updated_at) ON DUPLICATE KEY UPDATE subscribed = VALUES(subscribed), consent_at = VALUES(consent_at), updated_at = VALUES(updated_at)'
        : 'INSERT INTO tig_newsletter_subscribers (email, subscribed, consent_at, updated_at) VALUES (:email, :subscribed, :consent_at, :updated_at) ON CONFLICT(email) DO UPDATE SET subscribed = excluded.subscribed, consent_at = excluded.consent_at, updated_at = excluded.updated_at';
    $now = feedback_timestamp();
    $db->prepare($sql)->execute([
        ':email' => $email,
        ':subscribed' => $subscribed ? 1 : 0,
        ':consent_at' => $subscribed ? $now : null,
        ':updated_at' => $now,
    ]);
}