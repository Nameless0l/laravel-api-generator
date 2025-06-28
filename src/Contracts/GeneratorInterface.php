<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Contracts;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;

interface GeneratorInterface
{
    /**
     * Generate the file based on the entity definition.
     */
    public function generate(EntityDefinition $definition): bool;

    /**
     * Get the type of generator.
     */
    public function getType(): string;

    /**
     * Check if the generator supports the given entity definition.
     */
    public function supports(EntityDefinition $definition): bool;

    /**
     * Get the output path for the generated file.
     */
    public function getOutputPath(EntityDefinition $definition): string;
}
