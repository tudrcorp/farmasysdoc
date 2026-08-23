# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Language

Responder siempre en español al usuario, incluidas explicaciones y resúmenes de cambios. El código, nombres de variables/métodos, commits y comentarios en el código se mantienen en inglés según las convenciones existentes del proyecto.

Excepción: los textos que ve el usuario final (labels de Filament, `->label()`, `modelLabel`, notificaciones, mensajes de validación, vistas Blade, etiquetas de enums) van en español, siguiendo lo que ya hacen los archivos vecinos.

## Project Overview

Farmadoc (`farmasysdoc`) is a Laravel 13 + Filament 5 back-office system for a pharmacy chain operating in Venezuela: inventory/lots (FEFO), sales/POS, purchases, accounts payable/receivable, payroll & HR, fiscal receipts, product transfers between branches, delivery logistics, bank conciliation (BDV / Cashea), and marketing.

Satellite clients:
- `mobile/farmadelivery` — React Native (Expo, TypeScript) delivery-driver app, talks to the Sanctum-protected `api/v1/delivery/*` routes.
- Third-party pharmacy partners integrate via token-authenticated `api/external/*` routes (`App\Http\Middleware\AuthenticateApiClient` + the `ApiClient` model).
- A public storefront (`/`, `/buscar-productos`, `/docs/api`, `/sitemap.xml`) served by plain controllers/Blade.

Locale/currency context matters throughout: money is tracked in both USD and VES (`*_usd`/`*_ves` column pairs), exchange rates come from `App\Services\Dolar\*` / `VenezuelaOfficialUsdVesRateClient`, and fiscal/tax logic follows Venezuelan SENIAT rules. App locale is `es`, timezone `America/Caracas`.

## Commands

```bash
composer dev                              # server + queue:listen + pail + vite concurrently
composer lint                             # pint --parallel over the whole codebase
vendor/bin/pint --dirty --format agent    # format only changed PHP files (required after PHP edits)
php artisan test --compact                # full suite
php artisan test --compact --filter=name  # single test
npm run dev / npm run build               # Vite (Tailwind v4 + JS assets)
```

The app is always served via Laravel Herd at `https://farmasysdoc.test` — never start `php artisan serve` or another web server for it; use the `get-absolute-url` Boost tool for links.

**Do not run tests or test-adjacent DB operations yourself.** Per `.cursor/rules/no-agent-test-execution.mdc`, agents must not execute `php artisan test`, Pest/PHPUnit directly, `migrate:fresh`, `schema:dump`, or anything that triggers `RefreshDatabase`/`DatabaseMigrations`/`LazilyRefreshDatabase`, and must not create/edit/delete files under `tests/` — unless the user explicitly asks for it in that message. This overrides the generic "every change must be tested by running tests" guidance below: instead, write/update the test code and tell the user how to run it themselves.

Domain maintenance commands live in `app/Console/Commands` (`accounts-payable:recalculate-current-balances` — also scheduled daily at 07:00 America/Caracas in `bootstrap/app.php`; FEFO lot seeding/sync; inventory CSV imports; purchase fiscal backfill; WhatsApp cash-box smoke tests). Check there before writing a new one-off script.

## Architecture

### Six distinct auth mechanisms
Do not mix these up when adding routes, resources or endpoints:

| Surface | Guard / middleware | Identity model |
| --- | --- | --- |
| `farmaadmin` Filament panel (`/farmaadmin`) | `web` + Filament `Authenticate` | `User` |
| `business-partners` Filament panel | its own panel guard | `PartnerCompanyUser` |
| Employee Portal (`portal/*`) | `employee.portal` / `employee.portal.guest` | `Employee` |
| Delivery app (`api/v1/delivery/*`) | `auth:sanctum` | `User` (delivery role) |
| Partner API (`api/external/*`) | `api.client` (`AuthenticateApiClient`) | `ApiClient` / `PartnerCompany` |
| Shop PWA (`/app`) | `shop` / `shop.auth` / `shop.guest` | `ShopCustomer` (`pwa_customers`) |

New tables for the PWA use the `pwa_` prefix. Do not reuse POS `clients` or `users`.

Middleware aliases are registered in `bootstrap/app.php`.

