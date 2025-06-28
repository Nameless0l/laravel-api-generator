# Contributing to Laravel API Generator

Thank you for considering contributing to Laravel API Generator! This document provides guidelines for contributing to this project.

## Code of Conduct

This project adheres to a code of conduct. By participating, you are expected to uphold this code.

## Getting Started

### Prerequisites

- PHP 8.1 or higher
- Composer
- Laravel 10.x or 11.x

### Development Setup

1. Fork the repository
2. Clone your fork:
   ```bash
   git clone https://github.com/your-username/laravel-api-generator.git
   ```
3. Install dependencies:
   ```bash
   composer install
   ```
4. Run tests to ensure everything is working:
   ```bash
   composer test
   ```

## Development Guidelines

### Architecture Principles

This project follows clean architecture principles:

- **Value Objects**: Immutable objects that represent domain concepts
- **Service Layer**: Business logic is encapsulated in services
- **Dependency Injection**: All dependencies are injected through constructors
- **SOLID Principles**: Code follows SOLID design principles
- **Type Safety**: Full PHP 8.1+ type declarations

### Code Style

- Use PHP 8.1+ features and syntax
- Follow PSR-12 coding standard
- Use strict types (`declare(strict_types=1)`)
- Use readonly properties where appropriate
- Use constructor property promotion
- Write comprehensive PHPDoc comments

### Testing

- Write unit tests for all new functionality
- Maintain 100% code coverage for critical paths
- Use descriptive test method names
- Follow the Arrange-Act-Assert pattern

### Static Analysis

- Code must pass PHPStan level 8
- Use proper type hints and return types
- Avoid `@phpstan-ignore` comments unless absolutely necessary

## Making Changes

### Branch Naming

- Feature branches: `feature/your-feature-name`
- Bug fixes: `fix/bug-description`
- Documentation: `docs/what-you-changed`

### Commit Messages

Follow conventional commits format:

```
type(scope): description

- feat: new feature
- fix: bug fix
- docs: documentation changes
- style: code style changes
- refactor: code refactoring
- test: adding or modifying tests
- chore: maintenance tasks
```

### Pull Request Process

1. Create a feature branch from `develop`
2. Make your changes
3. Add/update tests
4. Ensure all tests pass
5. Update documentation if necessary
6. Submit a pull request to `develop`

### PR Requirements

- [ ] Tests pass
- [ ] Code follows style guidelines
- [ ] PHPStan analysis passes
- [ ] Documentation is updated
- [ ] Changes are described in PR description

## Project Structure

```
src/
├── Console/Commands/     # Artisan commands
├── Contracts/           # Interfaces
├── EntitiesGenerator/   # Code generators
├── Exceptions/          # Custom exceptions
├── Providers/           # Service providers
├── Services/            # Business logic services
├── Support/             # Helper classes
└── ValueObjects/        # Domain value objects

tests/
├── Unit/               # Unit tests
├── Integration/        # Integration tests
└── Feature/            # Feature tests

stubs/                  # Code generation templates
config/                 # Configuration files
```

## Adding New Generators

To add a new generator:

1. Create a class extending `AbstractGenerator`
2. Implement required methods:
   - `getType()`
   - `getOutputPath()`
   - `generateContent()`
   - `getStubName()`
   - `getReplacements()`
3. Create corresponding stub file
4. Register generator in `CodeGeneratorServiceProvider`
5. Add tests

Example:

```php
class CustomGenerator extends AbstractGenerator
{
    public function getType(): string
    {
        return 'Custom';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return app_path("Custom/{$definition->name}.php");
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->processStub($definition);
    }

    protected function getStubName(): string
    {
        return 'custom';
    }

    protected function getReplacements(EntityDefinition $definition): array
    {
        return [
            'className' => $definition->name,
            // ... other replacements
        ];
    }
}
```

## Documentation

- Update README.md for new features
- Add PHPDoc comments to all public methods
- Include code examples in documentation
- Update CHANGELOG.md

## Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run static analysis
composer analyse

# Format code
composer format
```

## Questions?

If you have questions about contributing, please:

1. Check existing issues and documentation
2. Create a new issue with the "question" label
3. Join our discussions

Thank you for contributing!
