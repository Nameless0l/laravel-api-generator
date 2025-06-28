@echo off
echo === Laravel API Generator - Deployment Script ===
echo.

REM Check if we're in the right directory
if not exist "composer.json" (
    echo Error: composer.json not found. Make sure you're in the package root directory.
    pause
    exit /b 1
)

echo 1. Checking Git status...
git status

echo.
echo 2. Adding all files...
git add .

echo.
echo 3. Creating commit...
git commit -m "feat: Major refactoring v3.0.0 - Clean architecture with Value Objects, Services, and improved generators

- Implemented clean architecture with Value Objects (EntityDefinition, FieldDefinition, RelationshipDefinition)
- Added professional Service Layer pattern with dependency injection  
- Created extensible generator system with AbstractGenerator
- Improved JSON parsing with better error handling
- Added comprehensive type safety with PHP 8.1+ features
- Fixed model generation with proper relationships and inheritance
- Enhanced stub system with better placeholder handling
- Added professional error handling with custom exceptions
- Improved documentation and contributing guidelines
- Added GitHub Actions CI/CD pipeline
- Updated PHPStan configuration to level 8
- Added comprehensive testing structure"

echo.
echo 4. Creating version tag...
git tag -a v3.0.0 -m "Release v3.0.0: Major refactoring with clean architecture"

echo.
echo 5. Pushing to repository...
git push origin main
git push origin --tags

echo.
echo 6. Deployment completed!
echo.
echo Next steps:
echo - Check GitHub for the new tag: https://github.com/your-username/laravel-api-generator/tags
echo - Check Packagist for the new version: https://packagist.org/packages/nameless/laravel-api-generator
echo - The package should be available for installation with: composer require nameless/laravel-api-generator:^3.0
echo.
pause
