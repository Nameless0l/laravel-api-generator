<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

use Composer\InstalledVersions;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\FileChange;
use Throwable;

/**
 * Shapes shared by make:fullapi --json and api-generator:serve. Adding a
 * field keeps VERSION; removing one or changing its meaning bumps it.
 */
final class Protocol
{
    public const VERSION = 1;

    private const PACKAGE = 'nameless/laravel-api-generator';

    /**
     * @param  array<int, FileChange>  $changes
     * @param  array<int, array{code: string, message: string}>  $warnings
     * @param  bool|null  $withContent  file contents, included in a dry run by default
     * @return array<string, mixed>
     */
    public static function planDocument(array $changes, array $warnings, bool $dryRun, ?bool $withContent = null): array
    {
        return [
            'protocol' => self::VERSION,
            'dryRun' => $dryRun,
            'files' => self::files($changes, $withContent ?? $dryRun),
            'warnings' => array_values($warnings),
            'errors' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function errorDocument(Throwable $e, bool $dryRun): array
    {
        return [
            'protocol' => self::VERSION,
            'dryRun' => $dryRun,
            'files' => [],
            'warnings' => [],
            'errors' => [self::error($e)],
        ];
    }

    /**
     * @param  array<int, FileChange>  $changes
     * @return array<int, array<string, string|bool>>
     */
    public static function files(array $changes, bool $withContent): array
    {
        return array_values(array_map(fn (FileChange $change) => $change->toArray($withContent), $changes));
    }

    /**
     * @return array{code: string, message: string, hint?: string}
     */
    public static function error(Throwable $e): array
    {
        if (! $e instanceof CodeGeneratorException) {
            return ['code' => 'unexpected_error', 'message' => $e->getMessage()];
        }

        $error = ['code' => $e->errorCode, 'message' => $e->getMessage()];

        if ($e->hint !== null) {
            $error['hint'] = $e->hint;
        }

        return $error;
    }

    /**
     * @return array<string, mixed>
     */
    public static function handshake(): array
    {
        return [
            'protocol' => self::VERSION,
            'package' => ['version' => self::packageVersion()],
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'capabilities' => [
                'fieldTypes' => FieldDefinition::CANONICAL_TYPES,
                'relationTypes' => SchemaParser::RELATION_KEYWORDS,
                'keepsEditedFiles' => true,
                'options' => [
                    'json_api' => class_exists(JsonApiResource::class)
                        ? ['supported' => true]
                        : ['supported' => false, 'reason' => 'JSON:API resources need Laravel 12.45+.'],
                ],
            ],
        ];
    }

    public static function packageVersion(): string
    {
        return InstalledVersions::isInstalled(self::PACKAGE)
            ? (InstalledVersions::getPrettyVersion(self::PACKAGE) ?? 'unknown')
            : 'unknown';
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public static function encode(array $document): string
    {
        return json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }
}
