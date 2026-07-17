# AGENTS.md — Backend Plant Doctor API

## Stack

- **Laravel 13** / PHP 8.4+ / Composer
- **JWT auth** (`php-open-source-saver/jwt-auth`, guard: `api`) — primary. Sanctum (SPA) secondary.
- **Octane** (FrankenPHP) for app server. **Reverb** for WebSocket (port 8006).
- **PostgreSQL 15 + PostGIS** for database (Docker service `postgres`).
- **Cloudinary** for image storage (HTTP API, no package — `CloudinaryService`).
- **Pl@ntNet API v2** for plant identification by photo (500 free/day).
- **Perenual API** for disease catalog data (100 free/day, cached locally).
- **Database-driven** queue, cache, session by default (Redis available via config).
- **Vite 8** + TailwindCSS 4 for frontend assets (vanilla JS, no framework).
- **Docker**: FrankenPHP + nginx + PostgreSQL + Redis + supervisord (octane, reverb, queue-worker, scheduler).

## Docker

All commands run inside the app container.

Define:
```
alias APP='docker compose exec plant-doctor-api'
```

Services:
- `plant-doctor-api` — FrankenPHP/Octane (port 9000 internal, 8006 Reverb)
- `nginx` — reverse proxy (port 8005)
- `postgres` — PostgreSQL 15 + PostGIS (port 5432)
- `redis` — Redis 7.2 (internal)

## Architecture

- **API-only** — no Blade views except welcome page and email templates.
- Routes split into files under `routes/`: `api.php` (core), `api_plants.php`, `api_diseases.php`, `api_diagnoses.php`, `channels.php` (broadcast).
- **`ApiResponseTrait`** on all controllers. Standard shape:
  `{ "success": bool, "message": string, "data": ... }`
  Helper methods:
  `successResponse($data, $message, $code)` — default 200
  `errorResponse($message, $code, $errors)` — default 400
  `validationErrorResponse($errors)` — always 422
  `notFoundResponse($message)` — always 404
  `unauthorizedResponse($message)` — always 401
  `forbiddenResponse($message)` — always 403
- **NEVER use inline `$request->validate([...])` in controllers.** All validation MUST be in dedicated Form Request classes under `app/Http/Requests/`. Controllers use `$request->validated()` to access validated data. No exceptions.
- **All controller methods MUST have try-catch blocks.** Every public method in a controller must wrap its logic in `try { ... } catch (Exception $e) { return $this->errorResponse(...); }`. This ensures consistent JSON error responses instead of unhandled exceptions.
- **ValidationException must be caught before Exception.** Always catch `ValidationException` first (returns 422 via `validationErrorResponse()`), then generic `Exception` (returns 500 via `errorResponse()`).
- Business logic in `app/Services/`, not controllers.
- **Polymorphic `Publication` model** aggregates Plant + Disease into a single feed.
- **`HasPublication` trait** on Plant and Disease — syncs the publication on model events.
- Geolocation via OpenStreetMap Nominatim (reverse geocode) + PostGIS spatial queries for nearby plants.
- Observers registered in `AppServiceProvider::boot()`, not in `EventServiceProvider` (no such file).
- All custom business exceptions return HTTP 400.
- Dual-auth: `GET /api/user` uses Sanctum (`auth:sanctum`); everything else uses JWT (`auth:api`).
- **Spatie Laravel Permission** for roles and permissions (admin, expert, user).
- **Swagger/OpenAPI** via `l5-swagger` (PHP 8 attributes). Annotations in `app/OpenApi/Spec.php` and controller docblocks. Generated docs at `storage/api-docs/api-docs.json`. UI at `/api/documentation`.

## Directory structure

```
.
├── app/
│   ├── Console/Commands/     # SyncDiseasesFromPerenual
│   ├── Events/               # DiagnosisCreated, PlantUpdated, DiseaseCatalogUpdated
│   ├── Exceptions/           # CustomExceptions + Handler
│   ├── Http/
│   │   ├── Controllers/      # PlantController, DiseaseController, DiagnosisController, AuthController
│   │   ├── Requests/         # Form requests (validation)
│   │   └── Resources/        # API resources (serialization)
│   ├── Mail/                 # DiagnosisReport, WelcomeEmail
│   ├── Models/               # User, Plant, Disease, Diagnosis, Publication
│   ├── Observers/            # PlantObserver, DiseaseObserver
│   ├── OpenApi/              # Spec.php, Schemas/ (Swagger annotations)
│   ├── Providers/            # AppServiceProvider
│   ├── Services/             # PlantService, DiseaseService, DiagnosisService, GeolocationService, CloudinaryService, PlantNetService, PerenualService
│   ├── Traits/               # ApiResponseTrait, HasPublication
│   └── Utils/
├── config/                   # JWT, Permission, Services, etc.
├── database/
│   ├── factories/            # PlantFactory, DiseaseFactory, DiagnosisFactory
│   ├── migrations/           # plants, diseases, diagnoses, publications, roles, permissions, postgis
│   └── seeders/              # DiseaseSeeder (catalog base)
├── resources/views/          # welcome + email templates
├── routes/                   # api.php, api_plants.php, api_diseases.php, api_diagnoses.php, channels.php
├── tests/                    # Unit + Feature (SQLite :memory:)
├── public/                   # HTTP entrypoint
├── docker-compose.yml
├── Dockerfile
├── init-postgis.sql
├── start.sh
└── supervisord.conf
```

