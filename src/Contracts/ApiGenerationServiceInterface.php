<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Contracts;

use Illuminate\Support\Collection;
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
     *
     * @deprecated Unused by the package commands; will be removed in 4.0.
     */
    public function generateFromJson(string $jsonData): bool;

    /**
     * Create the pivot table migrations required by manyToMany relationships.
     *
     * @param  Collection<int, EntityDefinition>  $definitions
     * @return array<int, string> created migration file paths
     */
    public function generatePivotMigrations(Collection $definitions): array;

    /**
     * Delete a complete API for the given entity.
     *
     * @deprecated Use the delete:fullapi command; will be removed in 4.0.
     */
    public function deleteCompleteApi(string $entityName): bool;
}
