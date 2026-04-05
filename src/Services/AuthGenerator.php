<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Services;

use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\Support\StubLoader;

class AuthGenerator
{
    public function __construct(
        private readonly StubLoader $stubLoader
    ) {}

    /**
     * @return array<int, string>
     */
    public function generate(): array
    {
        $generatedFiles = [];

        // Generate AuthController
        $controllerPath = app_path('Http/Controllers/AuthController.php');
        $this->ensureDirectoryExists($controllerPath);
        File::put($controllerPath, $this->stubLoader->load('auth.controller'));
        $generatedFiles[] = $controllerPath;

        // Generate LoginRequest
        $loginRequestPath = app_path('Http/Requests/LoginRequest.php');
        $this->ensureDirectoryExists($loginRequestPath);
        File::put($loginRequestPath, $this->stubLoader->load('auth.login-request'));
        $generatedFiles[] = $loginRequestPath;

        // Generate RegisterRequest
        $registerRequestPath = app_path('Http/Requests/RegisterRequest.php');
        $this->ensureDirectoryExists($registerRequestPath);
        File::put($registerRequestPath, $this->stubLoader->load('auth.register-request'));
        $generatedFiles[] = $registerRequestPath;

        // Add auth routes
        $this->generateAuthRoutes();

        return $generatedFiles;
    }

    private function generateAuthRoutes(): void
    {
        $apiFilePath = base_path('routes/api.php');
        $phpHeader = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\nuse App\\Http\\Controllers\\AuthController;\n\n";

        if (! File::exists($apiFilePath)) {
            File::put($apiFilePath, $phpHeader);
        }

        $content = File::get($apiFilePath);

        // Add AuthController import if missing
        if (! str_contains($content, 'use App\\Http\\Controllers\\AuthController')) {
            $content = str_replace(
                'use Illuminate\\Support\\Facades\\Route;',
                "use Illuminate\\Support\\Facades\\Route;\nuse App\\Http\\Controllers\\AuthController;",
                $content
            );
            File::put($apiFilePath, $content);
        }

        // Add public auth routes
        $authRoutes = <<<'ROUTES'

// Authentication routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);
});
ROUTES;

        $content = File::get($apiFilePath);
        if (! str_contains($content, "AuthController::class, 'register'")) {
            File::append($apiFilePath, PHP_EOL.$authRoutes);
        }
    }

    public function wrapRoutesInAuthMiddleware(): void
    {
        $apiFilePath = base_path('routes/api.php');

        if (! File::exists($apiFilePath)) {
            return;
        }

        $content = File::get($apiFilePath);

        // Find existing apiResource lines that are NOT inside a middleware group
        // and wrap them in auth:sanctum middleware
        if (str_contains($content, "Route::middleware('auth:sanctum')->group(function ()")) {
            // Middleware group already exists - move apiResource lines into it
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
                // Insert apiResource lines before the closing of the middleware group
                $apiResourceBlock = implode("\n", $apiResourceLines);
                $content = str_replace(
                    "Route::middleware('auth:sanctum')->group(function () {\n    Route::post('logout', [AuthController::class, 'logout']);\n    Route::get('user', [AuthController::class, 'user']);\n});",
                    "Route::middleware('auth:sanctum')->group(function () {\n    Route::post('logout', [AuthController::class, 'logout']);\n    Route::get('user', [AuthController::class, 'user']);\n\n{$apiResourceBlock}\n});",
                    $content
                );
                File::put($apiFilePath, $content);
            }
        }
    }

    private function ensureDirectoryExists(string $filePath): void
    {
        $directory = dirname($filePath);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }
}
