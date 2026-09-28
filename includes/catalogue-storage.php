<?php
declare(strict_types=1);

function catalogue_storage_env(string $key, string $default = ''): string
{
    $value = getenv($key);
    return $value === false ? $default : trim((string)$value);
}

function catalogue_storage_table(string $name): string
{
    $table = catalogue_storage_env($name, $name === 'CATALOGUE_TABLE' ? 'tig_catalogue' : 'tig_catalogue_assets');
    if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
        throw new RuntimeException('The configured catalogue table name is invalid.');
    }
    return '`' . $table . '`';
}

function catalogue_storage_load(): array
{
    try {
        $table = catalogue_storage_table('CATALOGUE_TABLE');
        $row = generate_log_db()->query("SELECT document FROM {$table} WHERE id = 1")->fetch();
        if (is_array($row)) {
            $catalogue = json_decode((string)$row['document'], true, 64, JSON_THROW_ON_ERROR);
            if (is_array($catalogue) && is_array($catalogue['brands'] ?? null)) {
                return $catalogue;
            }
        }
    } catch (Throwable) {
    }

    $cataloguePath = __DIR__ . '/../catalogue/tiles.json';
    $catalogue = is_file($cataloguePath) ? json_decode((string)file_get_contents($cataloguePath), true) : null;
    if (!is_array($catalogue) || !is_array($catalogue['brands'] ?? null)) {
        throw new RuntimeException('The predefined tile catalogue is unavailable.');
    }
    return $catalogue;
}

function catalogue_storage_object_key(string $objectKey): string
{
    $objectKey = ltrim(str_replace('\\', '/', trim($objectKey)), '/');
    if (!str_starts_with($objectKey, 'catalogue/images/') || str_contains($objectKey, '..')) {
        throw new InvalidArgumentException('The catalogue image path is invalid.');
    }
    foreach (explode('/', $objectKey) as $segment) {
        if ($segment === '' || preg_match('/^[A-Za-z0-9._-]+$/', $segment) !== 1) {
            throw new InvalidArgumentException('The catalogue image path contains unsupported characters.');
        }
    }
    return $objectKey;
}

function catalogue_storage_cdn_url(string $objectKey): string
{
    $host = preg_replace('#^https?://#i', '', catalogue_storage_env('BUNNY_CDN_HOST'));
    if ($host === null || $host === '' || str_contains($host, '/')) {
        throw new RuntimeException('BUNNY_CDN_HOST is not configured correctly.');
    }
    return 'https://' . $host . '/' . implode('/', array_map('rawurlencode', explode('/', catalogue_storage_object_key($objectKey))));
}

function catalogue_storage_local_file(string $objectKey): ?string
{
    $root = realpath(__DIR__ . '/../catalogue');
    $path = realpath(__DIR__ . '/../' . catalogue_storage_object_key($objectKey));
    if ($root === false || $path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path)) {
        return null;
    }
    return $path;
}

function catalogue_storage_cache_directory(): string
{
    static $resolvedDirectory = null;
    if (is_string($resolvedDirectory)) {
        return $resolvedDirectory;
    }

    $fallbackDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . '/tile-image-gen-' . substr(hash('sha256', __DIR__), 0, 12) . '/catalogue';
    $candidates = array_unique([
        catalogue_storage_env('CATALOGUE_CACHE_DIR'),
        $fallbackDirectory,
    ]);

    foreach ($candidates as $directory) {
        if ($directory === '') {
            continue;
        }
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            continue;
        }
        if (is_dir($directory) && is_writable($directory)) {
            $resolvedDirectory = rtrim($directory, DIRECTORY_SEPARATOR);
            return $resolvedDirectory;
        }
    }

    throw new RuntimeException('No writable catalogue image cache directory is available.');
}

