<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NoDirectWritesTest extends TestCase
{
    private const FORBIDDEN = ['File::put(', 'File::append(', 'File::makeDirectory(', 'File::ensureDirectoryExists(', 'file_put_contents('];

    private const GENERATION_FILES = [
        'Services/ApiGenerationService.php',
        'Services/AuthGenerator.php',
        'Services/PostmanExporter.php',
        'Services/EntityEvolutionService.php',
        'Support/ApiRoutesRegistrar.php',
    ];

    /**
     * @return array<string, array{string}>
     */
    public static function generationFiles(): array
    {
        $src = dirname(__DIR__, 2).'/src';
        $files = glob($src.'/EntitiesGenerator/*.php') ?: [];

        foreach (self::GENERATION_FILES as $file) {
            $files[] = $src.'/'.$file;
        }

        $cases = [];
        foreach ($files as $file) {
            $cases[basename($file)] = [$file];
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('generationFiles')]
    public function generation_code_writes_through_the_workspace(string $file): void
    {
        $source = (string) file_get_contents($file);

        foreach (self::FORBIDDEN as $call) {
            $this->assertStringNotContainsString($call, $source, basename($file)." calls {$call} directly; write through the Workspace.");
        }
    }
}
