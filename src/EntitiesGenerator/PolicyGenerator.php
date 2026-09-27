<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;

class PolicyGenerator extends AbstractGenerator
{
    public function getType(): string
    {
        return 'Policy';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return app_path("Policies/{$definition->name}Policy.php");
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->processStub($definition);
    }

    protected function getStubName(): string
    {
        return 'policy';
    }

    /**
     * @return array<string, string>
     */
    protected function getReplacements(EntityDefinition $definition): array
    {
        // The policy of User already receives a $user, as with php artisan make:policy.
        $isUser = $definition->name === 'User';

        return [
            'modelName' => $definition->name,
            'modelNameLower' => $definition->getNameLower(),
            'modelVariable' => $isUser ? 'model' : $definition->getNameCamel(),
            'userImport' => $isUser ? '' : "\nuse App\\Models\\User;",
        ];
    }
}
