<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;

class ControllerGenerator extends AbstractGenerator
{
    public function getType(): string
    {
        return 'Controller';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return app_path("Http/Controllers/{$definition->name}Controller.php");
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->processStub($definition);
    }

    protected function getStubName(): string
    {
        return 'controller';
    }

    /**
     * @return array<string, string>
     */
    protected function getReplacements(EntityDefinition $definition): array
    {
        $softDeleteMethods = '';
        if ($definition->hasSoftDeletes()) {
            $softDeleteMethods = <<<PHP

    /**
     * Restore the specified soft-deleted resource.
     */
    public function restore(int \$id)
    {
        \$this->service->restore(\$id);
        return response()->json(['message' => '{$definition->name} restored successfully.']);
    }

    /**
     * Permanently delete the specified resource.
     */
    public function forceDelete(int \$id)
    {
        \$this->service->forceDelete(\$id);
        return response(null, 204);
    }
PHP;
        }

        return [
            'modelName' => $definition->name,
            'modelNameLower' => $definition->getNameLower(),
            'pluralName' => $definition->getPluralName(),
            'softDeleteMethods' => $softDeleteMethods,
        ];
    }
}
