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
        $model = $definition->name;
        $variable = $definition->getNameLower();
        $softDeleteMethods = '';
        if ($definition->hasSoftDeletes()) {
            $softDeleteMethods = <<<PHP


    public function restore({$model} \${$variable}): {$model}
    {
        \${$variable}->restore();

        return \${$variable};
    }

    public function forceDelete({$model} \${$variable}): bool
    {
        return \${$variable}->forceDelete();
    }
PHP;
        }

        $firstFieldDefinition = $definition->fields->first();
        $firstField = $firstFieldDefinition instanceof FieldDefinition ? $firstFieldDefinition->name : 'id';
        $filters = $definition->usesQueryBuilder()
            ? implode(', ', array_map(fn (string $column) => "AllowedFilter::exact('{$column}')", $definition->getFilterableColumns()))
            : $this->quoteList($definition->getFilterableColumns());
        $maxPerPage = max(1, (int) config('api-generator.pagination.max_per_page', 100));

        return [
            'modelName' => $definition->name,
            'modelNameLower' => $definition->getNameLower(),
            'pluralName' => $definition->getPluralName(),
            'softDeleteMethods' => $softDeleteMethods,
            'allowedFilters' => $filters,
            'allowedSorts' => $this->quoteList($definition->getSortableColumns()),
            'primaryKey' => $definition->getPrimaryKeyName(),
            'perPage' => (string) min(max(1, (int) config('api-generator.pagination.per_page', 15)), $maxPerPage),
            'maxPerPage' => (string) $maxPerPage,
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
