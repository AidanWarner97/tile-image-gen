<?php
declare(strict_types=1);

require_once __DIR__ . '/generate-log.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

function fail_request(string $message, int $code = 400): never
{
    $requestId = $GLOBALS['generateLogRequestId'] ?? bin2hex(random_bytes(16));
    generate_log_write(generate_log_record($requestId, 'error', $message));
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function hex_to_rgb(string $hex): array
{
    $clean = ltrim(trim($hex), '#');
    if (strlen($clean) !== 6 || !ctype_xdigit($clean)) {
        return [0, 0, 0];
    }

    return [
        hexdec(substr($clean, 0, 2)),
        hexdec(substr($clean, 2, 2)),
        hexdec(substr($clean, 4, 2)),
    ];
}

function create_truecolor(int $width, int $height)
{
    $img = imagecreatetruecolor($width, $height);
    if (!($img instanceof GdImage)) {
        fail_request('Failed to allocate image canvas. Try a smaller tile size or simpler pattern.', 500);
    }
    imagealphablending($img, false);
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    return $img;
}

function resize_image($source, int $width, int $height)
{
    if (!($source instanceof GdImage)) {
        fail_request('Invalid source image received during resize.', 500);
    }

    $dest = create_truecolor($width, $height);
    imagecopyresampled($dest, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
    return $dest;
}

function rotate_tile($img, int $degrees)
{
    if (!($img instanceof GdImage)) {
        fail_request('Invalid image received during rotation.', 500);
    }

    $normalized = (($degrees % 360) + 360) % 360;

    if ($normalized === 0) {
        return $img;
    }

    $srcW = imagesx($img);
    $srcH = imagesy($img);

    if ($normalized === 90 || $normalized === 270) {
        $rotated = create_truecolor($srcH, $srcW);

        for ($x = 0; $x < $srcW; $x++) {
            for ($y = 0; $y < $srcH; $y++) {
                $color = imagecolorat($img, $x, $y);

                if ($normalized === 90) {
                    imagesetpixel($rotated, $srcH - 1 - $y, $x, $color);
                } else {
                    imagesetpixel($rotated, $y, $srcW - 1 - $x, $color);
                }
            }
        }

        return $rotated;
    }

    if ($normalized === 180) {
        $rotated = create_truecolor($srcW, $srcH);

        for ($x = 0; $x < $srcW; $x++) {
            for ($y = 0; $y < $srcH; $y++) {
                $color = imagecolorat($img, $x, $y);
                imagesetpixel($rotated, $srcW - 1 - $x, $srcH - 1 - $y, $color);
            }
        }

        return $rotated;
    }

    $bg = imagecolorallocatealpha($img, 0, 0, 0, 127);
    $rotated = imagerotate($img, 360 - $normalized, $bg);
    imagealphablending($rotated, false);
    imagesavealpha($rotated, true);
    return $rotated;
}

function safe_filename(string $name): string
{
    $clean = preg_replace('/[^A-Za-z0-9\-_ ]+/', '', $name) ?? 'tile_image_result';
    $clean = trim($clean);
    return $clean === '' ? 'tile_image_result' : $clean;
}

function grout_name(string $hex): string
{
    $map = [
        '#d1d1cf' => 'Gunmetal',
        '#9e9fa4' => 'Smoke',
        '#473938' => 'Dovetail',
        '#516d71' => 'Tornado Sky',
        '#817670' => 'Taupe Grey',
        '#485a68' => 'Storm Grey',
        '#414550' => 'Anthracite',
        '#211f20' => 'Ebony',
        '#ffffff' => 'White',
        '#f5eed4' => 'Jasmine',
        '#dac9b7' => 'Pebble',
        '#76480d' => 'Walnut',
        '#623d13' => 'Hazel',
        '#623619' => 'Mahogany',
        '#cadee5' => 'Cornflower White',
        '#b6d6cb' => 'Peppermint',
        '#f2c7c0' => 'Pink Champagne',
        '#fbf6cc' => 'Primrose',
        '#ece1ab' => 'Cream',
        '#e0cdbc' => 'Bahama Beige',
        '#efe3d3' => 'Jasmine',
        '#cdc9bd' => 'Limestone',
        '#5e5b54' => 'Taupe',
        '#716152' => 'Brown',
        '#afb3b4' => 'Silver Grey',
        '#a6acac' => 'Mid-Grey',
        '#8d9193' => 'Grey',
        '#4c5157' => 'Charcoal',
        '#000000' => 'Black',
    ];

    $key = strtolower($hex);
    return $map[$key] ?? $hex;
}

function layout_name(string $layout): string
{
    $map = [
        'stacked' => 'Horizontal Block',
        'brickBond' => 'Horizontal Half Block',
        'third' => 'Horizontal Third Block',
        'quarter' => 'Horizontal Quarter Block',
        'vertStacked' => 'Vertical Block',
        'vertBrick' => 'Vertical Half Block',
        'vertThird' => 'Vertical Third Block',
        'vertQuarter' => 'Vertical Quarter Block',
        'basketWeave' => 'Basket Weave',
        'herringbone' => 'Herringbone',
        'hexagon' => 'Hexagon',
    ];

    return $map[$layout] ?? 'Pattern';
}

function build_placements(string $layoutType, int $w, int $h, int $gs): array
{
    $ratio = $h > 0 ? ($w / $h) : 1.0;
    $ratioInt = (int)round($ratio);
    $isSupportedRatio = abs($ratio - $ratioInt) < 0.00001 && $ratioInt >= 2 && $ratioInt <= 10;

    switch ($layoutType) {
        case 'stacked':
        case 'vertStacked':
            return [
                [0, $gs, $gs, 0],
                [1, $w + ($gs * 2), $gs, 0],
                [2, $gs, $h + ($gs * 2), 0],
                [3, $w + ($gs * 2), $h + ($gs * 2), 0],
            ];
        case 'brickBond':
        case 'vertBrick':
            $half = (int)floor($w / 2);
            return [
                [0, -$half, $gs, 0],
                [1, $gs + $half, $gs, 0],
                [0, ($gs * 2) + $w + $half, $gs, 0],
                [2, $gs, ($gs * 2) + $h, 0],
                [3, ($gs * 2) + $w, ($gs * 2) + $h, 0],
            ];
        case 'third':
        case 'vertThird':
            $third = (int)floor($w / 3);
            $twothird = $third * 2;
            return [
                [0, -$third - $gs, $gs, 0],
                [0, $twothird, $gs, 0],
                [1, -$twothird - $gs, $h + ($gs * 2), 0],
                [1, $third, $h + ($gs * 2), 0],
                [2, $gs, ($h * 2) + ($gs * 3), 0],
            ];
        case 'quarter':
        case 'vertQuarter':
            $quarter = (int)floor($w / 4);
            $half = (int)floor($w / 2);
            return [
                [0, -$quarter, $gs, 0],
                [0, ($quarter * 3) + $gs, $gs, 0],
                [1, -$half, ($h + $gs) + $gs, 0],
                [1, $half + $gs, ($h + $gs) + $gs, 0],
                [2, -($quarter * 3), (($h + $gs) * 2) + $gs, 0],
                [2, $quarter + $gs, (($h + $gs) * 2) + $gs, 0],
                [3, $gs, (($h + $gs) * 3) + $gs, 0],
            ];
        case 'basketWeave':
            if ($isSupportedRatio && $ratioInt === 3) {
                return [
                    [0, $gs, $gs, 90],
                    [1, $h + ($gs * 2), $gs, 90],
                    [2, ($h * 2) + ($gs * 3), $gs, 90],
                    [3, ($h * 3) + ($gs * 4), $gs, 0],
                    [0, ($h * 3) + ($gs * 4), $h + ($gs * 2), 0],
                    [1, ($h * 3) + ($gs * 4), ($h * 2) + ($gs * 3), 0],
                    [2, $gs, ($h * 3) + ($gs * 2), 0],
                    [3, $gs, ($h * 4) + ($gs * 3), 0],
                    [0, $gs, ($h * 5) + ($gs * 4), 0],
                    [2, ($h * 3) + ($gs * 2), ($h * 3) + ($gs * 4), 90],
                    [3, ($h * 4) + ($gs * 3), ($h * 3) + ($gs * 4), 90],
                    [1, ($h * 5) + ($gs * 4), ($h * 3) + ($gs * 4), 90],
                ];
            }

            if ($isSupportedRatio && $ratioInt >= 3) {
                $n = $ratioInt;
                $placements = [];

                for ($i = 0; $i < $n; $i++) {
                    $placements[] = [$i % 4, ($gs * ($i + 1)) + ($h * $i), $gs, 90];
                }

                for ($i = 0; $i < $n; $i++) {
                    $placements[] = [$i % 4, ($gs * ($n + 1)) + ($h * $n), ($gs * ($i + 1)) + ($h * $i), 0];
                }

                for ($i = 0; $i < $n; $i++) {
                    $placements[] = [$i % 4, $gs, ($gs * ($i + 2)) + $w + ($h * $i), 0];
                }

                for ($i = 0; $i < $n; $i++) {
                    $placements[] = [$i % 4, ($gs * ($i + 2)) + ($h * ($n + $i)), ($gs * ($n + 1)) + ($h * $n), 90];
                }

                return $placements;
            }

            if (abs($ratio - 2.0) < 0.00001) {
                return [
                    [0, $gs, $gs, 90],
                    [1, $gs + $h + $gs, $gs, 90],
                    [2, $gs + $h + $gs + $h + $gs, $gs, 0],
                    [3, $gs + $h + $gs + $h + $gs, $gs + $h + $gs, 0],
                    [3, $gs, $gs + $w + $gs, 0],
                    [2, $gs, $gs + $w + $gs + $h + $gs, 0],
                    [1, $gs + $h + $gs + $h, $gs + $h + $gs + $h + $gs, 90],
                    [0, $gs + $h + $gs + $h + $gs + $h, $gs + $h + $gs + $h + $gs, 90],
                ];
            }

            return [
                [0, 0, 0, 90],
                [1, $h + $gs, 0, 90],
                [2, (2 * $h) + (2 * $gs), 0, 0],
                [3, (2 * $h) + (2 * $gs), $h + $gs, 0],
                [0, 0, $w + $gs, 0],
                [1, 0, $w + $h + (2 * $gs), 0],
                [2, $h + $gs, $w + $h + (2 * $gs), 90],
                [3, (2 * $h) + (2 * $gs), $w + $h + (2 * $gs), 90],
            ];
        case 'herringbone':
            if ($isSupportedRatio && $ratioInt === 10) {
                return [
                    // Horizontal
                    [0, ($gs * 9) + ($h * 9), $gs, 0],
                    [1, ($gs * 8) + ($h * 8), ($gs * 2) + $h, 0],
                    [2, ($gs * 7) + ($h * 7), ($gs * 3) + ($h * 2), 0],
                    [3, ($gs * 6) + ($h * 6), ($gs * 4) + ($h * 3), 0],
                    [0, ($gs * 5) + ($h * 5), ($gs * 5) + ($h * 4), 0],
                    [1, ($gs * 4) + ($h * 4), ($gs * 6) + ($h * 5), 0],
                    [2, ($gs * 3) + ($h * 3), ($gs * 7) + ($h * 6), 0],
                    [3, ($gs * 2) + ($h * 2), ($gs * 8) + ($h * 7), 0],
                    [0, $gs + $h, ($gs * 9) + ($h * 8), 0],
                    [1, 0, ($gs * 10) + ($h * 9), 0],
                    [2, -$gs - $h, ($gs * 11) + ($h * 10), 0],
                    [2, ($gs * 10) + ($h * 19), ($gs * 2) + ($h * 10), 0],
                    [3, -(($gs * 2) + ($h * 2)), ($gs * 12) + ($h * 11), 0],
                    [3, ($gs * 9) + ($h * 18), ($gs * 3) + ($h * 11), 0],
                    [0, -(($gs * 3) + ($h * 3)), ($gs * 13) + ($h * 12), 0],
                    [0, ($gs * 8) + ($h * 17), ($gs * 4) + ($h * 12), 0],
                    [1, -(($gs * 4) + ($h * 4)), ($gs * 14) + ($h * 13), 0],
                    [1, ($gs * 7) + ($h * 16), ($gs * 5) + ($h * 13), 0],
                    [2, -(($gs * 5) + ($h * 5)), ($gs * 15) + ($h * 14), 0],
                    [2, ($gs * 6) + ($h * 15), ($gs * 6) + ($h * 14), 0],
                    [3, -(($gs * 6) + ($h * 6)), ($gs * 16) + ($h * 15), 0],
                    [3, ($gs * 5) + ($h * 14), ($gs * 7) + ($h * 15), 0],
                    [0, -(($gs * 7) + ($h * 7)), ($gs * 17) + ($h * 16), 0],
                    [0, ($gs * 4) + ($h * 13), ($gs * 8) + ($h * 16), 0],
                    [1, -(($gs * 8) + ($h * 8)), ($gs * 18) + ($h * 17), 0],
                    [1, ($gs * 3) + ($h * 12), ($gs * 9) + ($h * 17), 0],
                    [2, -(($gs * 9) + ($h * 9)), ($gs * 19) + ($h * 18), 0],
                    [2, ($gs * 2) + ($h * 11), ($gs * 10) + ($h * 18), 0],
                    [3, $gs + ($h * 10), ($gs * 11) + ($h * 19), 0, $w + ($gs * 8), $h],

                    // Vertical
                    [0, ($gs * 10) + ($h * 19), $gs, 90],
                    [1, ($gs * 9) + ($h * 18), ($gs * 2) + $h, 90],
                    [2, ($gs * 8) + ($h * 17), ($gs * 3) + ($h * 2), 270],
                    [3, ($gs * 7) + ($h * 16), ($gs * 4) + ($h * 3), 90],
                    [0, ($gs * 6) + ($h * 15), ($gs * 5) + ($h * 4), 90],
                    [1, ($gs * 5) + ($h * 14), ($gs * 6) + ($h * 5), 90],
                    [2, ($gs * 4) + ($h * 13), ($gs * 7) + ($h * 6), 270],
                    [3, ($gs * 3) + ($h * 12), ($gs * 8) + ($h * 7), 90],
                    [0, ($gs * 2) + ($h * 11), ($gs * 9) + ($h * 8), 90],
                    [1, $gs + ($h * 10), ($gs * 10) + ($h * 9), 90],
                    [2, $h * 9, ($gs * 11) + ($h * 10), 270],
                    [3, ($h * 8) - $gs, ($gs * 12) + ($h * 11), 90],
                    [3, ($h * 8) + ($gs * 8), $gs - ($h * 9), 90],
                    [0, ($h * 7) - ($gs * 2), ($gs * 13) + ($h * 12), 90],
                    [0, ($h * 7) + ($gs * 7), ($gs * 2) - ($h * 8), 90],
                    [1, ($h * 6) - ($gs * 3), ($gs * 14) + ($h * 13), 90],
                    [1, ($h * 6) + ($gs * 6), ($gs * 3) - ($h * 7), 90],
                    [2, ($h * 5) - ($gs * 4), ($gs * 15) + ($h * 14), 270],
                    [2, ($h * 5) + ($gs * 5), ($gs * 4) - ($h * 6), 270],
                    [3, ($h * 4) - ($gs * 5), ($gs * 16) + ($h * 15), 90],
                    [3, ($h * 4) + ($gs * 4), ($gs * 5) - ($h * 5), 90],
                    [0, ($h * 3) - ($gs * 6), ($gs * 17) + ($h * 16), 90],
                    [0, ($h * 3) + ($gs * 3), ($gs * 6) - ($h * 4), 90],
                    [1, ($h * 2) - ($gs * 7), ($gs * 18) + ($h * 17), 90],
                    [1, ($h * 2) + ($gs * 2), ($gs * 7) - ($h * 3), 90],
                    [2, $h - ($gs * 8), ($gs * 19) + ($h * 18), 270],
                    [2, $h + $gs, ($gs * 8) - ($h * 2), 270],
                    [3, $gs, ($gs * 20) + ($h * 19), 90],
                    [3, $gs, ($gs * 9) - $h, 90],
                ];
            }

            if ($isSupportedRatio && $ratioInt === 9) {
                return [
                    [0, ($gs * 8) + ($h * 8), $gs, 0],
                    [1, ($gs * 7) + ($h * 7), ($gs * 2) + $h, 0],
                    [2, ($gs * 6) + ($h * 6), ($gs * 3) + ($h * 2), 0],
                    [3, ($gs * 5) + ($h * 5), ($gs * 4) + ($h * 3), 0],
                    [0, ($gs * 4) + ($h * 4), ($gs * 5) + ($h * 4), 0],
                    [1, ($gs * 3) + ($h * 3), ($gs * 6) + ($h * 5), 0],
                    [2, ($gs * 2) + ($h * 2), ($gs * 7) + ($h * 6), 0],
                    [3, $gs + $h, ($gs * 8) + ($h * 7), 0],
                    [0, $gs, ($gs * 9) + ($h * 8), 0],
                    [1, $gs - $h, ($gs * 10) + ($h * 9), 0],
                    [1, ($gs * 9) + ($h * 17), ($gs * 2) + ($h * 9), 0],
                    [2, $gs - ($h * 2), ($gs * 11) + ($h * 10), 0],
                    [2, ($gs * 8) + ($h * 16), ($gs * 3) + ($h * 10), 0],
                    [3, $gs - ($h * 3), ($gs * 12) + ($h * 11), 0],
                    [3, ($gs * 7) + ($h * 15), ($gs * 4) + ($h * 11), 0],
                    [0, $gs - ($h * 4), ($gs * 13) + ($h * 12), 0],
                    [0, ($gs * 6) + ($h * 14), ($gs * 5) + ($h * 12), 0],
                    [1, $gs - ($h * 5), ($gs * 14) + ($h * 13), 0],
                    [1, ($gs * 5) + ($h * 13), ($gs * 6) + ($h * 13), 0],
                    [2, $gs - ($h * 6), ($gs * 15) + ($h * 14), 0],
                    [2, ($gs * 4) + ($h * 12), ($gs * 7) + ($h * 14), 0],
                    [3, $gs - ($h * 7), ($gs * 16) + ($h * 15), 0],
                    [3, ($gs * 3) + ($h * 11), ($gs * 8) + ($h * 15), 0],
                    [0, $gs - ($h * 8), ($gs * 17) + ($h * 16), 0],
                    [0, ($gs * 2) + ($h * 10), ($gs * 9) + ($h * 16), 0],
                    [1, $gs + ($h * 9), ($gs * 10) + ($h * 17), 0, $w + ($gs * 7), $h],

                    [0, ($gs * 9) + ($h * 17), $gs, 90],
                    [1, ($gs * 8) + ($h * 16), ($gs * 2) + $h, 90],
                    [2, ($gs * 7) + ($h * 15), ($gs * 3) + ($h * 2), 270],
                    [3, ($gs * 6) + ($h * 14), ($gs * 4) + ($h * 3), 90],
                    [0, ($gs * 5) + ($h * 13), ($gs * 5) + ($h * 4), 90],
                    [1, ($gs * 4) + ($h * 12), ($gs * 6) + ($h * 5), 90],
                    [2, ($gs * 3) + ($h * 11), ($gs * 7) + ($h * 6), 270],
                    [3, ($gs * 2) + ($h * 10), ($gs * 8) + ($h * 7), 90],
                    [0, $gs + ($h * 9), ($gs * 9) + ($h * 8), 90],
                    [1, $h * 8, ($gs * 10) + ($h * 9), 90],
                    [2, ($h * 7) - $gs, ($gs * 11) + ($h * 10), 270],
                    [2, ($h * 7) + ($gs * 7), $gs - ($h * 8), 270],
                    [3, ($h * 6) - ($gs * 2), ($gs * 12) + ($h * 11), 90],
                    [3, ($h * 6) + ($gs * 6), ($gs * 2) - ($h * 7), 90],
                    [0, ($h * 5) - ($gs * 3), ($gs * 13) + ($h * 12), 90],
                    [0, ($h * 5) + ($gs * 5), ($gs * 3) - ($h * 6), 90],
                    [1, ($h * 4) - ($gs * 4), ($gs * 14) + ($h * 13), 90],
                    [1, ($h * 4) + ($gs * 4), ($gs * 4) - ($h * 5), 90],
                    [2, ($h * 3) - ($gs * 5), ($gs * 15) + ($h * 14), 270],
                    [2, ($h * 3) + ($gs * 3), ($gs * 5) - ($h * 4), 270],
                    [3, ($h * 2) - ($gs * 6), ($gs * 16) + ($h * 15), 90],
                    [3, ($h * 2) + ($gs * 2), ($gs * 6) - ($h * 3), 90],
                    [0, $h - ($gs * 7), ($gs * 17) + ($h * 16), 90],
                    [0, $h + $gs, ($gs * 7) - ($h * 2), 90],
                    [1, $gs, ($gs * 18) + ($h * 17), 90],
                    [1, $gs, ($gs * 8) - $h, 90],
                ];
            }

            if ($isSupportedRatio && $ratioInt === 8) {
                return [
                    [0, ($gs * 7) + ($h * 7), $gs, 0],
                    [1, ($gs * 6) + ($h * 6), ($gs * 2) + $h, 0],
                    [2, ($gs * 5) + ($h * 5), ($gs * 3) + ($h * 2), 0],
                    [3, ($gs * 4) + ($h * 4), ($gs * 4) + ($h * 3), 0],
                    [0, ($gs * 3) + ($h * 3), ($gs * 5) + ($h * 4), 0],
                    [1, ($gs * 2) + ($h * 2), ($gs * 6) + ($h * 5), 0],
                    [2, $gs + $h, ($gs * 7) + ($h * 6), 0],
                    [3, $gs, ($gs * 8) + ($h * 7), 0],
                    [0, $gs - ($h + $gs), ($gs * 9) + ($h * 8), 0, $w - $gs, $h],
                    [0, ($gs * 8) + ($h * 15), ($gs * 2) + ($h * 8), 0],
                    [1, $gs - (($h + $gs) * 2), ($gs * 10) + ($h * 9), 0, $w - $gs, $h],
                    [1, ($gs * 7) + ($h * 14), ($gs * 3) + ($h * 9), 0],
                    [2, $gs - (($h + $gs) * 3), ($gs * 11) + ($h * 10), 0, $w - $gs, $h],
                    [2, ($gs * 6) + ($h * 13), ($gs * 4) + ($h * 10), 0],
                    [3, $gs - (($h + $gs) * 4), ($gs * 12) + ($h * 11), 0, $w - $gs, $h],
                    [3, ($gs * 5) + ($h * 12), ($gs * 5) + ($h * 11), 0],
                    [0, $gs - (($h + $gs) * 5), ($gs * 13) + ($h * 12), 0, $w - $gs, $h],
                    [0, ($gs * 4) + ($h * 11), ($gs * 6) + ($h * 12), 0],
                    [1, $gs - (($h + $gs) * 6), ($gs * 14) + ($h * 13), 0, $w - $gs, $h],
                    [1, ($gs * 3) + ($h * 10), ($gs * 7) + ($h * 13), 0],
                    [2, $gs - (($h + $gs) * 7), ($gs * 15) + ($h * 14), 0, $w - $gs, $h],
                    [2, ($gs * 2) + ($h * 9), ($gs * 8) + ($h * 14), 0],
                    [3, $gs + ($h * 8), ($gs * 9) + ($h * 15), 0, $w + ($gs * 6), $h - $gs],

                    [0, ($gs * 8) + ($h * 15), $gs, 90],
                    [1, ($gs * 7) + ($h * 14), ($gs * 2) + $h, 90],
                    [2, ($gs * 6) + ($h * 13), ($gs * 3) + ($h * 2), 270],
                    [3, ($gs * 5) + ($h * 12), ($gs * 4) + ($h * 3), 90],
                    [0, ($gs * 4) + ($h * 11), ($gs * 5) + ($h * 4), 90],
                    [1, ($gs * 3) + ($h * 10), ($gs * 6) + ($h * 5), 90],
                    [2, ($gs * 2) + ($h * 9), ($gs * 7) + ($h * 6), 270],
                    [3, $gs + ($h * 8), ($gs * 8) + ($h * 7), 90],
                    [0, ($h * 6) - $gs, ($gs * 9) + ($h * 8), 90],
                    [0, $gs + ($h + $gs), $gs, 90, $h, $h],
                    [1, ($h * 5) - ($gs * 2), ($gs * 10) + ($h * 9), 90],
                    [1, $gs + (($h + $gs) * 2), $gs, 90, ($h * 2) + $gs, $h],
                    [2, ($h * 4) - ($gs * 3), ($gs * 11) + ($h * 10), 270],
                    [2, $gs + (($h + $gs) * 3), $gs, 270, ($h * 3) + ($gs * 2), $h],
                    [3, ($h * 3) - ($gs * 4), ($gs * 12) + ($h * 11), 90],
                    [3, $gs + (($h + $gs) * 4), $gs, 90, ($h * 4) + ($gs * 3), $h],
                    [0, ($h * 2) - ($gs * 5), ($gs * 13) + ($h * 12), 90],
                    [0, $gs + (($h + $gs) * 5), $gs, 90, ($h * 5) + ($gs * 4), $h],
                    [1, $h - ($gs * 6), ($gs * 14) + ($h * 13), 90],
                    [1, $gs + (($h + $gs) * 6), $gs, 90, ($h * 6) + ($gs * 5), $h],
                    [2, $gs, ($gs * 15) + ($h * 14), 270],
                    [2, $gs + (($h + $gs) * 7), $gs, 270, ($h * 7) + ($gs * 6), $h - $gs],
                ];
            }

            if ($isSupportedRatio && $ratioInt === 7) {
                return [
                    [0, ($gs * 6) + ($h * 6), $gs, 0],
                    [1, ($gs * 5) + ($h * 5), ($gs * 2) + $h, 0],
                    [2, ($gs * 4) + ($h * 4), ($gs * 3) + ($h * 2), 0],
                    [3, ($gs * 3) + ($h * 3), ($gs * 4) + ($h * 3), 0],
                    [0, ($gs * 2) + ($h * 2), ($gs * 5) + ($h * 4), 0],
                    [1, $gs + $h, ($gs * 6) + ($h * 5), 0],
                    [2, $gs, ($gs * 7) + ($h * 6), 0],
                    [3, $gs - ($h + $gs), ($gs * 8) + ($h * 7), 0, $w - $gs, $h],
                    [3, ($gs * 7) + ($h * 13), ($gs * 2) + ($h * 7), 0],
                    [0, $gs - (($h + $gs) * 2), ($gs * 9) + ($h * 8), 0, $w - $gs, $h],
                    [0, ($gs * 6) + ($h * 12), ($gs * 3) + ($h * 8), 0],
                    [1, $gs - (($h + $gs) * 3), ($gs * 10) + ($h * 9), 0, $w - $gs, $h],
                    [1, ($gs * 5) + ($h * 11), ($gs * 4) + ($h * 9), 0],
                    [2, $gs - (($h + $gs) * 4), ($gs * 11) + ($h * 10), 0, $w - $gs, $h],
                    [2, ($gs * 4) + ($h * 10), ($gs * 5) + ($h * 10), 0],
                    [3, $gs - (($h + $gs) * 5), ($gs * 12) + ($h * 11), 0, $w - $gs, $h],
                    [3, ($gs * 3) + ($h * 9), ($gs * 6) + ($h * 11), 0],
                    [0, $gs - (($h + $gs) * 6), ($gs * 13) + ($h * 12), 0, $w - $gs, $h],
                    [0, ($gs * 2) + ($h * 8), ($gs * 7) + ($h * 12), 0],
                    [1, $gs + ($h * 7), ($gs * 8) + ($h * 13), 0, $w + ($gs * 5), $h - $gs],

                    [0, ($gs * 7) + ($h * 13), $gs, 90],
                    [1, ($gs * 6) + ($h * 12), ($gs * 2) + $h, 90],
                    [2, ($gs * 5) + ($h * 11), ($gs * 3) + ($h * 2), 270],
                    [3, ($gs * 4) + ($h * 10), ($gs * 4) + ($h * 3), 90],
                    [0, ($gs * 3) + ($h * 9), ($gs * 5) + ($h * 4), 90],
                    [1, ($gs * 2) + ($h * 8), ($gs * 6) + ($h * 5), 90],
                    [2, $gs + ($h * 7), ($gs * 7) + ($h * 6), 270],
                    [3, $gs, ($gs * 8) + ($h * 7), 90],
                    [0, ($h * 5) - $gs, ($gs * 9) + ($h * 8), 90],
                    [0, $gs + ($h + $gs), $gs, 90, $h, $h],
                    [1, ($h * 4) - ($gs * 2), ($gs * 10) + ($h * 9), 90],
                    [1, $gs + (($h + $gs) * 2), $gs, 90, ($h * 2) + $gs, $h],
                    [2, ($h * 3) - ($gs * 3), ($gs * 11) + ($h * 10), 270],
                    [2, $gs + (($h + $gs) * 3), $gs, 270, ($h * 3) + ($gs * 2), $h],
                    [3, ($h * 2) - ($gs * 4), ($gs * 12) + ($h * 11), 90],
                    [3, $gs + (($h + $gs) * 4), $gs, 90, ($h * 4) + ($gs * 3), $h],
                    [0, $h - ($gs * 5), ($gs * 13) + ($h * 12), 90],
                    [0, $gs + (($h + $gs) * 5), $gs, 90, ($h * 5) + ($gs * 4), $h],
                    [1, $gs, ($gs * 14) + ($h * 13), 90],
                    [1, $gs + (($h + $gs) * 6), $gs, 90, ($h * 6) + ($gs * 5), $h - $gs],
                ];
            }

            if ($isSupportedRatio && $ratioInt === 3) {
                return [
                    // Horizontal
                    [0, ($gs * 2) + ($h * 2), $gs, 0],
                    [1, $gs + $h, ($gs * 2) + $h, 0],
                    [2, $gs, ($gs * 3) + ($h * 2), 0],
                    [3, -$h, ($gs * 4) + ($h * 3), 0],
                    [3, ($gs * 3) + ($h * 5), ($gs * 2) + ($h * 3), 0],
                    [1, -($h * 2), ($gs * 5) + ($h * 4), 0],
                    [3, ($gs * 2) + ($h * 4), ($gs * 3) + ($h * 4), 0],
                    [2, ($gs * 2) + $w, ($gs * 4) + ($h * 5), 0],

                    // Vertical
                    [0, ($gs * 3) + ($h * 5), $gs, 90],
                    [1, ($gs * 2) + ($h * 4), ($gs * 2) + $h, 270],
                    [2, ($gs * 2) + ($h * 3), ($gs * 3) + ($h * 2), 90],
                    [3, $gs + ($h * 2), ($gs * 4) + ($h * 3), 270],
                    [3, $gs + $h, -($h * 2) + $gs, 90],
                    [3, $gs + $h, ($gs * 5) + ($h * 4), 90],
                    [1, $gs, ($gs * 2) - $h, 90],
                    [1, 0, ($gs * 6) + ($h * 5), 90],
                ];
            }

            if ($isSupportedRatio && $ratioInt === 4) {
                return [
                    // Horizontal
                    [0, ($gs * 3) + ($h * 3), $gs, 0],
                    [1, ($gs * 2) + ($h * 2), ($gs * 2) + $h, 0],
                    [2, $gs + $h, ($gs * 3) + ($h * 2), 0],
                    [3, $gs, ($gs * 4) + ($h * 3), 0],
                    [0, -$h, ($gs * 5) + ($h * 4), 0],
                    [0, ($gs * 4) + ($h * 7), ($gs * 2) + ($h * 4), 0],
                    [1, -($h * 2), ($gs * 6) + ($h * 5), 0],
                    [1, ($gs * 3) + ($h * 6), ($gs * 3) + ($h * 5), 0],
                    [2, -($h * 3), ($gs * 7) + ($h * 6), 0],
                    [2, ($gs * 2) + ($h * 5), ($gs * 4) + ($h * 6), 0],
                    [3, ($gs * 2) + $w, ($gs * 5) + ($h * 7), 0, $w + $gs, $h],

                    // Vertical
                    [0, ($gs * 4) + ($h * 7), $gs, 90],
                    [1, ($gs * 3) + ($h * 6), ($gs * 2) + $h, 270],
                    [2, ($gs * 2) + ($h * 5), ($gs * 3) + ($h * 2), 90],
                    [3, ($gs * 2) + ($h * 4), ($gs * 4) + ($h * 3), 270],
                    [0, $gs + ($h * 3), ($gs * 5) + $w, 90],
                    [1, $gs + ($h * 2), ($gs * 6) + ($h * 5), 90],
                    [1, ($gs * 2) + ($h * 2), $gs - ($h * 3), 90],
                    [2, $gs + $h, ($gs * 7) + ($h * 6), 90],
                    [2, $gs + $h, ($gs * 2) - ($h * 2), 90],
                    [3, 0, ($gs * 8) + ($h * 7), 90],
                    [3, 0, ($gs * 3) - $h, 90],
                ];
            }

            if ($isSupportedRatio && $ratioInt === 5) {
                return [
                    // Horizontal
                    [0, ($gs * 4) + ($h * 4), $gs, 0],
                    [1, ($gs * 3) + ($h * 3), ($gs * 2) + $h, 0],
                    [2, ($gs * 2) + ($h * 2), ($gs * 3) + ($h * 2), 0],
                    [3, $gs + $h, ($gs * 4) + ($h * 3), 0],
                    [0, $gs, ($gs * 5) + ($h * 4), 0],
                    [1, -$h, ($gs * 6) + ($h * 5), 0],
                    [1, ($gs * 5) + ($h * 9), ($gs * 2) + ($h * 5), 0],
                    [2, -($h * 2), ($gs * 7) + ($h * 6), 0],
                    [2, ($gs * 4) + ($h * 8), ($gs * 3) + ($h * 6), 0],
                    [3, -($h * 3), ($gs * 8) + ($h * 7), 0],
                    [3, ($gs * 3) + ($h * 7), ($gs * 4) + ($h * 7), 0],
                    [0, -($h * 4), ($gs * 9) + ($h * 8), 0],
                    [0, ($gs * 2) + ($h * 6), ($gs * 5) + ($h * 8), 0],
                    [1, $gs + ($h * 5), ($gs * 6) + ($h * 9), 0, $w + ($gs * 3), $h],

                    // Vertical
                    [0, ($gs * 5) + ($h * 9), $gs, 90],
                    [1, ($gs * 4) + ($h * 8), ($gs * 2) + $h, 90],
                    [2, ($gs * 3) + ($h * 7), ($gs * 3) + ($h * 2), 270],
                    [3, ($gs * 2) + ($h * 6), ($gs * 4) + ($h * 3), 90],
                    [0, $gs + ($h * 5), ($gs * 5) + ($h * 4), 90],
                    [1, $h * 4, ($gs * 6) + ($h * 5), 270],
                    [2, ($h * 3) - $gs, ($gs * 7) + ($h * 6), 90],
                    [2, ($h * 3) + ($gs * 3), $gs - ($h * 4), 90],
                    [3, ($h * 2) - ($gs * 2), ($gs * 8) + ($h * 7), 90],
                    [3, ($h * 2) + ($gs * 2), ($gs * 2) - ($h * 3), 90],
                    [1, $gs, ($gs * 10) + ($h * 9), 90],
                    [1, $gs, ($gs * 4) - $h, 90],
                    [0, $h - ($gs * 3), ($gs * 9) + ($h * 8), 270],
                    [0, $h + ($gs * 2), ($gs * 3) - ($h * 2), 270],
                ];
            }

            if ($isSupportedRatio && $ratioInt === 6) {
                return [
                    // Horizontal
                    [0, ($gs * 5) + ($h * 5), $gs, 0],
                    [1, ($gs * 4) + ($h * 4), ($gs * 2) + $h, 0],
                    [2, ($gs * 3) + ($h * 3), ($gs * 3) + ($h * 2), 0],
                    [3, ($gs * 2) + ($h * 2), ($gs * 4) + ($h * 3), 0],
                    [0, $gs + $h, ($gs * 5) + ($h * 4), 0],
                    [1, $gs, ($gs * 6) + ($h * 5), 0],
                    [2, $gs - $h, ($gs * 7) + ($h * 6), 0],
                    [2, ($gs * 6) + ($h * 11), ($gs * 2) + ($h * 6), 0],
                    [3, $gs - ($h * 2), ($gs * 8) + ($h * 7), 0],
                    [3, ($gs * 5) + ($h * 10), ($gs * 3) + ($h * 7), 0],
                    [0, $gs - ($h * 3), ($gs * 9) + ($h * 8), 0],
                    [0, ($gs * 4) + ($h * 9), ($gs * 4) + ($h * 8), 0],
                    [1, $gs - ($h * 4), ($gs * 10) + ($h * 9), 0],
                    [1, ($gs * 3) + ($h * 8), ($gs * 5) + ($h * 9), 0],
                    [2, $gs - ($h * 5), ($gs * 11) + ($h * 10), 0],
                    [2, ($gs * 2) + ($h * 7), ($gs * 6) + ($h * 10), 0],
                    [3, $gs + ($h * 6), ($gs * 7) + ($h * 11), 0, $w + ($gs * 4), $h],

                    // Vertical
                    [0, ($gs * 6) + ($h * 11), $gs, 90],
                    [1, ($gs * 5) + ($h * 10), ($gs * 2) + $h, 90],
                    [2, ($gs * 4) + ($h * 9), ($gs * 3) + ($h * 2), 270],
                    [3, ($gs * 3) + ($h * 8), ($gs * 4) + ($h * 3), 90],
                    [0, ($gs * 2) + ($h * 7), ($gs * 5) + ($h * 4), 90],
                    [1, $gs + ($h * 6), ($gs * 6) + ($h * 5), 90],
                    [2, $h * 5, ($gs * 7) + ($h * 6), 270],
                    [3, ($h * 4) - $gs, ($gs * 8) + ($h * 7), 90],
                    [3, ($h * 4) + ($gs * 4), $gs - ($h * 5), 90],
                    [0, ($h * 3) - ($gs * 2), ($gs * 9) + ($h * 8), 90],
                    [0, ($h * 3) + ($gs * 3), ($gs * 2) - ($h * 4), 90],
                    [1, ($h * 2) - ($gs * 3), ($gs * 10) + ($h * 9), 270],
                    [1, ($h * 2) + ($gs * 2), ($gs * 3) - ($h * 3), 270],
                    [3, $gs, ($gs * 12) + ($h * 11), 90],
                    [3, $gs, ($gs * 5) - $h, 90],
                    [2, $h - ($gs * 4), ($gs * 11) + ($h * 10), 90],
                    [2, $h + $gs, ($gs * 4) - ($h * 2), 90],
                ];
            }

            if ($isSupportedRatio && $ratioInt >= 7) {
                $n = $ratioInt;
                $placements = [];

                for ($m = 1; $m <= $n; $m++) {
                    $idx = ($m - 1) % 4;
                    $x = ($n - $m) * ($h + $gs);
                    $y = ($m * $gs) + (($m - 1) * $h);
                    $placements[] = [$idx, $x, $y, 0];
                }

                for ($k = 1; $k <= $n - 1; $k++) {
                    $idx = ($k + 1) % 4;
                    $y = (($n + $k) * $gs) + (($n - 1 + $k) * $h);
                    $placements[] = [$idx, -($h + $gs) * $k, $y, 0];

                    $xR = (($n - $k) * ($h + $gs)) + ($n * $h) + $gs;
                    $yR = (($k + 1) * $gs) + (($n - 1 + $k) * $h);
                    $placements[] = [$idx, $xR, $yR, 0];
                }

                for ($m = 1; $m <= $n; $m++) {
                    $idx = ($m - 1) % 4;
                    $rot = $idx === 2 ? 270 : 90;
                    $x = (($n - $m + 1) * $gs) + (((2 * $n) - $m) * $h);
                    $y = ($m * $gs) + (($m - 1) * $h);
                    $placements[] = [$idx, $x, $y, $rot];
                }

                for ($k = 1; $k <= $n - 1; $k++) {
                    $idx = ($k + 1) % 4;
                    $rot = $idx === 2 ? 270 : 90;

                    $stairFactor = max(1, $n - $k - 1);

                    $xB = (($n - $k) * $h) - (($k - 1) * $gs);
                    $yB = (($n + $k) * $gs) + (($n - 1 + $k) * $h);
                    $placements[] = [$idx, $xB, $yB, $rot];

                    $xT = $stairFactor * ($h + $gs);
                    $yT = ($k * $gs) - ($stairFactor * $h);
                    $placements[] = [$idx, $xT, $yT, $rot];
                }

                if ($n >= 5) {
                    $bridgeIdx = ($n % 2 === 0) ? 3 : 1;
                    $bridgeW = $w + (($n - 2) * $gs);
                    $bridgeY = (($n + 1) * $gs) + (((2 * $n) - 1) * $h);
                    $placements[] = [$bridgeIdx, $gs + ($n * $h), $bridgeY, 0, $bridgeW, $h];
                }

                $endIdx = ($n % 2 === 0) ? 3 : 1;
                $endYBottom = (2 * $n * $gs) + (((2 * $n) - 1) * $h);
                $endYTop = (($n - 1) * $gs) - $h;
                $placements[] = [$endIdx, $gs, $endYBottom, 90];
                $placements[] = [$endIdx, $gs, $endYTop, 90];

                return $placements;
            }

            if (abs($ratio - 2.0) < 0.00001) {
                return [
                    [0, ($gs * 2) + $h, $gs, 0],
                    [1, $gs, ($gs * 2) + $h, 0],
                    [2, -$h, ($gs * 3) + ($h * 2), 0],
                    [2, ($gs * 3) + ($h * 3), ($gs * 2) + ($h * 2), 0],
                    [3, ($gs * 2) + $w, ($gs * 3) + ($h * 3), 0],
                    [0, ($gs * 3) + ($h * 3), $gs, 90],
                    [1, ($gs * 2) + $w, ($gs * 2) + $h, 270],
                    [2, $gs + $h, ($gs * 3) + ($h * 2), 90],
                    [3, $gs, ($gs * 4) + ($h * 3), 90],
                    [3, $gs, -$h + $gs, 90],
                ];
            }

            return [
                [0, $h + $gs, 0, 0],
                [1, $h + $w + (2 * $gs), 0, 90],
                [2, 0, $h + $gs, 0],
                [3, $h + $gs, $h + $gs, 90],
                [0, $h + $gs, (2 * $h) + (2 * $gs), 0],
                [1, (2 * $h) + (2 * $gs), $h + $gs, 90],
                [2, (2 * $h) + (2 * $gs), (2 * $h) + (2 * $gs), 0],
                [3, (3 * $h) + (3 * $gs), $h + $gs, 90],
            ];
        case 'hexagon':
            // Column 1 (leftmost): grout, tile shifted half off-canvas, grout, tile shifted half off-canvas, grout, tile shifted half off-canvas
            $halfW = intdiv($w, 2);
            // for a regular hexagon (flat top/bottom, pointed left/right) the flat edge spans the middle half of the width
            $hexInset = intdiv($w, 4);
            $col1X = -$halfW;
            $col1Y1 = $gs;
            $col1Y2 = $col1Y1 + $h + $gs;
            $col1Y3 = $col1Y2 + $h + $gs;

            // Column 2: top half cut off tile, grout, tile, grout, tile, grout, bottom half cut off tile
            // column 2's tip lines up with where column 1's flat edge ends, plus one grout line
            $col2X = ($halfW - $hexInset) + $gs;
            // the vertical stagger is half of the full repeating period (tile + grout), not just half the tile
            $halfPeriod = intdiv($h + $gs, 2);
            $col2Y1 = $halfPeriod - $h;
            $col2Y2 = $col2Y1 + $h + $gs;
            $col2Y3 = $col2Y2 + $h + $gs;
            $col2Y4 = $col2Y3 + $h + $gs;

            // Column 3: same rhythm as column 1, but full width since it's not an edge column
            // its tip lines up with where column 2's flat edge ends, plus one grout line
            $col3X = $col2X + ($w - $hexInset) + $gs;
            $col3Y1 = $gs;
            $col3Y2 = $col3Y1 + $h + $gs;
            $col3Y3 = $col3Y2 + $h + $gs;

            // Column 4: same rhythm as column 2, positioned off column 3 the same way column 2 was off column 1
            $col4X = $col3X + ($w - $hexInset) + $gs;
            $col4Y1 = $halfPeriod - $h;
            $col4Y2 = $col4Y1 + $h + $gs;
            $col4Y3 = $col4Y2 + $h + $gs;
            $col4Y4 = $col4Y3 + $h + $gs;

            // Column 5 (rightmost): mirror of column 1, cropped to its left half by the canvas edge
            $col5X = $col4X + ($w - $hexInset) + $gs;
            $col5Y1 = $gs;
            $col5Y2 = $col5Y1 + $h + $gs;
            $col5Y3 = $col5Y2 + $h + $gs;

            return [
                [0, $col1X, $col1Y1, 0],
                [1, $col1X, $col1Y2, 0],
                [2, $col1X, $col1Y3, 0],
                [3, $col2X, $col2Y1, 0],
                [0, $col2X, $col2Y2, 0],
                [1, $col2X, $col2Y3, 0],
                [2, $col2X, $col2Y4, 0],
                [0, $col3X, $col3Y1, 0],
                [1, $col3X, $col3Y2, 0],
                [2, $col3X, $col3Y3, 0],
                [3, $col4X, $col4Y1, 0],
                [0, $col4X, $col4Y2, 0],
                [1, $col4X, $col4Y3, 0],
                [2, $col4X, $col4Y4, 0],
                [0, $col5X, $col5Y1, 0],
                [1, $col5X, $col5Y2, 0],
                [2, $col5X, $col5Y3, 0],
            ];
        default:
            return [
                [0, 0, 0, 0],
                [1, $w + $gs, 0, 0],
                [2, 0, $h + $gs, 0],
                [3, $w + $gs, $h + $gs, 0],
            ];
    }
}

function fixed_canvas_size(string $layoutType, int $w, int $h, int $gs): ?array
{
    $ratio = $h > 0 ? ($w / $h) : 1.0;
    $ratioInt = (int)round($ratio);
    $isSupportedRatio = abs($ratio - $ratioInt) < 0.00001 && $ratioInt >= 2 && $ratioInt <= 10;

    switch ($layoutType) {
        case 'stacked':
        case 'vertStacked':
            return [($w * 2) + ($gs * 2), ($h * 2) + ($gs * 2)];
        case 'brickBond':
        case 'vertBrick':
            return [($w * 2) + ($gs * 2), ($h * 2) + ($gs * 2)];
        case 'third':
        case 'vertThird':
            return [$w + $gs, ($h * 3) + ($gs * 3)];
        case 'quarter':
        case 'vertQuarter':
            return [$w + $gs, ($h * 4) + ($gs * 4)];
        case 'basketWeave':
            if ($isSupportedRatio && $ratioInt === 3) {
                return [($w * 2) + ($gs * 4), ($h * 6) + $gs];
            }
            if ($isSupportedRatio) {
                return [($w * 2) + ($gs * ($ratioInt + 1)), ($w * 2) + ($gs * ($ratioInt + 1))];
            }
            if (abs($ratio - 2.0) < 0.00001) {
                return [($w * 2) + ($gs * 3), ($w * 2) + ($gs * 3)];
            }
            if (abs($ratio - 3.0) < 0.00001) {
                return [($w * 2) + ($gs * 4), ($h * 6) + $gs];
            }
            if (abs($ratio - 4.0) < 0.00001) {
                return [($w * 2) + ($gs * 5), ($w * 2) + ($gs * 5)];
            }
            if (abs($ratio - 5.0) < 0.00001) {
                return [($w * 2) + ($gs * 6), ($w * 2) + ($gs * 6)];
            }
            if (abs($ratio - 6.0) < 0.00001) {
                return [($w * 2) + ($gs * 7), ($w * 2) + ($gs * 7)];
            }
            return null;
        case 'herringbone':
            if ($isSupportedRatio && $ratioInt >= 3) {
                return [($w * 2) + ($gs * $ratioInt), ($w * 2) + ($gs * $ratioInt)];
            }
            if ($isSupportedRatio && $ratioInt === 2) {
                return [($w * 2) + $gs, ($w * 2) + $gs];
            }
            if (abs($ratio - 3.0) < 0.00001) {
                return [($w * 2) + ($gs * 3), ($w * 2) + ($gs * 3)];
            }
            if (abs($ratio - 4.0) < 0.00001) {
                return [($w * 2) + ($gs * 4), ($w * 2) + ($gs * 4)];
            }
            if (abs($ratio - 5.0) < 0.00001) {
                return [($w * 2) + ($gs * 5), ($w * 2) + ($gs * 5)];
            }
            if (abs($ratio - 6.0) < 0.00001) {
                return [($w * 2) + ($gs * 6), ($w * 2) + ($gs * 6)];
            }
            return [($w * 2) + $gs, ($w * 2) + $gs];
        case 'hexagon':
            // final size: 5 columns, with columns 1 and 5 cropped to a half-width mirror at each edge
            $halfW = intdiv($w, 2);
            $hexInset = intdiv($w, 4);
            $col2X = ($halfW - $hexInset) + $gs;
            $col3X = $col2X + ($w - $hexInset) + $gs;
            $col4X = $col3X + ($w - $hexInset) + $gs;
            $col5X = $col4X + ($w - $hexInset) + $gs;
            return [$col5X + $halfW, ($h * 3) + ($gs * 3)];
        default:
            return null;
    }
}

function output_svg_fallback(array $tmpFiles, string $tileName, string $layoutType, int $tileWidth, int $tileHeight, int $groutSize, string $groutHex): never
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $images = [];
    foreach ($tmpFiles as $tmp) {
        if (!is_string($tmp) || $tmp === '' || !is_uploaded_file($tmp)) {
            continue;
        }

        $content = file_get_contents($tmp);
        if ($content === false || $content === '') {
            continue;
        }

        $mime = $finfo->file($tmp) ?: 'application/octet-stream';
        if (strpos($mime, 'image/') !== 0) {
            continue;
        }

        $images[] = 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    if (count($images) === 0) {
        fail_request('No valid images were uploaded.');
    }

    while (count($images) < 4) {
        $images[] = $images[0];
    }

    $placements = build_placements($layoutType, $tileWidth, $tileHeight, $groutSize);
    $fixed = fixed_canvas_size($layoutType, $tileWidth, $tileHeight, $groutSize);

    if ($fixed !== null) {
        [$canvasWidth, $canvasHeight] = $fixed;
        $offsetX = 0;
        $offsetY = 0;
    } else {
        $bounds = [
            'minX' => PHP_INT_MAX,
            'minY' => PHP_INT_MAX,
            'maxX' => PHP_INT_MIN,
            'maxY' => PHP_INT_MIN,
        ];

        foreach ($placements as $placement) {
            $tileIndex = $placement[0];
            $x = $placement[1];
            $y = $placement[2];
            $deg = $placement[3] ?? 0;
            $customW = $placement[4] ?? null;
            $customH = $placement[5] ?? null;

            if (is_int($customW) && is_int($customH)) {
                if ($deg % 180 === 0) {
                    $tw = $customW;
                    $th = $customH;
                } else {
                    $tw = $customH;
                    $th = $customW;
                }
            } else {
                $tw = ($deg % 180 === 0) ? $tileWidth : $tileHeight;
                $th = ($deg % 180 === 0) ? $tileHeight : $tileWidth;
            }

            $bounds['minX'] = min($bounds['minX'], $x);
            $bounds['minY'] = min($bounds['minY'], $y);
            $bounds['maxX'] = max($bounds['maxX'], $x + $tw);
            $bounds['maxY'] = max($bounds['maxY'], $y + $th);
        }

        $padding = max(1, $groutSize);
        $canvasWidth = max(1, ($bounds['maxX'] - $bounds['minX']) + ($padding * 2));
        $canvasHeight = max(1, ($bounds['maxY'] - $bounds['minY']) + ($padding * 2));
        $offsetX = -$bounds['minX'] + $padding;
        $offsetY = -$bounds['minY'] + $padding;
    }

    $parts = [];
    $parts[] = '<?xml version="1.0" encoding="UTF-8"?>';
    $parts[] = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $canvasWidth . '" height="' . $canvasHeight . '" viewBox="0 0 ' . $canvasWidth . ' ' . $canvasHeight . '">';
    $parts[] = '<rect x="0" y="0" width="' . $canvasWidth . '" height="' . $canvasHeight . '" fill="' . htmlspecialchars($groutHex, ENT_QUOTES, 'UTF-8') . '" />';

    foreach ($placements as $placement) {
        $tileIndex = $placement[0];
        $x = $placement[1];
        $y = $placement[2];
        $deg = $placement[3] ?? 0;
        $customW = $placement[4] ?? null;
        $customH = $placement[5] ?? null;
        $href = htmlspecialchars($images[$tileIndex], ENT_QUOTES, 'UTF-8');
        $dx = $x + $offsetX;
        $dy = $y + $offsetY;

        $renderW = is_int($customW) ? $customW : $tileWidth;
        $renderH = is_int($customH) ? $customH : $tileHeight;

        if ($deg % 360 === 0) {
            $parts[] = '<image href="' . $href . '" x="' . $dx . '" y="' . $dy . '" width="' . $renderW . '" height="' . $renderH . '" preserveAspectRatio="none" />';
            continue;
        }

        if ($deg % 360 === 90) {
            $parts[] = '<g transform="translate(' . ($dx + $renderH) . ',' . $dy . ') rotate(90)"><image href="' . $href . '" x="0" y="0" width="' . $renderW . '" height="' . $renderH . '" preserveAspectRatio="none" /></g>';
            continue;
        }

        if ($deg % 360 === 270) {
            $parts[] = '<g transform="translate(' . $dx . ',' . ($dy + $renderW) . ') rotate(270)"><image href="' . $href . '" x="0" y="0" width="' . $renderW . '" height="' . $renderH . '" preserveAspectRatio="none" /></g>';
            continue;
        }

        $parts[] = '<image href="' . $href . '" x="' . $dx . '" y="' . $dy . '" width="' . $renderW . '" height="' . $renderH . '" preserveAspectRatio="none" />';
    }

    $parts[] = '</svg>';

    $layout = layout_name($layoutType);
    $groutText = grout_name($groutHex);
    $downloadName = sprintf('%s (%dx%d) (%s Grout) (%s).svg', $tileName, $tileWidth, $tileHeight, $groutText, $layout);
    $requestId = $GLOBALS['generateLogRequestId'] ?? bin2hex(random_bytes(16));
    generate_log_write(generate_log_record($requestId, 'generated'));

    header('Content-Type: image/svg+xml');
    header('X-Generation-Log-Id: ' . $requestId);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo implode('', $parts);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail_request('Method not allowed', 405);
}

$generateLogRequestId = bin2hex(random_bytes(16));
$GLOBALS['generateLogRequestId'] = $generateLogRequestId;
$tileName = safe_filename((string)($_POST['tileName'] ?? 'tile_image_result'));
$layoutType = (string)($_POST['layoutType'] ?? 'stacked');
$tileWidth = max(1, (int)($_POST['tileWidth'] ?? 0));
$tileHeight = max(1, (int)($_POST['tileHeight'] ?? 0));
$groutSize = max(0, (int)($_POST['groutSize'] ?? 0));
$groutHex = (string)($_POST['groutColour'] ?? '#000000');
$GLOBALS['generateLogRecord'] = [
    'ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
    'tile_name' => $tileName,
    'image_file_count' => isset($_FILES['images']['name']) && is_array($_FILES['images']['name']) ? count(array_filter($_FILES['images']['name'])) : 0,
    'tile_size_width' => $tileWidth,
    'tile_size_height' => $tileHeight,
    'layout' => layout_name($layoutType),
    'grout_colour' => $groutHex,
    'grout_size' => $groutSize,
];

if ($tileWidth < 1 || $tileHeight < 1) {
    fail_request('Tile width and height must be greater than 0.');
}

if ($layoutType === 'herringbone' && ($tileWidth / $tileHeight) > 6) {
    fail_request('Herringbone currently supports ratios up to 6:1. Higher ratios are temporarily disabled.');
}

$tmpFiles = $_FILES['images']['tmp_name'] ?? [];
if (!is_array($tmpFiles)) {
    $tmpFiles = [$tmpFiles];
}

if (!isset($_FILES['images'])) {
    fail_request('At least one image is required.');
}

if (!extension_loaded('gd')) {
    output_svg_fallback($tmpFiles, $tileName, $layoutType, $tileWidth, $tileHeight, $groutSize, $groutHex);
}

$images = [];
foreach ($tmpFiles as $tmp) {
    if (!is_string($tmp) || $tmp === '' || !is_uploaded_file($tmp)) {
        continue;
    }

    $content = file_get_contents($tmp);
    if ($content === false) {
        continue;
    }

    $img = @imagecreatefromstring($content);
    if ($img !== false) {
        imagealphablending($img, true);
        imagesavealpha($img, true);
        $images[] = $img;
    }
}

if (count($images) === 0) {
    fail_request('No valid images were uploaded.');
}

while (count($images) < 4) {
    $images[] = $images[0];
}

$tiles = [];
for ($i = 0; $i < 4; $i++) {
    $tiles[$i] = resize_image($images[$i], $tileWidth, $tileHeight);
}

[$r, $g, $b] = hex_to_rgb($groutHex);

$w = $tileWidth;
$h = $tileHeight;
$gs = $groutSize;

$placements = build_placements($layoutType, $w, $h, $gs);

$prepared = [];
$fixed = fixed_canvas_size($layoutType, $w, $h, $gs);

if ($fixed === null) {
    $bounds = [
        'minX' => PHP_INT_MAX,
        'minY' => PHP_INT_MAX,
        'maxX' => PHP_INT_MIN,
        'maxY' => PHP_INT_MIN,
    ];
}

foreach ($placements as $placement) {
    [$tileIndex, $x, $y, $deg] = $placement;
    $customW = $placement[4] ?? null;
    $customH = $placement[5] ?? null;

    if (!isset($tiles[$tileIndex]) || !($tiles[$tileIndex] instanceof GdImage)) {
        fail_request('Pattern generation error: missing tile image for placement index ' . (string)$tileIndex . '.', 500);
    }

    $srcBase = $tiles[$tileIndex];
    if (is_int($customW) && is_int($customH) && $customW > 0 && $customH > 0) {
        $srcBase = resize_image($tiles[$tileIndex], $customW, $customH);
    }

    $src = rotate_tile($srcBase, $deg);
    $tw = imagesx($src);
    $th = imagesy($src);

    $prepared[] = [$src, $x, $y, $tw, $th];

    if ($fixed === null) {
        $bounds['minX'] = min($bounds['minX'], $x);
        $bounds['minY'] = min($bounds['minY'], $y);
        $bounds['maxX'] = max($bounds['maxX'], $x + $tw);
        $bounds['maxY'] = max($bounds['maxY'], $y + $th);
    }
}

if ($fixed !== null) {
    [$canvasWidth, $canvasHeight] = $fixed;
    $offsetX = 0;
    $offsetY = 0;
} else {
    $padding = max(1, $gs);
    $canvasWidth = max(1, ($bounds['maxX'] - $bounds['minX']) + ($padding * 2));
    $canvasHeight = max(1, ($bounds['maxY'] - $bounds['minY']) + ($padding * 2));
    $offsetX = -$bounds['minX'] + $padding;
    $offsetY = -$bounds['minY'] + $padding;
}

$canvas = create_truecolor($canvasWidth, $canvasHeight);
$bg = imagecolorallocatealpha($canvas, $r, $g, $b, 0);
imagefilledrectangle($canvas, 0, 0, $canvasWidth, $canvasHeight, $bg);
imagealphablending($canvas, true);

foreach ($prepared as $item) {
    [$src, $x, $y, $tw, $th] = $item;
    $dx = $x + $offsetX;
    $dy = $y + $offsetY;
    imagecopy($canvas, $src, $dx, $dy, 0, 0, $tw, $th);
}

$ratio = $h > 0 ? ($w / $h) : 1.0;

// Python parity: add herringbone seam-fix grout overlays for specific ratios.
if ($layoutType === 'herringbone' && abs($ratio - 2.0) < 0.00001) {
    $patchColor = imagecolorallocatealpha($canvas, $r, $g, $b, 0);
    $x1 = $h;
    $y1 = $w + ($gs * 2);
    $x2 = $h + intdiv($gs, 2);
    $y2 = ($w * 2) + $gs;
    imagefilledrectangle($canvas, $x1, $y1, $x2, $y2, $patchColor);
}

if ($layoutType === 'herringbone' && abs($ratio - 3.0) < 0.00001) {
    $patchColor = imagecolorallocatealpha($canvas, $r, $g, $b, 0);
    imagefilledrectangle($canvas, $gs + ($h * 4), ($gs * 2) + $h, ($gs * 2) + ($h * 4), ($gs * 3) + ($h * 5), $patchColor);
    imagefilledrectangle($canvas, $h * 2, ($gs * 4) + ($h * 4), $h * 2 + intdiv($gs, 2), ($gs * 4) + ($h * 6), $patchColor);
    imagefilledrectangle($canvas, intdiv($gs, 2) + $h, 0, $gs + $h, ($gs * 2) + ($h * 2), $patchColor);
}

if ($layoutType === 'herringbone' && abs($ratio - 4.0) < 0.00001) {
    $patchColor = imagecolorallocatealpha($canvas, $r, $g, $b, 0);
    imagefilledrectangle($canvas, $gs + ($h * 5), ($gs * 3) + ($h * 3), (int)($gs * 1.5) + ($h * 5), ($gs * 4) + ($h * 7), $patchColor);
    imagefilledrectangle($canvas, $h * 3, ($gs * 4) + ($h * 5), intdiv($gs, 2) + ($h * 3), ($gs * 6) + ($h * 8), $patchColor);
    imagefilledrectangle($canvas, $h * 2, $h * 6, intdiv($gs, 2) + ($h * 2), ($gs * 3) + ($h * 8), $patchColor);
}

if ($layoutType === 'herringbone' && abs($ratio - 5.0) < 0.00001) {
    $patchColor = imagecolorallocatealpha($canvas, $r, $g, $b, 0);
    imagefilledrectangle($canvas, $h * 5, ($gs * 5) + ($h * 4), intdiv($gs, 2) + ($h * 5), ($gs * 6) + ($h * 5), $patchColor);
    imagefilledrectangle($canvas, ($h * 4) - $gs, ($gs * 5) + ($h * 5), ($h * 4) - intdiv($gs, 2), ($gs * 6) + ($h * 6), $patchColor);
    imagefilledrectangle($canvas, ($h * 3) - ($gs * 2), ($gs * 6) + ($h * 6), ($h * 3) - (int)($gs * 1.5), ($gs * 7) + ($h * 7), $patchColor);
    imagefilledrectangle($canvas, ($h * 2) - ($gs * 3), ($gs * 7) + ($h * 7), ($h * 2) - (int)($gs * 2.5), ($gs * 8) + ($h * 8), $patchColor);
    imagefilledrectangle($canvas, $h - ($gs * 4), ($gs * 8) + ($h * 8), $h - (int)($gs * 3.5), ($gs * 9) + ($h * 10), $patchColor);
    imagefilledrectangle($canvas, $h + $gs, 0, $h + (int)($gs * 1.5), ($h * 4) + ($gs * 4), $patchColor);
    imagefilledrectangle($canvas, ($h * 2) + ($gs * 2), 0, ($h * 2) + (int)($gs * 2.5), ($h * 3) + ($gs * 3), $patchColor);
}

if ($layoutType === 'herringbone' && abs($ratio - 6.0) < 0.00001) {
    $patchColor = imagecolorallocatealpha($canvas, $r, $g, $b, 0);
    imagefilledrectangle($canvas, $h * 6, ($gs * 6) + ($h * 5), intdiv($gs, 2) + ($h * 6), ($gs * 6) + ($h * 6), $patchColor);
    imagefilledrectangle($canvas, ($h * 5) - $gs, ($gs * 7) + ($h * 6), ($h * 5) - intdiv($gs, 2), ($gs * 7) + ($h * 7), $patchColor);
    imagefilledrectangle($canvas, ($h * 4) - ($gs * 2), ($gs * 8) + ($h * 7), ($h * 4) - (int)($gs * 1.5), ($gs * 8) + ($h * 8), $patchColor);
    imagefilledrectangle($canvas, ($h * 3) - ($gs * 3), ($gs * 9) + ($h * 8), ($h * 3) - (int)($gs * 2.5), ($gs * 9) + ($h * 9), $patchColor);
    imagefilledrectangle($canvas, ($h * 2) - ($gs * 4), ($gs * 10) + ($h * 9), ($h * 2) - (int)($gs * 3.5), ($gs * 10) + ($h * 10), $patchColor);
    imagefilledrectangle($canvas, $h - ($gs * 5), ($gs * 11) + ($h * 10), $h - (int)($gs * 4.5), ($gs * 12) + ($h * 12), $patchColor);
    imagefilledrectangle($canvas, $h, 0, $h + intdiv($gs, 2), ($gs * 5) + ($h * 5), $patchColor);
}

if ($layoutType === 'herringbone' && abs($ratio - 10.0) < 0.00001) {
    $patchColor = imagecolorallocatealpha($canvas, $r, $g, $b, 0);

    $x1 = $h + $gs;
    $y1 = 0;
    $x2 = $h + (int)round($gs * 1.5);
    $y2 = ($h * 9) + ($gs * 9);
    imagefilledrectangle($canvas, $x1, $y1, $x2, $y2, $patchColor);

    $x3 = $h - ($gs * 9);
    $y3 = ($h * 19) + ($gs * 8);
    $x4 = $h - (int)round($gs * 8.5);
    $y4 = ($h * 20) + ($gs * 8);
    imagefilledrectangle($canvas, $x3, $y3, $x4, $y4, $patchColor);
}

if (in_array($layoutType, ['vertStacked', 'vertBrick', 'vertThird', 'vertQuarter'], true)) {
    $bgRotate = imagecolorallocatealpha($canvas, $r, $g, $b, 0);
    $rot = imagerotate($canvas, 270, $bgRotate);
    imageflip($rot, IMG_FLIP_VERTICAL);
    $canvas = $rot;
}

$layout = layout_name($layoutType);
$groutText = grout_name($groutHex);
$downloadName = sprintf('%s (%dx%d) (%s Grout) (%s).png', $tileName, $tileWidth, $tileHeight, $groutText, $layout);
$requestId = $GLOBALS['generateLogRequestId'] ?? bin2hex(random_bytes(16));
generate_log_write(generate_log_record($requestId, 'generated'));

header('Content-Type: image/png');
header('X-Generation-Log-Id: ' . $requestId);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

imagepng($canvas);
imagedestroy($canvas);
