<?php

declare(strict_types=1);

const FEEDBACK_DIR = __DIR__ . '/feedback-data';
const FEEDBACK_DB = FEEDBACK_DIR . '/feedback.sqlite';
const LEGACY_FEEDBACK_FILE = FEEDBACK_DIR . '/feedback.json';
const FEEDBACK_ATTACHMENT_DIR = FEEDBACK_DIR . '/attachments';
const FEEDBACK_MAX_ATTACHMENT_SIZE = 10 * 1024 * 1024;
const FEEDBACK_MAX_ATTACHMENTS = 5;
const FEEDBACK_TABLE = 'tig_feedback';
const FEEDBACK_ATTACHMENTS_TABLE = 'tig_feedback_attachments';
const FEEDBACK_RESPONSES_TABLE = 'tig_feedback_responses';

function feedback_status_options(): array
{
    return [
        'new' => 'New',
        'open' => 'Open',
        'investigating' => 'Investigating',
        'building' => 'Building',
        'fixed' => 'Fixed',
        'added' => 'Added',
        'wont_fix' => "Closed, won't fix",
        'wont_add' => "Closed, won't add",
    ];
}

function feedback_default_statuses(): array
{
    return ['new', 'open', 'investigating', 'building'];
}

function feedback_status_label(string $status): string
{
    return feedback_status_options()[$status] ?? $status;
}

function feedback_load_environment(): void
{
  $envFile = __DIR__ . '/.env';
  if (!is_file($envFile)) {
    return;
  }

  $values = parse_ini_file($envFile, false, INI_SCANNER_RAW);
  if (!is_array($values)) {
    return;
  }

  foreach ($values as $key => $value) {
    if (getenv((string)$key) === false) {
      putenv((string)$key . '=' . (string)$value);
    }
  }
}

feedback_load_environment();

function feedback_is_mysql(): bool
{
    $dsn = getenv('DB_DSN') ?: getenv('MYSQL_DSN');
    if ($dsn !== false && $dsn !== '') {
        return true;
    }

    return getenv('DB_HOST') !== false || getenv('DB_DATABASE') !== false || getenv('DB_NAME') !== false;
}

