<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\Services\GenerationPlanner;
use nameless\CodeGenerator\Support\SchemaParser;
use nameless\CodeGenerator\Support\WorkspaceFactory;
use nameless\CodeGenerator\ValueObjects\FileChange;
use nameless\CodeGenerator\ValueObjects\GenerationRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class GenerationPlannerTest extends GeneratorTestCase
{
    /** @var array<string, string|null> absolute path => original content, null when absent */
    private array $originals = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->instance(WorkspaceFactory::class, new WorkspaceFactory(fn (): int => 1767225600));
    }

    protected function tearDown(): void
    {
        foreach ($this->originals as $path => $content) {
            $content === null ? File::delete($path) : file_put_contents($path, $content);
        }

        parent::tearDown();
    }

    /**
     * @return array<string, array{array<string, mixed>, bool, bool}>
     */
    public static function requests(): array
    {
        return [
            'unique slug and custom key' => [['entities' => ['Product' => ['fields' => ['code' => 'string primary', 'slug' => 'string unique', 'title' => 'string']]]], false, false],
            'enum and soft deletes' => [['entities' => ['Article' => ['soft_deletes' => true, 'fields' => ['title' => 'string', 'status' => 'enum(draft,published)']]]], false, false],
            'relations with a pivot' => [['entities' => [
                'Author' => ['fields' => ['name' => 'string']],
                'Book' => ['fields' => ['title' => 'string'], 'relations' => ['author' => 'belongsTo Author', 'genres' => 'belongsToMany Genre']],
                'Genre' => ['fields' => ['name' => 'string']],
            ]], false, false],
            'auth and postman' => [['entities' => ['Note' => ['fields' => ['body' => 'text']]]], true, true],
        ];
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function request(array $schema, bool $auth, bool $postman): GenerationRequest
    {
        return new GenerationRequest(app(SchemaParser::class)->parseArray($schema), $auth, $postman);
    }

    /**
     * @return array<string, string>
     */
    private function fingerprint(): array
    {
        $hashes = [];

        foreach (['app', 'database', 'routes', 'tests', 'bootstrap'] as $directory) {
            if (is_dir(base_path($directory))) {
                foreach (File::allFiles(base_path($directory)) as $file) {
                    $hashes[$file->getPathname()] = (string) md5_file($file->getPathname());
                }
            }
        }

        $postman = base_path('postman_collection.json');
        $hashes[$postman] = is_file($postman) ? (string) md5_file($postman) : 'absent';

        return $hashes;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    #[Test]
    #[DataProvider('requests')]
    public function planning_writes_nothing(array $schema, bool $auth, bool $postman): void
    {
        $before = $this->fingerprint();

        $changes = app(GenerationPlanner::class)->plan($this->request($schema, $auth, $postman))->changes();

        $this->assertNotEmpty($changes);
        $this->assertSame($before, $this->fingerprint());
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    #[Test]
    #[DataProvider('requests')]
    public function applying_writes_exactly_what_a_dry_run_announced(array $schema, bool $auth, bool $postman): void
    {
        $planner = app(GenerationPlanner::class);
        $announced = $planner->plan($this->request($schema, $auth, $postman))->changes();
        $plan = $planner->plan($this->request($schema, $auth, $postman));
        $changes = $plan->changes();

        foreach ($changes as $change) {
            $path = base_path($change->path);
            $this->originals[$path] = is_file($path) ? (string) file_get_contents($path) : null;
        }

        $plan->apply();

        $this->assertEquals($announced, $changes);
        foreach ($changes as $change) {
            $this->assertSame($change->content, file_get_contents(base_path($change->path)), $change->path);
        }
    }

    #[Test]
    public function a_plan_lists_every_kind_of_file_it_touches(): void
    {
        $plan = app(GenerationPlanner::class)->plan($this->request(self::requests()['auth and postman'][0], true, true));

        $kinds = array_unique(array_map(fn (FileChange $change) => $change->kind, $plan->changes()));

        foreach (['Model', 'Migration', 'Routes', 'DatabaseSeeder', 'Auth', 'Postman'] as $kind) {
            $this->assertContains($kind, $kinds);
        }
    }
}
