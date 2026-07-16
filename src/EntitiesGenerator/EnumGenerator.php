<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\EntitiesGenerator;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
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

    public function generate(EntityDefinition $definition): bool
    {
        try {
            foreach ($definition->fields as $field) {
                if (! $field->isEnum()) {
                    continue;
                }

                $path = app_path("Enums/{$field->getEnumClass()}.php");
                $this->ensureDirectoryExists($path);

                if (File::put($path, $this->generateEnumClass($field)) === false) {
                    throw CodeGeneratorException::fileCreationFailed($path);
                }
            }

            return true;
        } catch (\Exception $e) {
            throw CodeGeneratorException::generationFailed($this->getType(), $e->getMessage());
        }
    }

    private function generateEnumClass(FieldDefinition $field): string
    {
        $cases = implode("\n    ", array_map(
            fn (string $v) => 'case '.Str::studly(str_replace('-', '_', $v))." = '{$v}';",
            $field->getEnumValues()
        ));

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Enums;

enum {$field->getEnumClass()}: string
{
    {$cases}
}

PHP;
    }
}
