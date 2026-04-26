<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Validate user-published stubs against the placeholders the generators
 * actually inject. Prevents silent breakages after a user customizes a stub.
 *
 *   php artisan api-generator:validate-stubs
 *   php artisan api-generator:validate-stubs --json
 *
 * Exit code 0 when every stub is valid, 1 when at least one stub is missing
 * a required placeholder. Designed to be called from CI as well as from the
 * VS Code extension.
 */
class ValidateStubsCommand extends Command
{
    protected $signature = 'api-generator:validate-stubs {--json}';

    protected $description = 'Verify that user-published stubs still contain the required placeholders';

    /**
     * Required placeholders per stub. Keys match the basename of each stub
     * file. Optional placeholders (the ones a generator only emits in some
     * cases) are intentionally not listed here so we only fail on truly
     * missing ones.
     *
     * @var array<string, array<int, string>>
     */
    private const REQUIRED = [
        'model' => ['modelName', 'fillable'],
        'controller' => ['modelName', 'modelNameLower', 'pluralName'],
        'service' => ['modelName', 'modelNameLower'],
        'dto' => ['modelName', 'attributes', 'attributesFromRequest'],
        'request' => ['modelName', 'rules'],
        'resource' => ['modelName', 'fields'],
        'migrations' => ['tableName', 'fields'],
        'factory' => ['modelName', 'factoryFields'],
        'seed' => ['modelName'],
        'policy' => ['modelName', 'modelNameLower'],
        'test.feature' => ['modelName', 'modelNameLower', 'pluralName'],
        'test.unit' => ['modelName', 'modelNameLower'],
    ];

    public function handle(): int
    {
        $userStubsDir = base_path('stubs/vendor/laravel-api-generator');

        if (! File::isDirectory($userStubsDir)) {
            $payload = [
                'status' => 'no-customization',
                'message' => 'No published stubs found. Run: php artisan vendor:publish --tag=api-generator-stubs',
                'results' => [],
            ];
            $this->emit($payload);

            return self::SUCCESS;
        }

        $results = [];
        $hasError = false;

        foreach (self::REQUIRED as $stubName => $requiredPlaceholders) {
            $stubPath = $userStubsDir.DIRECTORY_SEPARATOR.$stubName.'.stub';

            if (! File::exists($stubPath)) {
                $results[] = [
                    'stub' => $stubName,
                    'status' => 'not-customized',
                    'missing' => [],
                ];

                continue;
            }

            $content = File::get($stubPath);
            $missing = [];

            foreach ($requiredPlaceholders as $placeholder) {
                if (! str_contains($content, '{{'.$placeholder.'}}')) {
                    $missing[] = $placeholder;
                }
            }

            $results[] = [
                'stub' => $stubName,
                'status' => empty($missing) ? 'ok' : 'invalid',
                'missing' => $missing,
            ];

            if (! empty($missing)) {
                $hasError = true;
            }
        }

        $payload = [
            'status' => $hasError ? 'invalid' : 'ok',
            'message' => $hasError
                ? 'One or more stubs are missing required placeholders.'
                : 'All customized stubs are valid.',
            'results' => $results,
        ];

        $this->emit($payload);

        return $hasError ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emit(array $payload): void
    {
        if ($this->option('json')) {
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            if ($json === false) {
                $this->error('Failed to encode JSON: '.json_last_error_msg());

                return;
            }
            $this->line($json);

            return;
        }

        $this->info($payload['message']);
        foreach (($payload['results'] ?? []) as $row) {
            $stub = $row['stub'];
            $status = $row['status'];
            $missing = $row['missing'] ?? [];

            if ($status === 'ok') {
                $this->line("  ✓ {$stub}");
            } elseif ($status === 'not-customized') {
                $this->line("  · {$stub} (using package default)");
            } else {
                $this->line("  ✗ {$stub} - missing: ".implode(', ', $missing));
            }
        }
    }
}
