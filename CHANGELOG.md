# Changelog

All notable changes to KronosCMS are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.3] — 2026-04-25

### Added
- WordPress-style Posts and Pages split with status views for published, draft, scheduled, private, and archived content.
- Menu editor with header/footer locations, post/page linking, drag ordering, nested parent selection, and public dropdown rendering.
- Taxonomy manager for categories and tags, plus category/tag assignment in the content editor.
- Media Library with uploads, DB-backed attachment metadata, attachment detail pages, and usage tracking.
- Featured images for posts/pages with public theme rendering.
- Editor improvements: publish date, preview links, revision snapshots, local autosave, and quick insert buttons.
- Content list table bulk status actions and per-page screen options.
- Customizer, Permalinks, and Tools settings foundations.
- Theme logo, typography, header layout, and footer layout settings.
- Marketplace compatibility badges and install warnings.
- WordPress-style role/capability aliases.

### Changed
- Public content routing now supports `/post/{slug}` and unpublished preview states for authenticated editors.
- Default theme navigation now renders configured menus instead of fixed links when menus exist.
- Dashboard navigation and styling moved closer to WordPress while keeping the existing Kronos builder workflow.

---

## [1.0.0] — 2025 — Stable Release

### Added
- **Phase 1 – Foundation**: PSR-4 bootstrap (`KronosApp`, `KronosRouter`, `KronosDB`, `KronosHooks`, `KronosConfig`, `KronosModule`, `KronosModuleLoader`), installer (`KronosInstaller`, 13-table schema), install wizard (3-step + complete page), global helper functions
- **Phase 2 – Auth**: JWT (`KronosJWT`) + httpOnly cookie middleware (`KronosMiddleware`), role-based access guards
- **Phase 3 – Dashboard**: Full admin dashboard module (`kronos-dashboard`) with 12 pages: Home, Content, Content Edit, Builder, Products, Orders, Analytics, AI Chat, Marketplace, Settings (5 tabs), Users, Login
- **Phase 4 – Builder Foundation**: `RenderEngine`, `WidgetBase`, built-in widgets: Heading, Text, Image, Button, Container; layout CRUD API
- **Phase 5 – Builder Editor UI**: Drag-and-drop canvas (`builder.js`) with undo/redo history, block inspector, keyboard shortcuts (`Ctrl+Z/Y/S`); builder dashboard page; `?preview=1` mode for drafts
- **Phase 6 – AI & SSE**: OpenAI proxy endpoint with session history and `ai_logs` storage; Server-Sent Events stream endpoint (`StreamEndpoint`); EventSource client (`stream.js`) with pub/sub, auto-reconnect (exponential backoff), named event handlers
- **Phase 7 – E-Commerce**: `kronos-commerce` module — product/cart/order management, `PaymentGatewayInterface`, `PaymentManager`, `StripeGateway`, `PayPalGateway` (raw cURL, no SDK), `NullGateway` (COD), cart.php + checkout.php frontend templates
- **Phase 8 – Marketplace**: `kronos-marketplace` module, `HubClient` (remote + local mock fallback), `PackageInstaller` (ZIP-slip-safe), `hub-mock/` local development server (5 packages)
- **Phase 9 – Themes & Polish**: `KronosThemeManager` (theme discovery, activation, asset copy), `themes/kronos-default` (6 templates, CSS, JS), demo layout JSONs (home, about, product-listing), `UpdateChecker` (24h GitHub releases cache), `SelfUpdater` (1-click ZIP-based update)
- `sync.bat` — Windows robocopy sync to XAMPP htdocs
- `release.bat` — Version bump, git tag, `gh release create` automation
- `README.md` — Full documentation
- `CHANGELOG.md` — This file

---

## [0.1.0] — Initial Development Release

### Added
- Initial commit — all core infrastructure, auth, API, dashboard, builder, commerce, marketplace, and default theme files scaffolded and committed to GitHub.
- Tagged and pushed to [github.com/TheoSfak/KronosCms](https://github.com/TheoSfak/KronosCms).

---

[1.0.0]: https://github.com/TheoSfak/KronosCms/compare/v0.1.0...v1.0.0
[0.1.0]: https://github.com/TheoSfak/KronosCms/releases/tag/v0.1.0
