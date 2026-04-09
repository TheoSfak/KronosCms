# KronosCMS

A standalone, open-source PHP 8 Content Management System and E-Commerce platform — no WordPress, no bloat.

[![Latest Release](https://img.shields.io/github/v/release/TheoSfak/KronosCms)](https://github.com/TheoSfak/KronosCms/releases)
[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

---

## Features

| Feature | Details |
|---|---|
| **Dual Mode** | Switch between CMS and full E-Commerce mode at runtime |
| **Visual Page Builder** | Drag-and-drop HTML5 builder with undo/redo and live preview |
| **AI Content Assistant** | OpenAI-backed chat streamed via Server-Sent Events |
| **E-Commerce** | Products, cart, orders, Stripe, PayPal, and COD gateways |
| **Marketplace** | Install themes & plugins from the hub |
| **1-Click Updates** | GitHub Releases-based self-updater |
| **Theme System** | `theme.json` manifests + PHP templates |
| **JWT Auth** | Stateless `hash_hmac` tokens in httpOnly cookies |
| **Hook System** | WordPress-style `add_action` / `add_filter` everywhere |

---

## Requirements

- PHP 8.0 or higher (8.2+ recommended)
- MySQL 5.7 / MariaDB 10.4 or higher
- Composer 2.x
- Apache 2.4+ with `mod_rewrite` (or Nginx with `try_files`)
- A web server writable `storage/` and `config/` directory

---

## Installation

### 1. Clone and install dependencies
```bash
git clone https://github.com/TheoSfak/KronosCms.git
cd KronosCms
composer install
```

### 2. Copy environment file
```bash
cp .env.example .env
# Edit .env with your database credentials, app URL, and API keys
```

### 3. Set directory permissions
```bash
chmod -R 775 storage/ config/
```

### 4. Point your web server root to `/public`

**Apache (VirtualHost):**
```apache
DocumentRoot /var/www/KronosCms/public
<Directory /var/www/KronosCms/public>
    AllowOverride All
    Require all granted
</Directory>
```

**Nginx:**
```nginx
root /var/www/KronosCms/public;
index index.php;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ { fastcgi_pass unix:/run/php/php8.2-fpm.sock; include fastcgi_params; }
```

### 5. Run the install wizard
Navigate to `http://your-domain/` — you will be redirected to the installer automatically.

---

## Directory Structure

```
KronosCMS/
├── config/             ← Runtime config (written by installer)
├── install/            ← Install wizard (multi-step)
├── modules/
│   ├── kronos-core/    ← Core module (API, mode switching)
│   ├── kronos-builder/ ← Page builder engine
│   ├── kronos-commerce/← E-Commerce module
│   ├── kronos-dashboard/← Admin dashboard
│   └── kronos-marketplace/← Plugin/theme marketplace
├── public/             ← Web root (point your server here)
│   ├── assets/         ← CSS, JS, images
│   └── index.php       ← Front controller
├── src/
│   ├── API/            ← REST endpoint classes
│   ├── Auth/           ← JWT middleware
│   ├── Builder/        ← RenderEngine + widgets
│   ├── Core/           ← App, Router, DB, Config, hooks…
│   ├── Helpers/        ← Global functions
│   └── Marketplace/    ← Hub client + package installer
├── storage/
│   ├── cache/          ← File-based cache
│   └── logs/           ← Application logs
├── themes/
│   └── kronos-default/ ← Default theme (templates, assets, layouts)
├── hub-mock/           ← Local mock Hub API for development
├── .env.example        ← Environment variable template
├── composer.json
├── sync.bat            ← Windows: copy to XAMPP htdocs
└── release.bat         ← Windows: bump version, tag, GitHub release
```

---

## Local Development (Windows + XAMPP)

```batch
sync.bat
```

Copies the project to `C:\xampp\htdocs\KronosCMS` with robocopy.

---

## Releasing a New Version

```batch
release.bat
```

Prompts for a version number, updates `KronosVersion::VERSION`, commits, tags, and creates a GitHub release.

---

## Configuration (.env)

| Key | Description |
|---|---|
| `DB_HOST` | Database host (default `127.0.0.1`) |
| `DB_PORT` | Database port (default `3306`) |
| `DB_NAME` | Database name |
| `DB_USER` | Database user |
| `DB_PASS` | Database password |
| `APP_URL` | Public URL (no trailing slash) |
| `APP_SECRET` | Random 32+ char secret for JWT signing |
| `OPENAI_API_KEY` | OpenAI key for AI assistant |
| `OPENAI_MODEL` | Model name (default `gpt-4o`) |
| `STRIPE_SECRET_KEY` | Stripe secret key |
| `STRIPE_WEBHOOK_SECRET` | Stripe webhook signing secret |
| `PAYPAL_CLIENT_ID` | PayPal client ID |
| `PAYPAL_CLIENT_SECRET` | PayPal client secret |
| `HUB_API_URL` | KronosCMS Hub URL (leave blank for local mock) |

---

## API Reference

All endpoints are under `/api/kronos/v1/`. Protected routes require the `kronos_token` httpOnly cookie.

| Method | Path | Description |
|---|---|---|
| `POST` | `/auth/login` | Issue JWT |
| `POST` | `/auth/refresh` | Refresh JWT |
| `POST` | `/auth/logout` | Clear JWT cookie |
| `GET/POST/PUT/DELETE` | `/builder/layouts` | Layout CRUD |
| `POST` | `/ai/chat` | AI completion |
| `GET` | `/stream` | SSE event stream |
| `GET` | `/system/update-check` | Check for update |
| `POST` | `/system/update` | Run self-updater |
| `GET/POST/PUT/DELETE` | `/commerce/products` | Product CRUD |
| `POST` | `/commerce/cart` | Add/update cart item |
| `POST` | `/commerce/orders` | Place order |
| `GET` | `/marketplace/directory` | List hub packages |
| `POST` | `/marketplace/install` | Install package |

---

## License

MIT — see [LICENSE](LICENSE).

---

## Author

Built by [TheoSfak](https://github.com/TheoSfak).
