# Changelog

All notable changes to `laravel-api-generator` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.2.0] - 2026-03-15

### Added
- **Auto-generated tests** -- Feature tests (CRUD endpoints) and Unit tests (Service layer) are now generated for every entity
- **Postman collection export** -- `--postman` flag generates a ready-to-import Postman v2.1 JSON collection with all endpoints and sample data
- **Soft Deletes support** -- `--soft-deletes` flag adds the SoftDeletes trait, migration column, restore/forceDelete controller and service methods, and dedicated routes
- **Query parameter filtering** -- All generated `index` endpoints now accept query parameters to filter on any fillable field
- **Complete generator implementations** -- All 9 generator classes (Controller, DTO, Factory, Migration, Policy, Request, Resource, Seeder, Service) are now fully implemented following the AbstractGenerator pattern
- **FeatureTestGenerator** and **UnitTestGenerator** for automatic test scaffolding
- **PostmanExporter** service for collection generation
- New stubs: `test.feature.stub`, `test.unit.stub`

### Fixed
- **StubLoader placeholder matching** -- Fixed a bug where `{{placeholder}}` syntax in stubs was not matched correctly (single vs double braces)
- **DTO stub** -- Replaced legacy `{$variable}` syntax with standard `{{placeholder}}` format
- **Policy stub** -- Removed heredoc wrapper and fixed placeholder syntax
- Empty stubs (service, request, factory, migrations, seed) now contain proper templates

### Changed
- **MakeApiCommand** is now the active command (replaces the legacy MakeApi command)
- Command signature updated: `make:fullapi {name?} {--fields=} {--soft-deletes} {--postman}`
- ServiceProvider now registers all 12 generators (including test generators)
- Generated controllers now accept `Request $request` in `index()` for filtering
- Generated services now accept `array $filters` in `getAll()` method
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
