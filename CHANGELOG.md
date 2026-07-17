# Changelog

All notable changes to `laravel-api-generator` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.7.1] - 2026-07-17

### Fixed
- Corrected the maintainer contact email in `composer.json` and the README security section: loicaronmbassiewolo@gmail.com.

## [3.7.0] - 2026-07-17

### Added
- **`--json-api`** -- Generates [JSON:API](https://jsonapi.org/)-compliant resources extending `Illuminate\Http\Resources\JsonApi\JsonApiResource` (Laravel 12.45+): the resource declares an `$attributes` list (fields plus timestamps, the `id` becoming the JSON:API resource identifier) and a `$relationships` list built from the entity's relations. Controllers are unchanged -- `Resource::collection()` and `new Resource()` produce the JSON:API envelope automatically. The generated feature test asserts the response with `assertJsonPath('data.id', ...)` in this mode. On Laravel below 12.45 the flag warns once and falls back to a standard resource rather than emit an unresolvable class.

## [3.6.1] - 2026-07-17

### Fixed
- **The package could not be installed on Laravel 13** -- Laravel 13.0 was released on 2026-03-17, but the `laravel/framework` constraint stopped at `^12.0`, so `composer require` was rejected on any up-to-date application. The constraint now allows `^13.0`, with `symfony/yaml` widened to `^8.0` accordingly.
- **55 of the 68 tests were silently not collected on PHPUnit 12** -- the suite declared its tests with `/** @test */`, a doc-comment form PHPUnit 12 no longer reads; it reported 13 tests and still exited 0. Tests now use the `#[Test]` attribute. Generated stubs were never affected: they name their methods `test_*`, which every PHPUnit version collects.

### Changed
- CI now covers PHP 8.3/8.4 x Laravel 13 (and Laravel 12 was already covered), alongside the existing Laravel 10 and 11 jobs.
- `orchestra/testbench` allows `^11.0` and `phpunit/phpunit` allows `^12.0`, both required to test against Laravel 13.
- Fixed the author email typo (`@email.com` -> `@gmail.com`) displayed on Packagist.

## [3.6.0] - 2026-07-16

### Added
- **Model PHPDoc** -- Every generated model now carries a full `@property` / `@property-read` docblock (fields with real PHP types, nullability, FK columns, relations as `Category` or `Collection<int, Comment>`, timestamps, soft-delete column). IDE autocompletion works out of the box in VS Code (Intelephense) and PhpStorm -- no `barryvdh/laravel-ide-helper` needed.
- **Native enum fields** -- `status:enum(draft,published)` (CLI) or `status: enum(draft,published)` (schema file) generates a backed `App\Enums\Status` enum, casts it on the model, validates with `Rule::enum()` in the Request, fakes with `fake()->randomElement(Status::cases())` in the factory and emits a real `$table->enum()` column in the migration.
- **`--pest`** -- Generates Pest tests (`it(...)`, `expect(...)`, `beforeEach`) instead of PHPUnit classes. Also available as a `pest: true` option in schema files. New publishable stubs: `test.feature.pest.stub`, `test.unit.pest.stub`.
- **Automatic inverse relations on schema/Mermaid sources** -- Declaring one side is now enough: `hasMany` synthesizes the `belongsTo` (and its FK column in the migration), `belongsTo` synthesizes the `hasMany`, `belongsToMany` synthesizes the other side. Same behaviour as `--from-database`, now shared via `RelationshipSynthesizer`.
- **Polymorphic relations** -- `morphTo`, `morphOne` and `morphMany` in schema files (`commentable: morphTo`, `comments: morphMany Comment`): `$table->morphs()` in the migration, correct Eloquent methods and PHPDoc on both models. `--from-database` detects `*_type`/`*_id` column pairs as `morphTo`.
- **Entity evolution (`--add-fields`)** -- `make:fullapi Post --add-fields="excerpt:text,status:enum(draft,published)"` adds fields to an already generated entity without touching manual changes: incremental `Schema::table` migration, in-place patches of `$fillable`/`$casts`/PHPDoc, validation rules, factory definition and resource fields. Fields that already exist are skipped; DTO and tests are reported as manual follow-ups. New publishable stub: `migration.add-fields.stub`.
- **Custom primary keys (`primary`)** -- `code:string:primary` (CLI) or `code: string primary` (schema file) replaces the default `id`: the migration emits `->primary()` and drops `$table->id()`, the model declares `$primaryKey`/`$incrementing`/`$keyType`, and every relation targeting the entity follows automatically (FK column named `country_code`, typed after the key, `->references('code')`, `exists:countries,code` validation, unique factory fakes). Generated tests use `getKey()` so they pass with either key style.
- **`api-generator:clean-routes`** -- removes route lines (and imports) referencing controllers whose file no longer exists, the leftovers that crash `route:list` and IDE tooling with a ReflectionException. Supports `--dry-run`.

### Changed
- `delete:fullapi` route cleanup is now reference-based: any `Route::` line or import mentioning the entity's controller is removed, in `routes/api.php` and `routes/web.php` both.

### Fixed
- Generated models no longer import their own class on self-referential relations.
- Models with `hasMany`/`belongsToMany`/`morphMany` relations now import `Illuminate\Database\Eloquent\Collection` for the PHPDoc types.

## [3.5.1] - 2026-07-16

### Fixed
- **Unique columns generated a broken validation rule** -- fields marked unique (typically discovered by `--from-database` or declared `unique` in a schema/Mermaid file) emitted a bare `unique` rule, making every generated store/update endpoint fail with a 500 ("Validation rule unique requires at least 1 parameters"). The generated Request now uses `Rule::unique('<table>')->ignore($this->route('<param>'))`, which also lets updates keep the current value.
- **Factories for unique columns collided** -- generated factories now use `fake()->unique()` for unique fields (and `fake()->slug()` for `slug` fields), so seeding or tests creating several rows no longer hit unique-constraint violations.
- **`--only` was ignored on multi-entity sources** -- `--from-database`, `--schema` and `--mermaid` regenerated every file type regardless of the filter. The filter now applies, and pivot migrations are only (re)created when `Migration` is included.
- **Duplicate model import in generated tests** -- self-referential relations (e.g. a `parent_id` on the same table) produced a fatal duplicate `use App\Models\X;` in the generated Feature/Unit tests.

## [3.5.0] - 2026-07-15

### Added
- **Generate from an existing database (`--from-database`)** -- Introspects the project database and generates a complete API for every table: columns become typed fields, foreign keys become `belongsTo` relations (with the inverse `hasMany` on generated parents), pure pivot tables become `belongsToMany` on both sides, and `deleted_at` columns enable soft deletes. Real FK constraints are read on Laravel 11+, with a `<singular>_id` naming-convention fallback for databases without declared constraints. Migrations are skipped by default (`--with-migrations` opts in) and the `users` table is protected unless explicitly requested via `--tables=`.
- **Declarative schema file (`--schema=api-schema.yaml`)** -- Describe every entity (fields with shorthand `string nullable unique default=x` or mapping form, Eloquent-style relations, global/per-entity options) in a single versionable YAML or JSON file. `make:fullapi` with no arguments now auto-detects `api-schema.yaml` / `.yml` / `.json` at the project root before falling back to `class_data.json`. Example in `examples/api-schema.yaml`.
- **Mermaid diagram import (`--mermaid=diagram.mmd`)** -- Generates entities from Mermaid `erDiagram` (cardinality symbols `||--o{`, `}o--o{`, attribute blocks with PK/FK/UK markers) and `classDiagram` (typed members, cardinalities, composition/aggregation, inheritance) definitions. Markdown fences and `%%` comments are stripped so diagrams can be pasted as-is; dropped entities/relations produce explicit warnings. Example in `examples/blog.mmd`.
- **Spatie QueryBuilder integration (`--query-builder`)** -- Generates the service and controller on top of `spatie/laravel-query-builder` (`allowedFilters` / `allowedSorts` for every fillable field, `?filter[field]=value&sort=-created_at`). Available as a CLI flag on every generation mode and as a `query_builder` option in the schema file; warns when the spatie package is not installed. New publishable stubs: `service.query-builder.stub`, `controller.query-builder.stub`.
- **Pivot table migrations for `belongsToMany`** -- Every generation mode now creates the pivot migration (composite primary key, cascading FKs, deduplicated across both sides) after the entity migrations. New publishable stub: `migration.pivot.stub`.
- **Foreign-key-safe migration ordering** -- Entities are sorted parents-first (topological sort on `belongsTo`/`hasOne` dependencies) and migration timestamps are sequenced, so `php artisan migrate` runs cleanly on schemas with relations.
- **`DatabaseIntrospector` support class** -- The schema-reading logic behind `api-generator:introspect` and `--from-database`, reusable by tooling.

### Changed
- `symfony/yaml` added as a runtime dependency (YAML schema file support).
- `api-generator:introspect` now delegates to `DatabaseIntrospector` (output unchanged).
- `ApiGenerationService` constructor now takes a `StubLoader`; `ApiGenerationServiceInterface` gains `generatePivotMigrations()`.
- JSON bulk generation (`class_data.json`) also benefits from dependency-sorted generation and pivot migrations.

## [3.3.1] - 2026-04-05

### Fixed
- **Strict typing compatibility** -- Changed `int $id` to `int|string $id` in service stubs, controller generators, and service generators. This fixes `TypeError` when using `declare(strict_types=1)` since Laravel route parameters are always strings.
- **JSON test value formatting** -- Fixed feature test generator to use compact JSON (`{"key":"value"}`) instead of pretty-printed JSON (`{"key": "value"}`), matching how databases store JSON columns.

### Added
- **`.gitignore`** -- Added proper `.gitignore` to exclude `vendor/`, `composer.lock`, `.phpunit.result.cache`, `.claude/`, and IDE files.

### Changed
- Updated README with VS Code extension reference and `int|string` service method signatures.

## [3.3.0] - 2026-03-20

### Added
- **Auto-register API routes in `bootstrap/app.php`** -- On Laravel 11+/12, the generator automatically adds `api: __DIR__.'/../routes/api.php'` to `withRouting()`. No more manual `php artisan install:api` needed.
- **Auto-register seeders in `DatabaseSeeder.php`** -- Generated seeders are now automatically registered with `$this->call()`, so `php artisan db:seed` works out of the box.
- **Migration duplicate detection** -- If a migration for the same table already exists, it is overwritten instead of creating a duplicate that crashes on migrate.
- **Route cleanup on delete** -- `delete:fullapi` now removes the `Route::apiResource()` line (and soft-delete routes) from `routes/api.php`.
- **Seeder cleanup on delete** -- `delete:fullapi` also removes the seeder registration from `DatabaseSeeder.php`.
- **Scramble integration docs** -- README now includes full setup guide and feature overview for automatic API documentation with Scramble.
- **Database seeding section** in README.

### Fixed
- **Validation rules default to `required`** -- Fields now default to `nullable: false`, generating `required|string|max:255` instead of `sometimes|string|max:255`. This fixes the bug where POSTing an empty body returned 201 instead of 422.
- **`delete:fullapi` did not clean `routes/api.php`** -- Routes were left behind after deleting an entity, causing "undefined controller" errors.

### Changed
- `FieldDefinition` constructor: `nullable` parameter default changed from `true` to `false`.
- Laravel 12 added to supported versions (`^12.0` in composer.json).
- Updated requirements in README: PHP >= 8.1, Laravel 10.x / 11.x / 12.x.

## [3.2.0] - 2026-03-15

### Added
- **Interactive wizard** -- `--interactive` flag launches a step-by-step guided setup: entity name, fields (with type, nullable, unique, default), relationships, options, preview, and confirmation
- **Sanctum authentication** -- `--auth` flag scaffolds a complete auth system: AuthController (register/login/logout/user), LoginRequest, RegisterRequest, auth routes, and wraps API resources in `auth:sanctum` middleware
- **Auto-generated tests** -- Feature tests (CRUD endpoints) and Unit tests (Service layer) are now generated for every entity
- **Postman collection export** -- `--postman` flag generates a ready-to-import Postman v2.1 JSON collection with all endpoints and sample data
- **Soft Deletes support** -- `--soft-deletes` flag adds the SoftDeletes trait, migration column, restore/forceDelete controller and service methods, and dedicated routes
- **Query parameter filtering** -- All generated `index` endpoints now accept query parameters to filter on any fillable field
- **Complete generator implementations** -- All 9 generator classes (Controller, DTO, Factory, Migration, Policy, Request, Resource, Seeder, Service) are now fully implemented following the AbstractGenerator pattern
- **FeatureTestGenerator** and **UnitTestGenerator** for automatic test scaffolding
- **PostmanExporter** service for collection generation
- **AuthGenerator** service for Sanctum auth scaffolding
- `unique` and `default` field constraints support in FieldDefinition, MigrationGenerator, and RequestGenerator
- New stubs: `test.feature.stub`, `test.unit.stub`, `auth.controller.stub`, `auth.login-request.stub`, `auth.register-request.stub`

### Fixed
- **StubLoader placeholder matching** -- Fixed a bug where `{{placeholder}}` syntax in stubs was not matched correctly (single vs double braces)
- **DTO stub** -- Replaced legacy `{$variable}` syntax with standard `{{placeholder}}` format
- **Policy stub** -- Removed heredoc wrapper and fixed placeholder syntax
- Empty stubs (service, request, factory, migrations, seed) now contain proper templates

### Changed
- **MakeApiCommand** is now the active command (replaces the legacy MakeApi command)
- Command signature updated: `make:fullapi {name?} {--fields=} {--soft-deletes} {--postman} {--auth} {--interactive}`
- ServiceProvider now registers all 12 generators (including test generators) plus AuthGenerator
- Generated controllers now accept `Request $request` in `index()` for filtering
- Generated services now accept `array $filters` in `getAll()` method
- Generated migrations now support `->unique()` and `->default()` modifiers
- Generated validation rules now include `unique` constraint when applicable
- `deleteCompleteApi()` now also cleans up generated test files
- PHPStan configuration cleaned up (removed deprecated options)

## [3.0.1] - 2025-06-28

### 📚 Documentation & Polish

### Updated
- **Documentation Improvements**
  - Completely updated README.md with modern features and architecture examples
  - Added comprehensive usage examples with new syntax
  - Improved installation and quick start guides
  - Added architecture overview with Service Layer and DTO examples
  - Enhanced field types documentation
  - Added configuration and testing sections

### Fixed
- Minor documentation formatting and consistency issues
- Updated examples to reflect current v3.0+ architecture

### Added
- Better code examples showing modern PHP 8.1+ features
- Enhanced Quick Start section with single entity and bulk generation examples
- Comprehensive field types and relationship documentation

## [3.0.0] - 2025-06-28

### 🚀 Major Refactoring - Clean Architecture Implementation

This is a major release that completely refactors the package architecture for better maintainability, extensibility, and professionalism.

### Added
- **Clean Architecture Implementation**
  - Value Objects for domain modeling (EntityDefinition, FieldDefinition, RelationshipDefinition)
  - Service Layer pattern with proper dependency injection
  - Contracts/Interfaces for better testability
  - Professional error handling with custom exceptions

- **Enhanced Generator System**
  - AbstractGenerator base class for extensibility
  - Improved stub system with better placeholder handling
  - Support for complex relationships and inheritance
  - Type-safe field definitions and validation

- **Developer Experience**
  - Comprehensive PHPDoc comments
  - PHPStan level 8 static analysis
  - GitHub Actions CI/CD pipeline
  - Professional contributing guidelines
  - Comprehensive test structure

- **New Features**
  - JSON parser with robust error handling
  - Field parser with validation
  - Stub loader system
  - Professional configuration system

### Changed
- **Architecture**: Complete rewrite using clean architecture principles
- **Type Safety**: Full PHP 8.1+ type declarations with readonly properties
- **Error Handling**: Professional exception handling throughout
- **Code Quality**: SOLID principles compliance
- **Documentation**: Complete rewrite with professional formatting

### Fixed
- **Model Generation**: Fixed issues with relationships and inheritance
- **JSON Parsing**: Better handling of different JSON formats
- **Stub Processing**: Resolved placeholder replacement issues
- **Field Types**: Improved type mapping and validation

### Technical Improvements
- **PHP 8.1+ Features**: Constructor property promotion, readonly classes, match expressions
- **Dependency Injection**: Proper DI container usage throughout
- **Static Analysis**: PHPStan level 8 compliance
- **Code Style**: Laravel Pint formatting
- **Testing**: Comprehensive test structure

### Breaking Changes
⚠️ **This is a major version with breaking changes**
- Namespace changes for better organization
- Service provider restructuring
- Command signature improvements
- Configuration format updates
- 🏗️ **Complete Architecture Refactoring**
  - Value Objects for type-safe domain modeling
  - Service Layer pattern implementation
  - Dependency Injection container integration
  - Clean Architecture principles
  - SOLID principles compliance

- 🔧 **New Features**
  - Professional DTO generation with readonly classes
  - Enhanced JSON parsing with relationship support
  - Configurable field types and validation rules
  - Extensible generator system
  - Custom exceptions and error handling

- 📁 **Improved Project Structure**
  - Contracts/Interfaces for better testability
  - Support classes for utilities
  - Organized generators by responsibility
  - Professional documentation

- 🚀 **Enhanced Code Generation**
  - Type-safe PHP 8.1+ code generation
  - Improved stub system with better templating
  - Relationship handling (One-to-One, One-to-Many, Many-to-Many)
  - Foreign key management
  - Fillable properties automation

- 🧪 **Quality Improvements**
  - PHPStan level 8 compliance
  - Comprehensive test structure
  - GitHub Actions CI/CD pipeline
  - Code style with Laravel Pint
  - Professional documentation

### Changed
- **Breaking**: Refactored entire codebase for clean architecture
- **Breaking**: Updated minimum PHP version to 8.1
- **Breaking**: New namespace structure
- Improved error messages and validation
- Enhanced JSON data parsing logic
- Better relationship detection and handling

### Fixed
- Model generation with proper inheritance handling
- Duplicate import statements
- Foreign key generation issues
- Stub placeholder processing
- Route generation and management

### Removed
- Legacy code patterns
- Outdated stub formats
- Unnecessary dependencies

## [2.0.6] - Previous Version
- Legacy implementation with basic functionality

---

## Migration Guide from 2.x to 3.0

### What's Changed
1. **PHP Version**: Minimum PHP 8.1 required
2. **Architecture**: Complete refactoring to clean architecture
3. **Type Safety**: Full type declarations throughout
4. **Better Error Handling**: Custom exceptions with clear messages

### How to Upgrade
1. Update your PHP version to 8.1+
2. Update the package: `composer update nameless/laravel-api-generator`
3. Clear your cache: `php artisan cache:clear`
4. Re-generate your APIs to benefit from new features

The package maintains backward compatibility for the main commands:
- `php artisan make:fullapi EntityName --fields="field:type"`
- `php artisan make:fullapi` (JSON mode)
