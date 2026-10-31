# M4 CMS

A lightweight, self-hosted content management system built in PHP with SQLite. M4 CMS ships a fast public blog frontend and a complete admin panel, styled with the **FinSec** design system with light and dark themes.

## Features

- Single-file SQLite database, no external services or dependencies
- FinSec design system with light/dark mode and a single brand accent
- Public frontend: posts, categories, tags, search and pagination
- **Post navigation**: each post links to the previous and next publication, so readers can keep browsing without going back to the list
- **Code blocks**: wrap a fragment between `[code]` and `[/code]` in the post content to render it as a collapsible box (with an optional title, `[code=Indicators]`) and a **Copy** button. The text is kept verbatim, so what you copy is exactly what you wrote
- RSS feed, XML sitemap and custom 404 page
- SEO-friendly URLs
- Admin panel: dashboard KPIs, post management, bulk actions, settings and logs
- **Built-in web analytics** (mini-Matomo): server-side tracking with no JavaScript and no third-party service, stored in a separate SQLite database (`db/analytics.db`). Matomo-style dashboard with period picker, visit summary, actions metrics, visits-over-time chart, channel types (including AI assistants), visit log, visitor profiles, devices, browsers, operating systems and searches
- **Login attempt log** with a dedicated tab and manual **IP blocking** (anti brute force): blocked IPs are rejected at the login form
- **Real client IP behind a CDN**: Cloudflare (and any other trusted proxy) is detected automatically, so the logs, the rate limit and the blocked-IP list work with the visitor's real address instead of the CDN edge
- **Search engine verification**: paste the Google Search Console / Bing Webmaster Tools token in the settings and the matching `meta` tag is added to every public page
- **Pinned posts**: pin up to 3 posts to the top of the homepage, with a custom order you can reorder from the admin list
- Optional **Blocks module**: free-form content at the top of the homepage, above the post list (plain text with auto-linked URLs, sanitized basic HTML, or an auto-detected media embed), disabled by default
- Hardened admin: CSRF token on every write, secure session cookies (`HttpOnly`/`SameSite`), login rate limiting, and security headers (CSP, `X-Frame-Options`, …)
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

The **Logs** panel has an *Activity* tab (everything) and a *Login attempts* tab with the successful and failed logins, the client IP and the user agent (bots are flagged). From there you can **block an IP permanently**: blocked IPs are stored in the `banned_ips` table and are rejected by the login form, next to the existing temporary rate limit (8 failed attempts in 15 minutes).

### Client IP behind a CDN or reverse proxy

If the site runs behind Cloudflare (or another proxy), the CMS resolves the **real visitor IP** from `CF-Connecting-IP` / `X-Forwarded-For`. Those headers are only trusted when the direct connection (`REMOTE_ADDR`) belongs to a trusted proxy, so nobody can fake their IP to dodge the rate limit or the blocked-IP list. Cloudflare's published ranges are built in; other proxies can be added in **Settings → Trusted proxies** as IP addresses or CIDR ranges.

Addresses belonging to a CDN edge are shown with a `proxy` badge and **cannot be blocked**: banning one would lock out every visitor sharing that edge.

## Project structure

```
public_html/
├── assets/         Frontend styles (FinSec)
├── db/             Database connection and data functions
│   ├── analytics.php  Web analytics (own SQLite database: db/analytics.db)
│   └── backups/    Update backups (web access denied)
├── entrada/        Admin panel (dashboard, posts, blocks, settings, logs, statistics, updates, login)
├── helpers/        i18n, theme, icons, content, session, auth, CSRF, client IP, analytics and blocks helpers
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

## Statistics

The admin panel includes a lightweight, self-hosted web analytics module (**Statistics**), inspired by Matomo but designed for a small site:

- Tracking is **server-side**: no JavaScript, no third-party requests, no cookies from external services. A **visit** is a 30-minute session (`m4_sid`) and a **visitor** is identified for 90 days (`m4_vid`); without cookies a stable fingerprint is used instead.
- Data is stored in **its own SQLite database** (`public_html/db/analytics.db`), completely independent from the CMS database.
- **Privacy**: no personal data is collected and no third-party service is involved. The **origin IP is stored anonymized by default** (`188.114.96.0`, i.e. the last IPv4 octet and the IPv6 /48 prefix are zeroed, exactly like Matomo); it can be changed to "do not store" or "full IP" in **Settings → Analytics privacy**. Raw rows are purged automatically after 180 days.
- The report mirrors the Matomo dashboard:
  - period picker (**Yesterday, Today, This week, Last week, This month, Last month, This year**, last 7/30/90 days or a custom range),
  - *Visits over time* (line chart with visits and page views),
  - *Visit summary*: visits, unique visitors, actions, max actions in one visit, actions per visit, average visit duration and bounce rate,
  - *Actions – main metrics*: page views, unique page views, searches and unique keywords (site search is tracked),
  - *Overview* cards with the variation against the previous period and a sparkline,
  - *Channel types*: direct, search engines, social networks, **AI assistants** and other websites, with visits, unique visitors, actions, actions per visit, average time and bounce rate,
  - *Visit log*: every session with its date, device, browser, OS, origin IP, entry referrer and the sequence of pages viewed, plus a link to the **visitor profile**,
  - reports for pages, entry pages, searched keywords, referrers, devices, browsers, operating systems, visits per hour and response codes.

The **visitor profile** unfolds **inline under the visit you click**, like a real accordion: the profile is loaded on demand (no page reload) and collapses again with a second click. It shows every visit of that visitor across the whole retention window — date, pages viewed, duration, referrer, origin IP and device — with a *Close profile* button. Without JavaScript the link still opens the profile server-side.

Technical requests (`/favicon.ico`, `/robots.txt`, `apple-touch-icon`, `/.well-known/…`) are **not** tracked: they are browser housekeeping, not visits. The panel and site stylesheets are requested with a `?v=<version>` parameter, so a new release always invalidates the browser cache and the interface never shows an outdated stylesheet.

## Themes

Two themes are available: `finsec` (light, default) and `finsec-dark` (dark). Switch between them from the header toggle; the choice is persisted per session and in the database.

## Updates

The admin panel includes an updater (`entrada/update.php`, under **System → Updates**). It checks the latest release of this repository on GitHub, downloads the release ZIP and synchronises the application files.

- The updater computes a **SHA-256 manifest** of the release (source) and compares it with the installed manifest (`public_html/db/.manifest.json`, destination), so only new or changed files are copied.
- Managed files that no longer exist in the release are **removed**, and obsolete/dangerous utilities (`reset-admin.php`, `install.php`, `cleanup.php`, `setup.php` on installed sites…) are deleted.
- Files that do not belong to the project are never touched.
- A code backup is created in `public_html/db/backups/` before every update and restore.
- `config.php`, `.env`, `admin_config.php` and the SQLite databases (`db/cms.db`, `db/analytics.db`) are **never** overwritten.
- If the admin folder has been renamed, the updater remaps the package's `entrada/` folder to the current admin path automatically.
- The repository is public, so no token is required. To raise the GitHub API rate limit, set a `GITHUB_TOKEN` environment variable.
- The updater needs a ZIP extractor: the `zip` extension (`ZipArchive`), `PharData`, or the `unzip` binary.

## Tests

There are no runtime dependencies. CI (`.github/workflows/ci.yml`) runs `php -l`
on every PHP file and a smoke test that exercises the data layer on a temporary
SQLite database:

```bash
php tests/smoke.php
```

`tests/` and `.github/` live outside `public_html/`, so they are neither part of
the web root nor synced by the updater.

## License

No license has been specified. All rights reserved unless stated otherwise.