## Key workflows

1. **Plant Diagnosis**: User registers plant → Uploads photo → Photo stored on Cloudinary → Pl@ntNet API identifies species → Returns diagnosis + confidence score → User can request expert review.
2. **Disease Catalog**: Perenual API syncs diseases daily → Cached locally in DB → Users browse and search → Admin can trigger manual sync.
3. **Plant History**: User's plants show diagnosis history → Track plant health over time → Notifications for new diagnoses.
4. **User Roles**: Admin (manages catalog), Expert (verifies diagnoses), User (creates plants, requests diagnoses).

## Commands

| Command | Description |
|---------|-------------|
| `APP composer run test` | Tests (PHPUnit, SQLite :memory:) |
| `APP php artisan jwt:secret` | Regenerate JWT secret |
| `APP php artisan config:cache && APP php artisan route:cache` | Cache bootstrap |
| `APP php artisan octane:reload` | Reload Octane without downtime |
| `APP php artisan migrate` | Run migrations |
| `APP php artisan db:seed` | Run seeders |
| `APP php artisan sync:diseases-from-perenual` | Sync diseases from Perenual API |
| `APP php artisan make:migration create_xxx_table` | Create migration |
| `APP php artisan make:model Xxx -m` | Model + migration |
| `APP php artisan make:controller XxxController` | Controller |
| `APP php artisan make:request XxxRequest` | Form request |
| `APP php artisan make:resource XxxResource` | API resource |
| `APP php artisan make:test XxxTest` | Test |
| `APP php artisan make:job XxxJob` | Job |
| `APP php artisan make:policy XxxPolicy --model=Xxx` | Policy |
| `APP php artisan permission:cache-clear` | Clear Spatie permission cache |
| `APP php artisan config:clear` | Clear config cache |
| `APP php artisan route:clear` | Clear route cache |
| `APP php artisan storage:link` | Link public storage |
| `APP php artisan l5-swagger:generate` | Generate Swagger docs |
| `APP php artisan l5-swagger:generate --all` | Regenerate all docs |

## Plugins / Packages

```bash
# Install new package
APP composer require vendor/package

# Publish config / assets / migrations
APP php artisan vendor:publish --provider="Vendor\Package\ServiceProvider"

# Run package migrations
APP php artisan migrate

# Reload Octane if new config was added
APP php artisan octane:reload

# Clear Spatie permission cache
APP php artisan permission:cache-clear
```

## External APIs

### Pl@ntNet API v2 (Plant Identification)
- Endpoint: `POST https://api.plantnet.org/v2/identify/all`
- Auth: `?api-key=PLANTNET_API_KEY`
- Params: `images` (URL from Cloudinary), `organs` (leaf/flower/fruit/bark)
- Free tier: 500 identifications/day
- Service: `app/Services/PlantNetService.php`

### Perenual API (Disease Catalog)
- Endpoint: `https://perenual.com/api/diseases`
- Auth: `?key=PERENUAL_API_KEY`
- Free tier: 100 requests/day
- Service: `app/Services/PerenualService.php`
- Sync command: `php artisan sync:diseases-from-perenual`

