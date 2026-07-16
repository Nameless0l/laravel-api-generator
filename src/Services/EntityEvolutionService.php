<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use nameless\CodeGenerator\EntitiesGenerator\MigrationGenerator;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\StubLoader;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;

/**
 * Adds fields to an already generated entity without regenerating it,
 * so manual changes in the existing files survive: incremental migration,
 * then in-place patches of fillable/casts/PHPDoc, validation rules,
 * factory definition and resource fields.
 */
class EntityEvolutionService
{
    /** @var array<int, string> */
    private array $changed = [];

    /** @var array<int, string> */
    private array $warnings = [];

    public function __construct(
        private readonly StubLoader $stubLoader
    ) {}

    /**
     * @param  Collection<int, FieldDefinition>  $fields
     * @return array{changed: array<int, string>, warnings: array<int, string>}
     */
    public function addFields(string $name, Collection $fields): array
    {
        $this->changed = [];
        $this->warnings = [];

        $modelPath = app_path("Models/{$name}.php");
        if (! File::exists($modelPath)) {
            throw CodeGeneratorException::fileNotFound($modelPath);
        }

        $table = Str::plural(Str::snake($name));
        $existing = $this->existingFillable($modelPath);
        $fields = $fields->reject(fn (FieldDefinition $f) => in_array($f->name, $existing, true))->values();

        if ($fields->isEmpty()) {
            $this->warnings[] = 'All requested fields already exist on the model; nothing to do.';

            return ['changed' => [], 'warnings' => $this->warnings];
        }

        $this->createMigration($table, $fields);
        $this->generateEnums($fields);
        $this->patchModel($modelPath, $fields);
        $this->patchAfterReturnArray(
            app_path("Http/Requests/{$name}Request.php"),
            'rules',
            $fields->map(fn (FieldDefinition $f) => $this->ruleLine($f))
        );
        $this->patchAfterReturnArray(
            database_path("factories/{$name}Factory.php"),
            'definition',
            $fields->map(fn (FieldDefinition $f) => "            '{$f->name}' => {$f->getFakeValue()},")
        );
        $this->patchAfterReturnArray(
            app_path("Http/Resources/{$name}Resource.php"),
            'toArray',
            $fields->map(fn (FieldDefinition $f) => "            '{$f->name}' => \$this->{$f->name},")
        );

        $this->warnings[] = "app/DTO/{$name}DTO.php was not patched (constructor promotion): add the new properties manually.";
        $this->warnings[] = 'Generated tests were not patched: new required fields may break the create/update tests.';

        return ['changed' => $this->changed, 'warnings' => $this->warnings];
    }

    /**
     * @return array<int, string>
     */
    private function existingFillable(string $modelPath): array
    {
        $content = File::get($modelPath);
        if (! preg_match('/protected \$fillable = \[([^\]]*)\]/s', $content, $matches)) {
            return [];
        }

        preg_match_all("/'([^']+)'/", $matches[1], $names);

        return $names[1];
    }

    /**
     * @param  Collection<int, FieldDefinition>  $fields
     */
    private function createMigration(string $table, Collection $fields): void
    {
        $columns = $fields
            ->map(fn (FieldDefinition $f) => '            '.MigrationGenerator::columnDefinition($f))
            ->implode("\n");
        $dropColumns = $fields
            ->map(fn (FieldDefinition $f) => "'{$f->name}'")
            ->implode(', ');

        $content = $this->stubLoader->load('migration.add-fields', [
            'tableName' => $table,
            'columns' => $columns,
            'dropColumns' => $dropColumns,
        ]);

        $slug = $fields->count() === 1
            ? $fields->first()?->name
            : 'fields';
        $path = database_path('migrations/'.MigrationGenerator::nextTimestamp()."_add_{$slug}_to_{$table}_table.php");

        File::put($path, $content);
        $this->changed[] = $path;
    }

