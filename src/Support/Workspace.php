<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

use Closure;
use DateTimeImmutable;
use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\ValueObjects\FileChange;

/**
 * One generation run. Writes stay in memory until commit() and reads see
 * them first, so a dry run executes exactly the code of a real run.
 */
final class Workspace
{
    /** @var array<string, array{content: string, kind: string, entity: ?string}> */
    private array $pending = [];

    private int $migrationSequence = 0;

    private ?int $migrationStart = null;

    /**
     * @param  Closure(): int  $clock
     */
    public function __construct(
        private readonly string $basePath,
        private readonly Closure $clock,
    ) {}

    public function exists(string $path): bool
    {
        return isset($this->pending[self::normalize($path)]) || File::exists($path);
    }

    public function get(string $path): string
    {
        $key = self::normalize($path);

        if (isset($this->pending[$key])) {
            return $this->pending[$key]['content'];
        }

        if (! File::exists($path)) {
            throw CodeGeneratorException::fileNotFound($path);
        }

        return File::get($path);
    }

    public function put(string $path, string $content, string $kind, ?string $entity = null): void
    {
        $key = self::normalize($path);
        $first = $this->pending[$key] ?? ['kind' => $kind, 'entity' => $entity];

        $this->pending[$key] = ['content' => $content, 'kind' => $first['kind'], 'entity' => $first['entity']];
    }

    public function append(string $path, string $content, string $kind, ?string $entity = null): void
    {
        $current = $this->exists($path) ? $this->get($path) : '';

        $this->put($path, $current.$content, $kind, $entity);
    }

    /**
     * @return array<int, string>
     */
    public function glob(string $pattern): array
    {
        $pattern = self::normalize($pattern);
        $matches = array_map(self::normalize(...), glob($pattern) ?: []);

        foreach (array_keys($this->pending) as $path) {
            if (fnmatch($pattern, $path)) {
                $matches[] = $path;
            }
        }

        $matches = array_values(array_unique($matches));
        sort($matches);

        return $matches;
    }

    /**
     * Consecutive within a run so migrations keep their generation order
     * (parents before children, pivots last), which foreign keys rely on,
     * and after every existing migration, even one created the same second.
     */
    public function migrationTimestamp(): string
    {
        $this->migrationStart ??= max(($this->clock)(), $this->newestMigrationTime() + 1);

        return date('Y_m_d_His', $this->migrationStart + $this->migrationSequence++);
    }

    private function newestMigrationTime(): int
    {
        $newest = PHP_INT_MIN;

        foreach ($this->glob(database_path('migrations/*.php')) as $migration) {
            $time = DateTimeImmutable::createFromFormat('!Y_m_d_His', substr(basename($migration), 0, 17));
            if ($time !== false) {
                $newest = max($newest, $time->getTimestamp());
            }
        }

        return $newest;
    }

    /**
     * @return array<int, FileChange>
     */
    public function changes(): array
    {
        $changes = [];

        foreach ($this->pending as $path => $file) {
            $action = match (true) {
                ! File::exists($path) => FileChange::CREATE,
                File::get($path) === $file['content'] => FileChange::UNCHANGED,
                default => FileChange::UPDATE,
            };

            $changes[] = new FileChange($this->relative($path), $file['kind'], $file['entity'], $action, $file['content']);
        }

        return $changes;
    }

    /**
     * @param  array<int, string>  $skip  paths relative to the project root that must stay as they are on disk
     */
    public function commit(array $skip = []): void
    {
        foreach ($this->pending as $path => $file) {
            if (in_array($this->relative($path), $skip, true)) {
                continue;
            }

            if (File::exists($path) && File::get($path) === $file['content']) {
                continue;
            }

            File::ensureDirectoryExists(dirname($path));

            if (File::put($path, $file['content']) === false) {
                throw CodeGeneratorException::fileCreationFailed($path);
            }
        }

        $this->pending = [];
    }

    private function relative(string $path): string
    {
        $base = rtrim(self::normalize($this->basePath), '/').'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }

    private static function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
