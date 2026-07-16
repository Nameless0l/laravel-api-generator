<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;

/**
 * Declaring one side of a relation in a schema/Mermaid source is enough:
 * the missing side is synthesized here, mirroring what DatabaseIntrospector
 * already does for introspected schemas. Synthesizing belongsTo from a
 * declared hasMany is what puts the FK column in the child migration.
 */
class RelationshipSynthesizer
{
    /**
     * @param  Collection<int, EntityDefinition>  $entities
     * @return Collection<int, EntityDefinition>
     */
    public static function addInverses(Collection $entities): Collection
    {
        /** @var array<string, array<int, RelationshipDefinition>> $added */
        $added = [];
        $byName = $entities->keyBy(fn (EntityDefinition $e) => $e->name);

        foreach ($entities as $entity) {
            foreach ($entity->relationships as $relation) {
                $target = $byName->get($relation->relatedModel);
                if ($target === null || $relation->isPolymorphic()) {
                    continue;
                }

                $inverse = self::inverseFor($entity->name, $relation);
                if ($inverse === null) {
                    continue;
                }

                if (self::hasRelationTo($target, $inverse->type, $entity->name)
                    || self::roleTaken($target, $added[$target->name] ?? [], $inverse->role)) {
                    continue;
                }

                $added[$target->name][] = $inverse;
            }
        }

        if ($added === []) {
            return $entities;
        }

        return $entities->map(function (EntityDefinition $entity) use ($added) {
            if (! isset($added[$entity->name])) {
                return $entity;
            }

            return new EntityDefinition(
                name: $entity->name,
                fields: $entity->fields,
                relationships: $entity->relationships->concat($added[$entity->name]),
                parent: $entity->parent,
                options: $entity->options
            );
        });
    }

    /**
     * When a relation targets an entity with a custom primary key, the FK
     * column name, its type and the referenced column must all follow it.
     *
     * @param  Collection<int, EntityDefinition>  $entities
     * @return Collection<int, EntityDefinition>
     */
    public static function resolveRelatedKeys(Collection $entities): Collection
    {
        $byName = $entities->keyBy(fn (EntityDefinition $e) => $e->name);

        return $entities->map(function (EntityDefinition $entity) use ($byName) {
            $changed = false;
            $relationships = $entity->relationships->map(function (RelationshipDefinition $rel) use ($byName, &$changed) {
                if (! $rel->requiresForeignKey() || $rel->relatedKey !== null) {
                    return $rel;
                }

                $target = $byName->get($rel->relatedModel);
                $primaryField = $target?->getPrimaryField();
                if ($primaryField === null) {
                    return $rel;
                }

                $changed = true;

                return new RelationshipDefinition(
                    type: $rel->type,
                    relatedModel: $rel->relatedModel,
                    role: $rel->role,
                    foreignKey: $rel->foreignKey,
                    localKey: $rel->localKey,
                    pivotTable: $rel->pivotTable,
                    morphName: $rel->morphName,
                    relatedKey: $primaryField->name,
                    relatedKeyType: $primaryField->type
                );
            });

            if (! $changed) {
                return $entity;
            }

            return new EntityDefinition(
                name: $entity->name,
                fields: $entity->fields,
                relationships: $relationships,
                parent: $entity->parent,
                options: $entity->options
            );
        });
    }

    private static function inverseFor(string $owner, RelationshipDefinition $relation): ?RelationshipDefinition
    {
        return match ($relation->type) {
            'manyToOne' => new RelationshipDefinition(
                type: 'oneToMany',
                relatedModel: $owner,
                role: Str::camel(Str::plural($owner))
            ),
            'oneToMany' => new RelationshipDefinition(
                type: 'manyToOne',
                relatedModel: $owner,
                role: Str::camel($owner)
            ),
            'manyToMany' => new RelationshipDefinition(
                type: 'manyToMany',
                relatedModel: $owner,
                role: Str::camel(Str::plural($owner)),
                pivotTable: $relation->pivotTable
            ),
            default => null,
        };
    }

    private static function hasRelationTo(EntityDefinition $entity, string $type, string $relatedModel): bool
    {
        return $entity->relationships->contains(
            fn (RelationshipDefinition $rel) => $rel->type === $type && $rel->relatedModel === $relatedModel
        );
    }

    /**
     * @param  array<int, RelationshipDefinition>  $pending
     */
    private static function roleTaken(EntityDefinition $entity, array $pending, string $role): bool
    {
        if ($entity->relationships->contains(fn (RelationshipDefinition $rel) => $rel->role === $role)) {
            return true;
        }

        foreach ($pending as $rel) {
            if ($rel->role === $role) {
                return true;
            }
        }

        return false;
    }
}
