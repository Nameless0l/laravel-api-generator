<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Services;

use nameless\CodeGenerator\Support\PhpImports;
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

        if (! $workspace->exists($apiFilePath)) {
            $workspace->put($apiFilePath, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n", 'Routes');
        }

        $content = PhpImports::add($workspace->get($apiFilePath), ['App\\Http\\Controllers\\AuthController']);

        $authRoutes = <<<'ROUTES'

// Authentication routes
Route::post('register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);
});
ROUTES;

        if (! str_contains($content, "AuthController::class, 'register'")) {
            $content = ApiGenerationService::appendLine($content, $authRoutes);
        }

        $workspace->put($apiFilePath, $content, 'Routes');
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
        $lines = preg_split('/\R/', $content) ?: [];
        $open = null;
        $close = null;

        foreach ($lines as $index => $line) {
            if ($open === null && str_contains($line, "Route::middleware('auth:sanctum')->group(function ()")) {
                $open = $index;
            } elseif ($open !== null && trim($line) === '});') {
                $close = $index;
                break;
            }
        }

        if ($open === null || $close === null) {
            return;
        }

        // Routes already inside the group stay there, so running --auth again never drops them
        $moved = [];
        $groupHasRoutes = false;
        foreach ($lines as $index => $line) {
            if (! $this->isResourceRoute($line)) {
                continue;
            }
            if ($index > $open && $index < $close) {
                $groupHasRoutes = true;
            } else {
                $moved[$index] = '    '.trim($line);
            }
        }

        if ($moved === []) {
            return;
        }

        $output = [];
        foreach ($lines as $index => $line) {
            if (isset($moved[$index])) {
                continue;
            }
            if ($output !== [] && trim($line) === '' && trim((string) end($output)) === '') {
                continue;
            }
            if ($index === $close) {
                if (! $groupHasRoutes) {
                    $output[] = '';
                }
                array_push($output, ...array_values($moved));
            }
            $output[] = $line;
        }

        $target->put($apiFilePath, implode(str_contains($content, "\r\n") ? "\r\n" : "\n", $output), 'Routes');

        if ($workspace === null) {
            $target->commit();
        }
    }

    private function isResourceRoute(string $line): bool
    {
        return (str_contains($line, 'Route::apiResource(') && ! str_contains($line, '//'))
            || (str_contains($line, 'Route::post(') && str_contains($line, 'restore'))
            || (str_contains($line, 'Route::delete(') && str_contains($line, 'force-delete'));
    }
}
