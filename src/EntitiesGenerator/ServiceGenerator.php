<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;

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
        return $this->processStub($definition);
    }

    protected function getStubName(): string
    {
        return 'service';
    }

    protected function getReplacements(EntityDefinition $definition): array
    {
        $softDeleteMethods = '';
        if ($definition->hasSoftDeletes()) {
            $softDeleteMethods = <<<PHP

    public function restore(int \$id): {$definition->name}
    {
        \${$definition->getNameLower()} = {$definition->name}::withTrashed()->findOrFail(\$id);
        \${$definition->getNameLower()}->restore();
        return \${$definition->getNameLower()};
    }

    public function forceDelete(int \$id): bool
    {
        \${$definition->getNameLower()} = {$definition->name}::withTrashed()->findOrFail(\$id);
        return \${$definition->getNameLower()}->forceDelete();
    }
PHP;
        }

        return [
            'modelName' => $definition->name,
            'modelNameLower' => $definition->getNameLower(),
            'softDeleteMethods' => $softDeleteMethods,
        ];
    }
}
