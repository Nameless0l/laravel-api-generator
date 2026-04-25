<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\ValueObjects;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class EntityDefinition
{
    /**
     * @param  Collection<int, FieldDefinition>  $fields
     * @param  Collection<int, RelationshipDefinition>  $relationships
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $name,
        public Collection $fields,
        public Collection $relationships,
        public ?string $parent = null,
        public array $options = []
    ) {
        $this->validateName($name);
        $this->validateFields($fields);
        $this->validateRelationships($relationships);
    }

    private function validateName(string $name): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Entity name cannot be empty');
        }

        if (! preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            throw new InvalidArgumentException("Invalid entity name: {$name}. Must start with uppercase letter.");
        }
    }

    /**
     * @param  Collection<int, FieldDefinition>  $fields
     */
    private function validateFields(Collection $fields): void
    {
        $fieldNames = $fields->pluck('name')->toArray();
        $duplicates = array_filter(array_count_values($fieldNames), fn ($count) => $count > 1);

        if (! empty($duplicates)) {
            throw new InvalidArgumentException('Duplicate field names found: '.implode(', ', array_keys($duplicates)));
        }
    }

    /**
     * @param  Collection<int, RelationshipDefinition>  $relationships
     */
    private function validateRelationships(Collection $relationships): void
    {
        $relationshipRoles = $relationships->pluck('role')->toArray();
        $duplicates = array_filter(array_count_values($relationshipRoles), fn ($count) => $count > 1);

        if (! empty($duplicates)) {
            throw new InvalidArgumentException('Duplicate relationship roles found: '.implode(', ', array_keys($duplicates)));
        }
    }

    public function getTableName(): string
    {
        return Str::plural(Str::snake($this->name));
    }

    public function getPluralName(): string
    {
        return Str::plural(Str::lower($this->name));
    }

    public function getNameLower(): string
    {
        return Str::lower($this->name);
    }

    public function getNameCamel(): string
    {
        return Str::camel($this->name);
    }

    /**
     * @return array<int, string>
     */
    public function getFillableFields(): array
    {
        $fillable = $this->fields->pluck('name')->toArray();

        // Add foreign keys from relationships
        $foreignKeys = $this->relationships
            ->filter(fn (RelationshipDefinition $rel) => $rel->requiresForeignKey())
            ->map(fn (RelationshipDefinition $rel) => $rel->getForeignKeyName())
            ->toArray();

        return array_merge($fillable, $foreignKeys);
    }

    /**
     * @return array<string, string>
     */
    public function getFieldsArray(): array
    {
        return $this->fields->mapWithKeys(function (FieldDefinition $field) {
            return [$field->name => $field->type];
        })->toArray();
    }

    public function hasRelationships(): bool
    {
        return $this->relationships->isNotEmpty();
    }

    /**
     * @return Collection<int, RelationshipDefinition>
     */
    public function getRelationshipsByType(string $type): Collection
    {
        return $this->relationships->filter(
            fn (RelationshipDefinition $rel) => $rel->type === $type
        );
    }

    public function hasParent(): bool
    {
        return $this->parent !== null;
    }

    public function hasSoftDeletes(): bool
    {
        return ($this->options['soft_deletes'] ?? false) === true;
    }

    public function hasAuth(): bool
    {
        return ($this->options['auth'] ?? false) === true;
    }
}