### Two Filament panels
- **`farmaadmin`** (`App\Providers\Filament\FarmaadminPanelProvider`, path `/farmaadmin`, `->default()`) — the main internal back-office. Resources/Pages/Widgets/Clusters are auto-discovered from `app/Filament/{Resources,Pages,Widgets,Clusters}`. Navigation groups are declared in the provider (`configuration`, `operations`, `hr`, `marketing`, `inventory`, `commercial_allies`, `reports`); its auth middleware stack adds `EnsureCashierShiftNotLocked`, `EnsureFarmaadminMenuAccess` and `AuditFarmaadminHttpActivity`.
- **`business-partners`** (`App\Providers\Filament\BusinessPartnersPanelProvider`) — a separate, self-contained panel for external partner companies with its own discovery roots under `app/Filament/BusinessPartners/{Resources,Pages}` and its own `Login` page.

When adding a resource, check which panel it belongs to first — the two are not interchangeable.

### Filament resource file layout
Each resource is a folder, not a single file. Follow the existing shape (see `app/Filament/Resources/Sales/`):

```
Resources/<Plural>/
  <Model>Resource.php      # only wiring: model, labels, icon, navigationGroup, pages
  Schemas/<Model>Form.php  # form schema
  Schemas/<Model>Infolist.php
  Tables/<Model>sTable.php
  Pages/{List,Create,Edit,View}<Model>.php
  Actions/…                # custom Filament actions
  Widgets/…                # resource-scoped stats/charts
  Support/…                # shared form-schema builders
```

Cross-resource behaviour lives in traits under `app/Filament/Resources/Concerns` (`AdministratorOnlyFarmaadminAccess`, `ChecksConfigurationAccess`, `RestrictsAccessForDeliveryUsers`).

Note: `Resources/Roles/RoleResource` is a thin subclass of `Resources/Rols/RolResource` kept so route names read `resources.roles.*`. Edit the `Rols/` implementation, not the alias.

### Domain layering: Models → Services → Support → Filament/Livewire/Http
- `app/Models` — 71 Eloquent models, one per business entity. Financial models generally carry parallel `_usd`/`_ves` money columns.
- `app/Services/{Sales,Purchases,Finance,Fiscal,Hr,Inventory,Dashboard,Marketing,Pricing,Dolar,BdvConciliation,Reports,Audit}` — domain services encapsulating workflows that span multiple models (`PurchaseBookFromPurchaseSynchronizer`, `SaleVoidService`, `PayrollCalculator`, `InventoryAuditApplyService`…). Prefer extending/reusing an existing service in the matching domain folder over writing logic inline in a Filament resource or controller.
- `app/Support/{...}` — lower-level helpers/value objects backing the services (Cash, Deliveries, Filament, Filesystem, Finance, Fiscal, Hr, Inventory, Livewire, Maps, Notifications, Orders, Partners, Products, ProductTransfers, Purchases, Qr, Sales, Storefront, Users).
- `app/Enums` — ~35 backed enums for statuses and typed domain vocabulary (`SaleStatus`, `OrderStatus`, `PurchaseStatus`, `ProductTransferStatus`, `Hr*`, `Marketing*`…). New status columns should get an enum + Spanish labels; `tests/Unit/EnumSpanishLabelsTest.php` guards those labels.
- `app/Filament` — panel UI that calls into Services; avoid putting business logic directly in resource/page classes.
- `app/Livewire/{Actions,EmployeePortal,Filament,Hr}` — standalone Livewire components outside Filament: the public Employee Portal, plus panel-injected widgets (`BcvExchangeRateBadge`, `BdvPagomovilConciliationFab`) mounted through `PanelsRenderHook` in the panel provider.
- `app/Http/Controllers` — thin controllers, mostly PDF/report endpoints and the external + delivery APIs.

### Authorization: roles, menu keys, and branch scope
There is no Spatie permissions package. Access control is homegrown and has three layers:

1. **Roles** — `Rol` model (`rols` table, name stored upper-cased) with an `allowed_menu_items` JSON column, related to branches through the `branch_rol` pivot. `User::isAdministrator()/isManager()/isCashier()/isCoordinator()/isDeliveryUser()` and friends read from it.
2. **Menu keys** — `App\Support\Filament\FarmaadminMenuAccessCatalog` is the single catalog mapping a menu key (`sales`, `purchases`, `hr_employees`, …) to a route-name fragment. `EnsureFarmaadminMenuAccess` enforces it on every `filament.farmaadmin.*` route, and `User::canAccessFarmaadminMenuKey()` gates UI. **Any new panel resource/page must be registered in this catalog**, or it becomes invisible/inaccessible to non-administrators. Keys prefixed `__permission_*__` (e.g. `sales_void`, `product_direct_price`) are permission flags with no route of their own.
3. **Branch scope** — `App\Support\Filament\BranchAuthScope` narrows table queries to `User::restrictedBranchIdsForQueries()` (union of role branches via `branch_rol`, GERENCIA branches via `branch_user`, and `users.branch_id`). Administrators and delivery users see everything; users with no branches get `whereRaw('1 = 0')`; cashiers additionally only see sales they created. Apply it in `getEloquentQuery()` on any new branch-scoped resource instead of hand-rolling a filter.

