# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Web interface for the RuWordNet Russian thesaurus database. PHP 8.3 app built on **Slim 4** (PSR-7/PSR-15) with Twig 3 templates, Doctrine DBAL 4 on top of PostgreSQL, and Monolog 3. Localized (ru/en) under `/{_locale}/...`.

## Commands

All common workflows are driven through the Makefile. Run `make help` for the auto-generated target list.

- `make build` — build all three Docker images (`dev`, `prod`, `nginx`). Individual: `make build-dev` / `make build-prod` / `make build-nginx`. Images are tagged `ruwordnetview:<stage>`.
- `make dev` — build the dev image and run it; mounts the repo at `/opt/app` and serves `php -S 0.0.0.0:8000 -t web` on `localhost:8000`. Composer is available inside the container. Pass `-e DEV_UID=1000 -e DEV_GID=1000` to avoid permission issues.
- `make css` — compile `web/static/css/layout.scss` → `layout.min.css` via `sassc` (host tool, not dockerized).
- `make deploy` — rsync the tree to the `ruwordnet` remote (SSH host alias) at `/var/www/ruwordnet-view/`, honoring `rsync-exclude`.
- Production stack: `docker compose up` uses `compose.yaml` (proxy + app + postgres:15.17). Requires `.env` populated from `.env.dist`. The `app` service reads DB connection from `POSTGRES_*` env vars — **no longer bakes credentials into the image**.
- CI: GitHub Actions (`.github/workflows/ci.yml`) runs PHP lint, php-cs-fixer dry-run, and Docker builds on every push/PR.
- Code style: `.php_cs.dist` configures php-cs-fixer v3 (`@PhpCsFixer` + `@PhpCsFixer:risky`, scanning `app/`, `src/`, `web/`). Run: `php-cs-fixer fix --config=.php_cs.dist`.

First-time local setup:
1. Set `POSTGRES_HOST`, `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD` env vars (export them in your shell or create a `.env.local`). `POSTGRES_PORT` defaults to `5432`.
2. Run `make dev` then `composer install` inside the container (deps are pre-installed in the image but the mount overrides them; run `composer install` if `vendor/` is missing).

## Architecture

Entrypoint is `web/index.php`, which loads `app/app.php` and calls `$app->run()`. `app/app.php` builds a PHP-DI container and creates a Slim 4 app:

1. **Database** — reads `POSTGRES_HOST`, `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD` (and optional `POSTGRES_PORT`) env vars and creates a `Doctrine\DBAL\Connection` via `DriverManager::getConnection()`. Missing vars throw a `RuntimeException` listing the missing names. `APP_DEBUG=1` enables Slim's detailed error output.
2. **Repositories** — `RepositoryFactoryService` (in `src/Zebradil/SilexDoctrineDbalModelRepository/`) lazy-instantiates four repositories: `SenseRepository`, `SynsetRepository`, `SenseRelationRepository`, `SynsetRelationRepository`. Repositories extend `AbstractRepository` and use Doctrine DBAL 4 directly (no ORM). Models use `_repositoryFactory` for lazy-loading related entities (Active Record–style).
3. **Controller** — `SiteController` (under `src/Zebradil/RuWordNet/Controllers/`) serves all routes. Actions are PSR-15: `(Request, Response, array $args): Response`.
4. **Twig** — templates in `views/`, compiled cache in `var/cache/twig`. Custom filters/functions registered in `app/app.php`: `translit` (Cyrillic→Latin), `trans` (translation), `path` (URL generation with auto-locale injection via `slim/twig-view`'s runtime).
5. **Translation** — `Symfony\Component\Translation\Translator` with YAML locale files from `app/locales/`. Locale is persisted in the PHP session and set per-request in the route group middleware.
6. **Routing** — `app/routing.php`. All user-facing routes are in a `/{_locale}/...` route group. Key named routes: `homepage`, `search`, `sense`. The locale middleware (attached to the group) runs after routing so `RouteContext` is available. Root `/` and un-prefixed paths redirect to `/{session-locale}/...`.

### Middleware stack (outermost → innermost)

```
ErrorMiddleware → RoutingMiddleware → TwigMiddleware → [group: LocaleMiddleware → RouteHandler]
```

### Domain model

RuWordNet concepts map directly to the PostgreSQL schema:

- **Synset** — a set of synonyms (the core concept).
- **Sense** — an individual word/phrase that belongs to a synset.
- **SynsetRelation** / **SenseRelation** — typed relations between synsets or senses (hyponym/hypernym, part-whole, antonym, domain, POS-synonymy, cause, entailment, etc.).

Models are plain PHP objects extending `AbstractModel`; comparison is provided by `ModelComparisonTrait`. View-side formatting helpers live in `src/Zebradil/RuWordNet/Views/` (`SenseTemplateTrait`, `SynsetTemplateTrait`) and are consumed by Twig templates.

### Generic infrastructure (`src/Zebradil/`)

- `ModelCollection/` — a reusable collection abstraction (`AbstractCollection` + `ArrayAccess`/`Countable`/`Iterator`/`Serializable` traits, plus `BitwiseFlagTrait`) used for returning grouped result sets from repositories.
- `SilexDoctrineDbalModelRepository/` — `AbstractRepository`, `AbstractModel`, `RepositoryFactoryService`, `ModelInterface`. Decoupled from the framework; `RepositoryServiceProvider` (the old Silex glue) has been removed.

## Conventions and gotchas

- PHP 8.3, `"minimum-stability": "stable"`. Slim 4 + Twig 3 + DBAL 4. Do not downgrade.
- PSR-4 autoload root is `src/` with an **empty namespace prefix** (`"": "src/"`), so a class `Zebradil\Foo\Bar` lives at `src/Zebradil/Foo/Bar.php`.
- DB connection is configured exclusively via `POSTGRES_*` env vars. In production they come from `compose.yaml`; locally, export them in your shell before running `make dev`.
- The `path()` Twig function auto-injects `_locale` from the current request context — call `path('sense', {name: ..., meaning: ...})` without specifying `_locale`.
- The dev server is PHP's built-in one (`php -S`), not FPM/nginx. Production uses the nginx image in `docker/nginx/` fronting the `prod` PHP-FPM image.
- Nginx config (`docker/nginx/app.conf`) is set up for `ruwordnet.ru` with Cloudflare DNS certbot authentication. The fastcgi cache validity is 60 days — clear it when content changes.
- Dependencies are auto-updated by Renovate (`renovate.json`).
