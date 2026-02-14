# Laravel Feature Flags

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mugiwara/laravel-feature-flags.svg?style=flat-square)](https://packagist.org/packages/mugiwara/laravel-feature-flags)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg?style=flat-square)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue.svg?style=flat-square)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-10.x%20%7C%2011.x%20%7C%2012.x-red.svg?style=flat-square)](https://laravel.com)

A powerful, zero-dependency feature flag package for Laravel. Control feature rollouts across every layer of your application — routes, Blade views, Eloquent models, service classes, and relationships — with multi-tenancy, user segments, and percentage-based rollouts built in.

## Features

- **Two storage drivers** — config file (zero setup) or database (full runtime control)
- **Route middleware** — gate routes with 4 response types (404, 403, JSON, redirect)
- **Blade directives** — `@feature`, `@elsefeature`, `@unlessfeature`, `@featureany`
- **Model protection** — hide entire Eloquent models via global scopes
- **Service method gating** — guard business logic with exceptions or silent fallbacks
- **Relationship scoping** — gate `hasMany`, `hasOne`, `belongsTo`, `belongsToMany`
- **User segment targeting** — restrict features to specific user groups
- **Percentage rollout** — gradual rollout with consistent hashing (CRC32)
- **Multi-tenancy** — tenant-scoped flags with global fallback inheritance
- **Artisan commands** — `feature:list`, `feature:create`, `feature:enable`, `feature:disable`
- **Events** — `FeatureEnabled` and `FeatureDisabled` dispatched on every toggle
- **Caching** — configurable TTL with automatic invalidation via model observer
- **Custom drivers** — implement the `FeatureDriver` interface for Redis, etc.
- **Custom resolvers** — override all checks with your own logic

## Requirements

- PHP 8.1+
- Laravel 10.x, 11.x, or 12.x

---

## Table of Contents

- [Installation](#installation)
- [Configuration](#configuration)
- [Quick Start](#quick-start)
- [Drivers](#drivers)
  - [Config Driver](#config-driver)
  - [Database Driver](#database-driver)
  - [Custom Drivers](#custom-drivers)
- [Usage](#usage)
  - [Facade](#facade)
  - [Dependency Injection](#dependency-injection)
  - [Route Middleware](#route-middleware)
  - [Blade Directives](#blade-directives)
  - [Model Protection](#model-protection)
  - [Service Method Gating](#service-method-gating)
  - [Relationship Scoping](#relationship-scoping)
- [User Segments](#user-segments)
- [Percentage Rollouts](#percentage-rollouts)
- [Multi-Tenancy](#multi-tenancy)
- [Artisan Commands](#artisan-commands)
- [Events](#events)
- [Exception Handling](#exception-handling)
- [Custom Resolvers](#custom-resolvers)
- [FeatureFlag Model](#featureflag-model)
- [Architecture](#architecture)
- [Testing](#testing)
- [License](#license)

---

## Installation

```bash
composer require mugiwara/laravel-feature-flags
```

The package uses Laravel's auto-discovery — no manual provider registration needed.

**Publish the config file:**

```bash
php artisan vendor:publish --tag=feature-flags-config
```

**If using the database driver**, also publish and run the migration:

```bash
php artisan vendor:publish --tag=feature-flags-migrations
php artisan migrate
```

> If you have disabled auto-discovery, register the provider and facade manually:
>
> ```php
> // config/app.php
> 'providers' => [
>     MugiWara\FeatureFlags\FeatureFlagsServiceProvider::class,
> ],
>
> 'aliases' => [
>     'Feature' => MugiWara\FeatureFlags\Facades\Feature::class,
> ],
> ```

---

## Configuration

The published config file is at `config/features.php`:

```php
return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Define your feature flags here. Each flag can be toggled via env vars.
    | These are used by the config driver. The database driver reads from
    | the feature_flags table instead.
    |
    */
    'flags' => [
        'beta_dashboard'     => env('FEATURE_BETA_DASHBOARD', false),
        'advanced_reporting' => env('FEATURE_ADVANCED_REPORTING', false),
        'premium_content'    => env('FEATURE_PREMIUM_CONTENT', false),
        'api_v2'             => env('FEATURE_API_V2', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "config", "database"
    |
    */
    'driver' => env('FEATURE_DRIVER', 'config'),

    /*
    |--------------------------------------------------------------------------
    | Cache (database driver only)
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => env('FEATURE_CACHE_ENABLED', true),
        'ttl'     => env('FEATURE_CACHE_TTL', 3600), // seconds
        'prefix'  => 'feature_flag:',
    ],
];
```

**Environment variables:**

```env
FEATURE_DRIVER=database
FEATURE_CACHE_ENABLED=true
FEATURE_CACHE_TTL=3600
FEATURE_BETA_DASHBOARD=false
FEATURE_API_V2=true
```

---

## Quick Start

```php
use MugiWara\FeatureFlags\Facades\Feature;

// Check a flag
if (Feature::isEnabled('beta_dashboard')) {
    // new experience
}

// Conditional with fallback
$result = Feature::when('beta_dashboard',
    fn () => view('dashboard.beta'),
    fn () => view('dashboard.classic')
);

// Toggle at runtime (database driver)
Feature::enable('beta_dashboard');
Feature::disable('beta_dashboard');
```

---

## Drivers

### Config Driver

Reads flags from the `flags` array in `config/features.php`. Flags can be toggled via environment variables. Changes made with `enable()`/`disable()` are held in memory only and **not persisted** across requests.

```env
FEATURE_DRIVER=config
```

Best for: simple setups, CI/CD-driven toggles, apps that don't need runtime changes.

### Database Driver

Stores flags in the `feature_flags` table. Supports full runtime CRUD, caching, multi-tenancy, segments, and percentage rollouts.

```env
FEATURE_DRIVER=database
```

**Database schema:**

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `name` | string | Feature flag name (indexed) |
| `enabled` | boolean | On/off state (default: `false`) |
| `tenant_id` | string, nullable | Tenant scope (indexed). `NULL` = global |
| `description` | text, nullable | Human-readable description |
| `metadata` | json, nullable | Percentage rollout, user segments, etc. |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

Unique constraint on `(name, tenant_id)`.

**Caching:** The database driver caches flag lookups using Laravel's cache. The cache key format is `{prefix}{name}{:tenant_id}`. An observer (`FeatureFlagObserver`) automatically clears the cache when a `FeatureFlag` model is saved or deleted.

### Custom Drivers

Implement the `FeatureDriver` interface:

```php
use MugiWara\FeatureFlags\Contracts\FeatureDriver;

class RedisDriver implements FeatureDriver
{
    public function isEnabled(string $feature): bool
    {
        return (bool) Redis::get("feature:{$feature}");
    }

    public function enable(string $feature): void
    {
        Redis::set("feature:{$feature}", 1);
    }

    public function disable(string $feature): void
    {
        Redis::set("feature:{$feature}", 0);
    }
}
```

Register in a service provider:

```php
use MugiWara\FeatureFlags\Contracts\FeatureDriver;

$this->app->singleton(FeatureDriver::class, RedisDriver::class);
```

---

## Usage

### Facade

```php
use MugiWara\FeatureFlags\Facades\Feature;

Feature::isEnabled('beta_dashboard');    // bool
Feature::isDisabled('beta_dashboard');   // bool

Feature::enable('beta_dashboard');       // void (database driver persists)
Feature::disable('beta_dashboard');      // void

// Conditional execution with optional fallback
Feature::when('premium_content',
    fn () => showPremiumContent(),
    fn () => showFreeContent()
);

// Get all flags as ['name' => bool]
Feature::all();

// User-aware check (respects segments & percentage rollout)
Feature::forUser($user, 'beta_dashboard');
```

### Dependency Injection

Inject the `FeatureManager` contract:

```php
use MugiWara\FeatureFlags\Contracts\FeatureManager;

class DashboardController extends Controller
{
    public function __construct(protected FeatureManager $features) {}

    public function index()
    {
        if ($this->features->isEnabled('beta_dashboard')) {
            return view('dashboard.beta');
        }

        return view('dashboard.classic');
    }
}
```

### Route Middleware

The package registers a `feature` route middleware automatically. No manual Kernel registration needed.

**Syntax:** `feature:{flag_name},{response_type}`

```php
// 404 Not Found (default)
Route::get('/beta', [BetaController::class, 'index'])
    ->middleware('feature:beta_dashboard');

// 403 Forbidden
Route::get('/admin/reports', [ReportController::class, 'index'])
    ->middleware('feature:advanced_reporting,forbidden');

// JSON error response {"error": "Feature not available"} (ideal for APIs)
Route::get('/api/v2/users', [UserController::class, 'index'])
    ->middleware('feature:api_v2,json');

// Redirect to home route with flash message
Route::get('/premium', [PremiumController::class, 'index'])
    ->middleware('feature:premium_content,redirect');
```

**Response types:**

| Type | HTTP Status | Response |
|---|---|---|
| *(default)* | 404 | Not Found |
| `forbidden` | 403 | "This feature is disabled." |
| `json` | 404 | `{"error": "Feature not available"}` |
| `redirect` | 302 | Redirect to `home` route with `error` flash |

**Protect a route group:**

```php
Route::middleware('feature:api_v2,json')->prefix('v2')->group(function () {
    Route::get('/users', [UserV2Controller::class, 'index']);
    Route::get('/posts', [PostV2Controller::class, 'index']);
});
```

### Blade Directives

Seven Blade directives are registered automatically:

```blade
{{-- Show content when feature is enabled --}}
@feature('beta_dashboard')
    <x-beta-dashboard />
@endfeature

{{-- With an else branch --}}
@feature('beta_dashboard')
    <x-beta-dashboard />
@elsefeature
    <x-classic-dashboard />
@endfeature

{{-- Show content when feature is disabled --}}
@unlessfeature('maintenance_mode')
    <a href="/dashboard">Go to Dashboard</a>
@endunlessfeature

{{-- Show content when ANY of the listed features is enabled --}}
@featureany(['beta_dashboard', 'new_ui', 'premium_content'])
    <p>At least one new feature is active!</p>
@endfeatureany
```

**Full example:**

```blade
<div class="container">
    @feature('welcome_banner')
        <div class="alert alert-info">
            New features are here! Check out the updated dashboard.
        </div>
    @elsefeature
        <p>Welcome back.</p>
    @endfeature

    @feature('maintenance_mode')
        <p class="text-danger">We're under maintenance. Check back later.</p>
    @endfeature

    @unlessfeature('maintenance_mode')
        <a href="/admin" class="btn btn-primary">Go to Admin</a>
    @endunlessfeature

    @featureany(['beta_dashboard', 'premium_content'])
        <div class="alert alert-success">
            You have access to new features!
        </div>
    @endfeatureany
</div>
```

### Model Protection

Use the `HasFeatureFlags` trait to make an Eloquent model invisible when its feature is disabled. A global scope is registered automatically at boot — no extra setup needed.

```php
use Illuminate\Database\Eloquent\Model;
use MugiWara\FeatureFlags\Concerns\HasFeatureFlags;

class PremiumContent extends Model
{
    use HasFeatureFlags;

    public function getFeatureFlag(): string
    {
        return 'premium_content';
    }
}
```

**When `premium_content` is disabled:**

```php
PremiumContent::all();              // Empty collection
PremiumContent::find(1);            // null
PremiumContent::where(...)->get();  // Empty collection
```

**When enabled:** all queries work normally.

**Instance methods:**

```php
$content->featureIsEnabled();    // bool
$content->featureIsDisabled();   // bool

$content->whenFeatureEnabled(
    fn () => $content->render(),        // runs if enabled
    fn () => 'Feature not available'    // optional fallback
);
```

**Bypass the scope:**

```php
PremiumContent::withoutGlobalScopes()->get();
```

### Service Method Gating

Use the `FeatureGuarded` trait to guard individual methods behind feature flags.

```php
use MugiWara\FeatureFlags\Concerns\FeatureGuarded;

class PaymentService
{
    use FeatureGuarded;

    // Throws FeatureDisabledException (403) when disabled
    public function processPayment(array $data): void
    {
        $this->requireFeature('online_payments');
        // ... process payment
    }

    // Returns null silently when disabled
    public function sendReceipt(string $email): ?bool
    {
        return $this->whenFeature('email_receipts', function () use ($email) {
            Mail::to($email)->send(new ReceiptMail());
            return true;
        });
    }

    // Throws FeatureDisabledException, then runs callback
    public function refund(int $orderId): array
    {
        return $this->executeIfFeature('online_payments', function () use ($orderId) {
            return $this->processRefund($orderId);
        });
    }
}
```

| Method | When disabled |
|---|---|
| `requireFeature(string $feature)` | Throws `FeatureDisabledException` |
| `whenFeature(string $feature, callable $callback)` | Returns `null` |
| `executeIfFeature(string $feature, callable $callback)` | Throws `FeatureDisabledException` |

### Relationship Scoping

Use the `FeatureScopedRelation` trait to gate Eloquent relationships. When the feature is disabled, the relationship returns empty results — no crashes, no null checks needed.

```php
use MugiWara\FeatureFlags\Concerns\FeatureScopedRelation;

class User extends Model
{
    use FeatureScopedRelation;

    public function reports()
    {
        return $this->hasManyWithFeature('advanced_reporting', Report::class);
    }

    public function premiumSubscription()
    {
        return $this->belongsToWithFeature('premium_access', Subscription::class);
    }

    public function betaProfile()
    {
        return $this->hasOneWithFeature('beta_profile', BetaProfile::class);
    }

    public function teams()
    {
        return $this->belongsToManyWithFeature('team_feature', Team::class);
    }
}
```

**Supported relationship types:**

| Method | Relationship |
|---|---|
| `hasManyWithFeature($flag, $related, ...)` | HasMany |
| `hasOneWithFeature($flag, $related, ...)` | HasOne |
| `belongsToWithFeature($flag, $related, ...)` | BelongsTo |
| `belongsToManyWithFeature($flag, $related, ...)` | BelongsToMany |
| `featureRelation($flag, $callback)` | Any relationship (generic) |

All methods accept the same optional parameters as their native Laravel counterparts (`$foreignKey`, `$localKey`, etc.).

**Generic approach:**

```php
public function customRelation()
{
    return $this->featureRelation('some_feature', function () {
        return $this->hasMany(SomeModel::class);
    });
}
```

---

## User Segments

Target features to specific user groups. Requires the database driver.

**Step 1:** Implement `HasFeatureSegments` on your User model:

```php
use MugiWara\FeatureFlags\Contracts\HasFeatureSegments;

class User extends Authenticatable implements HasFeatureSegments
{
    public function getFeatureSegments(): array
    {
        return array_filter([
            $this->role,    // 'admin', 'editor', 'viewer'
            $this->plan,    // 'free', 'pro', 'enterprise'
            'all_users',
        ]);
    }
}
```

**Step 2:** Create a flag with segment restrictions in the `metadata`:

```php
use MugiWara\FeatureFlags\Models\FeatureFlag;

FeatureFlag::create([
    'name'     => 'beta_dashboard',
    'enabled'  => true,
    'metadata' => [
        'segments' => ['beta_testers', 'admin'],
    ],
]);
```

**Step 3:** Use `forUser()` to check:

```php
Feature::forUser($user, 'beta_dashboard');
// Returns true only if the user's segments intersect with ['beta_testers', 'admin']
```

**How it works:** If the flag's `metadata.segments` array is empty or not set, `forUser()` returns `true` for all users (when the flag is enabled). If segments are defined, the user must belong to at least one of them.

---

## Percentage Rollouts

Gradually roll out a feature to a percentage of users. Uses consistent hashing (CRC32) so the same user always gets the same result — no flickering.

```php
use MugiWara\FeatureFlags\Strategies\PercentageRolloutStrategy;

$strategy = new PercentageRolloutStrategy();

// Enable for 25% of users
if ($strategy->shouldEnable('new_checkout', auth()->id(), 25)) {
    return view('checkout.new');
}

return view('checkout.classic');
```

**How it works:**

```
bucket = abs(crc32("{feature}:{identifier}")) % 100
enabled = bucket < percentage
```

- `percentage = 0` — always disabled
- `percentage = 100` — always enabled
- `percentage = 25` — enabled for ~25% of users, deterministically

**Debug a user's bucket:**

```php
$bucket = $strategy->getBucket('new_checkout', $userId);
// Returns 0–99
```

**Store percentage in the database:**

```php
FeatureFlag::create([
    'name'     => 'new_checkout',
    'enabled'  => true,
    'metadata' => ['percentage' => 25],
]);
```

When using `Feature::forUser($user, 'new_checkout')`, the database driver automatically reads the `metadata.percentage` and applies the rollout strategy.

---

## Multi-Tenancy

The database driver has built-in multi-tenant support. Tenant-specific flags override global flags.

**Set tenant context** (typically in middleware):

```php
use MugiWara\FeatureFlags\Drivers\DatabaseDriver;

class SetTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        $tenantId = $request->user()?->tenant_id;
        app(DatabaseDriver::class)->setTenant($tenantId);
        return $next($request);
    }
}
```

**Flag resolution order:**

1. Tenant-specific flag (`tenant_id = $tenantId`) — checked first
2. Global flag (`tenant_id IS NULL`) — fallback

**Example:**

```bash
# Global: beta_dashboard is OFF for everyone
php artisan feature:create beta_dashboard

# Override: ON for tenant "acme_corp"
php artisan feature:enable beta_dashboard --tenant=acme_corp
```

Acme Corp users see the beta dashboard. All other tenants see the classic dashboard.

---

## Artisan Commands

All commands support the `--tenant` option when using the database driver.

### `feature:list`

List all flags and their status.

```bash
php artisan feature:list
php artisan feature:list --tenant=acme_corp
```

### `feature:create`

Create a new feature flag (database driver only).

```bash
php artisan feature:create beta_dashboard
php artisan feature:create beta_dashboard --enabled
php artisan feature:create beta_dashboard --enabled --description="New dashboard experience"
php artisan feature:create beta_dashboard --enabled --percentage=25
php artisan feature:create beta_dashboard --tenant=acme_corp
```

| Option | Description |
|---|---|
| `--enabled` | Create in enabled state (default: disabled) |
| `--description=` | Human-readable description |
| `--percentage=` | Percentage rollout (1–99) |
| `--tenant=` | Scope to a specific tenant |

### `feature:enable`

Enable an existing flag.

```bash
php artisan feature:enable beta_dashboard
php artisan feature:enable beta_dashboard --tenant=acme_corp
```

### `feature:disable`

Disable an existing flag.

```bash
php artisan feature:disable beta_dashboard
php artisan feature:disable beta_dashboard --tenant=acme_corp
```

---

## Events

Two events are dispatched when flags are toggled via `Feature::enable()` or `Feature::disable()`:

```php
use MugiWara\FeatureFlags\Events\FeatureEnabled;
use MugiWara\FeatureFlags\Events\FeatureDisabled;
```

Both events have the same properties:

| Property | Type | Description |
|---|---|---|
| `$feature` | `string` | The feature flag name |
| `$tenantId` | `?string` | Tenant context at the time of change, or `null` |

**Listen for changes:**

```php
use Illuminate\Support\Facades\Event;
use MugiWara\FeatureFlags\Events\FeatureEnabled;
use MugiWara\FeatureFlags\Events\FeatureDisabled;

Event::listen(FeatureEnabled::class, function (FeatureEnabled $event) {
    Log::info("Feature enabled: {$event->feature}", ['tenant' => $event->tenantId]);
});

Event::listen(FeatureDisabled::class, function (FeatureDisabled $event) {
    Log::info("Feature disabled: {$event->feature}", ['tenant' => $event->tenantId]);
});
```

---

## Exception Handling

The `FeatureDisabledException` is thrown by `FeatureGuarded::requireFeature()` and `FeatureGuarded::executeIfFeature()`.

**Default rendering:**

| Request type | Response |
|---|---|
| JSON (`expectsJson()`) | `{"error": "This feature is currently disabled."}` with HTTP 403 |
| Web | `abort(403, 'This feature is currently disabled.')` |

**Custom handling** in your exception handler:

```php
use MugiWara\FeatureFlags\Exceptions\FeatureDisabledException;

// In bootstrap/app.php (Laravel 11+) or app/Exceptions/Handler.php
$this->renderable(function (FeatureDisabledException $e, Request $request) {
    if ($request->expectsJson()) {
        return response()->json(['message' => $e->getMessage()], 403);
    }

    return redirect()->back()->with('warning', $e->getMessage());
});
```

---

## Custom Resolvers

Override all feature checks with custom logic. When a resolver is set, it takes priority over the driver.

```php
use MugiWara\FeatureFlags\Facades\Feature;

// Set a custom resolver
Feature::resolveUsing(function (string $feature): bool {
    return ExternalService::isEnabled($feature);
});

// Reset to default (driver-based) behavior
Feature::resolveUsing(null);
```

---

## FeatureFlag Model

The `FeatureFlag` Eloquent model provides query scopes and metadata helpers.

```php
use MugiWara\FeatureFlags\Models\FeatureFlag;
```

**Query scopes:**

```php
FeatureFlag::global()->get();            // WHERE tenant_id IS NULL
FeatureFlag::forTenant('acme')->get();   // WHERE tenant_id = 'acme'
FeatureFlag::enabled()->get();           // WHERE enabled = true
FeatureFlag::disabled()->get();          // WHERE enabled = false
```

**Metadata helpers:**

```php
$flag = FeatureFlag::where('name', 'new_ui')->first();

$flag->hasPercentageRollout();                // bool
$flag->getRolloutPercentage();                // int|null
$flag->getAllowedSegments();                  // string[]
$flag->isAvailableForSegment('beta_testers'); // bool
```

**Creating flags with metadata:**

```php
FeatureFlag::create([
    'name'        => 'new_checkout',
    'enabled'     => true,
    'description' => 'Redesigned checkout flow',
    'metadata'    => [
        'percentage' => 25,
        'segments'   => ['beta_testers', 'staff'],
    ],
]);
```

---

## Architecture

```
Feature Facade
    └── FeatureManager (implements Contracts\FeatureManager)
            ├── Custom resolver (takes priority when set)
            └── FeatureDriver (interface)
                    ├── ConfigDriver (reads from config/features.php)
                    └── DatabaseDriver
                            ├── Cache layer (configurable TTL)
                            ├── Multi-tenant query scoping
                            ├── FeatureFlag Eloquent model
                            ├── FeatureFlagObserver (auto cache invalidation)
                            └── PercentageRolloutStrategy (CRC32 hashing)

Protection layers:
    ├── RequireFeature middleware (HTTP routes)
    ├── Blade directives (@feature, @elsefeature, @unlessfeature, @featureany)
    ├── HasFeatureFlags trait (Eloquent global scopes)
    ├── FeatureGuarded trait (service method gating)
    └── FeatureScopedRelation trait (relationship scoping)
```

**Key contracts:**

| Contract | Description |
|---|---|
| `Contracts\FeatureManager` | Main API: `isEnabled`, `enable`, `disable`, `when`, `all`, `forUser`, `resolveUsing` |
| `Contracts\FeatureDriver` | Storage backend: `isEnabled`, `enable`, `disable` |
| `Contracts\HasFeatureSegments` | User model interface: `getFeatureSegments(): array` |

---

## Testing

```bash
composer test
```

The test suite uses Orchestra Testbench and covers all features: facade, middleware, Blade directives, model protection, service gating, relationship scoping, user segments, percentage rollouts, multi-tenancy, events, caching, and Artisan commands.

---

## License

MIT License. See [LICENSE](LICENSE) for details.

---

**Author:** Sohaib Bilal — [sohaibhbill@gmail.com](mailto:sohaibhbill@gmail.com)
