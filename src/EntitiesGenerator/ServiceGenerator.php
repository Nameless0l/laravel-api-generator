<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;

class ServiceGenerator extends AbstractGenerator
{
    public function getType(): string
    {
        return 'Service';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return app_path("Services/{$definition->name}Service.php");
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        $stubName = $definition->usesQueryBuilder() ? 'service.query-builder' : 'service';

        return $this->stubLoader->load($stubName, $this->getReplacements($definition));
    }

    protected function getStubName(): string
    {
        return 'service';
    }

    /**
     * @return array<string, string>
     */
    protected function getReplacements(EntityDefinition $definition): array
    {
        $softDeleteMethods = '';
        if ($definition->hasSoftDeletes()) {
            $softDeleteMethods = <<<PHP

    public function restore(int|string \$id): {$definition->name}
    {
        \${$definition->getNameLower()} = {$definition->name}::withTrashed()->findOrFail(\$id);
        \${$definition->getNameLower()}->restore();
        return \${$definition->getNameLower()};
    }

    public function forceDelete(int|string \$id): bool
    {
        \${$definition->getNameLower()} = {$definition->name}::withTrashed()->findOrFail(\$id);
        return \${$definition->getNameLower()}->forceDelete();
    }
PHP;
        }

        $filterable = $definition->getFillableFields();
        $sortable = array_values(array_unique(array_merge(['id'], $filterable, ['created_at'])));
        $firstFieldDefinition = $definition->fields->first();
        $firstField = $firstFieldDefinition instanceof FieldDefinition ? $firstFieldDefinition->name : 'id';

        return [
            'modelName' => $definition->name,
            'modelNameLower' => $definition->getNameLower(),
            'pluralName' => $definition->getPluralName(),
            'softDeleteMethods' => $softDeleteMethods,
            'allowedFilters' => $this->quoteList($filterable),
            'allowedSorts' => $this->quoteList($sortable),
            'firstField' => $firstField,
        ];
    }

    /**
     * @param  array<int, string>  $values
     */
    private function quoteList(array $values): string
    {
        return implode(', ', array_map(fn (string $v) => "'{$v}'", $values));
    }
}
