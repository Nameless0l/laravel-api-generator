<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use nameless\CodeGenerator\Services\GenerationPlanner;
use nameless\CodeGenerator\Support\SchemaParser;
use nameless\CodeGenerator\ValueObjects\FileChange;
use nameless\CodeGenerator\ValueObjects\GenerationRequest;
use PHPUnit\Framework\Attributes\Test;

class McpToolsTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Ticket', 'Owner', 'Tool'];

    protected array $generatedTables = ['tickets', 'owners', 'tools'];

    private string $apiRoutes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiRoutes = (string) file_get_contents(base_path('routes/api.php'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(app_path('Mcp'));
        File::delete(base_path('routes/ai.php'));
        file_put_contents(base_path('routes/api.php'), $this->apiRoutes);
        foreach (glob(database_path('migrations/*_to_tickets_table.php')) ?: [] as $migration) {
            unlink($migration);
        }

        parent::tearDown();
    }

    private function tool(string $entity, string $class): string
    {
        return (string) file_get_contents(app_path("Mcp/Tools/{$entity}/{$class}.php"));
    }

    private function server(): string
    {
        return (string) file_get_contents(app_path('Mcp/Servers/ApiServer.php'));
    }

    private function assertParses(string $php): void
    {
        $this->assertNotEmpty(token_get_all($php, TOKEN_PARSE));
    }

    #[Test]
    public function it_generates_one_tool_per_action_and_registers_them(): void
    {
        Artisan::call('make:fullapi', ['name' => 'Ticket', '--fields' => 'subject:string,priority:enum(low,high)', '--mcp' => true]);

        foreach (['ListTicketsTool', 'ShowTicketTool', 'CreateTicketTool', 'UpdateTicketTool', 'DeleteTicketTool'] as $class) {
            $source = $this->tool('Ticket', $class);
            $this->assertParses($source);
            $this->assertStringContainsString("class {$class} extends Tool", $source);
        }
        $this->assertFileDoesNotExist(app_path('Mcp/Tools/Ticket/RestoreTicketTool.php'));

        $this->assertStringContainsString(<<<'PHP'
                protected array $tools = [
                    ListTicketsTool::class,
                    ShowTicketTool::class,
                    CreateTicketTool::class,
                    UpdateTicketTool::class,
                    DeleteTicketTool::class,
                ];
            PHP, $this->server());
        $this->assertStringContainsString('use App\Mcp\Tools\Ticket\ListTicketsTool;', $this->server());

        $this->assertSame(
            "<?php\n\nuse App\\Mcp\\Servers\\ApiServer;\nuse Laravel\\Mcp\\Facades\\Mcp;\n\nMcp::local('api', ApiServer::class);\n",
            file_get_contents(base_path('routes/ai.php'))
        );
        $this->assertStringContainsString('MCP server ready', Artisan::output());
    }

    #[Test]
    public function the_tools_go_through_the_policy_the_service_and_the_request_rules(): void
    {
        Artisan::call('make:fullapi', ['name' => 'Ticket', '--fields' => 'subject:string,priority:enum(low,high)', '--mcp' => true]);

        $list = $this->tool('Ticket', 'ListTicketsTool');
        $this->assertStringContainsString("#[Name('list-tickets')]", $list);
        $this->assertStringContainsString("Gate::authorize('viewAny', Ticket::class);", $list);
        $this->assertStringContainsString('$tickets->paginate(', $list);
        $this->assertStringContainsString('Columns: id, subject, priority.', $list);

        $create = $this->tool('Ticket', 'CreateTicketTool');
        $this->assertStringContainsString("Gate::authorize('create', Ticket::class);", $create);
        $this->assertStringContainsString('$tickets->create(TicketDTO::fromArray($request->validate($this->rules())))', $create);
        $this->assertStringContainsString("'subject' => 'required|string|max:255',", $create);
        $this->assertStringContainsString("'priority' => ['required', Rule::enum(TicketPriority::class)],", $create);
        $this->assertStringContainsString("'subject' => \$schema->string()->required(),", $create);
        $this->assertStringContainsString("'priority' => \$schema->string()->enum(array_column(TicketPriority::cases(), 'value'))->required(),", $create);
        $this->assertStringContainsString('use App\Enums\TicketPriority;', $create);

        $update = $this->tool('Ticket', 'UpdateTicketTool');
        $this->assertStringContainsString("Gate::authorize('update', \$ticket);", $update);
        $this->assertStringContainsString("'subject' => 'sometimes|required|string|max:255',", $update);
        $this->assertStringContainsString("'id' => \$schema->integer()->required(),", $update);
        $this->assertStringContainsString("'subject' => \$schema->string(),", $update);

        $this->assertStringContainsString('public static function fromArray(array $data): self', (string) file_get_contents(app_path('DTO/TicketDTO.php')));
    }

    #[Test]
    public function an_update_leaves_its_own_row_out_of_the_unique_rules(): void
    {
        Artisan::call('make:fullapi', ['--schema' => $this->schema(['Ticket' => ['mcp' => true, 'fields' => ['code' => 'string primary', 'slug' => 'string unique']]])]);

        $update = $this->tool('Ticket', 'UpdateTicketTool');
        $this->assertStringContainsString("Rule::unique('tickets')->ignore(\$ticket, 'code')],", $update);
        $this->assertStringContainsString("Ticket::find(\$request->validate(['code' => ['required', 'string']])['code'])", $update);
        $this->assertSame(1, substr_count($update, "'code' => \$schema->"));
    }

    #[Test]
    public function soft_deletes_add_a_restore_tool(): void
    {
        Artisan::call('make:fullapi', ['name' => 'Ticket', '--fields' => 'subject:string', '--mcp' => true, '--soft-deletes' => true]);

        $restore = $this->tool('Ticket', 'RestoreTicketTool');
        $this->assertParses($restore);
        $this->assertStringContainsString("Gate::authorize('restore', \$ticket);", $restore);
        $this->assertStringContainsString('RestoreTicketTool::class,', $this->server());
        $this->assertStringContainsString('restore-ticket brings it back.', $this->tool('Ticket', 'DeleteTicketTool'));
    }

    #[Test]
    public function a_model_named_like_an_imported_class_is_aliased(): void
    {
        Artisan::call('make:fullapi', ['name' => 'Tool', '--fields' => 'label:string', '--mcp' => true]);

        $show = $this->tool('Tool', 'ShowToolTool');
        $this->assertParses($show);
        $this->assertStringContainsString('use App\Models\Tool as ToolModel;', $show);
        $this->assertStringContainsString('$tool = ToolModel::find(', $show);
        $this->assertStringContainsString('class ShowToolTool extends Tool', $show);
    }

    #[Test]
    public function a_second_entity_joins_the_server_and_regenerating_adds_nothing(): void
    {
        Artisan::call('make:fullapi', ['name' => 'Ticket', '--fields' => 'subject:string', '--mcp' => true]);
        Artisan::call('make:fullapi', ['name' => 'Owner', '--fields' => 'name:string', '--mcp' => true]);
        Artisan::call('make:fullapi', ['name' => 'Ticket', '--fields' => 'subject:string', '--mcp' => true]);

        $server = $this->server();
        $this->assertSame(1, substr_count($server, 'ListTicketsTool::class'));
        $this->assertSame(1, substr_count($server, 'ListOwnersTool::class'));
        $this->assertSame(1, substr_count((string) file_get_contents(base_path('routes/ai.php')), 'Mcp::local('));
    }

    #[Test]
    public function the_web_route_is_only_written_behind_sanctum(): void
    {
        $entities = app(SchemaParser::class)->parseArray(['entities' => ['Ticket' => ['mcp' => true, 'fields' => ['subject' => 'string']]]]);
        $routes = fn (bool $auth): string => collect(app(GenerationPlanner::class)->plan(new GenerationRequest($entities, $auth))->changes())
            ->first(fn (FileChange $change) => $change->kind === 'McpRoutes')->content ?? '';

        $this->assertStringNotContainsString('Mcp::web', $routes(false));
        $this->assertStringContainsString("Mcp::local('api', ApiServer::class);\nMcp::web('/mcp/api', ApiServer::class)->middleware('auth:sanctum');\n", $routes(true));
    }

    #[Test]
    public function adding_fields_patches_the_rules_and_the_schema_of_both_tools(): void
    {
        Artisan::call('make:fullapi', ['name' => 'Ticket', '--fields' => 'subject:string', '--mcp' => true]);
        Artisan::call('make:fullapi', ['name' => 'Ticket', '--add-fields' => 'points:integer,state:enum(open,closed),ref:string:unique']);

        $create = $this->tool('Ticket', 'CreateTicketTool');
        $this->assertParses($create);
        $this->assertStringContainsString("'points' => 'required|integer',", $create);
        $this->assertStringContainsString("'points' => \$schema->integer()->required(),", $create);
        $this->assertStringContainsString('use App\Enums\TicketState;', $create);

        $update = $this->tool('Ticket', 'UpdateTicketTool');
        $this->assertParses($update);
        $this->assertStringContainsString("'points' => 'sometimes|required|integer',", $update);
        $this->assertStringContainsString("'state' => \$schema->string()->enum(array_column(TicketState::cases(), 'value')),", $update);
        $this->assertStringContainsString("Rule::unique('tickets')->ignore(\$ticket)],", $update);
        $this->assertStringContainsString('use Illuminate\Validation\Rule;', $update);
    }

    #[Test]
    public function deleting_the_entity_removes_its_tools_from_the_server(): void
    {
        Artisan::call('make:fullapi', ['name' => 'Ticket', '--fields' => 'subject:string', '--mcp' => true]);
        Artisan::call('make:fullapi', ['name' => 'Owner', '--fields' => 'name:string', '--mcp' => true]);
        Artisan::call('delete:fullapi', ['name' => 'Ticket', '--force' => true]);

        $this->assertDirectoryDoesNotExist(app_path('Mcp/Tools/Ticket'));
        $this->assertStringNotContainsString('Ticket', $this->server());
        $this->assertStringContainsString('ListOwnersTool::class,', $this->server());
        $this->assertParses($this->server());
    }

    #[Test]
    public function tools_asked_alone_warn_about_a_dto_that_cannot_feed_them(): void
    {
        Artisan::call('make:fullapi', ['name' => 'Ticket', '--fields' => 'subject:string']);
        File::put(app_path('DTO/TicketDTO.php'), "<?php\n\nreadonly class TicketDTO {}\n");

        Artisan::call('make:fullapi', ['name' => 'Ticket', '--mcp' => true, '--only' => 'McpTool']);

        $this->assertFileExists(app_path('Mcp/Tools/Ticket/ListTicketsTool.php'));
        $this->assertStringContainsString('app/DTO/TicketDTO.php has no fromArray()', Artisan::output());
    }

    #[Test]
    public function laravel_mcp_kept_in_require_dev_is_reported(): void
    {
        $composer = base_path('composer.json');
        $original = (string) file_get_contents($composer);
        File::put($composer, (string) json_encode(['require-dev' => ['laravel/mcp' => '^0.5']]));

        try {
            Artisan::call('make:fullapi', ['name' => 'Tool', '--fields' => 'label:string', '--mcp' => true]);
        } finally {
            File::put($composer, $original);
        }

        $this->assertStringContainsString('laravel/mcp is in require-dev', Artisan::output());
    }

    /**
     * @param  array<string, mixed>  $entities
     */
    private function schema(array $entities): string
    {
        $path = base_path('api-schema-mcp-test.json');
        File::put($path, (string) json_encode(['entities' => $entities]));
        $this->beforeApplicationDestroyed(function () use ($path): void {
            File::delete($path);
        });

        return $path;
    }
}
