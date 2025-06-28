<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Contracts;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;

interface ApiGenerationServiceInterface
{
    /**
     * Generate a complete API for the given entity.
     */
    public function generateCompleteApi(EntityDefinition $definition): bool;

    /**
     * Generate APIs from JSON data.
     */
    public function generateFromJson(string $jsonData): bool;

    /**
     * Delete a complete API for the given entity.
     */
    public function deleteCompleteApi(string $entityName): bool;
}
