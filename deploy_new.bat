@echo off
echo ========================================
echo Laravel API Generator - Deployment Script
echo ========================================
echo.

REM Check if version is provided as argument
if "%1"=="" (
    echo Usage: deploy_new.bat [version]
    echo Example: deploy_new.bat 3.0.2
    exit /b 1
)

set VERSION=%1

echo Deploying version %VERSION%...
echo.

REM Update version in composer.json
echo 1. Updating composer.json version...
powershell -Command "(Get-Content composer.json) -replace '\"version\": \".*\"', '\"version\": \"%VERSION%\"' | Set-Content composer.json"

REM Add changelog entry
echo 2. Remember to update CHANGELOG.md manually
echo.

REM Run tests
echo 3. Running tests...
call composer test
if errorlevel 1 (
    echo Tests failed! Aborting deployment.
    exit /b 1
)

REM Run static analysis
echo 4. Running static analysis...
call composer analyse
if errorlevel 1 (
    echo Static analysis failed! Aborting deployment.
    exit /b 1
)

REM Format code
echo 5. Formatting code...
call composer format

REM Git operations
echo 6. Committing changes...
git add .
git commit -m "Release v%VERSION% - Documentation and feature updates"

echo 7. Creating tag...
git tag v%VERSION%

echo 8. Pushing to GitHub...
git push origin main
git push origin v%VERSION%

echo.
echo ========================================
echo Deployment completed successfully!
echo ========================================
echo.
echo Version %VERSION% has been:
echo - Updated in composer.json
echo - Committed to Git
echo - Tagged as v%VERSION%
echo - Pushed to GitHub
echo.
echo Packagist will automatically detect the new version.
echo Check: https://packagist.org/packages/nameless/laravel-api-generator
echo.
echo Next steps:
echo 1. Verify the package appears on Packagist
echo 2. Test installation: composer require nameless/laravel-api-generator:^%VERSION%
echo 3. Update project documentation if needed
echo.
pause
