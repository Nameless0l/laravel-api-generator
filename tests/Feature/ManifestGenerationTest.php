<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\Services\GenerationPlanner;
use nameless\CodeGenerator\Support\FieldParser;
use nameless\CodeGenerator\Support\Manifest;
use nameless\CodeGenerator\Support\WorkspaceFactory;
use nameless\CodeGenerator\ValueObjects\EntityDefinition;
use nameless\CodeGenerator\ValueObjects\FieldDefinition;
use nameless\CodeGenerator\ValueObjects\FileChange;
use nameless\CodeGenerator\ValueObjects\GenerationPlan;
use nameless\CodeGenerator\ValueObjects\GenerationRequest;
use PHPUnit\Framework\Attributes\Test;

class ManifestGenerationTest extends GeneratorTestCase
{
    private const MODEL = 'app/Models/Ticket.php';

    protected array $generatedEntities = ['Ticket'];

    protected array $generatedTables = ['tickets'];

    private string $routes = '';

    private string $seeder = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->instance(WorkspaceFactory::class, new WorkspaceFactory(fn (): int => 1767225600));
        $this->routes = (string) file_get_contents(base_path('routes/api.php'));
        $this->seeder = (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'));
    }

    protected function tearDown(): void
    {
        file_put_contents(base_path('routes/api.php'), $this->routes);
        file_put_contents(database_path('seeders/DatabaseSeeder.php'), $this->seeder);
        foreach ((array) glob(database_path('migrations/*_to_tickets_table.php')) as $migration) {
            @unlink((string) $migration);
        }

        parent::tearDown();
    }

    private function generate(string $fields = 'title:string', bool $force = false): GenerationPlan
    {
        $ticket = new EntityDefinition(
            'Ticket',
            collect(FieldParser::parseFieldsString($fields))->map(fn (string $type, string $name) => new FieldDefinition($name, $type))->values(),
            collect()
        );
        $plan = app(GenerationPlanner::class)->plan(new GenerationRequest(collect([$ticket]), force: $force));
        $plan->apply();

        return $plan;
    }

    private function addField(string $name): void
    {
        app(GenerationPlanner::class)->planFieldAddition('Ticket', collect([new FieldDefinition($name, 'text')]))->apply();
    }

    private function editByHand(string $path): void
    {
        file_put_contents(base_path($path), file_get_contents(base_path($path))."\n// edited by hand\n");
    }

    private function manifest(): Manifest
    {
        return Manifest::load(base_path());
    }

    private function pristine(string $path): ?bool
    {
        return $this->manifest()->isPristine($path, (string) file_get_contents(base_path($path)));
    }

    /**
     * @return array<string, FileChange>
     */
    private function byPath(GenerationPlan $plan): array
    {
        return collect($plan->changes())->keyBy('path')->all();
    }

    #[Test]
    public function the_first_generation_records_every_generated_file(): void
    {
        $this->generate();

        $this->assertTrue($this->manifest()->exists());
        $this->assertContains(self::MODEL, $this->manifest()->filesOf('Ticket'));
        $this->assertTrue($this->pristine(self::MODEL));
        $this->assertNull($this->pristine('routes/api.php'));
    }

    #[Test]
    public function a_file_edited_by_hand_is_kept_on_the_next_run(): void
    {
        $this->generate();
        $this->editByHand(self::MODEL);

        $plan = $this->generate('title:string,priority:integer');

        $this->assertStringContainsString('// edited by hand', (string) file_get_contents(base_path(self::MODEL)));
        $this->assertTrue($this->byPath($plan)[self::MODEL]->kept);
        $this->assertStringContainsString('priority', (string) file_get_contents(app_path('Http/Requests/TicketRequest.php')));
        $this->assertContains('modified_file_kept', array_column($plan->warnings, 'code'));
        $this->assertFalse($this->pristine(self::MODEL));
    }

    #[Test]
    public function force_overwrites_an_edited_file_and_tracks_it_again(): void
    {
        $this->generate();
        $this->editByHand(self::MODEL);

        $this->generate('title:string,priority:integer', force: true);

        $this->assertStringNotContainsString('// edited by hand', (string) file_get_contents(base_path(self::MODEL)));
        $this->assertTrue($this->pristine(self::MODEL));
    }

    #[Test]
    public function an_unknown_file_of_a_tracked_entity_is_kept(): void
    {
        $this->generate();
        $manifest = $this->manifest();
        $manifest->forget(self::MODEL);
        $manifest->save();

        $plan = $this->generate('title:string,priority:integer');

        $this->assertTrue($this->byPath($plan)[self::MODEL]->kept);
    }

    #[Test]
    public function an_entity_generated_before_the_manifest_is_regenerated_as_before_then_tracked(): void
    {
        $this->generate();
        File::deleteDirectory(base_path('.api-generator'));
        $this->editByHand(self::MODEL);

        $plan = $this->generate('title:string,priority:integer');

        $this->assertFalse($this->byPath($plan)[self::MODEL]->kept);
        $this->assertStringNotContainsString('// edited by hand', (string) file_get_contents(base_path(self::MODEL)));
        $this->assertTrue($this->pristine(self::MODEL));
    }

    #[Test]
    public function adding_fields_keeps_pristine_files_pristine_and_edited_files_edited(): void
    {
        $this->generate();
        $this->editByHand('app/Http/Requests/TicketRequest.php');

        $this->addField('excerpt');

        $this->assertTrue($this->pristine(self::MODEL));
        $this->assertStringContainsString('excerpt', (string) file_get_contents(app_path('Http/Requests/TicketRequest.php')));
        $this->assertFalse($this->pristine('app/Http/Requests/TicketRequest.php'));
    }
}
