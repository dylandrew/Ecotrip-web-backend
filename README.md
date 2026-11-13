# LavaLust Framework

> A lightweight, fast PHP framework built for developers who want clean MVC architecture without unnecessary complexity or performance overhead.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-8892BF)](https://www.php.net/)
[![GitHub Stars](https://img.shields.io/github/stars/ronmarasigan/lavalust?style=flat)](https://github.com/ronmarasigan/lavalust/stargazers)

---

## Overview

**LavaLust** is an open-source PHP framework that follows the **MVC (Model–View–Controller)** architectural pattern. It is designed for developers who need a structured, maintainable, and scalable foundation — without the bloat of heavier modern frameworks.

Whether you are building a simple web application, a REST API, or a teaching project, LavaLust provides the right tools with minimal friction.

---

## Features

| Feature | Description |
|---|---|
| **MVC Architecture** | Clean separation of Models, Views, and Controllers for organized, maintainable code |
| **Built-in Routing** | Flexible URL routing that maps requests to controllers with minimal configuration |
| **Libraries & Helpers** | Reusable components for sessions, forms, validation, and database access |
| **Modular Design** | Scalable structure that supports clean organization as your application grows |
| **REST API Support** | First-class support for building RESTful APIs using LavaLust conventions |
| **ORM-like Models** | Simplified, readable database interaction without a heavy abstraction layer |

---

## Requirements

- PHP 8.0 or higher for EcoTrip and its maintained Composer dependencies
- A web server with URL rewriting support (Apache `.htaccess` or Nginx config)
- Composer (required for PHP dependencies)

## EcoTrip: low-carbon trip planning

The application includes trip CRUD and itinerary generation, user accounts, a dashboard, eco-stay and low-carbon transport directories, bookings and payment verification, a photo journal, reminders, an admin area, and an AI travel assistant. Travelers can edit scheduled itinerary stops, mark them complete, and optionally share their notes as location-searchable references. PayMongo checkout supports transport, activities, other trip expenses, and carbon offsets alongside hotel payments. Travelers can update journal captions and edit or delete pending reminders; these operations are scoped to records they own. Payment records are retained and updated through their payment lifecycle rather than deleted. Optional integrations are documented in [docs/INTEGRATIONS.md](docs/INTEGRATIONS.md); the database schema, seed data, sitemap, and ERD are in `database/` and `docs/`.

From `backend/`, configure `DB_DRIVER=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_USER`, `DB_PASSWORD`, and `DB_NAME=ecotrip_lavalust_db` in `backend/.env`; LavaLust loads its environment file from the backend root. The repository-root `.env.example` is a reference template, not an automatically loaded configuration file. Run `composer install` from `backend/`. Import `database/ecotrip_schema.sql`, then import `database/ecotrip_seed.sql` only for a fresh database; never import demo seed data into an existing database with user data. For an existing database, review and run `database/ecotrip_robustness_upgrade.sql` and `database/ecotrip_itinerary_payments_upgrade.sql` once after selecting the correct database. To enable email notifications and Android phone SMS reminders on an existing database, also run `database/ecotrip_email_notifications_upgrade.sql` and `database/ecotrip_android_sms_gateway_upgrade.sql`. Create the first administrator with `php console/cli.php admin:create`. Mark pending bookings older than 24 hours as failed with `php console/cli.php bookings:expire`. Queued email and reminder notifications are delivered by `php console/cli.php reminders:send`; this command is also available for manual runs. Delivery errors fail the run visibly, and previously failed pending reminder emails are retried automatically after valid SMTP settings are restored. Configure cron-job.org to trigger the GitHub Actions worker described below. Start the app from `backend/` with:

#### cron-job.org reminder schedule

The `.github/workflows/send-reminders.yml` workflow runs the CLI reminder worker on GitHub Actions when manually dispatched; it does not have its own schedule. Add these repository Actions secrets under **Settings → Secrets and variables → Actions**: `REMINDER_DB_HOST`, `REMINDER_DB_PORT`, `REMINDER_DB_NAME`, `REMINDER_DB_USER`, `REMINDER_DB_PASSWORD`, `REMINDER_MAIL_HOST`, `REMINDER_MAIL_PORT`, `REMINDER_MAIL_USERNAME`, `REMINDER_MAIL_PASSWORD`, `REMINDER_MAIL_ENCRYPTION` (`tls` or `ssl`), and `REMINDER_MAIL_FROM_ADDRESS`. The workflow uses `utf8mb4` and `EcoTrip` as the sender name.

To keep cron-job.org as the scheduler while sending mail from the GitHub Actions runner, create a fine-grained GitHub token restricted to this repository with **Actions: Read and write** permission. In cron-job.org, configure an HTTPS **POST** job to `https://api.github.com/repos/dylandrew/Ecotrip-web-backend/actions/workflows/send-reminders.yml/dispatches`, add `Authorization: Bearer <your token>`, `Accept: application/vnd.github+json`, `X-GitHub-Api-Version: 2022-11-28`, and `Content-Type: application/json` headers, and set the request body to `{"ref":"main"}`. Keep the token secret in cron-job.org; do not put it in the URL or commit it. Choose the desired execution interval there. A **204** response means GitHub accepted the dispatch; check the repository's **Actions** tab for the worker result. Delivery errors fail the workflow and appear in its logs. The database must accept connections from GitHub-hosted runners; do not make a production database world-accessible to solve firewall failures.

For API authentication and hardening on an existing database, apply `database/ecotrip_api_refresh_auth_codes.sql` and `database/ecotrip_api_hardening_upgrade.sql` after selecting the EcoTrip database. The hardening upgrade creates the throttle/audit tables and guarded booking indexes.

```bash
php -S 127.0.0.1:8080 -t public local-router.php
```

The backend is API-only: `GET /` and `GET /api/v1/health` return JSON health status, and application operations are exposed under `/api/v1`. The React website is in the sibling `../frontend/` directory and runs separately.

API v1 routes are organized by responsibility: `Api_auth`, `Api_account`, `Api_trips`, `Api_stays`, `Api_transport`, `Api_bookings`, `Api_journal`, `Api_reminders`, `Api_dashboard`, and `Api_admin` live in `app/controllers/`. They extend `Api_v1`, which owns shared API initialization, authentication, authorization, request parsing, rate limiting, and health handling. Composer classmap autoloading resolves this shared parent without manually including application files. The endpoint URLs and JSON contracts remain unchanged.

Legacy PHP page/controller files remain in the source tree but are not registered as routes. PHP dependencies for SMTP, Google OpenID Connect, and optional Sentry reporting are managed by Composer. On Windows, enable PHP's ZIP extension or install 7-Zip if Composer cannot extract package archives.

Journal photo storage uses the local `JOURNAL_STORAGE_DRIVER` by default. Additional S3 or Cloudinary adapters can provide the matching `api_storage_{driver}_store_upload` and `api_storage_{driver}_delete_upload` handlers. Render's local filesystem is ephemeral; configure a persistent storage provider before relying on uploaded photos in production.

---

## Traveler experiences

Travelers can copy a publicly shared completed trip into a new private draft from the Traveler experiences page. They choose their own dates, budget, and traveler count; copied itinerary stops remain editable, and the draft is never published automatically. Admins can review completed trips and stops in the Experiences section, edit shared notes and trip ratings, publish or hide eligible experiences, and remove shared content without deleting the traveler's trip or itinerary. These features use the existing trip-experience columns; an existing database should have the trip-experiences and trip-experience-rating upgrades applied.

## Installation

**Clone the repository:**

```bash
git clone https://github.com/ronmarasigan/lavalust.git
cd lavalust
```

**Or download a release directly:**

```bash
wget https://github.com/ronmarasigan/lavalust/archive/refs/heads/main.zip
unzip main.zip
```

Configure your web server to point to the project root and ensure `mod_rewrite` (Apache) or equivalent is enabled.

---

## Quick Start

### 1. Define a Route

**File:** `app/config/routes.php`

```php
$router->get('/', 'Dashboard::index');
$router->get('/about', 'Home::about');
$router->post('/users/store', 'Users::store');
```

### 2. Create a Controller

**File:** `app/controllers/Home.php`

```php
<?php

class Home extends Controller
{
    public function index()
    {
        $data['title'] = 'Home';
        $this->call->view('welcome', $data);
    }

    public function about()
    {
        $this->call->view('about');
    }
}
```

### 3. Create a View

**File:** `app/views/welcome.php`

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?></title>
</head>
<body>
    <h1>Welcome to LavaLust Framework</h1>
    <p>Lightweight. Fast. MVC.</p>
</body>
</html>
```

### 4. Create a Model

**File:** `app/models/User_model.php`

```php
<?php

class User_model extends Model
{
    protected $table = 'users';

    public function getAll()
    {
        return $this->db->table($this->table)->get()->getResult();
    }

    public function findById(int $id)
    {
        return $this->db->table($this->table)
                        ->where('id', $id)
                        ->get()
    }
}
```

---

## Project Structure

```
lavalust/
├── app/
│   ├── config/          # Application configuration (database, routes, etc.)
│   ├── controllers/     # Controller classes
│   ├── models/          # Model classes
│   ├── views/           # View templates
│   └── libraries/       # Custom libraries and helpers
├── scheme/              # Core framework files (do not modify)
├── public/              # Publicly accessible entry point
│   └── index.php
└── runtime/            # Cache, logs, and uploads (must be writable)
```

---

## Configuration

### Database

**File:** `app/config/database.php`

```php
$database['main'] = array(
    'driver'	=> getenv('DB_DRIVER') ?: '',
    'hostname'	=> getenv('DB_HOST') ?: '',
    'port'		=> getenv('DB_PORT') ?: '',
    'username'	=> getenv('DB_USER') ?: '',
    'password'	=> getenv('DB_PASSWORD') ?: '',
    'database'	=> getenv('DB_NAME') ?: '',
    'charset'	=> getenv('DB_CHARSET') ?: '',
    'dbprefix'	=> getenv('DB_PREFIX') ?: '',
    // Optional for SQLite
    'path'      => ''
);
```

### Base URL

**File:** `app/config/config.php`

```php
$config['base_url'] = 'http://localhost:3000/';
```

---

## Building a REST API

LavaLust supports REST API development out of the box. Controllers can return JSON responses for API endpoints.

```php
<?php

class Api extends Controller
{
    $this->call->library('api');

    public function users()
    {
        $this->api->require_method('GET');
        $auth = $this->api->require_jwt(); 

        $this->call->model('User_model');
        $users = $this->User_model->getAll();

        $this->api->respond(['data' => $users]);
    }
}
```

Route definition:

```php
$router->get('/api/users', 'Api::users');
```

---

## EcoTrip JSON API

The backend JSON API is available under `/api/v1`. Import `docs/openapi.yaml` into Swagger UI or another OpenAPI client for the complete endpoint specification.

Required database migration:

```bash
mysql -u "$DB_USER" -p "$DB_NAME" < database/ecotrip_api_refresh_auth_codes.sql
```

Important environment variables include `APP_URL`, `FRONTEND_URL`, `CORS_ALLOWED_ORIGIN`, `REFRESH_COOKIE_SAMESITE`, `REFRESH_COOKIE_DOMAIN`, `JWT_SECRET`, `REFRESH_TOKEN_KEY`, and the existing PayMongo, Google, Gemini, SMTP, database variables. Hotel checkout requires `PAYMONGO_SECRET_KEY`: use a PayMongo `sk_test_...` key with `APP_ENV=development`, and a live `sk_live_...` key only in production. Set `PAYMONGO_WEBHOOK_SECRET` from the PayMongo webhook configuration to accept signed payment events. On a hotel detail page, select a trip; the server calculates the total from the listed nightly rate and trip dates, then creates a PayMongo Checkout Session for GCash, Maya, QR Ph, or card. Keep these credentials in `backend/.env`, never in the frontend.

Journal uploads currently use the local storage driver at `public/uploads/journal`. Render's filesystem is ephemeral: uploaded files can disappear when a service redeploys or restarts. Production deployments should replace the local driver in `api_helper.php` with persistent object storage (S3/Cloudinary or similar) and configure a persistent public URL origin.

---

## Philosophy

LavaLust is built on a single principle: **minimal core, maximum control.**

Modern frameworks often add layers of abstraction that benefit large enterprise teams but get in the way of developers who want to understand exactly what their code is doing. LavaLust provides structure and utilities without hiding the underlying logic — making it an excellent choice for:

- **Rapid prototyping** — Get an application running in minutes
- **Learning MVC** — Understand how each architectural layer works
- **Lightweight production apps** — Deploy without dragging in unused dependencies
- **Teaching PHP development** — Clear conventions, readable source code

---

## Documentation

Full documentation is available at **[https://lavalust.netlify.app](https://lavalust.netlify.app)**

Topics covered include:

- Installation and server configuration
- Routing: static, dynamic, and grouped routes
- Controllers and request handling
- Models and query builder
- Views, layouts, and partials
- Built-in libraries (sessions, form validation, file upload)
- Helper functions
- REST API development
- Security best practices

---

## Contributing

Contributions are welcome. To contribute:

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature-name`
3. Commit your changes: `git commit -m "Add your feature description"`
4. Push to your branch: `git push origin feature/your-feature-name`
5. Open a pull request against `main`

Please ensure your code follows the existing style conventions and includes relevant documentation or comments where appropriate.

---

## Roadmap

- [ ] CLI tool for generating controllers, models, and migrations
- [ ] Middleware support
- [ ] Improved query builder with relationship support
- [ ] Enhanced error handling and debugging tools

---

## License

LavaLust Framework is open-source software licensed under the **[MIT License](https://opensource.org/licenses/MIT)**.

---

## Links

- **GitHub Repository:** [https://github.com/ronmarasigan/lavalust](https://github.com/ronmarasigan/lavalust)
- **Documentation:** [https://lavalust.netlify.app](https://lavalust.netlify.app)
- **Report an Issue:** [https://github.com/ronmarasigan/lavalust/issues](https://github.com/ronmarasigan/lavalust/issues)