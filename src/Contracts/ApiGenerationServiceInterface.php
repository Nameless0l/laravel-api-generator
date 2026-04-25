<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Contracts;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;

interface ApiGenerationServiceInterface
{
    /**
     * Generate a complete API for the given entity.
     *
     * @param  array<int, string>|null  $onlyTypes  When provided, only generators
     *                                              whose getType() matches one of
     *                                              these names are run.
     */
    public function generateCompleteApi(EntityDefinition $definition, ?array $onlyTypes = null): bool;

    /**
     * Generate APIs from JSON data.
     */
    public function generateFromJson(string $jsonData): bool;

    /**
     * Delete a complete API for the given entity.
     */
    public function deleteCompleteApi(string $entityName): bool;
}
