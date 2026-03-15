<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;

class RequestGenerator extends AbstractGenerator
{
    public function getType(): string
    {
        return 'Request';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return app_path("Http/Requests/{$definition->name}Request.php");
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return $this->processStub($definition);
    }

    protected function getStubName(): string
    {
        return 'request';
    }

    protected function getReplacements(EntityDefinition $definition): array
    {
        return [
            'modelName' => $definition->name,
            'rules' => $this->generateRules($definition),
        ];
    }

    private function generateRules(EntityDefinition $definition): string
    {
        $rules = $definition->fields->map(function (FieldDefinition $field) {
            $rule = $field->getValidationRule();
            return "            '{$field->name}' => '{$rule}',";
        })->toArray();

        return implode("\n", $rules);
    }
}
