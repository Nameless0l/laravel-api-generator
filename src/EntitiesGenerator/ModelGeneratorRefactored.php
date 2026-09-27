<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use Illuminate\Support\Str;
use nameless\CodeGenerator\Support\LaravelVersion;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;

class ModelGeneratorRefactored extends AbstractGenerator
{
    public function getType(): string
    {
        return 'Model';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return app_path("Models/{$definition->name}.php");
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->processStub($definition);
    }

    protected function getStubName(): string
    {
        return 'model';
    }

    /**
     * members and classAttributes feed the 4.0 stub; traits, fillable and
     * relationships keep stubs published before 4.0 working.
     *
     * @return array<string, string>
     */
    protected function getReplacements(EntityDefinition $definition): array
    {
        $attributes = app(LaravelVersion::class)->hasModelAttributes();
        $keyProperties = $this->keyProperties($definition);
        $fillable = '    protected $fillable = ['.$this->quotedFillable($definition).'];';
        $casts = $this->castsMethod($definition);
        $relationships = $definition->relationships
            ->map(fn (RelationshipDefinition $rel) => $this->relationshipMethod($rel, $definition))
            ->all();

        $traits = $definition->hasSoftDeletes() ? 'HasFactory, SoftDeletes' : 'HasFactory';
        $members = [
            "    use {$traits};",
            ...($attributes ? [] : [...$keyProperties, $fillable]),
            ...array_filter([$casts]),
            ...$relationships,
        ];

        return [
            'modelName' => $definition->name,
            'parentClass' => $this->getParentClass($definition),
            'imports' => implode("\n", array_map(fn (string $class) => "use {$class};", $this->imports($definition, $attributes))),
            'phpdoc' => $this->generatePhpDoc($definition),
            'classAttributes' => $attributes ? $this->classAttributes($definition) : '',
            'members' => implode("\n\n", $members),
            'traits' => $definition->hasSoftDeletes() ? '    use SoftDeletes;' : '',
            'fillable' => ltrim(implode("\n\n", [$fillable, ...$keyProperties, ...array_filter([$casts])])),
            'relationships' => implode("\n\n", $relationships),
        ];
    }

    private function quotedFillable(EntityDefinition $definition): string
    {
        return implode(', ', array_map(fn (string $name) => "'{$name}'", $definition->getFillableFields()));
    }

    /**
     * @return array<int, string>
     */
    private function keyProperties(EntityDefinition $definition): array
    {
        $primary = $definition->getPrimaryField();
        if ($primary === null) {
            return [];
        }

        $properties = [
            "    protected \$primaryKey = '{$primary->name}';",
            '    public $incrementing = false;',
        ];
        if ($primary->getKeyType() === 'string') {
            $properties[] = "    protected \$keyType = 'string';";
        }

        return $properties;
    }

    private function classAttributes(EntityDefinition $definition): string
    {
        $lines = [];

        $primary = $definition->getPrimaryField();
        if ($primary !== null) {
            $keyType = $primary->getKeyType() === 'string' ? ", keyType: 'string'" : '';
            $lines[] = "#[Table(key: '{$primary->name}'{$keyType}, incrementing: false)]";
        }

        if ($definition->getFillableFields() !== []) {
            $lines[] = '#[Fillable(['.$this->quotedFillable($definition).'])]';
        }

        return implode('', array_map(fn (string $line) => $line."\n", $lines));
    }

    private function castsMethod(EntityDefinition $definition): string
    {
        $casts = $definition->fields
            ->map(fn (FieldDefinition $field) => $field->getCastType($definition->name) === null
                ? null
                : "            '{$field->name}' => {$field->getCastType($definition->name)},")
            ->filter()
            ->implode("\n");

        if ($casts === '') {
            return '';
        }

        return "    protected function casts(): array\n    {\n        return [\n{$casts}\n        ];\n    }";
    }

