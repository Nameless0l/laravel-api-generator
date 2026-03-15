<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;

class SeederGenerator extends AbstractGenerator
{
    public function getType(): string
    {
        return 'Seeder';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return database_path("seeders/{$definition->name}Seeder.php");
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->processStub($definition);
    }

    protected function getStubName(): string
    {
        return 'seed';
    }

    protected function getReplacements(EntityDefinition $definition): array
    {
        return [
            'modelName' => $definition->name,
        ];
    }
}
