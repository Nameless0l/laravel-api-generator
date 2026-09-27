<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\Contracts\GeneratorInterface;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\StubLoader;
use nameless\CodeGenerator\Support\Workspace;
use nameless\CodeGenerator\Support\WorkspaceFactory;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;

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
        $workspace = app(WorkspaceFactory::class)->make();
        $this->render($definition, $workspace);
        $workspace->commit();

        return true;
    }

    public function render(EntityDefinition $definition, Workspace $workspace): void
    {
        try {
            $workspace->put(
                $this->getOutputPath($definition),
                $this->generateContent($definition),
                $this->getType(),
                $definition->name
            );
        } catch (\Exception $e) {
            throw CodeGeneratorException::generationFailed($this->getType(), $e->getMessage(), $e);
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
     *
     * @return array<string, string>
     */
    abstract protected function getReplacements(EntityDefinition $definition): array;

    /**
     * Load and process stub with replacements.
     */
    protected function processStub(EntityDefinition $definition): string
    {
        $replacements = $this->getReplacements($definition);

        return $this->stubLoader->load($this->getStubName(), $replacements);
    }
}