function feedback_db_options(): array
{
    return [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
}

  function feedback_table_exists(PDO $db, string $table): bool
  {
    if (feedback_is_mysql()) {
      $stmt = $db->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name');
      $stmt->execute([':table_name' => $table]);
      return (int)$stmt->fetchColumn() > 0;
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = :table_name");
    $stmt->execute([':table_name' => $table]);
    return (int)$stmt->fetchColumn() > 0;
  }

  function feedback_migrate_table_names(PDO $db): void
  {
    foreach ([
      'feedback' => FEEDBACK_TABLE,
      'feedback_attachments' => FEEDBACK_ATTACHMENTS_TABLE,
    ] as $oldTable => $newTable) {
      if (feedback_table_exists($db, $oldTable) && !feedback_table_exists($db, $newTable)) {
        if (feedback_is_mysql()) {
          $db->exec('RENAME TABLE ' . $oldTable . ' TO ' . $newTable);
        } else {
          $db->exec('ALTER TABLE ' . $oldTable . ' RENAME TO ' . $newTable);
        }
      }
    }
  }

function feedback_db(): PDO
{
    static $db = null;
    if ($db instanceof PDO) {
        return $db;
    }

    if (feedback_is_mysql()) {
        $dsn = getenv('DB_DSN') ?: getenv('MYSQL_DSN');
        if ($dsn === false || $dsn === '') {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $dbname = getenv('DB_NAME') ?: getenv('DB_DATABASE') ?: 'tile_image_gen';
            $charset = getenv('DB_CHARSET') ?: 'utf8mb4';
            $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbname . ';charset=' . $charset;
        }

        $user = getenv('DB_USER') ?: getenv('DB_USERNAME') ?: 'root';
        $pass = getenv('DB_PASSWORD') ?: getenv('DB_PASS') ?: '';
        $db = new PDO($dsn, $user, $pass, feedback_db_options());
    } else {
        if (!is_dir(FEEDBACK_DIR)) {
            mkdir(FEEDBACK_DIR, 0770, true);
        }

        $db = new PDO('sqlite:' . FEEDBACK_DB, null, null, feedback_db_options());
    }

      feedback_migrate_table_names($db);

    if (feedback_is_mysql()) {
        $db->exec('CREATE TABLE IF NOT EXISTS ' . FEEDBACK_TABLE . ' (
            id CHAR(64) PRIMARY KEY,
            public_id INT NULL UNIQUE,
            name VARCHAR(255) NOT NULL DEFAULT "",
            first_name VARCHAR(80) NOT NULL DEFAULT "",
            last_name VARCHAR(80) NOT NULL DEFAULT "",
            email VARCHAR(255) NOT NULL,
            subject VARCHAR(180) NOT NULL,
            feedback_category VARCHAR(20) NOT NULL DEFAULT "other",
            feedback_type VARCHAR(20) NOT NULL DEFAULT "general",
            message TEXT NOT NULL,
            contact_allowed TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT "pending",
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    } else {
        $db->exec('CREATE TABLE IF NOT EXISTS ' . FEEDBACK_TABLE . ' (
            id TEXT PRIMARY KEY,
            public_id INTEGER,
            name TEXT NOT NULL DEFAULT "",
            first_name TEXT NOT NULL DEFAULT "",
            last_name TEXT NOT NULL DEFAULT "",
            email TEXT NOT NULL,
            subject TEXT NOT NULL,
            feedback_category TEXT NOT NULL DEFAULT "other",
            feedback_type TEXT NOT NULL DEFAULT "general",
            message TEXT NOT NULL,
            contact_allowed INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT "pending",
            created_at TEXT NOT NULL
        )');
    }

      if (feedback_is_mysql()) {
        $db->exec('CREATE TABLE IF NOT EXISTS ' . FEEDBACK_ATTACHMENTS_TABLE . ' (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          feedback_id CHAR(64) NOT NULL,
          original_name VARCHAR(255) NOT NULL,
          stored_name VARCHAR(255) NOT NULL,
          mime_type VARCHAR(127) NOT NULL,
          file_size INT UNSIGNED NOT NULL,
          created_at DATETIME NOT NULL,
          INDEX tig_feedback_attachments_feedback_id (feedback_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
      } else {
        $db->exec('CREATE TABLE IF NOT EXISTS ' . FEEDBACK_ATTACHMENTS_TABLE . ' (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          feedback_id TEXT NOT NULL,
          original_name TEXT NOT NULL,
          stored_name TEXT NOT NULL,
          mime_type TEXT NOT NULL,
          file_size INTEGER NOT NULL,
          created_at TEXT NOT NULL
        )');
      }

      if (feedback_is_mysql()) {
        $db->exec('CREATE TABLE IF NOT EXISTS ' . FEEDBACK_RESPONSES_TABLE . ' (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          feedback_id CHAR(64) NOT NULL,
          body TEXT NOT NULL,
          author VARCHAR(100) NOT NULL,
          created_at DATETIME NOT NULL,
          emailed_at DATETIME NULL,
          email_error VARCHAR(500) NULL,
          INDEX tig_feedback_responses_feedback_id (feedback_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
      } else {
        $db->exec('CREATE TABLE IF NOT EXISTS ' . FEEDBACK_RESPONSES_TABLE . ' (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          feedback_id TEXT NOT NULL,
          body TEXT NOT NULL,
          author TEXT NOT NULL,
          created_at TEXT NOT NULL,
          emailed_at TEXT NULL,
          email_error TEXT NULL
        )');
        $db->exec('CREATE INDEX IF NOT EXISTS tig_feedback_responses_feedback_id ON ' . FEEDBACK_RESPONSES_TABLE . '(feedback_id)');
      }

    feedback_ensure_column($db, 'first_name', feedback_is_mysql() ? 'VARCHAR(80) NOT NULL DEFAULT ""' : 'TEXT NOT NULL DEFAULT ""');
    feedback_ensure_column($db, 'last_name', feedback_is_mysql() ? 'VARCHAR(80) NOT NULL DEFAULT ""' : 'TEXT NOT NULL DEFAULT ""');
    feedback_ensure_column($db, 'feedback_category', feedback_is_mysql() ? 'VARCHAR(20) NOT NULL DEFAULT "other"' : 'TEXT NOT NULL DEFAULT "other"');
    feedback_ensure_column($db, 'feedback_type', feedback_is_mysql() ? 'VARCHAR(20) NOT NULL DEFAULT "general"' : 'TEXT NOT NULL DEFAULT "general"');
    feedback_ensure_column($db, 'public_id', feedback_is_mysql() ? 'INT NULL UNIQUE' : 'INTEGER');
    feedback_migrate_names($db);
    feedback_migrate_statuses($db);
    feedback_backfill_public_ids($db);

    if (feedback_is_mysql()) {
        $indexExists = $db->query("SHOW INDEX FROM " . FEEDBACK_TABLE . " WHERE Key_name = 'tig_feedback_public_id_unique'")->fetch();
        if (!$indexExists) {
          $db->exec('CREATE UNIQUE INDEX tig_feedback_public_id_unique ON ' . FEEDBACK_TABLE . '(public_id)');
        }
    } else {
        $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS tig_feedback_public_id_unique ON ' . FEEDBACK_TABLE . '(public_id)');
    }

    feedback_migrate_legacy_json($db);
    return $db;
}

function feedback_ensure_column(PDO $db, string $column, string $definition): void
{
    $isMysql = feedback_is_mysql();
    if ($isMysql) {
      $sql = 'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name';
        $stmt = $db->prepare($sql);
      $stmt->execute([':table_name' => FEEDBACK_TABLE, ':column_name' => $column]);
        if ($stmt->fetch()) {
            return;
        }

        $db->exec('ALTER TABLE ' . FEEDBACK_TABLE . ' ADD COLUMN ' . $column . ' ' . $definition);
        return;
    }

    $columns = $db->query('PRAGMA table_info(' . FEEDBACK_TABLE . ')')->fetchAll();
    foreach ($columns as $row) {
        if (($row['name'] ?? '') === $column) {
            return;
        }
    }

    $db->exec('ALTER TABLE ' . FEEDBACK_TABLE . ' ADD COLUMN ' . $column . ' ' . $definition);
}

function feedback_migrate_names(PDO $db): void
{
  $rows = $db->query('SELECT id, name FROM ' . FEEDBACK_TABLE . ' WHERE first_name = "" AND name <> ""')->fetchAll();
  $stmt = $db->prepare('UPDATE ' . FEEDBACK_TABLE . ' SET first_name = :first_name, last_name = :last_name WHERE id = :id');
  foreach ($rows as $row) {
    $parts = preg_split('/\s+/', trim((string)$row['name']), 2) ?: [];
    $stmt->execute([
      ':first_name' => $parts[0] ?? '',
      ':last_name' => $parts[1] ?? '',
      ':id' => (string)$row['id'],
    ]);
  }
}

function feedback_migrate_statuses(PDO $db): void
{
    // legacy moderation flags predate the status workflow
    $db->exec('UPDATE ' . FEEDBACK_TABLE . ' SET status = \'new\' WHERE status = \'pending\'');
    $db->exec('UPDATE ' . FEEDBACK_TABLE . ' SET status = \'open\' WHERE status = \'approved\'');
}

function feedback_backfill_public_ids(PDO $db): void
{
  $rows = $db->query('SELECT id FROM ' . FEEDBACK_TABLE . ' WHERE public_id IS NULL ORDER BY created_at ASC, id ASC')->fetchAll();
  if (!$rows) {
    return;
  }

  $nextId = (int)$db->query('SELECT COALESCE(MAX(public_id), 0) + 1 FROM ' . FEEDBACK_TABLE)->fetchColumn();
  $stmt = $db->prepare('UPDATE ' . FEEDBACK_TABLE . ' SET public_id = :public_id WHERE id = :id');
  foreach ($rows as $row) {
    $stmt->execute([':public_id' => $nextId++, ':id' => (string)$row['id']]);
  }
}

function feedback_sql_is_null(PDO $db, string $column): bool
{
    if (feedback_is_mysql()) {
        $stmt = $db->query('SELECT COUNT(*) FROM ' . FEEDBACK_TABLE . ' WHERE ' . $column . ' IS NULL');
        return ((int)$stmt->fetchColumn()) > 0;
    }

    $stmt = $db->query('SELECT COUNT(*) FROM ' . FEEDBACK_TABLE . ' WHERE ' . $column . ' IS NULL');
    return ((int)$stmt->fetchColumn()) > 0;
}

function feedback_migrate_legacy_json(PDO $db): void
{
    static $migrated = false;
    if ($migrated || !is_file(LEGACY_FEEDBACK_FILE)) {
        $migrated = true;
        return;
    }

    $count = (int)$db->query('SELECT COUNT(*) FROM ' . FEEDBACK_TABLE)->fetchColumn();
    if ($count === 0) {
        $entries = json_decode((string)file_get_contents(LEGACY_FEEDBACK_FILE), true);
        if (is_array($entries)) {
          $insertSql = feedback_is_mysql()
            ? 'INSERT IGNORE INTO ' . FEEDBACK_TABLE . ' (id, name, email, subject, message, contact_allowed, status, created_at) VALUES (:id, :name, :email, :subject, :message, :contact_allowed, :status, :created_at)'
            : 'INSERT OR IGNORE INTO ' . FEEDBACK_TABLE . ' (id, name, email, subject, message, contact_allowed, status, created_at) VALUES (:id, :name, :email, :subject, :message, :contact_allowed, :status, :created_at)';
          $stmt = $db->prepare($insertSql);
            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $stmt->execute([
                    ':id' => (string)($entry['id'] ?? bin2hex(random_bytes(12))),
                    ':name' => (string)($entry['name'] ?? ''),
                    ':email' => (string)($entry['email'] ?? ''),
                    ':subject' => (string)($entry['subject'] ?? ''),
                    ':message' => (string)($entry['message'] ?? ''),
                    ':contact_allowed' => !empty($entry['contact_allowed']) ? 1 : 0,
                    ':status' => (string)($entry['status'] ?? 'pending'),
                    ':created_at' => feedback_timestamp(isset($entry['created_at']) ? (string)$entry['created_at'] : null),
                ]);
            }
        }
    }

    $migrated = true;
}

function feedback_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['feedback_token'])) {
        $_SESSION['feedback_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['feedback_token'];
}

function feedback_censor_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return implode(' ', array_map(static function (string $part): string {
        return strlen($part) <= 2 ? $part[0] . '*' : $part[0] . str_repeat('*', max(1, strlen($part) - 2)) . substr($part, -1);
    }, $parts));
}

function feedback_censor_email(string $email): string
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '';
    }

    [$local, $domain] = explode('@', $email, 2);
    return substr($local, 0, 1) . str_repeat('*', max(2, strlen($local) - 1)) . '@' . $domain;
}

function feedback_escape(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function feedback_timestamp(?string $value = null): string
{
  if ($value === null || trim($value) === '') {
    return gmdate('Y-m-d H:i:s');
  }

  try {
    return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  } catch (Exception) {
    return gmdate('Y-m-d H:i:s');
  }
}

function feedback_uploaded_files(): array
{
  $files = $_FILES['attachments'] ?? [];
  if (!is_array($files) || !isset($files['name']) || !is_array($files['name'])) {
    return [];
  }

  $uploads = [];
  foreach ($files['name'] as $index => $name) {
    $uploads[] = [
      'name' => (string)$name,
      'type' => (string)($files['type'][$index] ?? ''),
      'tmp_name' => (string)($files['tmp_name'][$index] ?? ''),
      'error' => (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
      'size' => (int)($files['size'][$index] ?? 0),
    ];
  }

  return $uploads;
}

function feedback_attachment_mime(string $path): string
{
  $finfo = new finfo(FILEINFO_MIME_TYPE);
  return (string)$finfo->file($path);
}

function feedback_attachment_allowed(string $mime): bool
{
  return in_array($mime, [
    'image/gif',
    'image/jpeg',
    'image/png',
    'image/webp',
    'application/pdf',
    'text/plain',
    'text/csv',
    'application/zip',
  ], true);
}

function feedback_turnstile_site_key(): string
{
  return trim((string)(getenv('TURNSTILE_SITE_KEY') ?: getenv('CLOUDFLARE_TURNSTILE_SITE_KEY') ?: ''));
}

function feedback_turnstile_secret_key(): string
{
  return trim((string)(getenv('TURNSTILE_SECRET_KEY') ?: getenv('CLOUDFLARE_TURNSTILE_SECRET_KEY') ?: ''));
}

function feedback_turnstile_enabled(): bool
{
  return feedback_turnstile_site_key() !== '' && feedback_turnstile_secret_key() !== '';
}

function feedback_verify_turnstile(string $token): bool
{
  if (!feedback_turnstile_enabled() || $token === '') {
    return !feedback_turnstile_enabled();
  }

  $payload = http_build_query([
    'secret' => feedback_turnstile_secret_key(),
    'response' => $token,
    'remoteip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
  ]);
  $context = stream_context_create([
    'http' => [
      'method' => 'POST',
      'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
      'content' => $payload,
      'timeout' => 5,
      'ignore_errors' => true,
    ],
  ]);
  $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
  if ($response === false) {
    return false;
  }

  $result = json_decode($response, true);
  return is_array($result) && ($result['success'] ?? false) === true;
}

if (defined('FEEDBACK_LIBRARY_ONLY')) {
  return;
}

$errors = [];
$success = false;
$old = ['first_name' => '', 'last_name' => '', 'email' => '', 'subject' => '', 'feedback_category' => 'other', 'feedback_type' => 'general', 'message' => ''];
$uploads = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $old['first_name'] = trim((string)($_POST['first_name'] ?? ''));
    $old['last_name'] = trim((string)($_POST['last_name'] ?? ''));
    $old['email'] = trim((string)($_POST['email'] ?? ''));
    $old['subject'] = trim((string)($_POST['subject'] ?? ''));
    $old['feedback_category'] = trim((string)($_POST['feedback_category'] ?? ''));
    $old['feedback_type'] = trim((string)($_POST['feedback_type'] ?? ''));
    $old['message'] = trim((string)($_POST['message'] ?? ''));
    $uploads = feedback_uploaded_files();

    if (!hash_equals(feedback_token(), (string)($_POST['csrf_token'] ?? ''))) {
        $errors[] = 'This form session has expired. Please try again.';
    }
    if (!feedback_verify_turnstile((string)($_POST['cf-turnstile-response'] ?? ''))) {
      $errors[] = 'Please complete the anti-spam check and try again.';
    }
    if (trim((string)($_POST['website'] ?? '')) !== '') {
        $errors[] = 'Unable to submit feedback.';
    }
    if ($old['first_name'] === '' || strlen($old['first_name']) > 80) {
        $errors[] = 'Please enter your first name.';
    }
    if ($old['last_name'] === '' || strlen($old['last_name']) > 80) {
        $errors[] = 'Please enter your last name.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($old['subject'] === '' || strlen($old['subject']) > 180) {
      $errors[] = 'Please enter a title.';
    }
    if (!in_array($old['feedback_category'], ['generation', 'layouts', 'grout_lines', 'other'], true)) {
      $errors[] = 'Please select a feedback category.';
    }
    if (!in_array($old['feedback_type'], ['general', 'bug', 'improvement'], true)) {
      $errors[] = 'Please select a feedback type.';
    }
    if ($old['message'] === '' || strlen($old['message']) > 10000) {
        $errors[] = 'Please enter feedback under 10,000 characters.';
    }
    $uploads = array_values(array_filter($uploads, static fn(array $upload): bool => $upload['error'] !== UPLOAD_ERR_NO_FILE));
    if (count($uploads) > FEEDBACK_MAX_ATTACHMENTS) {
      $errors[] = 'Please attach no more than ' . FEEDBACK_MAX_ATTACHMENTS . ' files.';
    }
    foreach ($uploads as $upload) {
      if ($upload['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'One of the attachments could not be uploaded.';
        continue;
      }
      if ($upload['size'] > FEEDBACK_MAX_ATTACHMENT_SIZE) {
        $errors[] = 'Each attachment must be 10 MB or smaller.';
        continue;
      }
      if (!is_uploaded_file($upload['tmp_name']) || !feedback_attachment_allowed(feedback_attachment_mime($upload['tmp_name']))) {
        $errors[] = 'Attachments must be images, PDFs, text files, CSV files, or ZIP files.';
      }
    }

    if (!$errors) {
      $db = feedback_db();
      $nextPublicId = (int)$db->query('SELECT COALESCE(MAX(public_id), 0) + 1 FROM ' . FEEDBACK_TABLE)->fetchColumn();
      $feedbackId = bin2hex(random_bytes(32));
      $stmt = $db->prepare('INSERT INTO ' . FEEDBACK_TABLE . ' (id, public_id, name, first_name, last_name, email, subject, feedback_category, feedback_type, message, contact_allowed, status, created_at) VALUES (:id, :public_id, :name, :first_name, :last_name, :email, :subject, :feedback_category, :feedback_type, :message, :contact_allowed, "new", :created_at)');
        $stmt->execute([
        ':id' => $feedbackId,
            ':public_id' => $nextPublicId,
            ':name' => trim($old['first_name'] . ' ' . $old['last_name']),
            ':first_name' => $old['first_name'],
            ':last_name' => $old['last_name'],
            ':email' => $old['email'],
            ':subject' => $old['subject'],
            ':feedback_category' => $old['feedback_category'],
            ':feedback_type' => $old['feedback_type'],
            ':message' => $old['message'],
            ':contact_allowed' => !empty($_POST['contact_allowed']) ? 1 : 0,
            ':created_at' => feedback_timestamp(),
        ]);
          if ($uploads) {
            if (!is_dir(FEEDBACK_ATTACHMENT_DIR)) {
              mkdir(FEEDBACK_ATTACHMENT_DIR, 0770, true);
            }
            $attachmentStmt = $db->prepare('INSERT INTO ' . FEEDBACK_ATTACHMENTS_TABLE . ' (feedback_id, original_name, stored_name, mime_type, file_size, created_at) VALUES (:feedback_id, :original_name, :stored_name, :mime_type, :file_size, :created_at)');
            foreach ($uploads as $upload) {
              $storedName = bin2hex(random_bytes(24));
              if (!move_uploaded_file($upload['tmp_name'], FEEDBACK_ATTACHMENT_DIR . '/' . $storedName)) {
                $errors[] = 'An attachment could not be saved. Please try again.';
                break;
              }
              $attachmentStmt->execute([
                ':feedback_id' => $feedbackId,
                ':original_name' => basename($upload['name']),
                ':stored_name' => $storedName,
                ':mime_type' => feedback_attachment_mime(FEEDBACK_ATTACHMENT_DIR . '/' . $storedName),
                ':file_size' => $upload['size'],
                ':created_at' => feedback_timestamp(),
              ]);
            }
          }
          if ($errors) {
            $db->prepare('DELETE FROM ' . FEEDBACK_TABLE . ' WHERE id = :id')->execute([':id' => $feedbackId]);
          }
        }
        if (!$errors) {
        $success = true;
        $old = ['first_name' => '', 'last_name' => '', 'email' => '', 'subject' => '', 'feedback_category' => 'other', 'feedback_type' => 'general', 'message' => ''];
    }
}

$statusOptions = feedback_status_options();
$defaultStatuses = feedback_default_statuses();
$statusParam = $_GET['status'] ?? '';
if (is_array($statusParam)) {
    $statusParam = implode(',', $statusParam);
}
$requestedStatuses = array_values(array_intersect(array_filter(array_map('trim', explode(',', (string)$statusParam))), array_keys($statusOptions)));
$activeStatuses = $requestedStatuses ?: $defaultStatuses;
$sortedActiveStatuses = $activeStatuses;
sort($sortedActiveStatuses);
$sortedDefaultStatuses = $defaultStatuses;
sort($sortedDefaultStatuses);
$isDefaultStatusSelection = $sortedActiveStatuses === $sortedDefaultStatuses;
$search = trim((string)($_GET['q'] ?? ''));

$listSql = 'SELECT feedback.public_id, feedback.first_name, feedback.last_name, feedback.email, feedback.subject, feedback.message, feedback.contact_allowed, feedback.status, feedback.created_at, (SELECT COUNT(*) FROM ' . FEEDBACK_RESPONSES_TABLE . ' response WHERE response.feedback_id = feedback.id) AS response_count FROM ' . FEEDBACK_TABLE . ' feedback WHERE feedback.status IN (' . implode(', ', array_map(static fn(int $i): string => ":status_$i", array_keys($activeStatuses))) . ')';
$listParams = [];
foreach ($activeStatuses as $i => $status) {
    $listParams[":status_$i"] = $status;
}
if ($search !== '') {
    $listSql .= ' AND (feedback.subject LIKE :search_subject OR feedback.message LIKE :search_message)';
    $listParams[':search_subject'] = '%' . $search . '%';
    $listParams[':search_message'] = '%' . $search . '%';
}
$listSql .= ' ORDER BY feedback.created_at DESC';
$listStmt = feedback_db()->prepare($listSql);
$listStmt->execute($listParams);
$feedback = $listStmt->fetchAll();
$csrfToken = feedback_token();
$turnstileSiteKey = feedback_turnstile_site_key();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Send feedback and browse selected feedback for Tile Image Generator.">
  <title>Feedback | Tile Image Generator</title>
  <link rel="icon" type="image/x-icon" href="/logo.png">
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
    <img src="/logo.png" alt="Tile Image Generator Logo" height="100" />
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
    <section class="content-section feedback-table-section">
      <div class="section-title"><h2>FEEDBACK</h2></div>
      <div class="section-content">
        <div class="feedback-toolbar">
          <p>Browse feedback from the community.</p>
          <button type="button" id="open-feedback-modal" class="feedback-submit-button">Submit Feedback</button>
        </div>
        <form method="get" action="/feedback" class="feedback-filter-form">
          <div class="feedback-search-row">
            <input type="search" name="q" value="<?= feedback_escape($search) ?>" placeholder="Search title and feedback content&hellip;" class="feedback-search-input">
            <button type="submit" class="feedback-search-button">Search</button>
          </div>
          <div class="feedback-table-wrap">
            <table class="feedback-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Issue</th>
                  <th>Comments</th>
                  <th>From</th>
                  <th class="feedback-status-header">
                    <details class="feedback-status-filter" data-default-statuses="<?= feedback_escape(implode(',', $defaultStatuses)) ?>">
                      <summary class="feedback-status-filter-button">Status</summary>
                      <div class="feedback-status-filter-panel">
                        <input type="hidden" name="status" value="<?= feedback_escape(implode(',', $activeStatuses)) ?>" <?= $isDefaultStatusSelection ? 'disabled' : '' ?> data-status-input>
                        <?php foreach ($statusOptions as $slug => $label): ?>
                          <label class="feedback-filter-chip">
                            <input type="checkbox" value="<?= feedback_escape($slug) ?>" <?= in_array($slug, $activeStatuses, true) ? 'checked' : '' ?> data-status-checkbox>
                            <span><?= feedback_escape($label) ?></span>
                          </label>
                        <?php endforeach; ?>
                      </div>
                    </details>
                  </th>
                </tr>
              </thead>
              <tbody>
              <?php if (!$feedback): ?>
                <tr><td colspan="5">No feedback matches the selected filters.</td></tr>
              <?php else: ?>
              <?php foreach ($feedback as $entry): ?>
                <tr>
                  <td><a class="feedback-id" href="/feedback/<?= (int)$entry['public_id'] ?>">#<?= (int)$entry['public_id'] ?></a></td>
                  <td>
                    <div class="feedback-issue-title"><?= feedback_escape((string)$entry['subject']) ?></div>
                    <div class="feedback-issue-meta"><?= feedback_escape(date('j M Y', strtotime((string)$entry['created_at']))) ?></div>
                  </td>
                  <td class="feedback-comments"><span class="feedback-comment-count" title="<?= (int)$entry['response_count'] ?> public comment<?= (int)$entry['response_count'] === 1 ? '' : 's' ?>"><span aria-hidden="true">&#128172;</span> <?= (int)$entry['response_count'] ?></span></td>
                  <td><?= feedback_escape((string)$entry['first_name']) ?></td>
                  <td><span class="feedback-status feedback-status-<?= feedback_escape((string)$entry['status']) ?>"><?= feedback_escape(feedback_status_label((string)$entry['status'])) ?></span></td>
                </tr>
              <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </form>
      </div>
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
  </main>

  <div id="feedback-modal" class="modal" hidden>
    <div class="modal-panel feedback-modal-panel" role="dialog" aria-modal="true" aria-labelledby="feedback-modal-title">
      <button type="button" id="close-feedback-modal" class="feedback-modal-close" aria-label="Close feedback form">&times;</button>
      <h2 id="feedback-modal-title">Submit Feedback</h2>
      <p>Feedback is reviewed before it appears publicly.</p>
      <?php if ($success): ?><div class="feedback-success">Thanks. Your feedback has been received for review.</div><?php endif; ?>
      <?php if ($errors): ?><div class="feedback-error"><ul><?php foreach ($errors as $error): ?><li><?= feedback_escape($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="feedback-form">
        <input type="hidden" name="csrf_token" value="<?= feedback_escape($csrfToken) ?>">
        <label class="feedback-trap" aria-hidden="true">Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        <div class="grid two">
          <label>First Name <input type="text" name="first_name" value="<?= feedback_escape($old['first_name']) ?>" maxlength="80" required></label>
          <label>Last Name <input type="text" name="last_name" value="<?= feedback_escape($old['last_name']) ?>" maxlength="80" required></label>
          <label class="feedback-form-full-width">Email <input type="email" name="email" value="<?= feedback_escape($old['email']) ?>" maxlength="254" required></label>
          <label class="feedback-form-full-width">Title <input type="text" name="subject" value="<?= feedback_escape($old['subject']) ?>" maxlength="180" required></label>
        </div>
        <div class="feedback-choice-group">
          <span class="feedback-choice-label">What is your feedback about?</span>
          <div class="feedback-choice-selects">
            <select name="feedback_category" required>
              <option value="generation" <?= $old['feedback_category'] === 'generation' ? 'selected' : '' ?>>Image Generation</option>
              <option value="layouts" <?= $old['feedback_category'] === 'layouts' ? 'selected' : '' ?>>Layouts</option>
              <option value="grout_lines" <?= $old['feedback_category'] === 'grout_lines' ? 'selected' : '' ?>>Grout Lines</option>
              <option value="other" <?= $old['feedback_category'] === 'other' ? 'selected' : '' ?>>Other</option>
            </select>
            <select name="feedback_type" required>
              <option value="general" <?= $old['feedback_type'] === 'general' ? 'selected' : '' ?>>General Feedback</option>
              <option value="bug" <?= $old['feedback_type'] === 'bug' ? 'selected' : '' ?>>Bug Report</option>
              <option value="improvement" <?= $old['feedback_type'] === 'improvement' ? 'selected' : '' ?>>Improvement Suggestion</option>
            </select>
          </div>
        </div>
        <label>Your feedback <textarea name="message" rows="8" maxlength="10000" required><?= feedback_escape($old['message']) ?></textarea></label>
        <label class="feedback-attachment-field">Attachments
          <input type="file" name="attachments[]" accept="image/*,.pdf,.txt,.csv,.zip" multiple>
          <span>Optional. Up to 5 files, 10 MB each.</span>
        </label>
        <?php if (feedback_turnstile_enabled()): ?>
          <div class="cf-turnstile" data-sitekey="<?= feedback_escape($turnstileSiteKey) ?>"></div>
        <?php endif; ?>
        <label class="checkbox-label"><input type="checkbox" name="contact_allowed" value="1"> I’m happy to be contacted about this feedback.</label>
        <button type="submit" class="feedback-submit-button">Send Feedback</button>
      </form>
    </div>
  </div>

  <footer class="site-footer"><p>&copy; <?= date('Y') ?> Aidan Warner. All rights reserved.</p></footer>
  <script>
    const feedbackModal = document.getElementById('feedback-modal');
    const openFeedbackModal = document.getElementById('open-feedback-modal');
    const closeFeedbackModal = document.getElementById('close-feedback-modal');
    openFeedbackModal.addEventListener('click', () => { feedbackModal.hidden = false; });
    closeFeedbackModal.addEventListener('click', () => { feedbackModal.hidden = true; });
    feedbackModal.addEventListener('click', (event) => { if (event.target === feedbackModal) feedbackModal.hidden = true; });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') feedbackModal.hidden = true; });
    <?php if ($success || $errors): ?>feedbackModal.hidden = false;<?php endif; ?>

    // tracks whichever status dropdown is currently in the DOM, since it gets replaced on every filter refresh
    let activeStatusFilter = null;
    document.addEventListener('click', (event) => {
      if (activeStatusFilter && activeStatusFilter.open && !activeStatusFilter.contains(event.target)) {
        activeStatusFilter.open = false;
      }
    });

    function initStatusFilter(scope) {
      const statusFilter = scope.querySelector('.feedback-status-filter');
      activeStatusFilter = statusFilter;
      if (!statusFilter) {
        return;
      }

      const statusTableWrap = scope.querySelector('.feedback-table-wrap');
      statusFilter.addEventListener('toggle', () => {
        if (statusTableWrap) {
          statusTableWrap.classList.toggle('feedback-table-wrap-menu-open', statusFilter.open);
        }
      });

      const statusInput = statusFilter.querySelector('[data-status-input]');
      const statusCheckboxes = statusFilter.querySelectorAll('[data-status-checkbox]');
      const defaultStatuses = (statusFilter.dataset.defaultStatuses || '').split(',').filter(Boolean).sort();
      statusCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
          const selected = Array.from(statusCheckboxes).filter((cb) => cb.checked).map((cb) => cb.value);
          const isDefault = [...selected].sort().join(',') === defaultStatuses.join(',');
          // omit the status param entirely when it matches the default selection
          statusInput.value = selected.join(',');
          statusInput.disabled = isDefault;
          filterForm.requestSubmit();
        });
      });
    }

    const filterForm = document.querySelector('.feedback-filter-form');
    if (filterForm) {
      // submit filters in the background so the address bar never changes
      filterForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const params = new URLSearchParams(new FormData(filterForm));
        fetch(`/feedback?${params.toString()}`)
          .then((response) => response.text())
          .then((html) => {
            const newWrap = new DOMParser().parseFromString(html, 'text/html').querySelector('.feedback-table-wrap');
            const oldWrap = filterForm.querySelector('.feedback-table-wrap');
            if (newWrap && oldWrap) {
              oldWrap.replaceWith(newWrap);
              initStatusFilter(filterForm);
            }
          })
          .catch(() => { filterForm.submit(); });
      });
      initStatusFilter(filterForm);
    }
  </script>
  <?php if (feedback_turnstile_enabled()): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script><?php endif; ?>
</body>
</html>
