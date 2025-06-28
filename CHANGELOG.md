# Changelog

All notable changes to `laravel-api-generator` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2025-06-28

### Added
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