### Cloudinary (Image Storage)
- HTTP API (no Laravel package — `cloudinary-labs/cloudinary-laravel` doesn't support Laravel 13)
- Service: `app/Services/CloudinaryService.php`
- Upload returns Cloudinary URL (stored in `image_path` columns)
- Fallback: local `public` disk if Cloudinary not configured

### OpenStreetMap Nominatim (Geolocation)
- Reverse geocode: lat/lon → country/state/city
- Cached 1 day
- Service: `app/Services/GeolocationService.php`

## Swagger / OpenAPI

- Package: `darkaonline/l5-swagger` (PHP 8 attributes via `OpenApi\Attributes as OA`)
- Base spec: `app/OpenApi/Spec.php` — defines info, servers, security, tags
- Schemas: `app/OpenApi/Schemas/` — separate files per entity (Request + Response schemas)
- Controller annotations: PHP 8 attributes on each method (`#[OA\Get(...)]`, `#[OA\Post(...)]`, etc.)
- Always reference schemas via `$ref`: `new OA\Schema(ref: '#/components/schemas/Xxx')`
- Config: `config/l5-swagger.php` — scan path: `base_path('app')`
- Generated docs: `storage/api-docs/api-docs.json`
- UI: `http://localhost:8005/api/documentation`
- `SWAGGER_SERVER_HOST` constant defined in `AppServiceProvider::boot()` — used in Spec.php
- Regenerate after adding/modifying annotations: `APP php artisan l5-swagger:generate`

## Testing

- **SQLite in-memory** (`DB_DATABASE=:memory:`) — no external DB needed for tests.
- Test suites: `tests/Unit`, `tests/Feature`.
- Create factories for Plant, Disease, Diagnosis as needed.
- Run focused: `APP php artisan test --filter=MethodName` or `APP php artisan test tests/Feature/SomeTest.php`.

## Broadcast events

All in `app/Events/`: `DiagnosisCreated`, `PlantUpdated`, `DiseaseCatalogUpdated`.
Channels: `plant.{id}`, `user.{id}`, `App.Models.User.{id}`.

## Notable config quirks

- `config/session.php` — driver: `database`, lifetime: 120min, same-site: `lax`.
- `config/queue.php` — driver: `database`, failed jobs: `database-uuids`.
- `config/jwt.php` — TTL: 60min, refresh TTL: 20160min (14d), algo: HS256.
- `config/cache.php` — default: `database`.
- `.env.example` defaults to PostgreSQL + log mailer — good for local dev.
- `AppServiceProvider::boot()` forces `Carbon::setLocale('es')` (Spanish dates) and `URL::forceRootUrl(config('app.url'))`.
- **Tests**: Run via `composer run test` (config:clear + phpunit). SQLite :memory:.
- **GeolocationService**: `findLocationByCoordinates()` caches Nominatim results for 1 day; `findNearbyPlants()` uses PostGIS spatial queries.
- **Status transitions**: Use `transitionTo()` on the model (validates via `canTransitionTo()`). Don't change `status` directly in controller `update()` — use dedicated status routes instead.
- **Observer timing**: Observers (`PlantObserver`, `DiseaseObserver`) call `$model->refresh()` after geolocation so the `HasPublication` trait sees fresh location IDs.
- Geolocation auto-runs on create/update via observers (Nominatim). The User-Agent header in `GeolocationService.php` is a placeholder — set a real value before production.
- Pl@ntNet API has rate limits (500/day) — queue requests for production.
- Perenual API has rate limits (100/day) — local cache via DiseaseSeeder + sync command.
- No CI/CD, no static analysis (PHPStan/Psalm), no pre-commit hooks configured.
- Laravel Pint available for formatting (`vendor/bin/pint`) — no custom config file, uses defaults.

## Gotchas

- **NEVER use `$request->validate([...])` in controllers.** Always create a Form Request class in `app/Http/Requests/` and use `$request->validated()` in the controller. This is mandatory for all endpoints.
- **Controller `update()` allows changing `status` directly via validation rules, **bypassing** the `transitionTo()` state machine — use the dedicated status routes instead.
- No custom exception handler for JSON: if a business exception escapes the controller `try/catch`, Laravel returns HTML, not JSON.
- `HasPublication` trait events fire **after** the explicit observer, so the first `syncPublication()` runs before geolocation assigns location IDs.
- Pl@ntNet API has rate limits (500/day) — queue requests for production.
- Perenual API has rate limits (100/day) — local cache is primary source.
- Geolocation auto-runs on create/update via observers (Nominatim). The User-Agent header in `GeolocationService.php` is a placeholder — set a real value before production.

## Deployment

- `start.sh` runs `config:cache`, `route:cache`, `view:cache` before Octane.
- Supervisord manages: octane (2 workers, 250 max-requests), reverb, queue-worker (`--queue=exports,default --sleep=3 --tries=3 --max-time=3600`), scheduler.
- Upload limit: 50M (`custom-php.ini`).

## Sensitive files (never commit)

`.env`, `custom-php.ini`, `docker-compose.yml`, `Dockerfile`, `nginx.conf`, `nginx-main.conf`, `supervisord.conf` are gitignored. Use `.example` counterparts as templates.
