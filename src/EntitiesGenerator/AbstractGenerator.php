<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\Contracts\GeneratorInterface;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\Support\StubLoader;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use Illuminate\Support\Facades\File;

abstract class AbstractGenerator implements GeneratorInterface
{
    public function __construct(
        protected readonly StubLoader $stubLoader
    ) {}

    /**
     * Generate the file based on the entity definition.
     */
    public function generate(EntityDefinition $definition): bool
    {
        try {
            $content = $this->generateContent($definition);
            $outputPath = $this->getOutputPath($definition);
            
            $this->ensureDirectoryExists($outputPath);
            
            if (!File::put($outputPath, $content)) {
                throw CodeGeneratorException::fileCreationFailed($outputPath);
            }
            
            return true;
        } catch (\Exception $e) {
            throw CodeGeneratorException::generationFailed($this->getType(), $e->getMessage());
        }
    }

    /**
     * Check if the generator supports the given entity definition.
     */
    public function supports(EntityDefinition $definition): bool
    {
        return true; // Default implementation supports all entities
    }

    /**
     * Generate the content for the file.
     */
    abstract protected function generateContent(EntityDefinition $definition): string;

    /**
     * Get the stub name for this generator.
     */
    abstract protected function getStubName(): string;

    /**
     * Get replacements for the stub.
     */
    abstract protected function getReplacements(EntityDefinition $definition): array;

    /**
     * Ensure the directory exists for the output path.
     */
    protected function ensureDirectoryExists(string $filePath): void
    {
        $directory = dirname($filePath);
        
        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }
    }

    /**
     * Load and process stub with replacements.
     */
    protected function processStub(EntityDefinition $definition): string
    {
        $replacements = $this->getReplacements($definition);
        return $this->stubLoader->load($this->getStubName(), $replacements);
    }
}
