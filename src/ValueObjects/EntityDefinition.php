<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\ValueObjects;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class EntityDefinition
{
    public const NAME_PATTERN = '/^[A-Z][a-zA-Z0-9]*$/';

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

    /**
     * @param  array<string, mixed>  $options
     */
    public function withOptions(array $options): self
    {
        return new self($this->name, $this->fields, $this->relationships, $this->parent, array_merge($this->options, $options));
    }

    /**
     * Fields the entity already has are left out.
     *
     * @param  Collection<int, FieldDefinition>  $fields
     */
    public function withFields(Collection $fields): self
    {
        $known = $this->fields->pluck('name')->all();
        $added = $fields
            ->reject(fn (FieldDefinition $field) => in_array($field->name, $known, true))
            ->map(fn (FieldDefinition $field) => $field->asAdded());

        return new self($this->name, $this->fields->concat($added)->values(), $this->relationships, $this->parent, $this->options);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'fields' => $this->fields->map(fn (FieldDefinition $field) => $field->toArray())->values()->all(),
            'relationships' => $this->relationships->map(fn (RelationshipDefinition $relation) => $relation->toArray())->values()->all(),
            'parent' => $this->parent,
            'options' => $this->options,
        ], fn (mixed $value) => $value !== null && $value !== []);
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(string $name, array $data): self
    {
        $fields = $data['fields'] ?? [];
        $relationships = $data['relationships'] ?? [];
        $parent = $data['parent'] ?? null;
        $options = $data['options'] ?? [];
        if (! is_array($fields) || ! is_array($relationships) || ! is_array($options)) {
            throw new InvalidArgumentException('fields, relationships and options must be lists');
        }

        $list = function (mixed $item): array {
            if (! is_array($item)) {
                throw new InvalidArgumentException('every field and relationship must be an object');
            }

            return $item;
        };

        /** @var array<string, mixed> $options */
        return new self(
            $name,
            collect(array_values($fields))->map(fn (mixed $field) => FieldDefinition::fromArray($list($field))),
            collect(array_values($relationships))->map(fn (mixed $relation) => RelationshipDefinition::fromArray($list($relation))),
            is_string($parent) ? $parent : null,
            $options
        );
    }

    private function validateName(string $name): void
    {
        if (empty(trim($name))) {
            throw new InvalidArgumentException('Entity name cannot be empty');
        }

        if (! preg_match(self::NAME_PATTERN, $name)) {
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

    /**
     * The parameter Route::apiResource() names, which implicit model binding
     * matches by name: "medium" for Media, not "media".
     */
    public function getRouteParameter(): string
    {
        return Str::singular($this->getPluralName());
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
     * The columns an index may filter on: the key and every fillable column except JSON ones.
     *
     * @return array<int, string>
     */
    public function getFilterableColumns(): array
    {
        $json = $this->fields->filter(fn (FieldDefinition $field) => $field->type === 'json')->pluck('name')->all();

        return array_values(array_unique([$this->getPrimaryKeyName(), ...array_diff($this->getFillableFields(), $json)]));
    }

    /**
     * @return array<int, string>
     */
    public function getSortableColumns(): array
    {
        return array_values(array_unique([...$this->getFilterableColumns(), 'created_at', 'updated_at']));
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

    public function usesQueryBuilder(): bool
    {
        return ($this->options['query_builder'] ?? false) === true;
    }

    public function skipsMigration(): bool
    {
        return ($this->options['skip_migration'] ?? false) === true;
    }

    public function usesPest(): bool
    {
        return ($this->options['pest'] ?? false) === true;
    }

    public function usesJsonApi(): bool
    {
        return ($this->options['json_api'] ?? false) === true;
    }

    public function getPrimaryField(): ?FieldDefinition
    {
        return $this->fields->first(fn (FieldDefinition $field) => $field->isPrimary());
    }

    public function getPrimaryKeyName(): string
    {
        $primary = $this->getPrimaryField();

        return $primary === null ? 'id' : $primary->name;
    }
}
