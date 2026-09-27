<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\RelationshipDefinition;

class FeatureTestGenerator extends AbstractGenerator
{
    public function getType(): string
    {
        return 'FeatureTest';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return base_path("tests/Feature/{$definition->name}ControllerTest.php");
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        $replacements = $this->getReplacements($definition);

        if (! $definition->usesPest()) {
            return $this->stubLoader->load('test.feature', $replacements);
        }

        // Pest closures sit one level shallower than PHPUnit methods
        foreach (['requestFields', 'relatedFkFields', 'updateRelatedFkFields', 'createRelatedModels'] as $key) {
            $replacements[$key] = (string) preg_replace('/^ {4}/m', '', $replacements[$key]);
        }

        return $this->stubLoader->load('test.feature.pest', $replacements);
    }

    protected function getStubName(): string
    {
        return 'test.feature';
    }

    /**
     * @return array<string, string>
     */
    protected function getReplacements(EntityDefinition $definition): array
    {
        $pk = $definition->getPrimaryKeyName();
        $deleteAssertion = $definition->hasSoftDeletes()
            ? "\$this->assertSoftDeleted('{$definition->getTableName()}', ['{$pk}' => \${$definition->getNameLower()}->getKey()]);"
            : "\$this->assertDatabaseMissing('{$definition->getTableName()}', ['{$pk}' => \${$definition->getNameLower()}->getKey()]);";

        $belongsToRels = $definition->relationships
            ->filter(fn (RelationshipDefinition $rel) => $rel->requiresForeignKey());

        $hasAuth = $definition->hasAuth();
        $lower = $definition->getNameLower();

        $showIdAssertion = $definition->usesJsonApi()
            ? "->assertJsonPath('data.id', (string) \${$lower}->getKey())"
            : "->assertJsonFragment(['{$pk}' => \${$lower}->getKey()])";

        $patched = $definition->fields->first(fn (FieldDefinition $field) => ! $field->isPrimary());
        $indent = $definition->usesPest() ? '    ' : '        ';
        $firstByKey = "{$definition->name}::query()->orderBy('{$pk}')->value('{$pk}')";

        return [
            'modelName' => $definition->name,
            'modelNameLower' => $definition->getNameLower(),
            'pluralName' => $definition->getPluralName(),
            'tableName' => $definition->getTableName(),
            'pkFieldQuoted' => "'{$pk}'",
            'showIdAssertion' => $showIdAssertion,
            'requestFields' => $this->generateRequestFields($definition),
            'deleteAssertion' => $deleteAssertion,
            'relatedImports' => $this->generateRelatedImports($belongsToRels, $definition->name),
            'createRelatedModels' => $this->generateCreateRelatedModels($belongsToRels),
            'relatedFkFields' => $this->generateRelatedFkFields($belongsToRels),
            'updateRelatedFkFields' => $this->generateUpdateRelatedFkFields($definition, $belongsToRels),
            'assertFields' => $this->databaseAssertion($definition, $belongsToRels, update: false),
            'updateAssertFields' => $this->databaseAssertion($definition, $belongsToRels, update: true),
            'userImport' => $hasAuth ? "\nuse App\\Models\\User;" : '',
            'userSetup' => $hasAuth ? $this->generateUserSetup($definition->usesPest()) : '',
            'actingAs' => $hasAuth ? '$this->actingAs($this->user)->' : '',
            'patchFields' => $patched === null ? '' : "'{$patched->name}' => {$this->sampleValue($patched)}",
            // JSON columns may store the payload reformatted, so the value is not looked up.
            'patchAssertion' => $patched === null || $patched->type === 'json'
                ? ''
                : "\n{$indent}\$this->assertDatabaseHas('{$definition->getTableName()}', ['{$pk}' => \${$lower}->getKey(), '{$patched->name}' => {$this->sampleValue($patched)}]);",
            'patchedColumns' => implode(', ', array_map(fn (string $column) => "'{$column}'", array_filter([$patched?->name, 'updated_at']))),
            'softDeleteTests' => $definition->hasSoftDeletes() ? $this->generateSoftDeleteTests($definition) : '',
            'filterField' => collect($definition->getFilterableColumns())->first(fn (string $column) => $column !== $pk) ?? $pk,
            'primaryKey' => $pk,
            'sortAssertion' => $definition->usesJsonApi()
                ? "->assertJsonPath('data.0.id', (string) {$firstByKey})"
                : "->assertJsonPath('data.0.{$pk}', {$firstByKey})",
        ];
    }

    private function sampleValue(FieldDefinition $field): string
    {
        if ($field->isEnum()) {
            return "'".($field->getEnumValues()[0] ?? 'test')."'";
        }

        return match ($field->type) {
            'string' => "'test_{$field->name}'",
            'text' => "'Test text content'",
            'integer', 'int', 'bigint' => '1',
            'boolean', 'bool' => 'true',
            'float', 'decimal' => '10.50',
            'json' => "['key' => 'value']",
            'date', 'datetime', 'timestamp' => "'2025-01-01 00:00:00'",
            'time' => "'10:30:00'",
            'uuid', 'UUID' => "'550e8400-e29b-41d4-a716-446655440000'",
            default => "'test'",
        };
    }

