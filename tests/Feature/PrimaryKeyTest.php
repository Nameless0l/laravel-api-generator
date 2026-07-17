<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Test;

class PrimaryKeyTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Country', 'City'];

    protected array $generatedTables = ['countries', 'cities'];

    private string $schemaPath = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->schemaPath = base_path('api-schema-pk-test.yaml');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->schemaPath)) {
            File::delete($this->schemaPath);
        }
        parent::tearDown();
    }

    #[Test]
    public function it_generates_a_custom_primary_key_from_the_cli(): void
    {
        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Country',
            '--fields' => 'code:string:primary,name:string',
        ]);
        $result->assertSuccessful();
        $result->run();

        $model = (string) file_get_contents(app_path('Models/Country.php'));
        $this->assertStringContainsString("protected \$primaryKey = 'code';", $model);
        $this->assertStringContainsString('public $incrementing = false;', $model);
        $this->assertStringContainsString("protected \$keyType = 'string';", $model);
        $this->assertStringNotContainsString('@property int $id', $model);

        $migration = (string) file_get_contents($this->firstMigrationFor('countries'));
        $this->assertStringContainsString("\$table->string('code')->primary();", $migration);
        $this->assertStringNotContainsString('$table->id();', $migration);

        $test = (string) file_get_contents(base_path('tests/Feature/CountryControllerTest.php'));
        $this->assertStringContainsString('getKey()', $test);
        $this->assertStringContainsString("['code' => \$country->getKey()]", $test);
    }

    #[Test]
    public function relations_follow_the_parent_custom_primary_key(): void
    {
        File::put($this->schemaPath, <<<'YAML'
        entities:
          Country:
            fields:
              code: string primary
              name: string
          City:
            fields:
              name: string
            relations:
              country: belongsTo Country
        YAML);

        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', ['--schema' => $this->schemaPath]);
        $result->assertSuccessful();
        $result->run();

        $migration = (string) file_get_contents($this->firstMigrationFor('cities'));
        $this->assertStringContainsString("\$table->string('country_code');", $migration);
        $this->assertStringContainsString("\$table->foreign('country_code')->references('code')->on('countries')->cascadeOnDelete();", $migration);
        $this->assertStringNotContainsString("foreignId('country_code')", $migration);

        $request = (string) file_get_contents(app_path('Http/Requests/CityRequest.php'));
        $this->assertStringContainsString("'country_code' => 'required|string|exists:countries,code',", $request);

        $city = (string) file_get_contents(app_path('Models/City.php'));
        $this->assertStringContainsString('@property string $country_code', $city);

        $factory = (string) file_get_contents(database_path('factories/CountryFactory.php'));
        $this->assertStringContainsString("'code' => fake()->unique()->word()", $factory);
    }

    #[Test]
    public function default_id_primary_key_is_unchanged(): void
    {
        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'City',
            '--fields' => 'name:string',
        ]);
        $result->run();

        $migration = (string) file_get_contents($this->firstMigrationFor('cities'));
        $this->assertStringContainsString('$table->id();', $migration);

        $model = (string) file_get_contents(app_path('Models/City.php'));
        $this->assertStringNotContainsString('$primaryKey', $model);
        $this->assertStringContainsString('@property int $id', $model);
    }
}
