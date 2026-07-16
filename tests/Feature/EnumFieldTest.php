<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;

class EnumFieldTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Article'];

    protected array $generatedTables = ['articles'];

    protected function tearDown(): void
    {
        if (File::exists(app_path('Enums/Status.php'))) {
            File::delete(app_path('Enums/Status.php'));
        }
        parent::tearDown();
    }

    /** @test */
    public function it_generates_a_backed_enum_from_a_cli_field(): void
    {
        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Article',
            '--fields' => 'title:string,status:enum(draft,published)',
        ]);
        $result->assertSuccessful();
        $result->run();

        $enumPath = app_path('Enums/Status.php');
        $this->assertFileExists($enumPath);

        $enum = (string) file_get_contents($enumPath);
        $this->assertStringContainsString('enum Status: string', $enum);
        $this->assertStringContainsString("case Draft = 'draft';", $enum);
        $this->assertStringContainsString("case Published = 'published';", $enum);
    }

    /** @test */
    public function it_wires_the_enum_into_model_request_factory_and_migration(): void
    {
        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Article',
            '--fields' => 'title:string,status:enum(draft,published)',
        ]);
        $result->run();

        $model = (string) file_get_contents(app_path('Models/Article.php'));
        $this->assertStringContainsString("'status' => \App\Enums\Status::class", $model);
        $this->assertStringContainsString('@property \App\Enums\Status $status', $model);

        $request = (string) file_get_contents(app_path('Http/Requests/ArticleRequest.php'));
        $this->assertStringContainsString('Rule::enum(\App\Enums\Status::class)', $request);

        $factory = (string) file_get_contents(database_path('factories/ArticleFactory.php'));
        $this->assertStringContainsString('fake()->randomElement(\App\Enums\Status::cases())', $factory);

        $migration = (string) file_get_contents($this->firstMigrationFor('articles'));
        $this->assertStringContainsString("\$table->enum('status', ['draft', 'published']);", $migration);
    }

    /** @test */
    public function it_parses_enum_fields_from_a_yaml_schema(): void
    {
        $schemaPath = base_path('api-schema-enum-test.yaml');
        File::put($schemaPath, <<<'YAML'
        entities:
          Article:
            fields:
              title: string
              status: enum(draft,published) default=draft
        YAML);

        try {
            /** @var PendingCommand $result */
            $result = $this->artisan('make:fullapi', ['--schema' => $schemaPath]);
            $result->assertSuccessful();
            $result->run();

            $this->assertFileExists(app_path('Enums/Status.php'));

            $migration = (string) file_get_contents($this->firstMigrationFor('articles'));
            $this->assertStringContainsString("\$table->enum('status', ['draft', 'published'])->default('draft');", $migration);
        } finally {
            File::delete($schemaPath);
        }
    }
}
