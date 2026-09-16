<?php
declare(strict_types=1);

const POSTS_DIR = __DIR__ . '/../posts';

/**
 * Escape string for safe HTML output.
 */
function escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a URL-friendly slug from text.
 */
function slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'post';
}

/**
 * Extract an excerpt from markdown or text.
 */
function make_excerpt(string $content, int $limit = 180): string
{
    // Remove code blocks, headers, and frontmatter
    $clean = preg_replace('/```[\s\S]*?```/', '', $content) ?? $content;
    $clean = preg_replace('/^#+\s+.*/m', '', $clean) ?? $clean;
    $clean = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $clean) ?? $clean;
    $clean = preg_replace('/[*_~`>]/', '', $clean) ?? $clean;
    $clean = preg_replace('/^[\*\-\+]\s+/m', '', $clean) ?? $clean;
    $clean = preg_replace('/^\d+\.\s+/m', '', $clean) ?? $clean;
    $clean = strip_tags($clean);
    $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? '');

    $len = function_exists('mb_strlen') ? mb_strlen($clean, 'UTF-8') : strlen($clean);
    if ($len <= $limit) {
        return $clean;
    }

    $truncated = function_exists('mb_substr') ? mb_substr($clean, 0, $limit, 'UTF-8') : substr($clean, 0, $limit);
    $lastSpace = function_exists('mb_strrpos') ? mb_strrpos($truncated, ' ', 0, 'UTF-8') : strrpos($truncated, ' ');
    if ($lastSpace !== false && $lastSpace > ($limit - 30)) {
        $truncated = function_exists('mb_substr') ? mb_substr($truncated, 0, $lastSpace, 'UTF-8') : substr($truncated, 0, $lastSpace);
    }

    return rtrim($truncated, " \t\n\r\0\x0B.,!?:;") . '...';
}

/**
 * Lightweight Markdown to HTML parser.
 */
function render_markdown_list(array $lines, int $index = 0, ?int $indent = null): array
{
    $html = '';
    $currentType = null;
    $openedList = false;

    while ($index < count($lines)) {
        if (trim($lines[$index]) === '') {
            $index++;
            continue;
        }

        if (!preg_match('/^(\s*)([-*+]|\d+\.)\s+(.+)$/', $lines[$index], $match)) {
            break;
        }

        $itemIndent = strlen(str_replace("\t", '    ', $match[1]));
        if ($indent === null) {
            $indent = $itemIndent;
        }
        if ($itemIndent < $indent) {
            break;
        }
        if ($itemIndent > $indent) {
            break;
        }

        $type = is_numeric(rtrim($match[2], '.')) ? 'ol' : 'ul';
        if ($currentType !== $type) {
            if ($openedList) {
                $html .= '</' . $currentType . '>';
            }
            $currentType = $type;
            $html .= '<' . $currentType . '>';
            $openedList = true;
        }

        $html .= '<li>' . format_inline_markdown(trim($match[3]));
        $index++;

        if ($index < count($lines) && preg_match('/^(\s*)([-*+]|\d+\.)\s+(.+)$/', $lines[$index], $childMatch)) {
            $childIndent = strlen(str_replace("\t", '    ', $childMatch[1]));
            if ($childIndent > $indent) {
                [$childHtml, $index] = render_markdown_list($lines, $index, $childIndent);
                $html .= $childHtml;
            }
        }

        $html .= '</li>';
    }

    if ($openedList) {
        $html .= '</' . $currentType . '>';
    }

    return [$html, $index];
}

