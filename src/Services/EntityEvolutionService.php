<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use nameless\CodeGenerator\EntitiesGenerator\EnumGenerator;
use nameless\CodeGenerator\EntitiesGenerator\MigrationGenerator;
use nameless\CodeGenerator\EntitiesGenerator\RequestGenerator;
use nameless\CodeGenerator\Exceptions\CodeGeneratorException;
use nameless\CodeGenerator\Support\PhpImports;
use nameless\CodeGenerator\Support\StubLoader;
use nameless\CodeGenerator\Support\Workspace;
use nameless\CodeGenerator\Support\WorkspaceFactory;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;

/**
 * Adds fields to an already generated entity without regenerating it,
 * so manual changes in the existing files survive: incremental migration,
 * then in-place patches of fillable/casts/PHPDoc, validation rules,
 * factory definition and resource fields.
 */
class EntityEvolutionService
{
    private const FILLABLE = '/(protected \$fillable = \[|#\[Fillable\(\[)([^\]]*)\]/s';

    /** @var array<int, string> */
    private array $changed = [];

    /** @var array<int, string> */
    private array $warnings = [];

    public function __construct(
        private readonly StubLoader $stubLoader,
        private readonly WorkspaceFactory $workspaces
    ) {}

    /**
     * Writes immediately unless a workspace is given.
     *
     * @param  Collection<int, FieldDefinition>  $fields
     * @return array{changed: array<int, string>, warnings: array<int, string>}
     */
    public function addFields(string $name, Collection $fields, ?Workspace $workspace = null): array
    {
        if (! preg_match(EntityDefinition::NAME_PATTERN, $name)) {
            throw CodeGeneratorException::invalidEntityName($name);
        }

        $this->changed = [];
        $this->warnings = [];
        $target = $workspace ?? $this->workspaces->make();

        $modelPath = app_path("Models/{$name}.php");
        if (! $target->exists($modelPath)) {
            throw CodeGeneratorException::fileNotFound($modelPath);
        }

        $table = Str::plural(Str::snake($name));
        $existing = $this->existingFillable($target->get($modelPath));
        $fields = $fields->reject(fn (FieldDefinition $f) => in_array($f->name, $existing, true))->values();

        if ($fields->isEmpty()) {
            $this->warnings[] = 'All requested fields already exist on the model; nothing to do.';

            return ['changed' => [], 'warnings' => $this->warnings];
        }

        $this->createMigration($target, $name, $table, $fields);
        $this->generateEnums($target, $name, $fields);
        $this->patchModel($target, $name, $modelPath, $fields);
        $this->patchRequests($target, $name, $fields);
        $this->patchAfterReturnArray(
            $target,
            $name,
            'Factory',
            database_path("factories/{$name}Factory.php"),
            'definition',
            $fields->map(fn (FieldDefinition $f) => "            '{$f->name}' => {$f->getFakeValue($name)},"),
            $this->enumClasses($name, $fields)
        );
        $this->patchAfterReturnArray(
            $target,
            $name,
            'Resource',
            app_path("Http/Resources/{$name}Resource.php"),
            'toArray',
            $fields->map(fn (FieldDefinition $f) => "            '{$f->name}' => \$this->{$f->name},")
        );

        $this->warnings[] = "app/DTO/{$name}DTO.php was not patched (constructor promotion): add the new properties manually.";
        $this->warnings[] = 'Generated tests were not patched: new required fields may break the create/update tests.';

        if ($workspace === null) {
            $target->commit();
        }

        return ['changed' => $this->changed, 'warnings' => $this->warnings];
    }

    /**
     * @return array<int, string>
     */
    private function existingFillable(string $modelContent): array
    {
        if (! preg_match(self::FILLABLE, $modelContent, $matches)) {
            return [];
        }

        preg_match_all("/'([^']+)'/", $matches[2], $names);

        return $names[1];
    }

    /**
     * @param  Collection<int, FieldDefinition>  $fields
     */
    private function createMigration(Workspace $workspace, string $entity, string $table, Collection $fields): void
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
        $path = database_path('migrations/'.$workspace->migrationTimestamp()."_add_{$slug}_to_{$table}_table.php");

