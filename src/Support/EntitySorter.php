<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

use Illuminate\Support\Collection;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;

/**
 * Orders entities so that referenced models (belongsTo / oneToOne targets)
 * are generated before the entities that depend on them. This guarantees
 * migrations are created in an order where foreign key constraints resolve.
 */
class EntitySorter
{
    /**
     * @param  Collection<int, EntityDefinition>  $entities
     * @return Collection<int, EntityDefinition>
     */
    public static function sortByDependencies(Collection $entities): Collection
    {
        $byName = $entities->keyBy(fn (EntityDefinition $e) => $e->name);

        $sorted = [];
        $visited = [];

        $visit = function (EntityDefinition $entity) use (&$visit, &$sorted, &$visited, $byName): void {
            if (isset($visited[$entity->name])) {
                return;
            }
            // Mark before recursing so cycles don't loop forever.
            $visited[$entity->name] = true;

            $entity->relationships
                ->filter(fn (RelationshipDefinition $rel) => $rel->requiresForeignKey())
                ->each(function (RelationshipDefinition $rel) use ($visit, $byName): void {
                    $parent = $byName->get($rel->relatedModel);
                    if ($parent !== null) {
                        $visit($parent);
                    }
                });

            $sorted[] = $entity;
        };

        $entities->each($visit);

        return collect($sorted);
    }
}