function parse_markdown(string $markdown): string
{
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $markdown));
    $html = [];
    $inCodeBlock = false;
    $codeBlockLang = '';
    $codeBlockContent = [];
    $inList = false;
    $listType = 'ul'; // 'ul' or 'ol'
    $inBlockquote = false;
    $blockquoteLines = [];
    $paragraph = [];

    $flushParagraph = function () use (&$html, &$paragraph) {
        if (!empty($paragraph)) {
            $text = implode("\n", $paragraph);
            $text = format_inline_markdown($text);
            $html[] = '<p>' . nl2br($text) . '</p>';
            $paragraph = [];
        }
    };

    $flushList = function () use (&$html, &$inList, &$listType) {
        if ($inList) {
            $html[] = '</' . $listType . '>';
            $inList = false;
        }
    };

    $flushBlockquote = function () use (&$html, &$inBlockquote, &$blockquoteLines) {
        if ($inBlockquote) {
            $text = implode("\n", $blockquoteLines);
            $html[] = '<blockquote>' . parse_markdown($text) . '</blockquote>';
            $inBlockquote = false;
            $blockquoteLines = [];
        }
    };

    for ($lineIndex = 0; $lineIndex < count($lines); $lineIndex++) {
        $line = $lines[$lineIndex];
        // Handle code blocks (```)
        if (preg_match('/^```(.*)$/', $line, $matches)) {
            if ($inCodeBlock) {
                $code = implode("\n", $codeBlockContent);
                $class = $codeBlockLang !== '' ? ' class="language-' . escape_html($codeBlockLang) . '"' : '';
                $html[] = '<pre><code' . $class . '>' . escape_html($code) . '</code></pre>';
                $inCodeBlock = false;
                $codeBlockContent = [];
                $codeBlockLang = '';
            } else {
                $flushParagraph();
                $flushList();
                $flushBlockquote();
                $inCodeBlock = true;
                $codeBlockLang = trim($matches[1]);
            }
            continue;
        }

        if ($inCodeBlock) {
            $codeBlockContent[] = $line;
            continue;
        }

        // Empty line flushes paragraph / blockquote / list
        if (trim($line) === '') {
            $flushParagraph();
            $flushList();
            $flushBlockquote();
            continue;
        }

        // Blockquotes (> quote)
        if (preg_match('/^>\s?(.*)$/', $line, $matches)) {
            $flushParagraph();
            $flushList();
            $inBlockquote = true;
            $blockquoteLines[] = $matches[1];
            continue;
        } elseif ($inBlockquote) {
            $flushBlockquote();
        }

        // Horizontal rules (---, ***, ___)
        if (preg_match('/^(?:-{3,}|\*{3,}|_{3,})\s*$/', $line)) {
            $flushParagraph();
            $flushList();
            $html[] = '<hr>';
            continue;
        }

        // Headings (# Heading)
        if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
            $flushParagraph();
            $flushList();
            $level = strlen($matches[1]);
            $title = format_inline_markdown(trim($matches[2]));
            $html[] = sprintf('<h%d>%s</h%d>', $level, $title, $level);
            continue;
        }

        // Ordered and unordered lists, including indented child lists.
        if (preg_match('/^\s*(?:[\*\-\+]|\d+\.)\s+.+$/', $line)) {
            $flushParagraph();
            $listLines = [];
            $listIndex = $lineIndex;
            while ($listIndex < count($lines)) {
                $candidate = $lines[$listIndex];
                if (trim($candidate) === '' || preg_match('/^\s+(?:[\*\-\+]|\d+\.)\s+.+$/', $candidate) || preg_match('/^(?:[\*\-\+]|\d+\.)\s+.+$/', $candidate)) {
                    $listLines[] = $candidate;
                    $listIndex++;
                    continue;
                }
                break;
            }
            [$listHtml, $nextIndex] = render_markdown_list($listLines);
            $html[] = $listHtml;
            $lineIndex += max(0, $nextIndex - 1);
            continue;
        }

        // End list if non-list line encountered
        $flushList();

        // Regular paragraph text
        $paragraph[] = $line;
    }

    $flushParagraph();
    $flushList();
    $flushBlockquote();

    if ($inCodeBlock) {
        $code = implode("\n", $codeBlockContent);
        $html[] = '<pre><code>' . escape_html($code) . '</code></pre>';
    }

    return implode("\n", $html);
}

/**
 * Format inline Markdown elements (bold, italic, code, links, images).
 */
function format_inline_markdown(string $text): string
{
    // Protect raw HTML/special chars before parsing markdown inline
    $text = escape_html($text);

    // Images: ![alt](url)
    $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)]+)\)/', function ($m) {
        $source = trim($m[2]);
        if (!preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/|#)/i', $source)) {
            $source = '/updates/images/' . rawurlencode(basename($source));
        }
        return '<img src="' . $source . '" alt="' . $m[1] . '">';
    }, $text) ?? $text;

    // Links: [text](url)
    $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($m) {
        return '<a href="' . $m[2] . '">' . $m[1] . '</a>';
    }, $text) ?? $text;

    // Inline code: `code`
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text) ?? $text;

    // Bold + Italic: ***text*** or ___text___
    $text = preg_replace('/\*\*\*([^*]+)\*\*\*/', '<strong><em>$1</em></strong>', $text) ?? $text;
    $text = preg_replace('/___([^_]+)___/', '<strong><em>$1</em></strong>', $text) ?? $text;

    // Bold: **text** or __text__
    $text = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text) ?? $text;
    $text = preg_replace('/__([^_]+)__/', '<strong>$1</strong>', $text) ?? $text;

    // Italic: *text* or _text_
    $text = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $text) ?? $text;
    $text = preg_replace('/_([^_]+)_/', '<em>$1</em>', $text) ?? $text;

    // Strikethrough: ~~text~~
    $text = preg_replace('/~~([^~]+)~~/', '<del>$1</del>', $text) ?? $text;

    return $text;
}

