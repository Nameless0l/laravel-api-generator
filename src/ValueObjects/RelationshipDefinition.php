<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\ValueObjects;

use InvalidArgumentException;
use Illuminate\Support\Str;

final readonly class RelationshipDefinition
{
    public function __construct(
        public string $type,
        public string $relatedModel,
        public string $role,
        public ?string $foreignKey = null,
        public ?string $localKey = null,
        public ?string $pivotTable = null
    ) {
        $this->validateType($type);
        $this->validateRelatedModel($relatedModel);
        $this->validateRole($role);
    }

    private function validateType(string $type): void
    {
        $allowedTypes = ['oneToOne', 'oneToMany', 'manyToOne', 'manyToMany'];
        
        if (!in_array($type, $allowedTypes, true)) {
            throw new InvalidArgumentException("Invalid relationship type: {$type}");
        }
    }

    private function validateRelatedModel(string $relatedModel): void
    {
        if (empty(trim($relatedModel))) {
            throw new InvalidArgumentException('Related model cannot be empty');
        }
    }

    private function validateRole(string $role): void
    {
        if (empty(trim($role))) {
            throw new InvalidArgumentException('Role cannot be empty');
        }
    }

    public function getEloquentMethod(): string
    {
        return match ($this->type) {
            'oneToOne' => 'hasOne',
            'oneToMany' => 'hasMany',
            'manyToOne' => 'belongsTo',
            'manyToMany' => 'belongsToMany',
        };
    }

    public function requiresForeignKey(): bool
    {
        return in_array($this->type, ['manyToOne', 'oneToOne'], true);
    }

    public function getForeignKeyName(): string
    {
        if ($this->foreignKey !== null) {
            return $this->foreignKey;
        }

        return Str::snake($this->role) . '_id';
    }

    public function getMethodName(): string
    {
        return Str::camel($this->role);
    }
}
