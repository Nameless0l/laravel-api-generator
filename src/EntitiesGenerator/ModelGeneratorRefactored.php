<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;
use Illuminate\Support\Str;

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
     */
    protected function getReplacements(EntityDefinition $definition): array
    {
        return [
            'modelName' => $definition->name,
            'fillable' => $this->generateFillableArray($definition),
            'relationships' => $this->generateRelationships($definition),
            'parentClass' => $this->getParentClass($definition),
            'imports' => $this->generateImports($definition),
        ];
    }

    /**
     * Generate fillable array string.
     */
    private function generateFillableArray(EntityDefinition $definition): string
    {
        $fillable = $definition->getFillableFields();
        $fillableString = "'" . implode("', '", $fillable) . "'";
        
        return "protected \$fillable = [{$fillableString}];";
    }

    /**
     * Generate relationship methods.
     */
    private function generateRelationships(EntityDefinition $definition): string
    {
        if (!$definition->hasRelationships()) {
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
        return $definition->hasParent() ? $definition->parent : 'Model';
    }

    /**
     * Generate imports based on relationships and parent class.
     */
    private function generateImports(EntityDefinition $definition): string
    {
        $imports = ['use Illuminate\Database\Eloquent\Model;'];
        
        if ($definition->hasParent()) {
            $imports[] = "use App\\Models\\{$definition->parent};";
        }

        // Add imports for related models
        $relatedModels = $definition->relationships
            ->pluck('relatedModel')
            ->unique()
            ->map(fn($model) => "use App\\Models\\{$model};")
            ->toArray();

        return implode("\n", array_merge($imports, $relatedModels));
    }
}