### Two synchronized ledgers per transaction
Purchases and sales don't just write one record: creating/annulling a `Purchase` fans out to `PurchaseBook` (SENIAT fiscal book), `PurchaseLedger`, `PurchaseHistory`, and `AccountsPayable` via dedicated `*FromPurchaseSynchronizer` services in `app/Services/Finance`, each keeping its own copy of derived monetary/fiscal fields. Sales fan out to `AccountsReceivableFromSaleRegistrar` and inventory movements. When changing purchase or sale logic, check whether a synchronizer needs updating too, not just the source model.

### SENIAT IVA retention (Venezuela tax rule)
The retention percentage always comes from `suppliers.seniat_retention_percent` (never hardcode 75%/100%/other fixed values). Formula: `tax_retained_ves = tax_caused_ves × (seniat_retention_percent / 100)`. If a supplier has no percent configured, require it before proceeding rather than defaulting. `PurchaseBook` sync must read the percent from the purchase's supplier and persist both the percent and the computed retained amount; PDFs/widgets/totals must sum the already-computed retained value, not recompute with a different percent. (See `.cursor/rules/seniat-retention-from-supplier.mdc`.) Retention-agent identity and voucher numbering live in `config/fiscal.php`.

### Tax rates come from the DB, not config
`config/orders.php` (`default_vat_rate_percent`, `default_igtf_rate_percent`) is only a fallback. The effective IVA/IGTF rates live in the single-row `financial_settings` table (`FinancialSetting::current()`, edited on the "Administración financiera" page) and are read through `App\Support\Finance\DefaultVatRate` / `DefaultIgtfRate`, which cache and invalidate on save. Read rates through those helpers.

### Multi-currency & exchange rates
`App\Services\Dolar\{DolarApiDolaresService,DolarApiEstadoService}` (config `config/dolar.php`) and `Finance\VenezuelaOfficialUsdVesRateClient` fetch the official BCV rate; `HrBcvRateResolver`/`HrUsdVesConverter` apply it to payroll. Always resolve rates through these services rather than calling an external endpoint directly.

### Inventory: FEFO lots
Stock is tracked both as aggregate `Inventory` rows and as `ProductLot` / `InventoryLotBalance` records dispatched First-Expired-First-Out. `app/Services/Inventory` holds the dispatch (`FefoLotSaleDispatchService`, `FefoLotTransferDispatchService`), sync (`InventoryLotBalanceSyncService`), audit (`InventoryAuditApplyService`, OTP-protected) and POS alerting (`PosFefoAlertLogRegistrar`) pieces. Near-expiry thresholds and alert dedupe windows are in `config/inventory.php`.

### Cashier shift locking
After closing the physical cash box, a cashier is locked out until the next daily unlock hour (`config/cashier_shift.php`), enforced by `EnsureCashierShiftNotLocked` in the panel's auth middleware; administrators can override from the "Acceso cajeros (turno)" page.

### Audit trail
Two complementary mechanisms, both writing `AuditLog` rows via `App\Services\Audit\AuditLogger`:
- `AuditModelObserver` on the models listed in `config/audit.php` (`models` key) records created/updated/deleted.
- `AuditFarmaadminHttpActivity` records panel navigation, throttled per user+route by `audit.http_log_window_seconds`.

To audit a new model, add it to `config/audit.php` rather than wiring an observer by hand.

### PDFs, mail and WhatsApp
PDFs use `barryvdh/laravel-dompdf` with Blade views under `resources/views/pdf`, generated by `*PdfFactory`/`*PdfGenerator` services and served by controllers in `app/Http/Controllers/{Finance,Purchases,Sales,ProductTransfers,Reports}`. Most report URLs are `->middleware('signed')` — generate them with `URL::signedRoute()`, not `route()`. Mailables in `app/Mail` are dispatched through queued jobs in `app/Jobs`. WhatsApp delivery goes through `App\Support\Notifications\UltramsgWhatsAppClient` / `WhatsAppLink`.

