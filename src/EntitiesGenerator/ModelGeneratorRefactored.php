<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;

class ModelGeneratorRefactored extends AbstractGenerator
{
    /**
     * Get the type of generator.
     */
    public function getType(): string
    {
        return 'Model';
    }

    /**
     * Get the output path for the generated file.
     */
    public function getOutputPath(EntityDefinition $definition): string
    {
        return app_path("Models/{$definition->name}.php");
    }

    /**
     * Generate the content for the file.
     */
    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->processStub($definition);
    }

    /**
     * Get the stub name for this generator.
     */
    protected function getStubName(): string
    {
        return 'model';
    }

    /**
     * Get replacements for the stub.
     *
     * @return array<string, string>
     */
    protected function getReplacements(EntityDefinition $definition): array
    {
        return [
            'modelName' => $definition->name,
            'fillable' => $this->generateFillableArray($definition).$this->generatePrimaryKey($definition).$this->generateCasts($definition),
            'relationships' => $this->generateRelationships($definition),
            'parentClass' => $this->getParentClass($definition),
            'imports' => $this->generateImports($definition),
            'traits' => $this->generateTraits($definition),
            'phpdoc' => $this->generatePhpDoc($definition),
        ];
    }

    /**
     * Generate fillable array string.
     */
    private function generateFillableArray(EntityDefinition $definition): string
    {
        $fillable = $definition->getFillableFields();
        $fillableString = "'".implode("', '", $fillable)."'";

        return "protected \$fillable = [{$fillableString}];";
    }

    /**
     * Generate relationship methods.
     */
    private function generateRelationships(EntityDefinition $definition): string
    {
        if (! $definition->hasRelationships()) {
            return '';
        }

        $methods = [];

        foreach ($definition->relationships as $relationship) {
            $methods[] = $this->generateRelationshipMethod($relationship);
        }

        return implode("\n\n", $methods);
    }

    /**
     * Generate a single relationship method.
     */
    private function generateRelationshipMethod(RelationshipDefinition $relationship): string
    {
        $methodName = $relationship->getMethodName();
        $eloquentMethod = $relationship->getEloquentMethod();
        $relatedModel = $relationship->relatedModel;

        if ($relationship->type === 'morphTo') {
            return "    public function {$methodName}()
    {
        return \$this->morphTo();
    }";
        }

        if ($relationship->isPolymorphic()) {
            return "    public function {$methodName}()
    {
        return \$this->{$eloquentMethod}({$relatedModel}::class, '{$relationship->getMorphName()}');
    }";
        }

        return "    public function {$methodName}()
    {
        return \$this->{$eloquentMethod}({$relatedModel}::class);
    }";
    }

    /**
     * Get parent class for inheritance.
     */
    private function getParentClass(EntityDefinition $definition): string
    {
        return $definition->hasParent() ? ($definition->parent ?? 'Model') : 'Model';
    }

    /**
     * Generate imports based on relationships and parent class.
     */
    private function generateImports(EntityDefinition $definition): string
    {
        $imports = [];

        if ($definition->hasSoftDeletes()) {
            $imports[] = 'use Illuminate\Database\Eloquent\SoftDeletes;';
        }

        $hasCollectionRelation = $definition->relationships->contains(
            fn (RelationshipDefinition $rel) => in_array($rel->getEloquentMethod(), ['hasMany', 'belongsToMany', 'morphMany'], true)
        );
        if ($hasCollectionRelation) {
            $imports[] = 'use Illuminate\Database\Eloquent\Collection;';
        }

        if ($definition->hasParent()) {
            $imports[] = "use App\\Models\\{$definition->parent};";
        }

        // morphTo has no concrete related model; a self-referential import
        // would collide with the class being declared.
        $relatedModels = $definition->relationships
            ->filter(fn (RelationshipDefinition $rel) => $rel->type !== 'morphTo')
            ->pluck('relatedModel')
            ->unique()
            ->filter(fn ($model) => $model !== $definition->name)
            ->map(fn ($model) => "use App\\Models\\{$model};")
            ->toArray();

        return implode("\n", array_merge($imports, $relatedModels));
    }

    private function generatePrimaryKey(EntityDefinition $definition): string
    {
        $primary = $definition->getPrimaryField();
        if ($primary === null) {
            return '';
        }

        $lines = [
            "protected \$primaryKey = '{$primary->name}';",
            'public $incrementing = false;',
        ];
        if ($primary->getKeyType() === 'string') {
            $lines[] = "protected \$keyType = 'string';";
        }

        return "\n\n    ".implode("\n\n    ", $lines);
    }

    /**
     * Generate $casts array for JSON and other special field types.
     */
    private function generateCasts(EntityDefinition $definition): string
    {
        $casts = [];

        foreach ($definition->fields as $field) {
            $cast = $field->getCastType();
            if ($cast !== null) {
                $casts[] = "'{$field->name}' => {$cast}";
            }
        }

        if (empty($casts)) {
            return '';
        }

        $castsString = implode(",\n        ", $casts);

        return "\n\n    protected \$casts = [\n        {$castsString},\n    ];";
    }

    private function generateTraits(EntityDefinition $definition): string
    {
        if ($definition->hasSoftDeletes()) {
            return '    use SoftDeletes;';
        }

        return '';
    }

    private function generatePhpDoc(EntityDefinition $definition): string
    {
        $lines = ['/**'];
        if ($definition->getPrimaryField() === null) {
            $lines[] = ' * @property int $id';
        }

        foreach ($definition->fields as $field) {
            $phpType = $field->isEnum()
                ? '\\App\\Enums\\'.$field->getEnumClass()
                : $this->phpTypeFromField($field->type);
            $nullable = $field->nullable ? '|null' : '';
            $lines[] = " * @property {$phpType}{$nullable} \${$field->name}";
        }

        foreach ($definition->relationships as $rel) {
            if ($rel->requiresForeignKey()) {
                $fkType = $rel->referencesCustomKey() && ! in_array($rel->relatedKeyType, ['integer', 'int', 'bigint'], true)
                    ? 'string'
                    : 'int';
                $lines[] = " * @property {$fkType} \${$rel->getForeignKeyName()}";
            }
        }

        foreach ($definition->relationships as $rel) {
            $phpType = match ($rel->getEloquentMethod()) {
                'belongsTo', 'hasOne', 'morphOne' => $rel->relatedModel,
                'hasMany', 'belongsToMany', 'morphMany' => "Collection<int, {$rel->relatedModel}>",
                'morphTo' => '\\Illuminate\\Database\\Eloquent\\Model',
                default => 'mixed',
            };

            $lines[] = " * @property-read {$phpType} \${$rel->getMethodName()}";
        }

        $lines[] = ' * @property \Illuminate\Support\Carbon|null $created_at';
        $lines[] = ' * @property \Illuminate\Support\Carbon|null $updated_at';

        if ($definition->hasSoftDeletes()) {
            $lines[] = ' * @property \Illuminate\Support\Carbon|null $deleted_at';
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
            'date', 'datetime', 'timestamp' => '\\Illuminate\\Support\\Carbon',
            default => 'string',
        };
    }
}
