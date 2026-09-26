# M4 CMS

A lightweight, self-hosted content management system built in PHP with SQLite. M4 CMS ships a fast public blog frontend and a complete admin panel, styled with the **FinSec** design system with light and dark themes.

## Features

- Single-file SQLite database, no external services or dependencies
- FinSec design system with light/dark mode and a single brand accent
- Public frontend: posts, categories, tags, search and pagination
- RSS feed, XML sitemap and custom 404 page
- SEO-friendly URLs
- Admin panel: dashboard KPIs, post management, bulk actions, settings and logs
- Multilingual UI (English / Spanish)
- Inline SVG icons, no external icon libraries

## Requirements

- PHP 8.0+ with the `PDO` and `SQLite3` extensions
- A web server (Apache with `.htaccess`, or Nginx with equivalent rules)
- Write access to the database directory

## Installation

1. Upload the contents of `public_html/` to your web root.
2. Make sure the web server can write to `public_html/db/`.
3. Open `setup.php` in your browser and follow the steps to configure the site and create the admin account.
4. Remove `setup.php` (or restrict access to it) once installation is complete.
5. Sign in to the admin panel to start publishing.

`db/cms.db`, `admin_config.php` and `.env` are never versioned; they are generated at install time.

## Project structure

```
public_html/
├── assets/         Frontend styles (FinSec)
├── db/             Database connection and data functions
├── entrada/        Admin panel (dashboard, posts, settings, logs, login)
├── helpers/        i18n, theme, icons and content helpers
├── templates/      Shared layouts
├── index.php       Public home
├── post.php        Single post
├── create.php      Create post
├── edit.php        Edit post
├── delete.php      Delete post
├── rss.php         RSS feed
├── sitemap.php     XML sitemap
├── 404.php         Error page
├── setup.php       Installer
└── config.php      Bootstrap and configuration
```

## Themes

Two themes are available: `finsec` (light, default) and `finsec-dark` (dark). Switch between them from the header toggle; the choice is persisted per session and in the database.

## License

No license has been specified. All rights reserved unless stated otherwise.