/**
 * Parse a markdown post file with optional frontmatter.
 */
function parse_post_file(string $filePath): ?array
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        return null;
    }

    $rawContent = file_get_contents($filePath);
    if ($rawContent === false) {
        return null;
    }

    $filename = basename($filePath);
    $filenameWithoutExt = preg_replace('/\.(md|markdown)$/i', '', $filename) ?? $filename;

    // Extract date prefix from filename if available (e.g. 2026-09-12-title.md)
    $fileDate = '';
    $slugCandidate = $filenameWithoutExt;
    if (preg_match('/^(\d{4}-\d{2}-\d{2})[-_](.+)$/', $filenameWithoutExt, $m)) {
        $fileDate = $m[1];
        $slugCandidate = $m[2];
    }

    $metadata = [];
    $body = $rawContent;

    // Parse YAML frontmatter if present
    if (preg_match('/^---\s*\n([\s\S]*?)\n---\s*\n?([\s\S]*)$/', $rawContent, $matches)) {
        $frontmatterText = $matches[1];
        $body = $matches[2];

        foreach (explode("\n", $frontmatterText) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, ':')) {
                continue;
            }
            [$k, $v] = explode(':', $line, 2);
            $k = strtolower(trim($k));
            $v = trim($v, " \t\n\r\0\x0B\"'");
            $metadata[$k] = $v;
        }
    }

    // Title: frontmatter -> first heading -> filename
    $title = $metadata['title'] ?? '';
    if ($title === '') {
        if (preg_match('/^#\s+(.+)$/m', $body, $titleMatch)) {
            $title = trim($titleMatch[1]);
            // Remove the first heading from body to avoid duplicate display
            $body = preg_replace('/^#\s+.+\n?/m', '', $body, 1) ?? $body;
        } else {
            $title = ucwords(str_replace(['-', '_'], ' ', $slugCandidate));
        }
    }

    // Date: frontmatter -> filename date -> file modified time
    $date = $metadata['date'] ?? '';
    if ($date === '') {
        $date = $fileDate !== '' ? $fileDate : gmdate('Y-m-d', filemtime($filePath));
    }

    // Author
    $author = $metadata['author'] ?? 'Aidan Warner';

    // Slug: frontmatter -> filename slug
    $slug = $metadata['slug'] ?? '';
    if ($slug === '') {
        $slug = slugify($slugCandidate);
    } else {
        $slug = slugify($slug);
    }

    // Published status: default true, unless explicitly false/draft
    $published = true;
    if (isset($metadata['published'])) {
        $val = strtolower((string)$metadata['published']);
        $published = !in_array($val, ['false', '0', 'no', 'draft', 'off'], true);
    }
    if (isset($metadata['draft'])) {
        $val = strtolower((string)$metadata['draft']);
        if (in_array($val, ['true', '1', 'yes', 'on'], true)) {
            $published = false;
        }
    }

    // Excerpt: frontmatter -> auto generated
    $excerpt = $metadata['excerpt'] ?? '';
    if ($excerpt === '') {
        $excerpt = make_excerpt($body);
    }

    $htmlContent = parse_markdown($body);

    return [
        'id' => $slug,
        'slug' => $slug,
        'title' => $title,
        'date' => $date,
        'author' => $author,
        'published' => $published,
        'excerpt' => $excerpt,
        'content_raw' => $body,
        'content_html' => $htmlContent,
        'file_path' => $filePath,
    ];
}

/**
 * Get all published posts from the posts directory sorted by date descending.
 */
function get_all_posts(string $postsDir = POSTS_DIR): array
{
    if (!is_dir($postsDir)) {
        return [];
    }

    $files = glob($postsDir . '/*.{md,markdown}', GLOB_BRACE) ?: [];
    $posts = [];

    foreach ($files as $file) {
        $post = parse_post_file($file);
        $publishAt = $post !== null ? strtotime((string)$post['date']) : false;
        if ($post !== null && !empty($post['published']) && ($publishAt === false || $publishAt <= time())) {
            $posts[] = $post;
        }
    }

    usort($posts, function (array $a, array $b): int {
        $dateCmp = strcmp((string)$b['date'], (string)$a['date']);
        if ($dateCmp !== 0) {
            return $dateCmp;
        }
        return strcmp((string)$b['slug'], (string)$a['slug']);
    });

    return $posts;
}

/**
 * Find a specific post by slug.
 */
function get_post_by_slug(string $slug, string $postsDir = POSTS_DIR): ?array
{
    $slug = slugify($slug);
    $posts = get_all_posts($postsDir);

    foreach ($posts as $post) {
        if ($post['slug'] === $slug) {
            return $post;
        }
    }

    return null;
}
