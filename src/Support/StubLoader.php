<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;

class StubLoader
{
    private const STUB_EXTENSION = '.stub';

    /**
     * Directory inside the user's project where published stubs are stored.
     * php artisan vendor:publish --tag=api-generator-stubs
     */
    private const USER_STUBS_DIR = 'stubs/vendor/laravel-api-generator';

    public function __construct(
        private readonly string $stubsPath
    ) {}

    /**
     * Load a stub file and replace placeholders.
     * User-published stubs (stubs/vendor/laravel-api-generator/) take priority
     * over the package's built-in stubs.
     *
     * @param  array<string, string>  $replacements
     */
    public function load(string $stubName, array $replacements = []): string
    {
        $stubPath = $this->resolveStubPath($stubName);

        if (! File::exists($stubPath)) {
            throw CodeGeneratorException::fileNotFound($stubPath);
        }

        $content = File::get($stubPath);

        return $this->replacePlaceholders($content, $replacements);
    }

    /**
     * Resolve the stub path: user-published stubs first, package stubs as fallback.
     */
    private function resolveStubPath(string $stubName): string
    {
        $stubName = str_replace(self::STUB_EXTENSION, '', $stubName);
        $fileName = $stubName.self::STUB_EXTENSION;

        $userStubPath = base_path(self::USER_STUBS_DIR.DIRECTORY_SEPARATOR.$fileName);

        if (File::exists($userStubPath)) {
            return $userStubPath;
        }

        return $this->stubsPath.DIRECTORY_SEPARATOR.$fileName;
    }

    /**
     * Replace placeholders in stub content.
     *
     * @param  array<string, string>  $replacements
     */
    private function replacePlaceholders(string $content, array $replacements): string
    {
        foreach ($replacements as $placeholder => $value) {
            $content = str_replace("{{{$placeholder}}}", $value, $content);
        }

        return $content;
    }

    /**
     * Check if a stub exists (user-published or package).
     */
    public function exists(string $stubName): bool
    {
        return File::exists($this->resolveStubPath($stubName));
    }

    /**
     * Return the resolved path for a given stub (useful for debugging).
     */
    public function getResolvedPath(string $stubName): string
    {
        return $this->resolveStubPath($stubName);
    }
}
