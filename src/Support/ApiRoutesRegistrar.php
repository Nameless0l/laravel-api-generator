<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

/**
 * Laravel 11+ apps load routes/api.php only once bootstrap/app.php
 * registers it, which a fresh app does not.
 */
final class ApiRoutesRegistrar
{
    private const WEB_ROUTES = '/(->withRouting\([^)]*)(web:\s*__DIR__\s*\.\s*\'[^\']*\/routes\/web\.php\',?)/s';

    /**
     * @return array<int, array{code: string, message: string}>
     */
    public function register(Workspace $workspace): array
    {
        $bootstrapApp = base_path('bootstrap/app.php');

        if (! $workspace->exists($bootstrapApp)) {
            return [];
        }

        $content = $workspace->get($bootstrapApp);

        if (str_contains($content, 'api:') || str_contains($content, "'api.php'") || str_contains($content, '"api.php"')) {
            return [];
        }

        if (! $workspace->exists(base_path('routes/api.php'))) {
            return [];
        }

        if (preg_match(self::WEB_ROUTES, $content, $matches)) {
            $webLine = $matches[2];
            $patched = str_replace($webLine, rtrim($webLine, ', ').",\n        api: __DIR__.'/../routes/api.php',", $content);

            if ($patched !== $content) {
                $workspace->put($bootstrapApp, $patched, 'Bootstrap');

                return [];
            }
        }

        return [[
            'code' => 'api_routes_not_loaded',
            'message' => 'API routes file exists but may not be loaded by your application. Run: php artisan install:api',
        ]];
    }
}
