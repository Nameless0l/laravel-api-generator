<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use Illuminate\Support\Str;
use nameless\CodeGenerator\Support\Workspace;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;

class EnumGenerator extends AbstractGenerator
{
    public function getType(): string
    {
        return 'Enum';
    }

    public function getOutputPath(EntityDefinition $definition): string
    {
        return app_path('Enums');
    }

    protected function generateContent(EntityDefinition $definition): string
    {
        return '';
    }

    protected function getStubName(): string
    {
        return '';
    }

    protected function getReplacements(EntityDefinition $definition): array
    {
        return [];
    }

    public function supports(EntityDefinition $definition): bool
    {
        return $definition->fields->contains(fn (FieldDefinition $f) => $f->isEnum());
    }

    public function render(EntityDefinition $definition, Workspace $workspace): void
    {
        foreach ($definition->fields as $field) {
            if ($field->isEnum()) {
                $class = $field->getEnumClass($definition->name);
                $workspace->put(app_path("Enums/{$class}.php"), self::source($class, $field->getEnumValues()), $this->getType(), $definition->name);
            }
        }
    }

    /**
     * @param  array<int, string>  $values
     */
    public static function source(string $class, array $values): string
    {
        $cases = implode("\n", array_map(
            fn (string $value) => '    case '.Str::studly(str_replace('-', '_', $value))." = '{$value}';",
            $values
        ));

        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\Enums;\n\nenum {$class}: string\n{\n{$cases}\n}\n";
    }
}
