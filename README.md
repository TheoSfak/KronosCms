<div align="center">

# KronosCMS

**A modern, standalone PHP 8 CMS & E-Commerce platform — built from scratch, no WordPress.**

[![Latest Release](https://img.shields.io/github/v/release/TheoSfak/KronosCms?style=for-the-badge&color=2563eb)](https://github.com/TheoSfak/KronosCms/releases)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-22c55e?style=for-the-badge)](LICENSE)

</div>

---

KronosCMS is a fully self-contained content management and e-commerce system written in PHP 8. It ships with a visual drag-and-drop page builder, an AI content assistant powered by OpenAI, a complete e-commerce stack with Stripe and PayPal, a plugin/theme marketplace, and a clean hook-based extension API — all without depending on WordPress or any legacy framework.

---

## Features

### Content Management
- **Dual Mode** — toggle between pure CMS and full E-Commerce mode from the dashboard at any time
- **Visual Page Builder** — HTML5 drag-and-drop canvas with block inspector, undo/redo history, and keyboard shortcuts (`Ctrl+Z/Y/S`)
- **Draft Preview** — preview unpublished drafts with a secure `?preview=1` URL before going live
- **Custom Themes** — `theme.json`-driven theme system with PHP templates and hot-asset-swapping on activation

### E-Commerce
- **Products, Cart & Orders** — full product management, session-based cart, and order lifecycle
- **Multiple Payment Gateways** — Stripe (Elements), PayPal (REST), and Cash on Delivery out of the box
- **Payment Webhooks** — Stripe and PayPal webhook verification built in

### Developer Experience
- **Hook System** — WordPress-style `add_action` / `add_filter` API throughout the entire codebase
- **PSR-4 Autoloading** — clean `Kronos\` namespace, no global spaghetti
- **REST API** — all functionality exposed under `/api/kronos/v1/` with JWT authentication
- **JWT Auth** — stateless tokens via `hash_hmac SHA-256` stored in httpOnly cookies
- **Module System** — drop a folder into `/modules/` and it auto-loads on boot

### AI & Real-Time
- **AI Content Assistant** — OpenAI chat proxy with session history, streamed token-by-token via SSE
- **Server-Sent Events** — real-time order updates, notifications, and AI streaming with auto-reconnect

### Platform
- **Marketplace** — browse and install plugins and themes from the hub with one click
- **1-Click Self-Updater** — checks GitHub Releases, downloads, and applies updates automatically
- **Installer Wizard** — guided 3-step setup: database, site config, and admin account

---

## Installation

Clone the repo, run `composer install`, point your web server root at the `/public` directory, then navigate to your site — the **install wizard** will guide you through the rest.

> All database setup, environment configuration, and admin account creation is handled by the wizard.

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.0+ (8.2+ recommended) |
| MySQL / MariaDB | 5.7+ / 10.4+ |
| Composer | 2.x |
| Web server | Apache 2.4+ (`mod_rewrite`) or Nginx (`try_files`) |

---

## Directory Structure

```
KronosCMS/
├── config/              ← Runtime config (written by the installer)
├── install/             ← Install wizard
├── modules/
│   ├── kronos-core/     ← Core module (API router, mode switching)
│   ├── kronos-builder/  ← Page builder engine
│   ├── kronos-commerce/ ← E-Commerce module & payment gateways
│   ├── kronos-dashboard/← Admin dashboard (12 pages)
│   └── kronos-marketplace/ ← Plugin/theme marketplace
├── public/              ← Web root — point your server here
│   ├── assets/          ← CSS, JS, images
│   └── index.php        ← Front controller
├── src/
│   ├── API/             ← REST endpoint classes
│   ├── Auth/            ← JWT middleware
│   ├── Builder/         ← RenderEngine + widget system
│   ├── Core/            ← App, Router, DB, Config, Hooks…
│   ├── Helpers/         ← Global helper functions
│   └── Marketplace/     ← HubClient + PackageInstaller
├── storage/
│   ├── cache/           ← File-based cache
│   └── logs/            ← Application logs
├── themes/
│   └── kronos-default/  ← Default theme (templates, assets, demo layouts)
├── hub-mock/            ← Local mock Hub API for development
└── .env.example         ← Environment variable reference
```

---

## Environment Variables

Fill in `.env` (copied from `.env.example`) before or during setup:

| Key | Description |
|---|---|
| `DB_HOST` | Database host (default `127.0.0.1`) |
| `DB_NAME` | Database name |
| `DB_USER` | Database user |
| `DB_PASS` | Database password |
| `APP_URL` | Public URL without trailing slash |
| `APP_SECRET` | 32+ character random secret for JWT signing |
| `OPENAI_API_KEY` | OpenAI API key for the AI assistant |
| `OPENAI_MODEL` | Model name (default `gpt-4o`) |
| `STRIPE_SECRET_KEY` | Stripe secret key |
| `STRIPE_WEBHOOK_SECRET` | Stripe webhook signing secret |
| `PAYPAL_CLIENT_ID` | PayPal REST client ID |
| `PAYPAL_CLIENT_SECRET` | PayPal REST client secret |
| `HUB_API_URL` | Marketplace hub URL (leave blank to use local mock) |

---

## API Reference

All endpoints live under `/api/kronos/v1/`. Authenticated routes require the `kronos_token` httpOnly cookie.

| Method | Path | Description |
|---|---|---|
| `POST` | `/auth/login` | Issue JWT token |
| `POST` | `/auth/refresh` | Refresh JWT token |
| `POST` | `/auth/logout` | Clear JWT cookie |
| `GET / POST / PUT / DELETE` | `/builder/layouts` | Layout CRUD |
| `POST` | `/ai/chat` | AI chat completion |
| `GET` | `/stream` | SSE real-time event stream |
| `GET` | `/system/update-check` | Check for available update |
| `POST` | `/system/update` | Run self-updater |
| `GET / POST / PUT / DELETE` | `/commerce/products` | Product CRUD |
| `POST` | `/commerce/cart` | Add / update cart item |
| `POST` | `/commerce/orders` | Place an order |
| `GET` | `/marketplace/directory` | List hub packages |
| `POST` | `/marketplace/install` | Install a package |

---

## License

MIT — see [LICENSE](LICENSE).

---

<div align="center">
Built by <a href="https://github.com/TheoSfak">TheoSfak</a>
</div>
