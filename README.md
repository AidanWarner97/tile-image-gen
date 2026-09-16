# tile-image-gen (PHP)

A PHP-based tile pattern generator for producing downloadable PNG layout sheets from one to four uploaded tile images.

## Stack

- PHP 8+
- GD extension (required)
- HTML/CSS/JavaScript frontend

## Features

- Upload 1 to 4 tile images (missing variants are auto-filled from image 1)
- Adjustable tile dimensions and grout width
- Grout color selection from BAL and UltraTile ranges
- Pattern modes:
  - Horizontal Block / Half / Third / Quarter
  - Vertical Block / Half / Third / Quarter
  - Basket Weave
  - Herringbone
  - Hexagon
- PNG download response with descriptive filename

## Updates

Updates and release notes are stored as Markdown files in the `posts/` directory.

Example file `posts/2026-09-09-my-update.md`:

```markdown
---
title: My Update Title
date: 2026-09-09
author: Aidan Warner
---

Update content in Markdown format...
```

- The latest update is displayed dynamically on the homepage.
- All updates are listed at `/updates/`.
- Individual posts are available at `/updates/post/slug-here`.

## Feedback

Feedback is available at `/feedback.php`. The page shows approved submissions in a table and opens the submission form in a modal.

Cloudflare Turnstile can be enabled for feedback submissions by setting `TURNSTILE_SITE_KEY` and `TURNSTILE_SECRET_KEY` in the server environment. Both values are required; when configured, the widget is shown and every submission is verified server-side.

## Generation logging

Generator activity is written to the MariaDB/MySQL table `tig_generate_log`. Redis is used as the fast queue/cache via the Unix socket in `REDIS_SOCKET` (default `/run/redis/redis.sock`), with `REDIS_HOST`/`REDIS_PORT` as a fallback. `REDIS_PASSWORD` and `REDIS_DATABASE` are also supported. Set `DB_*` and `REDIS_*` in the server environment; `tig_generate_log` is created automatically. A generated response is marked `generated`, validation or processing failures are marked `error`, and the browser's Download action marks `downloaded` as `1`.
