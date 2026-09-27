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
        'controller' => ['modelName', 'pluralName', 'routeParameter'],
        'controller.query-builder' => ['modelName', 'pluralName', 'routeParameter'],
        'service' => ['modelName', 'modelNameLower', 'allowedFilters', 'allowedSorts', 'perPage', 'maxPerPage'],
        'service.query-builder' => ['modelName', 'modelNameLower', 'allowedFilters', 'allowedSorts', 'perPage', 'maxPerPage'],
        'dto' => ['modelName', 'attributes', 'attributesFromValidated'],
        'request.store' => ['modelName', 'rules'],
        'request.update' => ['modelName', 'rules'],
        'resource' => ['modelName', 'fields'],
        'migrations' => ['tableName', 'fields'],
        'factory' => ['modelName', 'factoryFields'],
        'seed' => ['modelName'],
        'policy' => ['modelName', 'modelVariable'],
        'test.feature' => ['modelName', 'modelNameLower', 'pluralName'],
        'test.unit' => ['modelName', 'modelNameLower'],
        'test.feature.pest' => ['modelName', 'modelNameLower', 'pluralName'],
        'test.unit.pest' => ['modelName', 'modelNameLower'],
        'migration.add-fields' => ['tableName', 'columns', 'dropColumns'],
    ];

    /**
     * Published stubs the generator no longer reads, so their changes are lost.
     *
     * @var array<string, string>
     */
    private const OBSOLETE = [
        'request' => 'not used since 4.0: move your changes to request.store.stub and request.update.stub, then delete it',
    ];

    private const CALLS_GET_ALL = 'calls getAll(), which paginate() replaces since 4.0';

    private const SAVES_EVERY_PROPERTY = 'saves every DTO property, so a PATCH would reset the fields it leaves out: call $dto->toArray() instead of get_object_vars($dto)';

    /**
     * Code that only 3.x stubs contain and that breaks the generated API since 4.0.
     *
     * @var array<string, array{string, string}> stub => [code, reason]
     */
    private const OUTDATED = [
        'service' => ['get_object_vars($dto)', self::SAVES_EVERY_PROPERTY],
        'service.query-builder' => ['get_object_vars($dto)', self::SAVES_EVERY_PROPERTY],
        'test.unit' => ['->getAll(', self::CALLS_GET_ALL],
        'test.unit.pest' => ['->getAll(', self::CALLS_GET_ALL],
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

            [$code, $reason] = self::OUTDATED[$stubName] ?? [null, null];
            $outdated = $code !== null && str_contains($content, $code);

            $results[] = [
                'stub' => $stubName,
                'status' => empty($missing) && ! $outdated ? 'ok' : 'invalid',
                'missing' => $missing,
                ...($outdated ? ['reason' => $reason] : []),
            ];

            if (! empty($missing) || $outdated) {
                $hasError = true;
            }
        }

        foreach (self::OBSOLETE as $stubName => $reason) {
            if (File::exists($userStubsDir.DIRECTORY_SEPARATOR.$stubName.'.stub')) {
                $results[] = ['stub' => $stubName, 'status' => 'obsolete', 'missing' => [], 'reason' => $reason];
            }
        }

        $payload = [
            'status' => $hasError ? 'invalid' : 'ok',
            'message' => $hasError
                ? 'One or more stubs are missing required placeholders or were written for 3.x.'
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
            } elseif ($status === 'obsolete') {
                $this->line("  ! {$stub} - {$row['reason']}");
            } else {
                $details = array_filter([$missing === [] ? null : 'missing: '.implode(', ', $missing), $row['reason'] ?? null]);
                $this->line("  ✗ {$stub} - ".implode('; ', $details));
            }
        }
    }
}
