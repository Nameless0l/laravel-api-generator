<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FileChange;

/**
 * Remembers what the generator wrote and from which definitions, so a later
 * run can tell a file left untouched (safe to regenerate) from one edited by
 * hand, and regenerate an entity without its source.
 */
final class Manifest
{
    public const PATH = '.api-generator/manifest.json';

    /**
     * Shared files are patched in place (routes, seeders, bootstrap) or
     * rebuilt whole every run (Postman), so there is nothing to protect.
     */
    private const UNTRACKED_KINDS = ['Routes', 'DatabaseSeeder', 'Bootstrap', 'Postman'];

    /**
     * @param  array<string, array{entity: ?string, kind: string, hash: string}>  $files
     * @param  array<string, array<mixed>>  $entities
     */
    private function __construct(
        private readonly string $basePath,
        private array $files,
        private readonly bool $exists,
        private array $entities = [],
    ) {}

    public static function load(string $basePath): self
    {
        $path = self::file($basePath);

        if (! File::exists($path)) {
            return new self($basePath, [], false);
        }

        $data = json_decode(File::get($path), true);
        if (! is_array($data) || ! is_array($data['files'] ?? null)) {
            throw new CodeGeneratorException(
                'The generation manifest '.self::PATH.' is not valid JSON.',
                'invalid_manifest',
                'Restore it from version control, or delete it to rebuild it on the next run.'
            );
        }

        $files = [];
        foreach ($data['files'] as $file => $entry) {
            if (is_string($file) && is_array($entry) && is_string($entry['kind'] ?? null) && is_string($entry['hash'] ?? null)) {
                $files[$file] = [
                    'entity' => is_string($entry['entity'] ?? null) ? $entry['entity'] : null,
                    'kind' => $entry['kind'],
                    'hash' => $entry['hash'],
                ];
            }
        }

        $entities = [];
        foreach (is_array($data['entities'] ?? null) ? $data['entities'] : [] as $entity => $definition) {
            if (is_string($entity) && is_array($definition)) {
                $entities[$entity] = $definition;
            }
        }

        return new self($basePath, $files, true, $entities);
    }

    public static function tracks(string $kind): bool
    {
        return ! in_array($kind, self::UNTRACKED_KINDS, true);
    }

    /**
     * Line endings are ignored so a checkout with core.autocrlf does not
     * look like a manual edit.
     */
    public static function hash(string $content): string
    {
        return hash('sha256', str_replace("\r\n", "\n", $content));
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Null when the manifest does not know the file.
     */
    public function isPristine(string $path, string $diskContent): ?bool
    {
        $entry = $this->files[$path] ?? null;

        return $entry === null ? null : $entry['hash'] === self::hash($diskContent);
    }

    public function record(FileChange $change): void
    {
        $this->files[$change->path] = [
            'entity' => $change->entity,
            'kind' => $change->kind,
            'hash' => self::hash($change->content),
        ];
    }

    public function forget(string $path): void
    {
        unset($this->files[$path]);
    }

    public function define(EntityDefinition $entity): void
    {
        $this->entities[$entity->name] = $entity->toArray();
    }

    /**
     * Null for an entity generated before 4.1, which recorded no definition.
     */
    public function definition(string $entity): ?EntityDefinition
    {
        if (! isset($this->entities[$entity])) {
            return null;
        }

        try {
            return EntityDefinition::fromArray($entity, $this->entities[$entity]);
        } catch (InvalidArgumentException $e) {
            throw new CodeGeneratorException(
                "The definition of {$entity} recorded in ".self::PATH." is not valid: {$e->getMessage()}.",
                'invalid_manifest',
                'Restore the manifest from version control, or regenerate the entity from its source.'
            );
        }
    }

    public function forgetDefinition(string $entity): void
    {
        unset($this->entities[$entity]);
    }

    /**
     * @return array<string, array{entity: ?string, kind: string, hash: string}>
     */
    public function entries(): array
    {
        return $this->files;
    }

    /**
     * @param  array<int, string>  $exceptKinds
     * @return array<int, string>
     */
    public function filesOf(string $entity, array $exceptKinds = []): array
    {
        return array_keys(array_filter(
            $this->files,
            fn (array $entry) => $entry['entity'] === $entity && ! in_array($entry['kind'], $exceptKinds, true)
        ));
    }

    public function save(): void
    {
        ksort($this->files);
        ksort($this->entities);

        $data = ['version' => 1, 'files' => $this->files];
        if ($this->entities !== []) {
            $data['entities'] = $this->entities;
        }

        File::ensureDirectoryExists(dirname(self::file($this->basePath)));
        File::put(
            self::file($this->basePath),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n"
        );
    }

    private static function file(string $basePath): string
    {
        return rtrim($basePath, '/\\').'/'.self::PATH;
    }
}
