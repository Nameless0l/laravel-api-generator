<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use nameless\CodeGenerator\Support\StdinReader;
use nameless\CodeGenerator\Support\WorkspaceFactory;
use nameless\CodeGenerator\Tests\AssertsProtocol;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PlanSnapshotTest extends GeneratorTestCase
{
    use AssertsProtocol;

    private const SHARED_KINDS = ['Routes', 'DatabaseSeeder', 'Bootstrap'];

    protected array $generatedEntities = ['Report'];

    protected array $generatedTables = ['reports'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->instance(WorkspaceFactory::class, new WorkspaceFactory(fn (): int => 1767225600));
    }

    /**
     * @return array<string, array{string, array<string, bool>}>
     */
    public static function schemas(): array
    {
        return [
            'unique-slug-custom-key' => ['{"entities":{"Product":{"fields":{"code":"string primary","slug":"string unique","title":"string"}}}}', []],
            'enum-soft-deletes' => ['{"entities":{"Article":{"soft_deletes":true,"fields":{"title":"string","status":"enum(draft,published)"}}}}', []],
            'relations-pivot' => ['{"entities":{"Author":{"fields":{"name":"string"}},"Book":{"fields":{"title":"string"},"relations":{"author":"belongsTo Author","genres":"belongsToMany Genre"}},"Genre":{"fields":{"name":"string"}}}}', []],
            'auth-postman' => ['{"entities":{"Note":{"fields":{"body":"text"}}}}', ['--auth' => true, '--postman' => true]],
        ];
    }

    /**
     * @param  array<string, bool>  $flags
     */
    #[Test]
    #[DataProvider('schemas')]
    public function the_planned_files_match_their_snapshot(string $schema, array $flags): void
    {
        $this->instance(StdinReader::class, new class($schema) extends StdinReader
        {
            public function __construct(private readonly string $schema) {}

            public function read(): string
            {
                return $this->schema;
            }
        });

        $this->assertSnapshot((string) $this->dataName(), $this->plannedFiles(['--schema' => '-'] + $flags));
    }

    #[Test]
    public function adding_fields_matches_its_snapshot(): void
    {
        Artisan::call('make:fullapi', ['name' => 'Report', '--fields' => 'title:string']);

        $this->assertSnapshot('add-fields', $this->plannedFiles(['name' => 'Report', '--add-fields' => 'excerpt:text']));
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<int, array<string, string>>
     */
    private function plannedFiles(array $parameters): array
    {
        $exitCode = Artisan::call('make:fullapi', $parameters + ['--dry-run' => true, '--json' => true]);
        $lines = array_values(array_filter(explode("\n", trim(Artisan::output()))));
        $document = json_decode((string) end($lines), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode, (string) json_encode($document['errors'] ?? []));
        $this->assertMatchesProtocol($document, 'planDocument');

        $files = [];
        foreach ($document['files'] as $file) {
            if (! in_array($file['kind'], self::SHARED_KINDS, true)) {
                $file['content'] = str_replace("\r\n", "\n", $file['content']);
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @param  array<int, array<string, string>>  $files
     */
    private function assertSnapshot(string $name, array $files): void
    {
        $path = dirname(__DIR__).'/Fixtures/plans/'.$name.'.json';

        if (getenv('UPDATE_SNAPSHOTS') === '1' || ! is_file($path)) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            file_put_contents($path, json_encode($files, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
            $this->markTestIncomplete("Snapshot {$name} written: review it, then commit it.");
        }

        $this->assertSame(json_decode((string) file_get_contents($path), true), $files);
    }
}
