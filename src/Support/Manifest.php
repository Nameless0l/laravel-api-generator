<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\ValueObjects\FileChange;

/**
 * Remembers what the generator wrote, so a later run can tell a file left
 * untouched (safe to regenerate) from one edited by hand.
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
     */
    private function __construct(
        private readonly string $basePath,
        private array $files,
        private readonly bool $exists,
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

        return new self($basePath, $files, true);
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

    /**
     * @return array<int, string>
     */
    public function filesOf(string $entity): array
    {
        return array_keys(array_filter($this->files, fn (array $entry) => $entry['entity'] === $entity));
    }

    public function save(): void
    {
        ksort($this->files);

        File::ensureDirectoryExists(dirname(self::file($this->basePath)));
        File::put(
            self::file($this->basePath),
            json_encode(['version' => 1, 'files' => $this->files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n"
        );
    }

    private static function file(string $basePath): string
    {
        return rtrim($basePath, '/\\').'/'.self::PATH;
    }
}