    /**
     * @param  Collection<int, FieldDefinition>  $fields
     */
    private function generateEnums(Collection $fields): void
    {
        foreach ($fields as $field) {
            if (! $field->isEnum()) {
                continue;
            }

            $cases = implode("\n    ", array_map(
                fn (string $v) => 'case '.Str::studly(str_replace('-', '_', $v))." = '{$v}';",
                $field->getEnumValues()
            ));
            $path = app_path("Enums/{$field->getEnumClass()}.php");

            File::ensureDirectoryExists(dirname($path));
            File::put($path, "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Enums;\n\nenum {$field->getEnumClass()}: string\n{\n    {$cases}\n}\n");
            $this->changed[] = $path;
        }
    }

    /**
     * @param  Collection<int, FieldDefinition>  $fields
     */
    private function patchModel(string $modelPath, Collection $fields): void
    {
        $content = File::get($modelPath);

        $fillableEntries = $fields->map(fn (FieldDefinition $f) => "'{$f->name}'")->implode(', ');
        $patched = preg_replace_callback(
            '/protected \$fillable = \[([^\]]*)\]/s',
            function (array $matches) use ($fillableEntries) {
                $inside = rtrim($matches[1]);
                $separator = trim($inside) === '' ? '' : ', ';

                return 'protected $fillable = ['.$inside.$separator.$fillableEntries.']';
            },
            $content,
            1,
            $count
        );

        if ($patched === null || $count === 0) {
            $this->warnings[] = basename($modelPath).': $fillable not found, model left untouched.';

            return;
        }
        $content = $patched;

        $castEntries = $fields
            ->map(fn (FieldDefinition $f) => $f->getCastType() !== null ? "'{$f->name}' => {$f->getCastType()}" : null)
            ->filter();

        if ($castEntries->isNotEmpty()) {
            $castsBlock = $castEntries->implode(",\n        ");
            if (preg_match('/protected \$casts = \[/', $content)) {
                $content = (string) preg_replace(
                    '/(protected \$casts = \[)/',
                    "$1\n        {$castsBlock},",
                    $content,
                    1
                );
            } else {
                $content = (string) preg_replace(
                    '/(protected \$fillable = \[[^\]]*\];)/s',
                    "$1\n\n    protected \$casts = [\n        {$castsBlock},\n    ];",
                    $content,
                    1
                );
            }
        }

        $phpdocLines = $fields->map(function (FieldDefinition $f) {
            $type = $f->isEnum() ? '\\App\\Enums\\'.$f->getEnumClass() : $f->getPhpType();
            $nullable = $f->nullable ? '|null' : '';

            return " * @property {$type}{$nullable} \${$f->name}";
        })->implode("\n");

        if (preg_match('/\/\*\*\R(?: \* @property[^\r\n]*\R)+/', $content)) {
            $content = (string) preg_replace(
                '/((?: \* @property[^\r\n]*\R)+)( \*\/)/',
                "$1{$phpdocLines}\n$2",
                $content,
                1
            );
        }

        File::put($modelPath, $content);
        $this->changed[] = $modelPath;
    }

    /**
     * @param  Collection<int, string>  $lines
     */
    private function patchAfterReturnArray(string $path, string $method, Collection $lines): void
    {
        if (! File::exists($path)) {
            $this->warnings[] = basename($path).': file not found, skipped.';

            return;
        }

        $content = File::get($path);
        $pattern = '/(function '.$method.'\([^)]*\)(?::\s*array)?\s*\{\s*return \[\R)/';

        $patched = preg_replace($pattern, '$1'.str_replace(['\\', '$'], ['\\\\', '\\$'], $lines->implode("\n"))."\n", $content, 1, $count);

        if ($patched === null || $count === 0) {
            $this->warnings[] = basename($path).": could not locate {$method}() return array, skipped.";

            return;
        }

        File::put($path, $patched);
        $this->changed[] = $path;
    }

    private function ruleLine(FieldDefinition $field): string
    {
        if ($field->isEnum()) {
            $prefix = $field->nullable ? 'sometimes' : 'required';

            return "            '{$field->name}' => ['{$prefix}', \\Illuminate\\Validation\\Rule::enum(\\App\\Enums\\{$field->getEnumClass()}::class)],";
        }

        return "            '{$field->name}' => '{$field->getValidationRule()}',";
    }
}
