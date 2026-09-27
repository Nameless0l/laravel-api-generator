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
        $stubName = $definition->usesQueryBuilder() ? 'controller.query-builder' : 'controller';

        return $this->stubLoader->load($stubName, $this->getReplacements($definition));
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
        $model = $definition->name;
        $parameter = $definition->getRouteParameter();
        $softDeleteMethods = '';
        if ($definition->hasSoftDeletes()) {
            $softDeleteMethods = <<<PHP


    /**
     * Restore the specified soft-deleted resource.
     */
    public function restore({$model} \${$parameter})
    {
        Gate::authorize('restore', \${$parameter});

        return new {$model}Resource(\$this->service->restore(\${$parameter}));
    }

    /**
     * Permanently delete the specified resource.
     */
    public function forceDelete({$model} \${$parameter})
    {
        Gate::authorize('forceDelete', \${$parameter});

        \$this->service->forceDelete(\${$parameter});

        return response()->noContent();
    }
PHP;
        }

        return [
            'modelName' => $model,
            'modelNameLower' => $definition->getNameLower(),
            'pluralName' => $definition->getPluralName(),
            'routeParameter' => $parameter,
            'softDeleteMethods' => $softDeleteMethods,
        ];
    }
}
