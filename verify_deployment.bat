@echo off
echo ========================================
echo Laravel API Generator - Verification Script
echo ========================================
echo.

echo Checking deployment status...
echo.

echo 1. Current Git status:
git status
echo.

echo 2. Recent commits:
git log --oneline -n 5
echo.

echo 3. Available tags:
git tag | sort
echo.

echo 4. Remote repository info:
git remote -v
echo.

echo 5. Current composer.json version:
findstr "version" composer.json
echo.

echo 6. Checking if tag exists on remote:
git ls-remote --tags origin
echo.

echo ========================================
echo Verification completed!
echo ========================================
echo.
echo To verify on Packagist:
echo 1. Visit: https://packagist.org/packages/nameless/laravel-api-generator
echo 2. Check if the latest version appears
echo 3. If not, click "Update" to force refresh
echo.
echo To test installation in a Laravel project:
echo composer require nameless/laravel-api-generator
echo.
pause