function catalogue_storage_cached_file(string $objectKey): string
{
    $objectKey = catalogue_storage_object_key($objectKey);
    if (catalogue_storage_env('BUNNY_CDN_HOST') === '') {
        $localFile = catalogue_storage_local_file($objectKey);
        if ($localFile === null) {
            throw new RuntimeException('The catalogue image is unavailable.');
        }
        return $localFile;
    }

    $directory = catalogue_storage_cache_directory();
    $extension = strtolower(pathinfo($objectKey, PATHINFO_EXTENSION));
    $cacheFile = $directory . '/' . hash('sha256', $objectKey) . ($extension === '' ? '' : '.' . $extension);
    if (is_file($cacheFile) && filesize($cacheFile) > 0) {
        touch($cacheFile);
        return $cacheFile;
    }

    $lock = fopen($cacheFile . '.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        if (is_resource($lock)) fclose($lock);
        throw new RuntimeException('The catalogue image cache could not be locked.');
    }

    try {
        clearstatcache(true, $cacheFile);
        if (!is_file($cacheFile) || filesize($cacheFile) === 0) {
            catalogue_storage_download($objectKey, $cacheFile);
            catalogue_storage_trim_cache($directory, $cacheFile);
        }
        touch($cacheFile);
        return $cacheFile;
    } catch (Throwable $exception) {
        $localFile = catalogue_storage_local_file($objectKey);
        if ($localFile !== null) {
            return $localFile;
        }
        throw $exception;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
        @unlink($cacheFile . '.lock');
    }
}

function catalogue_storage_download(string $objectKey, string $cacheFile): void
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required to fetch catalogue images.');
    }
    $temporary = $cacheFile . '.' . bin2hex(random_bytes(6)) . '.tmp';
    $stream = fopen($temporary, 'wb');
    if ($stream === false) {
        throw new RuntimeException('A temporary catalogue cache file could not be created.');
    }

    $handle = curl_init(catalogue_storage_cdn_url($objectKey));
    if ($handle === false) {
        fclose($stream);
        @unlink($temporary);
        throw new RuntimeException('The catalogue image request could not be initialized.');
    }
    curl_setopt_array($handle, [
        CURLOPT_FILE => $stream,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_FAILONERROR => false,
        CURLOPT_USERAGENT => 'TileImageGenerator/1.0',
    ]);
    $success = curl_exec($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $contentType = strtolower((string)curl_getinfo($handle, CURLINFO_CONTENT_TYPE));
    $error = curl_error($handle);
    curl_close($handle);
    fclose($stream);

    $size = is_file($temporary) ? filesize($temporary) : false;
    if ($success === false || $status !== 200 || $size === false || $size < 1 || $size > 15 * 1024 * 1024 || !str_starts_with($contentType, 'image/')) {
        @unlink($temporary);
        $detail = $error !== '' ? $error : 'HTTP ' . $status;
        throw new RuntimeException('The catalogue image could not be fetched from storage: ' . $detail . '.');
    }
    if (!rename($temporary, $cacheFile)) {
        @unlink($temporary);
        throw new RuntimeException('The catalogue image could not be committed to cache.');
    }
}

function catalogue_storage_trim_cache(string $directory, string $protectedFile): void
{
    $limit = filter_var(catalogue_storage_env('CATALOGUE_CACHE_MAX_BYTES', '5368709120'), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 104857600],
    ]);
    if ($limit === false) {
        throw new RuntimeException('CATALOGUE_CACHE_MAX_BYTES must be at least 100 MB.');
    }

    $files = [];
    $total = 0;
    foreach (new DirectoryIterator($directory) as $file) {
        if (!$file->isFile() || str_ends_with($file->getFilename(), '.lock') || str_ends_with($file->getFilename(), '.tmp')) continue;
        $size = $file->getSize();
        $total += $size;
        $files[] = ['path' => $file->getPathname(), 'size' => $size, 'used' => $file->getATime()];
    }
    usort($files, static fn(array $left, array $right): int => $left['used'] <=> $right['used']);
    foreach ($files as $file) {
        if ($total <= $limit) break;
        if ($file['path'] === $protectedFile) continue;
        if (@unlink($file['path'])) $total -= $file['size'];
    }
}
