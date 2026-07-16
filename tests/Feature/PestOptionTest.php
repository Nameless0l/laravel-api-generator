<?php

declare(strict_types=1);

namespace nameless\CodeGenerator\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;

class PestOptionTest extends GeneratorTestCase
{
    protected array $generatedEntities = ['Gadget'];

    protected array $generatedTables = ['gadgets'];

    /** @test */
    public function it_generates_pest_tests_with_the_pest_flag(): void
    {
        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Gadget',
            '--fields' => 'name:string',
            '--pest' => true,
        ]);
        $result->assertSuccessful();
        $result->run();

        $featureTest = (string) file_get_contents(base_path('tests/Feature/GadgetControllerTest.php'));
        $this->assertStringContainsString('uses(Tests\TestCase::class, RefreshDatabase::class);', $featureTest);
        $this->assertStringContainsString("it('lists gadgets', function () {", $featureTest);
        $this->assertStringNotContainsString('class GadgetControllerTest', $featureTest);

        $unitTest = (string) file_get_contents(base_path('tests/Unit/GadgetServiceTest.php'));
        $this->assertStringContainsString('beforeEach(function () {', $unitTest);
        $this->assertStringContainsString('expect($result)->toHaveCount(5);', $unitTest);
        $this->assertStringNotContainsString('class GadgetServiceTest', $unitTest);
    }

    /** @test */
    public function it_generates_phpunit_tests_by_default(): void
    {
        /** @var PendingCommand $result */
        $result = $this->artisan('make:fullapi', [
            'name' => 'Gadget',
            '--fields' => 'name:string',
        ]);
        $result->run();

        $featureTest = (string) file_get_contents(base_path('tests/Feature/GadgetControllerTest.php'));
        $this->assertStringContainsString('class GadgetControllerTest extends TestCase', $featureTest);
    }

    /** @test */
    public function it_applies_the_pest_global_option_from_a_schema(): void
    {
        $schemaPath = base_path('api-schema-pest-test.yaml');
        File::put($schemaPath, <<<'YAML'
        options:
          pest: true
        entities:
          Gadget:
            fields:
              name: string
        YAML);

        try {
            /** @var PendingCommand $result */
            $result = $this->artisan('make:fullapi', ['--schema' => $schemaPath]);
            $result->run();

            $featureTest = (string) file_get_contents(base_path('tests/Feature/GadgetControllerTest.php'));
            $this->assertStringContainsString("it('lists gadgets', function () {", $featureTest);
        } finally {
            File::delete($schemaPath);
        }
    }
}