    private function generateSoftDeleteTests(EntityDefinition $definition): string
    {
        $template = $definition->usesPest() ? <<<'PEST'


it('restores a {lower}', function () {
    ${lower} = {model}::factory()->create();
    ${lower}->delete();

    $response = {actingAs}$this->postJson("/api/{plural}/{${lower}->getKey()}/restore");

    $response->assertStatus(200);
    $this->assertNotSoftDeleted('{table}', ['{pk}' => ${lower}->getKey()]);
});

it('force deletes a {lower}', function () {
    ${lower} = {model}::factory()->create();
    ${lower}->delete();

    $response = {actingAs}$this->deleteJson("/api/{plural}/{${lower}->getKey()}/force-delete");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('{table}', ['{pk}' => ${lower}->getKey()]);
});
PEST : <<<'PHPUNIT'


    public function test_can_restore_{lower}(): void
    {
        ${lower} = {model}::factory()->create();
        ${lower}->delete();

        $response = {actingAs}$this->postJson("/api/{plural}/{${lower}->getKey()}/restore");

        $response->assertStatus(200);
        $this->assertNotSoftDeleted('{table}', ['{pk}' => ${lower}->getKey()]);
    }

    public function test_can_force_delete_{lower}(): void
    {
        ${lower} = {model}::factory()->create();
        ${lower}->delete();

        $response = {actingAs}$this->deleteJson("/api/{plural}/{${lower}->getKey()}/force-delete");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('{table}', ['{pk}' => ${lower}->getKey()]);
    }
PHPUNIT;

        return strtr($template, [
            '{lower}' => $definition->getNameLower(),
            '{model}' => $definition->name,
            '{plural}' => $definition->getPluralName(),
            '{table}' => $definition->getTableName(),
            '{pk}' => $definition->getPrimaryKeyName(),
            '{actingAs}' => $definition->hasAuth() ? '$this->actingAs($this->user)->' : '',
        ]);
    }

    private function generateUserSetup(bool $pest): string
    {
        if ($pest) {
            return "\nbeforeEach(function () {\n".
                "    \$this->user = User::factory()->create();\n".
                "});\n";
        }

        return "\n    private User \$user;\n\n".
            "    protected function setUp(): void\n".
            "    {\n".
            "        parent::setUp();\n".
            "        \$this->user = User::factory()->create();\n".
            "    }\n";
    }

    private function generateRequestFields(EntityDefinition $definition): string
    {
        return $definition->fields
            ->map(fn (FieldDefinition $field) => "            '{$field->name}' => {$this->sampleValue($field)},")
            ->implode("\n");
    }

    /**
     * @param  Collection<int, RelationshipDefinition>  $belongsToRels
     */
    private function generateRelatedImports($belongsToRels, string $selfModel): string
    {
        if ($belongsToRels->isEmpty()) {
            return '';
        }

        // Self-referential relations must not re-import the entity's own
        // model: the stub already imports it, and a duplicate use is fatal.
        return $belongsToRels
            ->filter(fn (RelationshipDefinition $rel) => $rel->relatedModel !== $selfModel)
            ->map(fn (RelationshipDefinition $rel) => "use App\\Models\\{$rel->relatedModel};")
            ->unique()
            ->map(fn (string $import) => "\n".$import)
            ->implode('');
    }

    /**
     * @param  Collection<int, RelationshipDefinition>  $belongsToRels
     */
    private function generateCreateRelatedModels($belongsToRels): string
    {
        if ($belongsToRels->isEmpty()) {
            return '';
        }

        return $belongsToRels
            ->map(function (RelationshipDefinition $rel) {
                $varName = Str::camel($rel->relatedModel);

                return "        \${$varName} = {$rel->relatedModel}::factory()->create();";
            })
            ->implode("\n")."\n\n";
    }

    /**
     * @param  Collection<int, RelationshipDefinition>  $belongsToRels
     */
    private function generateRelatedFkFields($belongsToRels): string
    {
        if ($belongsToRels->isEmpty()) {
            return '';
        }

        return $belongsToRels
            ->map(function (RelationshipDefinition $rel) {
                $varName = Str::camel($rel->relatedModel);

                return "            '{$rel->getForeignKeyName()}' => \${$varName}->getKey(),";
            })
            ->implode("\n")."\n";
    }

    /**
     * @param  Collection<int, RelationshipDefinition>  $belongsToRels
     */
    private function generateUpdateRelatedFkFields(EntityDefinition $definition, $belongsToRels): string
    {
        if ($belongsToRels->isEmpty()) {
            return '';
        }

        $modelVar = $definition->getNameLower();

        return $belongsToRels
            ->map(fn (RelationshipDefinition $rel) => "            '{$rel->getForeignKeyName()}' => \${$modelVar}->{$rel->getForeignKeyName()},")
            ->implode("\n")."\n";
    }

    /**
     * JSON columns may store the payload reformatted, so the asserted column is the first other one.
     *
     * @param  Collection<int, RelationshipDefinition>  $belongsToRels
     */
    private function databaseAssertion(EntityDefinition $definition, Collection $belongsToRels, bool $update): string
    {
        $indent = $definition->usesPest() ? '    ' : '        ';
        $field = $definition->fields->first(fn (FieldDefinition $field) => $field->type !== 'json');
        $lines = $field === null ? [] : ["'{$field->name}' => {$this->sampleValue($field)},"];

        foreach ($belongsToRels as $rel) {
            $lines[] = $update
                ? "'{$rel->getForeignKeyName()}' => \${$definition->getNameLower()}->{$rel->getForeignKeyName()},"
                : "'{$rel->getForeignKeyName()}' => \$".Str::camel($rel->relatedModel).'->getKey(),';
        }

        if ($lines === []) {
            return "\$this->assertDatabaseCount('{$definition->getTableName()}', 1);";
        }

        return "\$this->assertDatabaseHas('{$definition->getTableName()}', [\n"
            .implode("\n", array_map(fn (string $line) => "{$indent}    {$line}", $lines))
            ."\n{$indent}]);";
    }
}