    private function relationshipMethod(RelationshipDefinition $relationship, EntityDefinition $owner): string
    {
        $eloquentMethod = $relationship->getEloquentMethod();
        $signature = "    public function {$relationship->getMethodName()}(): ".Str::studly($eloquentMethod);

        if ($relationship->type === 'morphTo') {
            return "{$signature}\n    {\n        return \$this->morphTo();\n    }";
        }

        $arguments = "{$relationship->relatedModel}::class";

        if ($relationship->isPolymorphic()) {
            $arguments .= ", '{$relationship->getMorphName()}'";
        }

        // Eloquent guesses owner_id, or owner_<key> with a custom primary key
        $guessedKey = Str::snake($owner->name).'_'.$owner->getPrimaryKeyName();
        if (in_array($relationship->type, ['oneToMany', 'oneToOne'], true) && $relationship->foreignKey !== null && $relationship->foreignKey !== $guessedKey) {
            $arguments .= ", '{$relationship->foreignKey}'";
        }

        return "{$signature}\n    {\n        return \$this->{$eloquentMethod}({$arguments});\n    }";
    }

    private function getParentClass(EntityDefinition $definition): string
    {
        return $definition->hasParent() ? ($definition->parent ?? 'Model') : 'Model';
    }

    /**
     * @return array<int, string>
     */
    private function imports(EntityDefinition $definition, bool $attributes): array
    {
        $imports = [
            'Illuminate\\Database\\Eloquent\\Factories\\HasFactory',
            'Illuminate\\Support\\Carbon',
        ];

        $morphTo = $definition->relationships->contains(fn (RelationshipDefinition $rel) => $rel->type === 'morphTo');
        if (! $definition->hasParent() || $morphTo) {
            $imports[] = 'Illuminate\\Database\\Eloquent\\Model';
        }

        if ($definition->hasSoftDeletes()) {
            $imports[] = 'Illuminate\\Database\\Eloquent\\SoftDeletes';
        }

        if ($definition->relationships->contains(fn (RelationshipDefinition $rel) => in_array($rel->getEloquentMethod(), ['hasMany', 'belongsToMany', 'morphMany'], true))) {
            $imports[] = 'Illuminate\\Database\\Eloquent\\Collection';
        }

        foreach ($definition->relationships as $relationship) {
            $imports[] = 'Illuminate\\Database\\Eloquent\\Relations\\'.Str::studly($relationship->getEloquentMethod());
        }

        foreach ($definition->fields as $field) {
            if ($field->isEnum()) {
                $imports[] = 'App\\Enums\\'.$field->getEnumClass($definition->name);
            }
        }

        if ($attributes) {
            if ($definition->getPrimaryField() !== null) {
                $imports[] = 'Illuminate\\Database\\Eloquent\\Attributes\\Table';
            }
            if ($definition->getFillableFields() !== []) {
                $imports[] = 'Illuminate\\Database\\Eloquent\\Attributes\\Fillable';
            }
        }

        return array_values(array_unique($imports));
    }

    private function generatePhpDoc(EntityDefinition $definition): string
    {
        $lines = ['/**'];
        if ($definition->getPrimaryField() === null) {
            $lines[] = ' * @property int $id';
        }

        foreach ($definition->fields as $field) {
            $phpType = $field->isEnum()
                ? $field->getEnumClass($definition->name)
                : $this->phpTypeFromField($field->type);
            $nullable = $field->nullable ? '|null' : '';
            $lines[] = " * @property {$phpType}{$nullable} \${$field->name}";
        }

        foreach ($definition->relationships as $rel) {
            if ($rel->requiresForeignKey()) {
                $lines[] = " * @property {$rel->getForeignKeyPhpType()} \${$rel->getForeignKeyName()}";
            }
        }

        foreach ($definition->relationships as $rel) {
            $phpType = match ($rel->getEloquentMethod()) {
                'belongsTo', 'hasOne', 'morphOne' => $rel->relatedModel,
                'hasMany', 'belongsToMany', 'morphMany' => "Collection<int, {$rel->relatedModel}>",
                'morphTo' => 'Model|null',
                default => 'mixed',
            };

            $lines[] = " * @property-read {$phpType} \${$rel->getMethodName()}";
        }

        $lines[] = ' * @property Carbon|null $created_at';
        $lines[] = ' * @property Carbon|null $updated_at';

        if ($definition->hasSoftDeletes()) {
            $lines[] = ' * @property Carbon|null $deleted_at';
        }

        $lines[] = ' */';

        return implode("\n", $lines);
    }

    private function phpTypeFromField(string $fieldType): string
    {
        return match ($fieldType) {
            'integer', 'int', 'bigint' => 'int',
            'float', 'double', 'decimal' => 'float',
            'boolean', 'bool' => 'bool',
            'json' => 'array',
            'date', 'datetime', 'timestamp' => 'Carbon',
            default => 'string',
        };
    }
}
