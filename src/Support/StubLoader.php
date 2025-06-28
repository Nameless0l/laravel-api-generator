<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;

class StubLoader
{
    private const STUB_EXTENSION = '.stub';
    
    public function __construct(
        private readonly string $stubsPath
    ) {}

    /**
     * Load a stub file and replace placeholders.
     */
    public function load(string $stubName, array $replacements = []): string
    {
        $stubPath = $this->getStubPath($stubName);
        
        if (!File::exists($stubPath)) {
            throw CodeGeneratorException::fileNotFound($stubPath);
        }

        $content = File::get($stubPath);
        
        return $this->replacePlaceholders($content, $replacements);
    }

    /**
     * Get the full path to a stub file.
     */
    private function getStubPath(string $stubName): string
    {
        $stubName = str_replace(self::STUB_EXTENSION, '', $stubName);
        return $this->stubsPath . DIRECTORY_SEPARATOR . $stubName . self::STUB_EXTENSION;
    }

    /**
     * Replace placeholders in stub content.
     */
    private function replacePlaceholders(string $content, array $replacements): string
    {
        foreach ($replacements as $placeholder => $value) {
            $content = str_replace("{{$placeholder}}", $value, $content);
        }

        return $content;
    }

    /**
     * Check if a stub exists.
     */
    public function exists(string $stubName): bool
    {
        return File::exists($this->getStubPath($stubName));
    }
}
