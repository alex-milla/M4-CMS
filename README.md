# M4 CMS

A lightweight, self-hosted content management system built in PHP with SQLite. M4 CMS ships a fast public blog frontend and a complete admin panel, styled with the **FinSec** design system with light and dark themes.

## Features

- Single-file SQLite database, no external services or dependencies
- FinSec design system with light/dark mode and a single brand accent
- Public frontend: posts, categories, tags, search and pagination
- RSS feed, XML sitemap and custom 404 page
- SEO-friendly URLs
- Admin panel: dashboard KPIs, post management, bulk actions, settings and logs
- Optional **Blocks module**: free-form content on the homepage footer (plain text with auto-linked URLs, sanitized basic HTML, or an auto-detected media embed), disabled by default
- One-click updates from GitHub Releases with SHA-256 manifests, automatic backups and restore
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
4. Remove `setup.php` (or restrict access to it) once installation is complete. It also deletes itself after a successful install.
5. Sign in to the admin panel to start publishing.

`db/cms.db`, `admin_config.php` and `.env` are never versioned; they are generated at install time.

## Security

The root `.htaccess` blocks direct web access to sensitive files:

- dotfiles such as `.env`, `.setup_completed` and `db/.manifest.json`
- database files (`*.db`, `*.sqlite*`, `*.bak`)
- `admin_config.php` and one-off utilities (`install.php`, `cleanup.php`, `fix-bom.php`)
- internal directories (`db/`, `helpers/`, `templates/`) and dangerous dotdirs (`.git`, …), keeping `.well-known` available for SSL

`db/backups/` ships with its own deny rules. If you use Nginx instead of Apache, replicate these rules in the server config.

Requests to **existing but blocked** resources keep the HTTP **403** status, and **non-existent** URLs (including unknown post slugs) return a real HTTP **404**. Both are rendered with the site's own error page (`404.php`), instead of the server's default page.

There are **no default credentials**. The admin account is created during `setup.php` and stored in `.env` / `admin_config.php`; there is no built-in username or password.

## Project structure

```
public_html/
├── assets/         Frontend styles (FinSec)
├── db/             Database connection and data functions
│   └── backups/    Update backups (web access denied)
├── entrada/        Admin panel (dashboard, posts, blocks, settings, logs, updates, login)
├── helpers/        i18n, theme, icons, content and blocks helpers
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
├── VERSION         Installed version
└── config.php      Bootstrap and configuration
```

## Themes

Two themes are available: `finsec` (light, default) and `finsec-dark` (dark). Switch between them from the header toggle; the choice is persisted per session and in the database.

## Updates

The admin panel includes an updater (`entrada/update.php`, under **System → Updates**). It checks the latest release of this repository on GitHub, downloads the release ZIP and synchronises the application files.

- The updater computes a **SHA-256 manifest** of the release (source) and compares it with the installed manifest (`public_html/db/.manifest.json`, destination), so only new or changed files are copied.
- Managed files that no longer exist in the release are **removed**, and obsolete/dangerous utilities (`reset-admin.php`, `install.php`, `cleanup.php`, `setup.php` on installed sites…) are deleted.
- Files that do not belong to the project are never touched.
- A code backup is created in `public_html/db/backups/` before every update and restore.
- `config.php`, `.env`, `admin_config.php` and the SQLite database are **never** overwritten.
- If the admin folder has been renamed, the updater remaps the package's `entrada/` folder to the current admin path automatically.
- The repository is public, so no token is required. To raise the GitHub API rate limit, set a `GITHUB_TOKEN` environment variable.
- The updater needs a ZIP extractor: the `zip` extension (`ZipArchive`), `PharData`, or the `unzip` binary.

## License

No license has been specified. All rights reserved unless stated otherwise.
