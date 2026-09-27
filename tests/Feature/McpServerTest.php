<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Mcp\Server as McpServer;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Mcp\Server\Registrar;
use Laravel\Mcp\Server\Testing\TestResponse;
use nameless\CodeGenerator\Console\Commands\McpCommand;
use nameless\CodeGenerator\Mcp\ApiGeneratorServer;
use nameless\CodeGenerator\Mcp\Prompts\DesignApiPrompt;
use nameless\CodeGenerator\Mcp\Resources\ApiSchemaResource;
use nameless\CodeGenerator\Mcp\Tools\AddFieldsTool;
use nameless\CodeGenerator\Mcp\Tools\GenerateApiTool;
use nameless\CodeGenerator\Mcp\Tools\ListEntitiesTool;
use nameless\CodeGenerator\Mcp\Tools\PlanApiTool;
use nameless\CodeGenerator\Support\WorkspaceFactory;
use PHPUnit\Framework\Attributes\Test;

class McpServerTest extends GeneratorTestCase
{
    private const MODEL = 'app/Models/Gizmo.php';

    private const SCHEMA = ['entities' => ['Gizmo' => ['fields' => ['name' => 'string', 'price' => 'decimal nullable']]]];

    protected array $generatedEntities = ['Gizmo'];

    protected array $generatedTables = ['gizmos'];

    private string $routes = '';

    private string $seeder = '';

    protected function getPackageProviders($app): array
    {
        return class_exists(McpServiceProvider::class)
            ? [...parent::getPackageProviders($app), McpServiceProvider::class]
            : parent::getPackageProviders($app);
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(McpServer::class)) {
            $this->markTestSkipped('laravel/mcp is not installed (it needs Laravel 12.41+).');
        }