### Livewire request normalization quirk
`TrimStrings` and `ConvertEmptyStringsToNull` are removed from the global stack and replaced by `ConditionalTrimStrings`/`ConditionalConvertEmptyStringsToNull`, which skip Livewire payloads (`App\Support\Livewire\LivewireRequestPayload`) to avoid corrupting component state. Don't re-add the framework defaults.

## Testing

Pest 4 with `tests/Feature` (majority) and `tests/Unit`. `phpunit.xml` runs against in-memory SQLite with `APP_LOCALE=es`, array mail/cache/session and `QUEUE_CONNECTION=sync` — assertions about UI text should expect Spanish. Filament coverage lives in `tests/Feature/Filament` and uses `livewire()` component tests; external API coverage in `tests/Feature/Api`.

Remember the rule above: write or update tests, but let the user run them.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- filament/filament (FILAMENT) - v5
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- livewire/flux (FLUXUI_FREE) - v2
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `fluxui-development` — Use this skill for Flux UI development in Livewire applications only. Trigger when working with <flux:*> components, building or customizing Livewire component UIs, creating forms, modals, tables, or other interactive elements. Covers: flux: components (buttons, inputs, modals, forms, tables, date-pickers, kanban, badges, tooltips, etc.), component composition, Tailwind CSS styling, Heroicons/Lucide icon integration, validation patterns, responsive design, and theming. Do not use for non-Livewire frameworks or non-component styling.
- `livewire-development` — Use for any task or question involving Livewire. Activate if user mentions Livewire, wire: directives, or Livewire-specific concepts like wire:model, wire:click, wire:sort, or islands, invoke this skill. Covers building new components, debugging reactivity issues, real-time form validation, drag-and-drop, loading states, migrating from Livewire 3 to 4, converting component formats (SFC/MFC/class-based), and performance optimization. Do not use for non-Livewire reactive UI (React, Vue, Alpine-only, Inertia.js) or standard Laravel forms without Livewire.
- `pest-testing` — Use this skill for Pest PHP testing in Laravel projects only. Trigger whenever any test is being written, edited, fixed, or refactored — including fixing tests that broke after a code change, adding assertions, converting PHPUnit to Pest, adding datasets, and TDD workflows. Always activate when the user asks how to write something in Pest, mentions test files or directories (tests/Feature, tests/Unit, tests/Browser), or needs browser testing, smoke testing multiple pages for JS errors, or architecture tests. Covers: it()/expect() syntax, datasets, mocking, browser testing (visit/click/fill), smoke testing, arch(), Livewire component tests, RefreshDatabase, and all Pest 4 features. Do not use for factories, seeders, migrations, controllers, models, or non-test PHP code.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.
- `fortify-development` — Laravel Fortify headless authentication backend development. Activate when implementing authentication features including login, registration, password reset, email verification, two-factor authentication (2FA/TOTP), profile updates, headless auth, authentication scaffolding, or auth guards in Laravel applications.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan Commands

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`, `php artisan tinker --execute "..."`).
- Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Debugging

- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.
- To execute PHP code for debugging, run `php artisan tinker --execute "your code here"` directly.
- To read configuration values, read the config files directly or run `php artisan config:show [key]`.
- To inspect routes, run `php artisan route:list` directly.
- To check environment variables, read the `.env` file directly.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd and will be available at: `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs for the user.
- You must not run any commands to make the site available via HTTP(S). It is always available through Laravel Herd.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== filament/filament rules ===

## Filament

- Filament is used by this application. Follow the existing conventions for how and where it is implemented.
- Filament is a Server-Driven UI (SDUI) framework for Laravel that lets you define user interfaces in PHP using structured configuration objects. Built on Livewire, Alpine.js, and Tailwind CSS.
- Use the `search-docs` tool for official documentation on Artisan commands, code examples, testing, relationships, and idiomatic practices. If `search-docs` is unavailable, refer to https://filamentphp.com/docs.

### Artisan

- Always use Filament-specific Artisan commands to create files. Find available commands with the `list-artisan-commands` tool, or run `php artisan --help`.
- Always inspect required options before running a command, and always pass `--no-interaction`.

### Patterns

Always use static `make()` methods to initialize components. Most configuration methods accept a `Closure` for dynamic values.

