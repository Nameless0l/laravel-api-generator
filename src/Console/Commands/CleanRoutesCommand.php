<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Removes route lines (and their imports) that reference controllers whose
 * class file no longer exists — the leftovers that make `route:list` and
 * IDE tooling crash with a ReflectionException after manual deletions.
 */
class CleanRoutesCommand extends Command
{
    protected $signature = 'api-generator:clean-routes {--dry-run : List the orphan lines without touching the files}';

    protected $description = 'Remove routes pointing to controllers that no longer exist';

    public function handle(): int
    {
        $removedTotal = 0;

        foreach (['routes/api.php', 'routes/web.php'] as $routeFile) {
            $removedTotal += $this->cleanFile(base_path($routeFile));
        }

        if ($removedTotal === 0) {
            $this->info('No orphan routes found.');
        } elseif ($this->option('dry-run')) {
            $this->warn("{$removedTotal} orphan line(s) found. Run without --dry-run to remove them.");
        } else {
            $this->info("{$removedTotal} orphan line(s) removed.");
        }

        return self::SUCCESS;
    }

    private function cleanFile(string $path): int
    {
        if (! File::exists($path)) {
            return 0;
        }

        $lines = explode("\n", File::get($path));
        $kept = [];
        $removed = 0;

        foreach ($lines as $line) {
            $controller = $this->referencedController($line);
            if ($controller !== null && ! $this->controllerExists($controller)) {
                $this->line('  - '.basename($path).': '.trim($line));
                $removed++;

                continue;
            }
            $kept[] = $line;
        }

        if ($removed > 0 && ! $this->option('dry-run')) {
            $content = implode("\n", $kept);
            $result = preg_replace("/\n{3,}/", "\n\n", $content);
            File::put($path, is_string($result) ? $result : $content);
        }

        return $removed;
    }

    private function referencedController(string $line): ?string
    {
        $trimmed = trim($line);

        if (str_starts_with($trimmed, 'use ') && preg_match('/^use\s+App\\\\Http\\\\Controllers\\\\(\w+);/', $trimmed, $m)) {
            return $m[1];
        }

        if (str_contains($trimmed, 'Route::') && preg_match('/([\w\\\\]*?)(\w+Controller)::class/', $trimmed, $m)) {
            return $m[2];
        }

        return null;
    }

    private function controllerExists(string $controller): bool
    {
        return File::exists(app_path("Http/Controllers/{$controller}.php"));
    }
}
