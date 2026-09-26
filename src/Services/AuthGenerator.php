<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Services;

use nameless\CodeGenerator\Support\StubLoader;
use nameless\CodeGenerator\Support\Workspace;
use nameless\CodeGenerator\Support\WorkspaceFactory;

class AuthGenerator
{
    public function __construct(
        private readonly StubLoader $stubLoader,
        private readonly WorkspaceFactory $workspaces
    ) {}

    /**
     * Writes immediately unless a workspace is given.
     *
     * @return array<int, string>
     */
    public function generate(?Workspace $workspace = null): array
    {
        $target = $workspace ?? $this->workspaces->make();

        $files = [
            app_path('Http/Controllers/AuthController.php') => 'auth.controller',
            app_path('Http/Requests/LoginRequest.php') => 'auth.login-request',
            app_path('Http/Requests/RegisterRequest.php') => 'auth.register-request',
        ];

        foreach ($files as $path => $stub) {
            $target->put($path, $this->stubLoader->load($stub), 'Auth');
        }

        $this->generateAuthRoutes($target);

        if ($workspace === null) {
            $target->commit();
        }

        return array_keys($files);
    }

    private function generateAuthRoutes(Workspace $workspace): void
    {
        $apiFilePath = base_path('routes/api.php');
        $phpHeader = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\nuse App\\Http\\Controllers\\AuthController;\n\n";

        if (! $workspace->exists($apiFilePath)) {
            $workspace->put($apiFilePath, $phpHeader, 'Routes');
        }

        $content = $workspace->get($apiFilePath);

        if (! str_contains($content, 'use App\\Http\\Controllers\\AuthController')) {
            $workspace->put($apiFilePath, str_replace(
                'use Illuminate\\Support\\Facades\\Route;',
                "use Illuminate\\Support\\Facades\\Route;\nuse App\\Http\\Controllers\\AuthController;",
                $content
            ), 'Routes');
        }

        $authRoutes = <<<'ROUTES'

// Authentication routes
Route::post('register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);
});
ROUTES;

        if (! str_contains($workspace->get($apiFilePath), "AuthController::class, 'register'")) {
            $workspace->append($apiFilePath, PHP_EOL.$authRoutes, 'Routes');
        }
    }

    /**
     * Writes immediately unless a workspace is given.
     */
    public function wrapRoutesInAuthMiddleware(?Workspace $workspace = null): void
    {
        $target = $workspace ?? $this->workspaces->make();
        $apiFilePath = base_path('routes/api.php');

        if (! $target->exists($apiFilePath)) {
            return;
        }

        $content = $target->get($apiFilePath);

        // Move apiResource lines that are not inside a middleware group into the auth:sanctum group
        if (str_contains($content, "Route::middleware('auth:sanctum')->group(function ()")) {
            $lines = explode("\n", $content);
            $apiResourceLines = [];
            $otherLines = [];

            foreach ($lines as $line) {
                if (str_contains($line, 'Route::apiResource(') && ! str_contains($line, '//')) {
                    $apiResourceLines[] = '    '.trim($line);
                } elseif (str_contains($line, 'Route::post(') && str_contains($line, 'restore')) {
                    $apiResourceLines[] = '    '.trim($line);
                } elseif (str_contains($line, 'Route::delete(') && str_contains($line, 'force-delete')) {
                    $apiResourceLines[] = '    '.trim($line);
                } else {
                    $otherLines[] = $line;
                }
            }

            if (! empty($apiResourceLines)) {
                $content = implode("\n", $otherLines);
                $apiResourceBlock = implode("\n", $apiResourceLines);
                $content = str_replace(
                    "Route::middleware('auth:sanctum')->group(function () {\n    Route::post('logout', [AuthController::class, 'logout']);\n    Route::get('user', [AuthController::class, 'user']);\n});",
                    "Route::middleware('auth:sanctum')->group(function () {\n    Route::post('logout', [AuthController::class, 'logout']);\n    Route::get('user', [AuthController::class, 'user']);\n\n{$apiResourceBlock}\n});",
                    $content
                );
                $target->put($apiFilePath, $content, 'Routes');
            }
        }

        if ($workspace === null) {
            $target->commit();
        }
    }
}