        $workspace->put($path, $content, 'Migration', $entity);
        $this->changed[] = $path;
    }

    /**
     * @param  Collection<int, FieldDefinition>  $fields
     */
    private function generateEnums(Workspace $workspace, string $entity, Collection $fields): void
    {
        foreach ($fields as $field) {
            if (! $field->isEnum()) {
                continue;
            }

            $class = $field->getEnumClass($entity);
            $path = app_path("Enums/{$class}.php");

            $workspace->put($path, EnumGenerator::source($class, $field->getEnumValues()), 'Enum', $entity);
            $this->changed[] = $path;
        }
    }

    /**
     * @param  Collection<int, FieldDefinition>  $fields
     * @return array<int, string>
     */
    private function enumClasses(string $entity, Collection $fields): array
    {
        return $fields
            ->filter(fn (FieldDefinition $f) => $f->isEnum())
            ->map(fn (FieldDefinition $f) => 'App\\Enums\\'.$f->getEnumClass($entity))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, FieldDefinition>  $fields
     */
    private function patchModel(Workspace $workspace, string $entity, string $modelPath, Collection $fields): void
    {
        $content = $workspace->get($modelPath);

        $entries = $fields->map(fn (FieldDefinition $f) => "'{$f->name}'")->implode(', ');
        $patched = preg_replace_callback(
            self::FILLABLE,
            fn (array $m) => $m[1].rtrim($m[2]).(trim($m[2]) === '' ? '' : ', ').$entries.']',
            $content,
            1,
            $count
        );

        if ($patched === null || $count === 0) {
            $this->warnings[] = basename($modelPath).': neither $fillable nor #[Fillable] found, model left untouched.';

            return;
        }

        $casts = $fields
            ->filter(fn (FieldDefinition $f) => $f->getCastType($entity) !== null)
            ->map(fn (FieldDefinition $f) => "            '{$f->name}' => {$f->getCastType($entity)},");
        $content = $casts->isEmpty() ? $patched : $this->patchCasts($patched, $casts->implode("\n"));

        $phpdocLines = $fields->map(function (FieldDefinition $f) use ($entity) {
            $type = match (true) {
                $f->isEnum() => $f->getEnumClass($entity),
                in_array($f->type, ['date', 'datetime', 'timestamp'], true) => 'Carbon',
                default => $f->getPhpType(),
            };
            $nullable = $f->nullable ? '|null' : '';

            return " * @property {$type}{$nullable} \${$f->name}";
        })->implode("\n");

        if (preg_match('/\/\*\*\R(?: \* @property[^\r\n]*\R)+/', $content)) {
            $content = (string) preg_replace_callback(
                '/((?: \* @property[^\r\n]*\R)+)( \*\/)/',
                fn (array $m) => $m[1].$phpdocLines."\n".$m[2],
                $content,
                1
            );
        }

        $dates = $fields->contains(fn (FieldDefinition $f) => in_array($f->type, ['date', 'datetime', 'timestamp'], true));
        $imports = [...$this->enumClasses($entity, $fields), ...($dates ? ['Illuminate\\Support\\Carbon'] : [])];

        $workspace->put($modelPath, PhpImports::add($content, $imports), 'Model', $entity);
        $this->changed[] = $modelPath;
    }

    /**
     * Adds to casts(), to a $casts property written before 4.0, or creates casts().
     */
    private function patchCasts(string $content, string $lines): string
    {
        if (preg_match('/protected function casts\(\): array\s*\{\s*return \[\R/', $content)) {
            return (string) preg_replace_callback(
                '/(protected function casts\(\): array\s*\{\s*return \[\R)/',
                fn (array $m) => $m[1].$lines."\n",
                $content,
                1
            );
        }

        if (preg_match('/protected \$casts = \[\R/', $content)) {
            return (string) preg_replace_callback(
                '/(protected \$casts = \[\R)/',
                fn (array $m) => $m[1].preg_replace('/^    /m', '', $lines)."\n",
                $content,
                1
            );
        }

        $method = "    protected function casts(): array\n    {\n        return [\n{$lines}\n        ];\n    }";

        if (preg_match('/^    public function /m', $content, $m, PREG_OFFSET_CAPTURE)) {
            return substr_replace($content, $method."\n\n", (int) $m[0][1], 0);
        }

        $close = (int) strrpos($content, '}');

        return rtrim(substr($content, 0, $close))."\n\n".$method."\n}\n";
    }

    /**
     * @param  Collection<int, string>  $lines
     * @param  array<int, string>  $imports
     */
    private function patchAfterReturnArray(Workspace $workspace, string $entity, string $kind, string $path, string $method, Collection $lines, array $imports = []): void
    {
        if (! $workspace->exists($path)) {
            $this->warnings[] = basename($path).': file not found, skipped.';

            return;
        }

        $content = $workspace->get($path);
        $pattern = '/(function '.$method.'\([^)]*\)(?::\s*array)?\s*\{\s*return \[\R)/';

        $patched = preg_replace($pattern, '$1'.str_replace(['\\', '$'], ['\\\\', '\\$'], $lines->implode("\n"))."\n", $content, 1, $count);

        if ($patched === null || $count === 0) {
            $this->warnings[] = basename($path).": could not locate {$method}() return array, skipped.";

            return;
        }

        $workspace->put($path, PhpImports::add($patched, $imports), $kind, $entity);
        $this->changed[] = $path;
    }

    /**
     * Entities generated before 4.0 have a single request, patched like a store request.
     *
     * @param  Collection<int, FieldDefinition>  $fields
     */
    private function patchRequests(Workspace $workspace, string $entity, Collection $fields): void
    {
        $store = app_path("Http/Requests/Store{$entity}Request.php");
        $legacy = app_path("Http/Requests/{$entity}Request.php");

        if (! $workspace->exists($store) && $workspace->exists($legacy)) {
            $this->patchAfterReturnArray($workspace, $entity, 'Request', $legacy, 'rules', $this->ruleLines($entity, $fields, update: false), RequestGenerator::imports($fields, $entity));

            return;
        }

        $this->patchAfterReturnArray($workspace, $entity, 'Request', $store, 'rules', $this->ruleLines($entity, $fields, update: false), RequestGenerator::imports($fields, $entity));
        $this->patchAfterReturnArray($workspace, $entity, 'Request', app_path("Http/Requests/Update{$entity}Request.php"), 'rules', $this->ruleLines($entity, $fields, update: true), RequestGenerator::imports($fields, $entity));
    }

    /**
     * @param  Collection<int, FieldDefinition>  $fields
     * @return Collection<int, string>
     */
    private function ruleLines(string $entity, Collection $fields, bool $update): Collection
    {
        return $fields->map(fn (FieldDefinition $f) => "            '{$f->name}' => ".RequestGenerator::fieldRule($f, $update, $entity).',');
    }
}