        $this->instance(WorkspaceFactory::class, new WorkspaceFactory(fn (): int => 1767225600));
        $this->routes = (string) file_get_contents(base_path('routes/api.php'));
        $this->seeder = (string) file_get_contents(database_path('seeders/DatabaseSeeder.php'));
    }

    protected function tearDown(): void
    {
        if ($this->routes !== '') {
            file_put_contents(base_path('routes/api.php'), $this->routes);
            file_put_contents(database_path('seeders/DatabaseSeeder.php'), $this->seeder);
        }
        foreach ((array) glob(database_path('migrations/*_to_gizmos_table.php')) as $migration) {
            @unlink((string) $migration);
        }

        parent::tearDown();
    }

    #[Test]
    public function the_server_exposes_four_tools_and_the_schema_resource(): void
    {
        ApiGeneratorServer::tools()->assertRegistered([
            ListEntitiesTool::class,
            PlanApiTool::class,
            GenerateApiTool::class,
            AddFieldsTool::class,
        ]);
        ApiGeneratorServer::resources()->assertRegistered(ApiSchemaResource::class);
    }

    #[Test]
    public function annotations_tell_clients_which_tools_only_read(): void
    {
        $this->assertEquals(['readOnlyHint' => true, 'idempotentHint' => true, 'openWorldHint' => false], (new ListEntitiesTool)->annotations());
        $this->assertEquals(['readOnlyHint' => true, 'idempotentHint' => true, 'openWorldHint' => false], (new PlanApiTool)->annotations());
        $this->assertEquals(['destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false], (new GenerateApiTool)->annotations());
        $this->assertEquals(['destructiveHint' => false, 'openWorldHint' => false], (new AddFieldsTool)->annotations());
    }

    #[Test]
    public function the_input_schema_describes_the_api_schema_format(): void
    {
        $input = (new PlanApiTool)->toArray()['inputSchema'] ?? [];

        $this->assertArrayNotHasKey('required', $input);
        $this->assertArrayHasKey('openapi', $input['properties']);
        $this->assertStringContainsString('belongsToMany', $input['properties']['schema']['description']);
        $this->assertStringContainsString('uuid', $input['properties']['schema']['description']);
        $this->assertContains('FeatureTest', $input['properties']['only']['items']['enum']);
    }

    #[Test]
    public function plan_api_lists_the_files_and_writes_nothing(): void
    {
        $plan = $this->structured(ApiGeneratorServer::tool(PlanApiTool::class, ['schema' => self::SCHEMA])->assertOk());

        $this->assertSame(1, $plan['protocol']);
        $this->assertTrue($plan['dryRun']);
        $this->assertSame('create', $this->file($plan, self::MODEL)['action']);
        $this->assertArrayNotHasKey('content', $this->file($plan, self::MODEL));
        $this->assertFileDoesNotExist(base_path(self::MODEL));
    }

    #[Test]
    public function plan_api_returns_the_contents_when_asked(): void
    {
        $plan = $this->structured(ApiGeneratorServer::tool(PlanApiTool::class, [
            'schema' => self::SCHEMA,
            'only' => ['Model'],
            'include_content' => true,
        ])->assertOk());

        $this->assertStringContainsString('class Gizmo extends Model', $this->file($plan, self::MODEL)['content']);
        $this->assertNotContains('app/Http/Controllers/GizmoController.php', array_column($plan['files'], 'path'));
    }

    #[Test]
    public function generate_api_writes_the_files_and_a_second_call_changes_nothing(): void
    {
        $first = $this->structured(ApiGeneratorServer::tool(GenerateApiTool::class, ['schema' => self::SCHEMA])->assertOk());

        $this->assertFalse($first['dryRun']);
        $this->assertSame('create', $this->file($first, self::MODEL)['action']);
        $this->assertArrayNotHasKey('content', $this->file($first, self::MODEL));
        $this->assertFileExists(base_path(self::MODEL));

        $second = $this->structured(ApiGeneratorServer::tool(GenerateApiTool::class, ['schema' => self::SCHEMA])->assertOk());

        $this->assertSame(['unchanged'], array_values(array_unique(array_column($second['files'], 'action'))));
    }

    #[Test]
    public function generate_api_keeps_a_file_edited_by_hand(): void
    {
        ApiGeneratorServer::tool(GenerateApiTool::class, ['schema' => self::SCHEMA])->assertOk();
        file_put_contents(base_path(self::MODEL), file_get_contents(base_path(self::MODEL))."\n// edited by hand\n");

        $schema = self::SCHEMA;
        $schema['entities']['Gizmo']['fields']['stock'] = 'integer';
        $result = $this->structured(ApiGeneratorServer::tool(GenerateApiTool::class, ['schema' => $schema])->assertOk());

        $this->assertTrue($this->file($result, self::MODEL)['kept'] ?? false);
        $this->assertContains('modified_file_kept', array_column($result['warnings'], 'code'));
        $this->assertStringContainsString('// edited by hand', (string) file_get_contents(base_path(self::MODEL)));
        $this->assertStringContainsString('stock', (string) file_get_contents(app_path('Http/Requests/StoreGizmoRequest.php')));
    }

    #[Test]
    public function add_fields_previews_then_patches_an_entity(): void
    {
        ApiGeneratorServer::tool(GenerateApiTool::class, ['schema' => self::SCHEMA])->assertOk();
        $arguments = ['entity' => 'Gizmo', 'fields' => ['summary' => 'text nullable']];

        $preview = $this->structured(ApiGeneratorServer::tool(AddFieldsTool::class, [...$arguments, 'dry_run' => true])->assertOk());

        $this->assertTrue($preview['dryRun']);
        $this->assertContains(self::MODEL, array_column($preview['files'], 'path'));
        $this->assertSame([], glob(database_path('migrations/*_add_summary_to_gizmos_table.php')));

        $result = $this->structured(ApiGeneratorServer::tool(AddFieldsTool::class, $arguments)->assertOk());

        $this->assertFalse($result['dryRun']);
        $migrations = (array) glob(database_path('migrations/*_add_summary_to_gizmos_table.php'));
        $this->assertCount(1, $migrations);
        $this->assertStringContainsString("\$table->text('summary')->nullable();", (string) file_get_contents((string) $migrations[0]));
        $this->assertStringContainsString("'summary'", (string) file_get_contents(base_path(self::MODEL)));
    }

    #[Test]
    public function errors_come_back_with_a_stable_code(): void
    {
        ApiGeneratorServer::tool(PlanApiTool::class, ['schema' => ['entities' => []]])->assertHasErrors(['"code":"invalid_schema"']);
        ApiGeneratorServer::tool(GenerateApiTool::class, [])->assertHasErrors(['"code":"invalid_request"']);
        ApiGeneratorServer::tool(AddFieldsTool::class, ['entity' => 'Gizmo', 'fields' => ['summary' => 'text']])->assertHasErrors(['"code":"file_not_found"']);
        ApiGeneratorServer::tool(AddFieldsTool::class, ['entity' => '../../config/app', 'fields' => ['name' => 'string']])->assertHasErrors(['"code":"invalid_entity_name"']);

        $this->assertFileDoesNotExist(base_path(self::MODEL));
    }

    #[Test]
    public function an_unknown_field_type_comes_back_as_a_warning(): void
    {
        $plan = $this->structured(ApiGeneratorServer::tool(PlanApiTool::class, [
            'schema' => ['entities' => ['Gizmo' => ['fields' => ['name' => 'strng']]]],
        ])->assertOk());

        $this->assertContains('unknown_field_type', array_column($plan['warnings'], 'code'));
    }

    #[Test]
    public function an_openapi_spec_of_the_project_can_replace_the_schema(): void
    {
        copy(__DIR__.'/../Fixtures/openapi/petstore.yaml', base_path('mcp-petstore.yaml'));

        try {
            $plan = $this->structured(ApiGeneratorServer::tool(PlanApiTool::class, ['openapi' => 'mcp-petstore.yaml'])->assertOk());

            $this->assertSame('create', $this->file($plan, 'app/Models/Pet.php')['action']);
            $this->assertContains('openapi_schema_skipped', array_column($plan['warnings'], 'code'));
        } finally {
            unlink(base_path('mcp-petstore.yaml'));
        }

        ApiGeneratorServer::tool(PlanApiTool::class, ['openapi' => 'missing.yaml'])->assertHasErrors(['"code":"file_not_found"']);
        ApiGeneratorServer::tool(PlanApiTool::class, ['openapi' => '../../../../composer.json'])->assertHasErrors(['"code":"invalid_request"']);
        ApiGeneratorServer::tool(PlanApiTool::class, ['openapi' => 'composer.json', 'schema' => self::SCHEMA])->assertHasErrors(['"code":"invalid_request"']);
    }

    #[Test]
    public function the_schema_can_also_be_sent_as_yaml_text(): void
    {
        $plan = $this->structured(ApiGeneratorServer::tool(PlanApiTool::class, [
            'schema' => "entities:\n  Gizmo:\n    fields:\n      name: string\n",
        ])->assertOk());

        $this->assertSame('create', $this->file($plan, self::MODEL)['action']);
    }

    #[Test]
    public function list_entities_reports_generated_files_and_manual_edits(): void
    {
        $before = $this->structured(ApiGeneratorServer::tool(ListEntitiesTool::class)->assertOk());

        $this->assertFalse($before['manifest']);
        $this->assertSame([], $before['entities']);

        ApiGeneratorServer::tool(GenerateApiTool::class, ['schema' => self::SCHEMA])->assertOk();
        file_put_contents(base_path(self::MODEL), file_get_contents(base_path(self::MODEL))."\n// edited by hand\n");
        unlink(app_path('Policies/GizmoPolicy.php'));

        $after = $this->structured(ApiGeneratorServer::tool(ListEntitiesTool::class)->assertOk());
        $gizmo = $this->find($after['entities'], 'name', 'Gizmo');
        $this->assertIsArray($gizmo['files']);
        $status = array_column($gizmo['files'], 'status', 'path');

        $this->assertTrue($after['manifest']);
        $this->assertSame('edited', $status[self::MODEL] ?? null);
        $this->assertSame('missing', $status['app/Policies/GizmoPolicy.php'] ?? null);
        $this->assertSame('intact', $status['app/Http/Controllers/GizmoController.php'] ?? null);
    }

    #[Test]
    public function the_design_prompt_walks_the_agent_from_a_description_to_the_generation(): void
    {
        ApiGeneratorServer::prompts()->assertRegistered(DesignApiPrompt::class);

        ApiGeneratorServer::prompt(DesignApiPrompt::class, ['description' => 'a library that lends books to members'])
            ->assertOk()
            ->assertSee(['a library that lends books to members', 'list-entities', 'plan-api', 'generate-api', 'belongsToMany', 'php artisan migrate']);

        ApiGeneratorServer::prompt(DesignApiPrompt::class, [])->assertHasErrors();
    }

    #[Test]
    public function the_schema_resource_serves_the_json_schema(): void
    {
        ApiGeneratorServer::resource(ApiSchemaResource::class)
            ->assertOk()
            ->assertSee('https://nameless0l.github.io/laravel-api-generator/schema/api-schema.json');
    }

    #[Test]
    public function the_package_registers_the_local_server_started_by_its_command(): void
    {
        $this->assertNotNull(app(Registrar::class)->getLocalServer(McpCommand::HANDLE));
    }

    /**
     * @return array<string, mixed>
     */
    private function structured(TestResponse $response): array
    {
        $content = [];
        $response->assertStructuredContent(function (AssertableJson $json) use (&$content): void {
            $content = $json->toArray();
            $json->etc();
        });

        return $content;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<mixed>
     */
    private function file(array $document, string $path): array
    {
        return $this->find($document['files'], 'path', $path);
    }

    /**
     * @return array<mixed>
     */
    private function find(mixed $items, string $key, string $value): array
    {
        $this->assertIsArray($items);

        foreach ($items as $item) {
            if (is_array($item) && ($item[$key] ?? null) === $value) {
                return $item;
            }
        }

        $this->fail("No item with {$key} {$value}");
    }
}