Use `Get $get` to read other form field values for conditional logic:

<code-snippet name="Conditional form field visibility" lang="php">
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

Select::make('type')
    ->options(CompanyType::class)
    ->required()
    ->live(),

TextInput::make('company_name')
    ->required()
    ->visible(fn (Get $get): bool => $get('type') === 'business'),

</code-snippet>

Use `state()` with a `Closure` to compute derived column values:

<code-snippet name="Computed table column value" lang="php">
use Filament\Tables\Columns\TextColumn;

TextColumn::make('full_name')
    ->state(fn (User $record): string => "{$record->first_name} {$record->last_name}"),

</code-snippet>

Actions encapsulate a button with an optional modal form and logic:

<code-snippet name="Action with modal form" lang="php">
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;

Action::make('updateEmail')
    ->schema([
        TextInput::make('email')
            ->email()
            ->required(),
    ])
    ->action(fn (array $data, User $record) => $record->update($data))

</code-snippet>

### Testing

Always authenticate before testing panel functionality. Filament uses Livewire, so use `Livewire::test()` or `livewire()` (available when `pestphp/pest-plugin-livewire` is in `composer.json`):

<code-snippet name="Table test" lang="php">
use function Pest\Livewire\livewire;

livewire(ListUsers::class)
    ->assertCanSeeTableRecords($users)
    ->searchTable($users->first()->name)
    ->assertCanSeeTableRecords($users->take(1))
    ->assertCanNotSeeTableRecords($users->skip(1));

</code-snippet>

<code-snippet name="Create resource test" lang="php">
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

livewire(CreateUser::class)
    ->fillForm([
        'name' => 'Test',
        'email' => 'test@example.com',
    ])
    ->call('create')
    ->assertNotified()
    ->assertRedirect();

assertDatabaseHas(User::class, [
    'name' => 'Test',
    'email' => 'test@example.com',
]);

</code-snippet>

<code-snippet name="Testing validation" lang="php">
use function Pest\Livewire\livewire;

livewire(CreateUser::class)
    ->fillForm([
        'name' => null,
        'email' => 'invalid-email',
    ])
    ->call('create')
    ->assertHasFormErrors([
        'name' => 'required',
        'email' => 'email',
    ])
    ->assertNotNotified();

</code-snippet>

<code-snippet name="Calling actions in pages" lang="php">
use Filament\Actions\DeleteAction;
use function Pest\Livewire\livewire;

livewire(EditUser::class, ['record' => $user->id])
    ->callAction(DeleteAction::class)
    ->assertNotified()
    ->assertRedirect();

</code-snippet>

<code-snippet name="Calling actions in tables" lang="php">
use Filament\Actions\Testing\TestAction;
use function Pest\Livewire\livewire;

livewire(ListUsers::class)
    ->callAction(TestAction::make('promote')->table($user), [
        'role' => 'admin',
    ])
    ->assertNotified();

</code-snippet>

### Correct Namespaces

- Form fields (`TextInput`, `Select`, etc.): `Filament\Forms\Components\`
- Infolist entries (`TextEntry`, `IconEntry`, etc.): `Filament\Infolists\Components\`
- Layout components (`Grid`, `Section`, `Fieldset`, `Tabs`, `Wizard`, etc.): `Filament\Schemas\Components\`
- Schema utilities (`Get`, `Set`, etc.): `Filament\Schemas\Components\Utilities\`
- Actions (`DeleteAction`, `CreateAction`, etc.): `Filament\Actions\`. Never use `Filament\Tables\Actions\`, `Filament\Forms\Actions\`, or any other sub-namespace for actions.
- Icons: `Filament\Support\Icons\Heroicon` enum (e.g., `Heroicon::PencilSquare`)

### Common Mistakes

- **Never assume public file visibility.** File visibility is `private` by default. Always use `->visibility('public')` when public access is needed.
- **Never assume full-width layout.** `Grid`, `Section`, and `Fieldset` do not span all columns by default. Explicitly set column spans when needed.

=== laravel/fortify rules ===

# Laravel Fortify

- Fortify is a headless authentication backend that provides authentication routes and controllers for Laravel applications.
- IMPORTANT: Always use the `search-docs` tool for detailed Laravel Fortify patterns and documentation.
- IMPORTANT: Activate `developing-with-fortify` skill when working with Fortify authentication features.

</laravel-boost-guidelines>
